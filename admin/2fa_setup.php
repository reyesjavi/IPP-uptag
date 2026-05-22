<?php
$pageTitle    = 'Seguridad — 2FA';
$pageSubtitle = 'Autenticación en dos pasos (TOTP)';
$activeAdmin  = '2fa';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../lib/totp.php';
requiereRol('admin', 'administrativo');

$pdo    = getDB();
$uid    = $_SESSION['usuario_id'];
$flash  = $_SESSION['flash_admin'] ?? null;
unset($_SESSION['flash_admin']);

// ── Estado actual del usuario ─────────────────────────────────
$usuario = $pdo->prepare("SELECT totp_secret, totp_habilitado FROM usuarios_registrados WHERE id_usuario = :id");
$usuario->execute([':id' => $uid]);
$usuario = $usuario->fetch();
$habilitado = !empty($usuario['totp_habilitado']);

// ── POST: habilitar, verificar o deshabilitar ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'generar') {
        // Generar nuevo secreto y guardarlo (sin habilitar aún)
        $secret = TOTP::generateSecret();
        $pdo->prepare("UPDATE usuarios_registrados SET totp_secret = :s, totp_habilitado = 0 WHERE id_usuario = :id")
            ->execute([':s' => $secret, ':id' => $uid]);
        $_SESSION['2fa_setup_secret'] = $secret;
        header('Location: ' . url('admin/2fa_setup.php') . '?paso=verificar');
        exit;
    }

    if ($accion === 'verificar') {
        $secret = $_SESSION['2fa_setup_secret'] ?? $usuario['totp_secret'] ?? '';
        $codigo = preg_replace('/\D/', '', $_POST['codigo'] ?? '');
        if ($secret && TOTP::verify($secret, $codigo)) {
            $pdo->prepare("UPDATE usuarios_registrados SET totp_secret = :s, totp_habilitado = 1 WHERE id_usuario = :id")
                ->execute([':s' => $secret, ':id' => $uid]);
            unset($_SESSION['2fa_setup_secret']);
            registrarLog('2fa_habilitado', '2FA TOTP activado');
            $_SESSION['flash_admin'] = ['ok' => true, 'msg' => '¡Autenticación en dos pasos activada correctamente!'];
        } else {
            $_SESSION['flash_admin'] = ['ok' => false, 'msg' => 'Código incorrecto. Escanea el QR de nuevo o espera el siguiente código.'];
        }
        header('Location: ' . url('admin/2fa_setup.php'));
        exit;
    }

    if ($accion === 'deshabilitar') {
        $pdo->prepare("UPDATE usuarios_registrados SET totp_secret = NULL, totp_habilitado = 0 WHERE id_usuario = :id")
            ->execute([':id' => $uid]);
        unset($_SESSION['2fa_setup_secret']);
        registrarLog('2fa_deshabilitado', '2FA TOTP desactivado');
        $_SESSION['flash_admin'] = ['ok' => true, 'msg' => 'Autenticación en dos pasos desactivada.'];
        header('Location: ' . url('admin/2fa_setup.php'));
        exit;
    }
}

$paso         = $_GET['paso'] ?? 'inicio';
$setupSecret  = $_SESSION['2fa_setup_secret'] ?? $usuario['totp_secret'] ?? '';
$ci           = $_SESSION['usuario_ci'] ?? '';
$otpauthUrl   = $setupSecret ? TOTP::getOtpauthUrl($setupSecret, $ci) : '';

require_once __DIR__ . '/header.php';
?>

<?php if ($flash): ?>
  <div class="flash-msg <?= $flash['ok'] ? 'flash-ok' : 'flash-err' ?>">
    <i class="ti <?= $flash['ok'] ? 'ti-check' : 'ti-alert-circle' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>

<div style="max-width:520px">

<?php if ($habilitado && $paso !== 'verificar'): ?>

  <!-- ── 2FA ya activa ── -->
  <div class="report-box">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.2rem">
      <div style="width:44px;height:44px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:22px">🔒</div>
      <div>
        <div style="font-weight:700;font-size:15px;color:var(--text)">2FA activado</div>
        <div style="font-size:12px;color:var(--text-3)">Tu cuenta está protegida con autenticación TOTP</div>
      </div>
      <span class="badge badge-green" style="margin-left:auto">Activo</span>
    </div>
    <p style="font-size:13px;color:var(--text-2);margin-bottom:1.25rem">
      Cada vez que inicies sesión, el sistema te pedirá el código de 6 dígitos de tu aplicación autenticadora (Google Authenticator, Aegis, Authy, etc.).
    </p>
    <form method="POST" action="<?= url('admin/2fa_setup.php') ?>">
      <?= campoCsrf() ?>
      <input type="hidden" name="accion" value="deshabilitar" />
      <button type="submit" class="btn" style="background:var(--red-light);color:var(--red);border:1px solid var(--red)"
              onclick="return confirm('¿Desactivar la autenticación en dos pasos? Tu cuenta quedará menos protegida.')">
        <i class="ti ti-shield-off"></i> Desactivar 2FA
      </button>
    </form>
  </div>

