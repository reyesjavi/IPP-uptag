<?php
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';

// Requiere sesión activa pero no verifica vigencia (evita bucle)
if (!estaAutenticado()) {
    header('Location: ' . url('login.php'));
    exit;
}

// Si ya tiene vigencia activa, redirigir al dashboard
if ($_SESSION['vigencia_activa'] ?? false) {
    redirigirSegunRol();
}

$ci = $_SESSION['usuario_ci'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Vigencia Vencida — IPP - UPTAG</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.14.0/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>"/>
  <style>
    body { background:var(--bg); display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .vig-card {
      background:var(--surface); border:1px solid var(--border);
      border-radius:var(--radius); padding:2.5rem 2rem;
      width:480px; max-width:95vw; box-shadow:var(--shadow-md);
    }
    .vig-icon { font-size:3rem; text-align:center; margin-bottom:1rem; }
    .vig-card h2 { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; color:var(--text); margin-bottom:.5rem; text-align:center; }
    .vig-card .sub { font-size:13px; color:var(--text-3); margin-bottom:1.5rem; line-height:1.6; text-align:center; }
    .info-ci { background:var(--surface-2); border:1px solid var(--border); border-radius:8px; padding:.75rem 1rem; margin-bottom:1.25rem; font-size:14px; }
    .info-ci strong { color:var(--primary); }
    .result-box { padding:1rem; border-radius:8px; margin-bottom:1rem; display:none; font-size:13px; }
    .result-ok  { background:var(--primary-light); border:1px solid #9FE1CB; color:var(--primary-dark); }
    .result-err { background:var(--red-light); border:1px solid #E8A0A0; color:var(--red); }
  </style>
</head>
<body>
<div class="vig-card">
  <div class="vig-icon">📅</div>
  <h2>Tu vigencia ha vencido</h2>
  <p class="sub">
    Tu acceso al portal es anual (1 enero – 31 diciembre).<br>
    Para continuar usando el sistema, debes renovar tu vigencia para <?= date('Y') ?>.
  </p>

  <div class="info-ci">
    Renovando acceso para: <strong><?= htmlspecialchars($ci) ?></strong>
  </div>

  <div class="result-box" id="resultado"></div>

  <div id="formWrap">
    <div class="form-group">
      <label>Contraseña actual</label>
      <input type="password" id="password" placeholder="Ingresa tu contraseña" required minlength="16" />
    </div>
    <button class="btn-primary" id="btnRenovar" onclick="renovar()">
      <i class="ti ti-refresh"></i> Renovar mi vigencia <?= date('Y') ?>
    </button>
  </div>

  <div style="text-align:center;margin-top:1.25rem">
    <a href="<?= url('logout.php') ?>" class="btn-link" style="font-size:13px;color:var(--text-3)">
      <i class="ti ti-logout"></i> Cerrar sesión
    </a>
  </div>
</div>

<script>
async function renovar() {
  const btn  = document.getElementById('btnRenovar');
  const pass = document.getElementById('password').value.trim();
  const res  = document.getElementById('resultado');

  if (!pass) { mostrar('error', 'Ingresa tu contraseña.'); return; }

  btn.disabled  = true;
  btn.innerHTML = '<i class="ti ti-loader"></i> Renovando...';
  res.style.display = 'none';

  try {
    const resp = await fetch('<?= url("api/registro.php") ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ci: '<?= htmlspecialchars($ci, ENT_QUOTES) ?>', password: pass })
    });
    const json = await resp.json();

    if (json.ok) {
      mostrar('ok', '¡Vigencia renovada! Redirigiendo...');
      document.getElementById('formWrap').style.display = 'none';
      setTimeout(() => { window.location.href = '<?= url("dashboard.php") ?>'; }, 2000);
    } else {
      mostrar('error', json.msg || 'No se pudo renovar la vigencia.');
    }
  } catch {
    mostrar('error', 'Error de conexión. Intenta de nuevo.');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = '<i class="ti ti-refresh"></i> Renovar mi vigencia <?= date('Y') ?>';
  }
}

function mostrar(tipo, msg) {
  const el = document.getElementById('resultado');
  el.className = 'result-box result-' + (tipo === 'error' ? 'err' : 'ok');
  el.textContent = msg;
  el.style.display = 'block';
}
</script>
</body>
</html>
