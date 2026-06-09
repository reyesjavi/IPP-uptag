<?php
// admin/header.php
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/branding.php';
requiereRol('admin','administrativo');

$nombreCorto = $_SESSION['afiliado_nombre'] ?? ($_SESSION['usuario_ci'] ?? 'Admin');
$rol         = $_SESSION['usuario_rol'] ?? '';
$iniciales   = strtoupper(substr(str_replace('-','',$nombreCorto), 0, 2));

// Flash genérico del panel admin
$flash = $_SESSION['flash_admin'] ?? null;
unset($_SESSION['flash_admin']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?= htmlspecialchars($pageTitle??'Panel Admin') ?> — IPP Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.14.0/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>"/>
  <link rel="stylesheet" href="<?= assetUrl('assets/css/admin.css') ?>"/>
</head>
<body class="admin-body">
<div class="admin-layout">

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <?= logoIPP('nav') ?>
    <div>
      <div class="nav-name" style="font-size:14px">IPP Admin</div>
      <div class="nav-sub"><?= $rol==='admin' ? 'Administrador' : 'Administrativo' ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="sidebar-section">General</div>

    <a href="<?= url('admin/dashboard.php') ?>" class="sidebar-link <?= ($activeAdmin??'')==='dashboard' ?'active':'' ?>">
      <i class="ti ti-layout-dashboard"></i> Dashboard
    </a>
    <a href="<?= url('admin/afiliados.php') ?>" class="sidebar-link <?= ($activeAdmin??'')==='afiliados' ?'active':'' ?>">
      <i class="ti ti-users"></i> Afiliados
    </a>
    <a href="<?= url('admin/reembolsos.php') ?>" class="sidebar-link <?= ($activeAdmin??'')==='reembolsos' ?'active':'' ?>">
      <i class="ti ti-receipt"></i> Reembolsos
    </a>
    <a href="<?= url('admin/avales.php') ?>" class="sidebar-link <?= ($activeAdmin??'')==='avales' ?'active':'' ?>">
      <i class="ti ti-file-certificate"></i> Cartas Aval
    </a>
    <a href="<?= url('admin/reportes.php') ?>" class="sidebar-link <?= ($activeAdmin??'')==='reportes' ?'active':'' ?>">
      <i class="ti ti-chart-bar"></i> Reportes
    </a>

    <div class="sidebar-section">Convenios</div>
    <a href="<?= url('admin/medicos.php') ?>" class="sidebar-link <?= ($activeAdmin??'')==='medicos' ?'active':'' ?>">
      <i class="ti ti-stethoscope"></i> Médicos/Convenios
    </a>

    <?php if (esAdmin()): ?>
    <div class="sidebar-section">Administración</div>
    <a href="<?= url('admin/usuarios.php') ?>" class="sidebar-link <?= ($activeAdmin??'')==='usuarios' ?'active':'' ?>">
      <i class="ti ti-user-cog"></i> Usuarios
    </a>
    <a href="<?= url('admin/logs.php') ?>" class="sidebar-link <?= ($activeAdmin??'')==='logs' ?'active':'' ?>">
      <i class="ti ti-list-details"></i> Logs
    </a>
    <?php endif; ?>
    <a href="<?= url('admin/2fa_setup.php') ?>" class="sidebar-link <?= ($activeAdmin??'')==='2fa' ?'active':'' ?>">
      <i class="ti ti-shield-lock"></i> Seguridad 2FA
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= url('dashboard.php') ?>" class="sidebar-link">
      <i class="ti ti-home"></i> Portal Afiliado
    </a>
    <a href="<?= url('logout.php') ?>" class="sidebar-link" style="color:#F08080"
       onclick="return confirm('¿Cerrar sesión?')">
      <i class="ti ti-logout"></i> Cerrar sesión
    </a>
  </div>
</aside>

<!-- ── CONTENIDO ── -->
<div class="admin-content">
<header class="admin-topbar">
  <div class="topbar-title">
    <h1><?= htmlspecialchars($pageTitle??'Panel') ?></h1>
    <?php if (!empty($pageSubtitle)): ?>
      <p><?= htmlspecialchars($pageSubtitle) ?></p>
    <?php endif; ?>
  </div>
  <div class="topbar-user">
    <div class="avatar" style="background:var(--primary-light);color:var(--primary);width:34px;height:34px;font-size:13px">
      <?= htmlspecialchars($iniciales) ?>
    </div>
    <span style="font-size:13px;color:var(--text-2);font-weight:500"><?= htmlspecialchars($nombreCorto) ?></span>
    <span class="badge <?= $rol==='admin'?'badge-amber':'badge-blue' ?>">
      <?= $rol==='admin' ? 'Admin' : 'Administrativo' ?>
    </span>
  </div>
</header>

<?php if ($flash): ?>
<div style="padding:.5rem 2rem 0">
  <div class="flash-msg <?= $flash['ok']?'flash-ok':'flash-err' ?>">
    <i class="ti <?= $flash['ok']?'ti-check':'ti-alert-circle' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
</div>
<?php endif; ?>

<main class="admin-main">
