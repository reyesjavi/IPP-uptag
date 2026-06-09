<?php
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Acceso Denegado — UPTAG</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Nunito:wght@400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.14.0/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>"/>
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg)">
  <div style="text-align:center;padding:3rem">
    <div style="font-size:60px;margin-bottom:1rem"><i class="ti ti-lock" style="color:var(--red)"></i></div>
    <h1 style="font-family:'Syne',sans-serif;font-size:28px;color:var(--text);margin-bottom:.5rem">Acceso denegado</h1>
    <p style="font-size:14px;color:var(--text-3);margin-bottom:2rem">No tienes permisos para ver esta página.</p>
    <a href="<?= url('dashboard.php') ?>" class="btn btn-teal"><i class="ti ti-arrow-left"></i> Volver al inicio</a>
    <a href="<?= url('logout.php') ?>" class="btn btn-outline" style="margin-left:8px"><i class="ti ti-logout"></i> Cerrar sesión</a>
  </div>
</body>
</html>
