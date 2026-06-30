<?php
// includes/mailer.php — Envío de correo centralizado (SMTP vía PHPMailer)
require_once __DIR__ . '/../lib/phpmailer/Exception.php';
require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

// Envía un correo de texto plano. Devuelve true si se envió, false si falló
// (el fallo se registra en el error_log y nunca interrumpe el flujo del caller).
function enviarCorreo(string $destino, string $asunto, string $cuerpo): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('MAIL_USER') ?: '';
        $mail->Password   = getenv('MAIL_PASS') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)(getenv('MAIL_PORT') ?: 587);
        $mail->CharSet    = 'UTF-8';

        $fromName = getenv('MAIL_FROM_NAME') ?: 'IPP UPTAG';
        $fromAddr = getenv('MAIL_USER') ?: 'no-reply@uptag.edu.ve';
        $mail->setFrom($fromAddr, $fromName);
        $mail->addAddress($destino);

        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('[UPTAG Mail] No se pudo enviar a ' . $destino . ': ' . $e->getMessage());
        return false;
    }
}
