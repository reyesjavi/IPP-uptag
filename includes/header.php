<?php
// includes/header.php — Portal del Profesorado
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/auth.php';
requiereLogin();
$afiliado    = getAfiliado();
$rolActual   = $_SESSION['usuario_rol'] ?? 'afiliado';
$nombreCorto = $_SESSION['afiliado_nombre'] ?? ($_SESSION['usuario_ci'] ?? 'Usuario');
$iniciales   = strtoupper(substr($afiliado['nombre']??'',0,1).substr($afiliado['apellido']??'',0,1));
if (!$iniciales) $iniciales = strtoupper(substr($_SESSION['usuario_ci']??'U', 0, 2));

// Flash de cambio de contraseña
$flashPass = $_SESSION['flash_pass'] ?? null;
unset($_SESSION['flash_pass']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle??'Portal') ?> — IPP - UPTAG</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.14.0/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"/>
  <style>
    /* Dropdown del usuario */
    .nav-user-wrap    { position:relative; }
    .user-dropdown    {
      display:none; position:absolute; top:calc(100% + 8px); right:0;
      background:var(--surface,#fff); border:1px solid #E2E5E2;
      border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12);
      min-width:200px; z-index:200; overflow:hidden;
    }
    .nav-user-wrap:hover .user-dropdown,
    .nav-user-wrap.open  .user-dropdown { display:block; }

    .dropdown-header  { padding:12px 14px; border-bottom:1px solid #E2E5E2; }
    .dropdown-name    { font-size:13px; font-weight:600; color:#1A1F1A; }
    .dropdown-role    { font-size:11px; color:#7A847A; margin-top:2px; text-transform:uppercase; letter-spacing:.4px; }
    .dropdown-item    {
      display:flex; align-items:center; gap:9px;
      padding:10px 14px; font-size:13px; color:#4A524A;
      text-decoration:none; cursor:pointer;
      border:none; background:none; width:100%; font-family:'Nunito',sans-serif;
      transition:background .12s;
    }
    .dropdown-item:hover { background:#F5F6F4; color:#1A1F1A; }
    .dropdown-item.danger { color:#A32D2D; }
    .dropdown-item.danger:hover { background:#FCEBEB; }
    .dropdown-item i  { font-size:16px; flex-shrink:0; }

    /* Modal cambio de contraseña */
    .modal-bg         { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:300; align-items:center; justify-content:center; }
    .modal-bg.open    { display:flex; }
    .modal            { background:#fff; border-radius:12px; padding:1.75rem; width:420px; max-width:95vw; box-shadow:0 8px 32px rgba(0,0,0,.15); position:relative; }
    .modal h3         { font-family:'Syne',sans-serif; font-size:17px; font-weight:700; margin-bottom:1.2rem; color:#1A1F1A; }
    .modal-close      { position:absolute; top:1rem; right:1rem; background:none; border:none; cursor:pointer; font-size:20px; color:#7A847A; }
    .modal-close:hover{ color:#1A1F1A; }
  </style>
</head>
<body>

<nav class="nav">
  <a class="nav-brand" href="<?= url('dashboard.php') ?>">
    <div class="nav-logo">IPP</div>
    <div>
      <div class="nav-name">IPP - UPTAG</div>
      <div class="nav-sub">Portal del Profesorado</div>
    </div>
  </a>

  <div class="nav-links">
    <a href="<?= url('dashboard.php') ?>"  class="<?= ($activeNav??'')==='dashboard'  ?'active':'' ?>">Inicio</a>
    <a href="<?= url('salud.php') ?>"      class="<?= ($activeNav??'')==='salud'      ?'active':'' ?>">Salud</a>
    <a href="<?= url('finanzas.php') ?>"   class="<?= ($activeNav??'')==='finanzas'   ?'active':'' ?>">Finanzas</a>
    <a href="<?= url('directorio.php') ?>" class="<?= ($activeNav??'')==='directorio' ?'active':'' ?>">Directorio</a>
    <a href="<?= url('noticias.php') ?>"   class="<?= ($activeNav??'')==='noticias'   ?'active':'' ?>">Noticias</a>
  </div>

  <!-- MENÚ DESPLEGABLE DEL USUARIO -->
  <div class="nav-user-wrap" id="userWrap">
    <div class="nav-user" onclick="toggleDropdown()" style="cursor:pointer">
      <div class="avatar"><?= htmlspecialchars($iniciales) ?></div>
      <span><?= htmlspecialchars($nombreCorto) ?></span>
      <i class="ti ti-chevron-down" style="font-size:14px;color:rgba(255,255,255,.7)"></i>
    </div>

    <div class="user-dropdown">
      <div class="dropdown-header">
        <div class="dropdown-name"><?= htmlspecialchars($nombreCorto) ?></div>
        <div class="dropdown-role"><?= ucfirst($rolActual) ?></div>
      </div>

      <a href="<?= url('perfil.php') ?>" class="dropdown-item">
        <i class="ti ti-user"></i> Mi perfil
      </a>

      <?php if (in_array($rolActual, ['admin','administrativo'])): ?>
      <a href="<?= url('admin/dashboard.php') ?>" class="dropdown-item">
        <i class="ti ti-layout-dashboard"></i> Panel Admin
      </a>
      <?php endif; ?>

      <button class="dropdown-item" onclick="abrirCambioPass()">
        <i class="ti ti-lock"></i> Cambiar contraseña
      </button>

      <a href="<?= url('logout.php') ?>" class="dropdown-item danger"
         onclick="return confirm('¿Cerrar sesión?')">
        <i class="ti ti-logout"></i> Cerrar sesión
      </a>
    </div>
  </div>
</nav>

<?php if ($flashPass): ?>
<div style="position:fixed;top:70px;right:1.5rem;z-index:250;max-width:320px">
  <div class="flash-msg <?= $flashPass['ok']?'flash-ok':'flash-err' ?>" style="box-shadow:0 4px 16px rgba(0,0,0,.12)">
    <i class="ti <?= $flashPass['ok']?'ti-check':'ti-alert-circle' ?>"></i>
    <?= htmlspecialchars($flashPass['msg']) ?>
  </div>
</div>
<?php endif; ?>

<!-- MODAL CAMBIO DE CONTRASEÑA -->
<div class="modal-bg" id="modalPass">
  <div class="modal">
    <button class="modal-close" onclick="cerrarCambioPass()"><i class="ti ti-x"></i></button>
    <h3><i class="ti ti-lock" style="color:var(--primary,#0F6E56)"></i> Cambiar contraseña</h3>
    <form method="POST" action="<?= url('cambiar_password.php') ?>">
      <?= campoCsrf() ?>
      <div class="fl" style="margin-bottom:.9rem">
        <label style="font-size:12px;font-weight:600;color:#4A524A;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px">Contraseña actual</label>
        <input type="password" name="pass_actual" required
               style="width:100%;padding:9px 12px;border:1.5px solid #E2E5E2;border-radius:8px;font-size:13px;font-family:'Nunito',sans-serif;outline:none"/>
      </div>
      <div class="fl" style="margin-bottom:.9rem">
        <label style="font-size:12px;font-weight:600;color:#4A524A;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px">Nueva contraseña</label>
        <input type="password" name="pass_nueva" id="passNueva" required minlength="16"
               style="width:100%;padding:9px 12px;border:1.5px solid #E2E5E2;border-radius:8px;font-size:13px;font-family:'Nunito',sans-serif;outline:none"/>
      </div>
      <div class="fl" style="margin-bottom:1.2rem">
        <label style="font-size:12px;font-weight:600;color:#4A524A;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px">Confirmar nueva contraseña</label>
        <input type="password" name="pass_confirmar" id="passConfirmar" required minlength="16"
               style="width:100%;padding:9px 12px;border:1.5px solid #E2E5E2;border-radius:8px;font-size:13px;font-family:'Nunito',sans-serif;outline:none"/>
      </div>
      <div id="passError" style="display:none;color:#A32D2D;font-size:13px;margin-bottom:.8rem"></div>
      <div style="display:flex;gap:10px">
        <button type="submit" onclick="return validarPass()"
                style="flex:1;padding:10px;background:#0F6E56;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;font-family:'Nunito',sans-serif;cursor:pointer">
          <i class="ti ti-check"></i> Guardar cambios
        </button>
        <button type="button" onclick="cerrarCambioPass()"
                style="padding:10px 16px;background:none;border:1.5px solid #E2E5E2;border-radius:8px;font-size:13px;font-family:'Nunito',sans-serif;cursor:pointer">
          Cancelar
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleDropdown() {
  document.getElementById('userWrap').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  if (!document.getElementById('userWrap').contains(e.target)) {
    document.getElementById('userWrap').classList.remove('open');
  }
});
function abrirCambioPass() {
  document.getElementById('userWrap').classList.remove('open');
  document.getElementById('modalPass').classList.add('open');
}
function cerrarCambioPass() {
  document.getElementById('modalPass').classList.remove('open');
}
function validarPass() {
  const nueva     = document.getElementById('passNueva').value;
  const confirmar = document.getElementById('passConfirmar').value;
  const err       = document.getElementById('passError');
  if (nueva !== confirmar) {
    err.textContent = '⚠️ Las contraseñas no coinciden.';
    err.style.display = 'block';
    return false;
  }
  if (nueva.length < 16) {
    err.textContent = '⚠️ La contraseña debe tener al menos 16 caracteres.';
    err.style.display = 'block';
    return false;
  }
  err.style.display = 'none';
  return true;
}
// Auto-ocultar flash
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.flash-msg').forEach(el => {
    setTimeout(() => { el.style.opacity='0'; setTimeout(()=>el.remove(),400); }, 4000);
  });
});
</script>

<main class="main">
