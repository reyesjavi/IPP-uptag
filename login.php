<?php
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';

if (estaAutenticado()) { redirigirSegunRol(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $ci       = trim($_POST['ci']       ?? '');
    $password = trim($_POST['password'] ?? '');
    if (empty($ci) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $resultado = login($ci, $password);
        if ($resultado['ok']) {
            redirigirSegunRol();
        } else {
            $error = $resultado['msg'];
        }
    }
}
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
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"/>
</head>
<body>
<div class="login-page">
  <div class="login-left"><div class="bg-circle-1"></div><div class="bg-circle-2"></div><div class="ipp-logo-big"><span>IPP</span></div><p class="inst-name">Instituto de Previsión del Profesorado del UPTAG</p>
    <h1>Portal de Servicios<br><span>del Profesorado</span></h1>
    <p>Gestiona tu afiliación, reembolsos médicos, caja de ahorros y mucho más desde un solo lugar.</p>
    <div class="feat-list">
      <div class="feat-item"><div class="feat-icon"><i class="ti ti-heart-rate-monitor"></i></div><p>Planes médicos y cartas aval</p></div>
      <div class="feat-item"><div class="feat-icon"><i class="ti ti-wallet"></i></div><p>Caja de ahorros y préstamos</p></div>
      <div class="feat-item"><div class="feat-icon"><i class="ti ti-users"></i></div><p>Gestión de beneficiarios</p></div>
      <div class="feat-item"><div class="feat-icon"><i class="ti ti-shield-check"></i></div><p>Acceso por roles y permisos</p></div>
    </div>
  </div>
  <div class="login-right">
    <h2>Iniciar Sesión</h2>
    <p>Accede con tu cédula o correo institucional</p>
    <?php if ($error): ?>
      <div class="flash-msg flash-err"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="<?= url('login.php') ?>">
      <?= campoCsrf() ?>
      <div class="form-group">
        <label>Cédula / Usuario</label>
        <input type="text" name="ci" placeholder="V-12345678 o correo" value="<?= htmlspecialchars($_POST['ci']??'') ?>" required autofocus/>
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
      <a class="btn-link" href="<?= url('recuperar_password.php') ?>">
        ¿Olvidaste tu contraseña?
      </button>
    </div>
  </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
