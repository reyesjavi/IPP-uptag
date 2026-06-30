<?php
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/branding.php';
require_once __DIR__ . '/lib/totp.php';

if (estaAutenticado()) { redirigirSegunRol(); }

$paso  = $_GET['paso'] ?? 'login';
$error = '';

// ── Paso 2FA: verificar código TOTP ──────────────────────────
if ($paso === '2fa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $pendiente = $_SESSION['2fa_pendiente'] ?? null;

    if (!$pendiente || $pendiente['expira'] < time()) {
        unset($_SESSION['2fa_pendiente']);
        $error = 'La sesión de verificación expiró. Inicia sesión de nuevo.';
        $paso  = 'login';
    } else {
        $codigo = preg_replace('/\D/', '', $_POST['codigo_totp'] ?? '');
        if (TOTP::verify($pendiente['secret'], $codigo)) {
            unset($_SESSION['2fa_pendiente']);
            $u = $pendiente['usuario'];

            // Completar la sesión igual que en login() normal
            session_regenerate_id(true);
            $_SESSION['usuario_id']      = $u['id_usuario'];
            $_SESSION['usuario_ci']      = $u['username'];
            $_SESSION['usuario_rol']     = $u['rol'];
            $_SESSION['afiliado_id']     = $u['id_afiliado'];
            $_SESSION['afiliado_nombre'] = trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
            $_SESSION['afiliado_cod_pm'] = $u['cod_pm'];
            $_SESSION['vigencia_activa'] = $pendiente['vigencia_activa'];

            $pdo = getDB();
            $pdo->prepare("UPDATE usuarios_registrados SET ultimo_acceso=NOW() WHERE id_usuario=:id")
                ->execute([':id' => $u['id_usuario']]);
            registrarLog('login_2fa', 'Inicio de sesión con 2FA', $u['id_usuario']);

            redirigirSegunRol();
        } else {
            $error = 'Código incorrecto o expirado. Intenta de nuevo.';
        }
    }
}