<?php elseif ($paso === 'verificar' && $setupSecret): ?>

  <!-- ── Paso 2: escanear QR y verificar ── -->
  <div class="report-box">
    <h3 style="margin-bottom:.5rem">Paso 2: Escanea el código QR</h3>
    <p style="font-size:13px;color:var(--text-2);margin-bottom:1.2rem">
      Abre tu aplicación autenticadora (Google Authenticator, Aegis, Authy) y escanea el código QR. Luego ingresa el código de 6 dígitos para confirmar.
    </p>

    <!-- QR Code generado por JS (sin dependencia de servidor externo) -->
    <div style="text-align:center;margin-bottom:1.2rem">
      <canvas id="qrCanvas" style="border:6px solid #fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.12)"></canvas>
    </div>

    <details style="margin-bottom:1.2rem">
      <summary style="font-size:12px;color:var(--text-3);cursor:pointer">¿No puedes escanear? Ingresa el código manualmente</summary>
      <div style="margin-top:.6rem;padding:.6rem .8rem;background:var(--surface-2);border-radius:6px;font-family:monospace;font-size:13px;word-break:break-all;color:var(--text)">
        <?= htmlspecialchars($setupSecret) ?>
      </div>
      <p style="font-size:12px;color:var(--text-3);margin-top:.4rem">Algoritmo: SHA1 · Dígitos: 6 · Período: 30s</p>
    </details>

    <form method="POST" action="<?= url('admin/2fa_setup.php') ?>">
      <?= campoCsrf() ?>
      <input type="hidden" name="accion" value="verificar" />
      <div class="form-group">
        <label>Código de verificación</label>
        <input type="text" name="codigo" inputmode="numeric" maxlength="6"
               placeholder="000000" required autofocus autocomplete="one-time-code"
               style="font-size:20px;letter-spacing:5px;text-align:center;font-weight:700" />
      </div>
      <button type="submit" class="btn btn-teal" style="width:100%;justify-content:center">
        <i class="ti ti-shield-check"></i> Verificar y activar 2FA
      </button>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
  <script>
    QRCode.toCanvas(document.getElementById('qrCanvas'), <?= json_encode($otpauthUrl) ?>, {
      width: 200, margin: 2,
      color: { dark: '#1E367B', light: '#FFFFFF' }
    });
  </script>

<?php else: ?>

  <!-- ── Paso 1: iniciar configuración ── -->
  <div class="report-box">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.2rem">
      <div style="width:44px;height:44px;border-radius:50%;background:var(--gold-light);display:flex;align-items:center;justify-content:center;font-size:22px">🔓</div>
      <div>
        <div style="font-weight:700;font-size:15px;color:var(--text)">2FA no activado</div>
        <div style="font-size:12px;color:var(--text-3)">Activa la verificación en dos pasos para mayor seguridad</div>
      </div>
      <span class="badge badge-amber" style="margin-left:auto">Inactivo</span>
    </div>

    <p style="font-size:13px;color:var(--text-2);margin-bottom:1.25rem">
      Con la autenticación en dos pasos (TOTP), además de tu contraseña necesitarás un código temporal de 6 dígitos generado por una aplicación autenticadora. Incluso si alguien roba tu contraseña, no podrá acceder a tu cuenta.
    </p>

    <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.5rem;font-size:13px;color:var(--text-2)">
      <div><i class="ti ti-circle-check" style="color:var(--accent)"></i> Compatible con Google Authenticator</div>
      <div><i class="ti ti-circle-check" style="color:var(--accent)"></i> Compatible con Aegis (Android) y Raivo (iOS)</div>
      <div><i class="ti ti-circle-check" style="color:var(--accent)"></i> Códigos que cambian cada 30 segundos</div>
    </div>

    <form method="POST" action="<?= url('admin/2fa_setup.php') ?>">
      <?= campoCsrf() ?>
      <input type="hidden" name="accion" value="generar" />
      <button type="submit" class="btn btn-teal" style="width:100%;justify-content:center">
        <i class="ti ti-shield-lock"></i> Configurar autenticación en dos pasos
      </button>
    </form>
  </div>

<?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
