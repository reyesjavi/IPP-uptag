<?php
// lib/totp.php — Implementación mínima de TOTP (RFC 6238 / RFC 4226)
// Sin dependencias externas. Compatible con Google Authenticator, Aegis, etc.

class TOTP
{
    private const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // Genera un secreto Base32 aleatorio (160 bits = 20 bytes = 32 chars Base32)
    public static function generateSecret(): string
    {
        $bytes  = random_bytes(20);
        $secret = '';
        for ($i = 0; $i < 20; $i++) {
            $secret .= self::CHARS[ord($bytes[$i]) & 0x1F];
        }
        return $secret;
    }

    // Devuelve el código TOTP de 6 dígitos para el slot de tiempo dado
    public static function getCode(string $secret, ?int $timeSlot = null): string
    {
        $timeSlot ??= (int)(time() / 30);
        $key  = self::base32Decode($secret);
        // Mensaje de 8 bytes big-endian: 4 bytes cero + 4 bytes del slot
        $msg  = "\0\0\0\0" . pack('N', $timeSlot);
        $hash = hash_hmac('sha1', $msg, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
             (ord($hash[$offset + 3]) & 0xFF)
        ) % 1_000_000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    // Verifica un código con ventana de ±1 slot (±30 s de tolerancia de reloj)
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) return false;
        $slot = (int)(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::getCode($secret, $slot + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    // URL otpauth:// para generar el QR con el autenticador
    public static function getOtpauthUrl(string $secret, string $account, string $issuer = 'IPP-UPTAG'): string
    {
        return 'otpauth://totp/' . rawurlencode("$issuer:$account")
             . '?secret=' . $secret
             . '&issuer=' . rawurlencode($issuer)
             . '&algorithm=SHA1&digits=6&period=30';
    }

    // ── Base32 decode ─────────────────────────────────────────
    private static function base32Decode(string $s): string
    {
        $s      = strtoupper(rtrim($s, '='));
        $output = '';
        $buf    = 0;
        $bits   = 0;
        for ($i = 0; $i < strlen($s); $i++) {
            $v = strpos(self::CHARS, $s[$i]);
            if ($v === false) continue;
            $buf   = ($buf << 5) | $v;
            $bits += 5;
            if ($bits >= 8) {
                $bits  -= 8;
                $output .= chr(($buf >> $bits) & 0xFF);
            }
        }
        return $output;
    }
}
