<?php
// verificar_correo.php — Activa una cuenta nueva tras confirmar el correo
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/branding.php';

$token = $_GET['token'] ?? '';
$ok    = false;
$error = '';

if ($token === '' || !ctype_xdigit($token)) {
    $error = 'El enlace de activación no es válido.';
} else {
    $pdo  = getDB();
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT v.id_verificacion, v.id_usuario
        FROM verificacion_correo v
        JOIN usuarios_registrados u ON u.id_usuario = v.id_usuario
        WHERE v.token = :tok AND v.usado = 0 AND v.expira_en > NOW()
        LIMIT 1
    ");
    $stmt->execute([':tok' => $hash]);
    $reg = $stmt->fetch();

    if (!$reg) {
        $error = 'El enlace de activación es inválido o ya expiró. Vuelve a registrarte para recibir uno nuevo.';
    } else {
        // Activar la cuenta y consumir el token (una sola vez)
        $pdo->prepare("UPDATE usuarios_registrados SET activo = 1, correo_verificado = 1 WHERE id_usuario = :id")
            ->execute([':id' => $reg['id_usuario']]);
        $pdo->prepare("UPDATE verificacion_correo SET usado = 1 WHERE id_verificacion = :id")
            ->execute([':id' => $reg['id_verificacion']]);

        try {
            $pdo->prepare("INSERT INTO log_actividad (id_usuario, accion, detalle, ip) VALUES (:u,'correo_verificado','Cuenta activada por verificación de correo',:ip)")
                ->execute([':u' => $reg['id_usuario'], ':ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Exception $e) {}

        $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Activación de cuenta — UPTAG</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.14.0/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>"/>
  <style>
    body { background:var(--bg); display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .rec-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:2.5rem 2rem; width:420px; max-width:95vw; box-shadow:var(--shadow-md); text-align:center; }
    .rec-card h2 { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; color:var(--text); margin-bottom:.5rem; }
    .rec-card p  { font-size:13px; color:var(--text-3); margin-bottom:1.5rem; line-height:1.6; }
  </style>
</head>
<body>
<div class="rec-card">
  <div style="margin:0 auto 1rem;display:flex;justify-content:center"><?= logoIPP('nav') ?></div>

  <?php if ($ok): ?>
    <h2>¡Cuenta activada!</h2>
    <p>Tu correo fue verificado correctamente. Ya puedes iniciar sesión en el portal.</p>
    <a href="<?= url('login.php') ?>" class="btn btn-teal" style="width:100%;justify-content:center">
      <i class="ti ti-login"></i> Ir al inicio de sesión
    </a>
  <?php else: ?>
    <h2>No se pudo activar</h2>
    <div class="flash-msg flash-err" style="text-align:left"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($error) ?></div>
    <div style="margin-top:1.5rem">
      <a href="<?= url('registro.php') ?>" class="btn btn-teal" style="width:100%;justify-content:center">
        <i class="ti ti-user-plus"></i> Volver a registrarme
      </a>
    </div>
  <?php endif; ?>

  <div style="margin-top:1.5rem">
    <a href="<?= url('login.php') ?>" class="btn-link" style="font-size:13px;color:var(--text-3)">
      <i class="ti ti-arrow-left"></i> Volver al inicio de sesión
    </a>
  </div>
</div>
</body>
</html>