// ── Paso login: verificar credenciales ───────────────────────
if ($paso === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $ci       = trim($_POST['ci'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (ctype_digit($ci)) $ci = 'V-' . $ci;

    if (empty($ci) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $resultado = login($ci, $password);
        if ($resultado['ok']) {
            if (!empty($resultado['2fa_requerido'])) {
                $u = $resultado['usuario'];
                // Calcular vigencia para guardar junto con los datos pendientes
                $pdo = getDB();
                $vig = $pdo->prepare("
                    SELECT v.estado FROM vigencia_anual v
                    JOIN agremiado ag ON ag.id_agremiado = v.id_agremiado
                    WHERE ag.ci = :ci AND v.anio = YEAR(CURDATE()) AND v.estado = 'activa' LIMIT 1
                ");
                $vig->execute([':ci' => $u['username']]);

                // Capturar el secreto TOTP aparte y NO dejar datos sensibles
                // (hash de contraseña, secreto TOTP) dentro del array de sesión.
                $secret = $u['totp_secret'];
                unset($u['password_hash'], $u['totp_secret'], $u['totp_habilitado'],
                      $u['intentos_fallidos'], $u['bloqueado']);

                $_SESSION['2fa_pendiente'] = [
                    'usuario'         => $u,
                    'secret'          => $secret,
                    'vigencia_activa' => (bool) $vig->fetch(),
                    'expira'          => time() + 300,
                ];
                header('Location: ' . url('login.php') . '?paso=2fa');
                exit;
            }
            redirigirSegunRol();
        } else {
            $error = $resultado['msg'];
        }
    }
}
// Para re-llenar el campo de display tras un error
$ciPost    = trim($_POST['ci'] ?? '');
$ciDisplay = preg_match('/^V?-?(\d+)$/i', $ciPost, $m) ? $m[1] : $ciPost;
$ciIsCI    = ($ciDisplay === '' || ctype_digit($ciDisplay));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Iniciar Sesión — IPP - UPTAG</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.14.0/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>"/>
</head>
<body>
<div class="login-page">
  <div class="login-left"><div class="bg-circle-1"></div><div class="bg-circle-2"></div><?= logoIPP('hero') ?><p class="inst-name">Instituto de Previsión del Profesorado del UPTAG</p>
    <h1>Portal de Servicios<br><span>del Profesorado</span></h1>
    <p>Gestiona tu afiliación, reembolsos médicos, cartas aval y servicios de salud desde un solo lugar.</p>
    <div class="feat-list">
      <div class="feat-item"><div class="feat-icon"><i class="ti ti-heart-rate-monitor"></i></div><p>Planes médicos y cartas aval</p></div>
      <div class="feat-item"><div class="feat-icon"><i class="ti ti-file-certificate"></i></div><p>Cartas aval y reembolsos</p></div>
      <div class="feat-item"><div class="feat-icon"><i class="ti ti-users"></i></div><p>Gestión de beneficiarios</p></div>
      <div class="feat-item"><div class="feat-icon"><i class="ti ti-shield-check"></i></div><p>Acceso por roles y permisos</p></div>
    </div>
  </div>
  <div class="login-right">
    <?php if ($paso === '2fa'): ?>
      <h2>Verificación en dos pasos</h2>
      <p>Ingresa el código de 6 dígitos de tu aplicación autenticadora</p>
      <?php if ($error): ?>
        <div class="flash-msg flash-err"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" action="<?= url('login.php') ?>?paso=2fa">
        <?= campoCsrf() ?>
        <div class="form-group">
          <label>Código TOTP</label>
          <input type="text" name="codigo_totp" inputmode="numeric" maxlength="6"
                 placeholder="000000" required autofocus autocomplete="one-time-code"
                 style="font-size:22px;letter-spacing:6px;text-align:center;font-weight:700" />
        </div>
        <button type="submit" class="btn-primary"><i class="ti ti-shield-check"></i> Verificar código</button>
      </form>
      <div class="login-links" style="margin-top:1rem">
        <a href="<?= url('login.php') ?>" class="btn-link" style="font-size:13px">
          <i class="ti ti-arrow-left"></i> Volver al inicio de sesión
        </a>
      </div>
    <?php else: ?>
      <h2>Iniciar Sesión</h2>
      <p>Accede con tu cédula o correo institucional</p>
      <?php if ($error): ?>
        <div class="flash-msg flash-err"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" action="<?= url('login.php') ?>">
        <?= campoCsrf() ?>
        <div class="form-group">
          <label>Cédula / Usuario</label>
          <div class="ci-group" id="ciGroup">
            <span class="ci-prefix" id="ciPrefix"<?= !$ciIsCI ? ' style="display:none"' : '' ?>>V-</span>
            <input type="text" id="ciInput" inputmode="numeric"
                   placeholder="12345678" autocomplete="username"
                   value="<?= htmlspecialchars($ciDisplay) ?>" required autofocus />
          </div>
          <input type="hidden" name="ci" id="ciHidden" value="<?= htmlspecialchars($ciPost) ?>" />
        </div>
        <div class="form-group">
          <label>Contraseña</label>
          <input type="password" name="password" placeholder="••••••••" required/>
        </div>
        <button type="submit" class="btn-primary">Entrar al Portal</button>
      </form>
      <div class="login-links">
        <a href="<?= url('registro.php') ?>" class="btn-link">¿Eres agremiado y no tienes cuenta? Regístrate</a>
        <br>
        <a class="btn-link" href="<?= url('recuperar_password.php') ?>">¿Olvidaste tu contraseña?</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
<script>
(function () {
  const input  = document.getElementById('ciInput');
  const hidden = document.getElementById('ciHidden');
  const prefix = document.getElementById('ciPrefix');

  input.addEventListener('input', function () {
    const val = this.value;
    if (/[^0-9]/.test(val)) {
      // Contiene letras → modo usuario/admin: ocultar prefijo
      prefix.style.display = 'none';
    } else {
      // Solo dígitos → modo cédula: filtrar y mostrar prefijo
      this.value = val.replace(/\D/g, '');
      prefix.style.display = '';
    }
    hidden.value = this.value;
  });

  document.querySelector('form').addEventListener('submit', function () {
    const val = input.value.trim();
    // Si son solo dígitos, el PHP se encarga de agregar "V-"
    hidden.value = val;
  });
})();
</script>
</body>
</html>
