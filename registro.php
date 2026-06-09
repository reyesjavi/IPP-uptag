<?php require_once __DIR__ . '/config/base.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Registro — IPP - UPTAG</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.14.0/tabler-icons.min.css"/>
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>"/>
  <style>
    .reg-wrap  { min-height:100vh; display:grid; grid-template-columns:1fr 460px; }
    .reg-left  { background:var(--primary); padding:4rem 3.5rem; display:flex; flex-direction:column; justify-content:center; position:relative; overflow:hidden; }
    .reg-left::before { content:''; position:absolute; top:-80px; right:-80px; width:360px; height:360px; border-radius:50%; background:rgba(255,255,255,.05); }
    .reg-left h1 { font-family:'Syne',sans-serif; font-weight:800; font-size:30px; color:#fff; line-height:1.2; margin-bottom:1rem; }
    .reg-left h1 span { color:#9FE1CB; }
    .reg-left > p { font-size:14px; color:rgba(255,255,255,.75); line-height:1.7; margin-bottom:2rem; }
    .timeline  { display:flex; flex-direction:column; gap:16px; }
    .tl-item   { display:flex; align-items:flex-start; gap:12px; }
    .tl-dot    { width:32px; height:32px; border-radius:50%; background:rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
    .tl-text   { font-size:13px; color:rgba(255,255,255,.85); line-height:1.6; padding-top:5px; }
    .tl-text strong { color:#9FE1CB; }
    .reg-right { background:var(--surface); padding:3rem 2.5rem; overflow-y:auto; }
    .reg-right h2 { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; color:var(--text); margin-bottom:6px; }
    .reg-right > p { font-size:13px; color:var(--text-3); margin-bottom:1.5rem; }
    .result-box  { padding:1.25rem; border-radius:10px; margin-bottom:1.2rem; display:none; }
    .result-ok   { background:var(--primary-light); border:1px solid #9FE1CB; }
    .result-err  { background:var(--red-light);     border:1px solid #E8A0A0; }
    .result-warn { background:var(--gold-light);    border:1px solid #FAC775; }
    .result-box h4 { font-size:14px; font-weight:700; margin-bottom:6px; }
    .result-box p  { font-size:13px; line-height:1.6; }
    .result-ok  h4 { color:var(--primary-dark); }
    .result-err h4 { color:var(--red); }
    .result-warn h4{ color:var(--gold); }
  </style>
</head>
<body>
<div class="reg-wrap">

  <div class="reg-left">
    <h1>Instituto de Previsión<br><span>del Profesorado UPTAG</span></h1>
    <p>Si eres agremiado de la UPTAG, puedes crear tu cuenta de acceso al portal de forma inmediata.</p>
    <div class="timeline">
      <div class="tl-item">
        <div class="tl-dot">🏛</div>
        <div class="tl-text">
          <strong>Agremiación vitalicia</strong><br>
          Estar en el padrón de agremiados es el único requisito para crear tu cuenta.
        </div>
      </div>
      <div class="tl-item">
        <div class="tl-dot">⚡</div>
        <div class="tl-text">
          <strong>Acceso inmediato</strong><br>
          Si tu cédula está en el sistema, tu cuenta se crea al instante sin aprobaciones.
        </div>
      </div>
      <div class="tl-item">
        <div class="tl-dot">📅</div>
        <div class="tl-text">
          <strong>Vigencia anual</strong><br>
          Tu acceso se renueva cada año (1 enero – 31 diciembre) volviendo a registrarte.
        </div>
      </div>
    </div>
  </div>

  <div class="reg-right">
    <h2>Crear mi cuenta</h2>
    <p>Ingresa tu cédula para verificar que estás en el padrón de agremiados</p>

    <div class="result-box" id="resultado"></div>

    <form id="formRegistro">
      <div class="form-group">
        <label>Cédula de identidad *</label>
        <div class="ci-group">
          <span class="ci-prefix">V-</span>
          <input type="text" id="ci" inputmode="numeric"
                 placeholder="12345678" required maxlength="9" />
        </div>
      </div>
      <div class="form-group">
        <label>Contraseña *</label>
        <input type="password" id="password" name="password" placeholder="Mínimo 16 caracteres" required minlength="16"/>
      </div>
      <div class="form-group">
        <label>Confirmar contraseña *</label>
        <input type="password" id="password2" placeholder="Repite tu contraseña" required/>
      </div>
      <div class="form-group">
        <label>Correo de contacto <span style="color:var(--text-3)">(opcional)</span></label>
        <input type="email" id="correo" name="correo" placeholder="tu@correo.com"/>
      </div>
      <div class="form-group">
        <label>Teléfono <span style="color:var(--text-3)">(opcional)</span></label>
        <input type="tel" id="telefono" name="telefono" placeholder="0414-555-0000"/>
      </div>
      <button type="submit" class="btn-primary" id="btnEnviar">
        <i class="ti ti-user-check"></i> Crear mi cuenta
      </button>
    </form>

    <div class="login-links" style="margin-top:1.2rem">
      <a href="<?= url('login.php') ?>" class="btn-link">¿Ya tienes cuenta? Inicia sesión →</a>
    </div>
  </div>
</div>

<script>
// Solo dígitos en el campo CI
document.getElementById('ci').addEventListener('input', function () {
  this.value = this.value.replace(/\D/g, '');
});

document.getElementById('formRegistro').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btnEnviar');
  const res = document.getElementById('resultado');

  const pass  = document.getElementById('password').value;
  const pass2 = document.getElementById('password2').value;
  if (pass !== pass2) {
    mostrarResultado('error', 'Error', 'Las contraseñas no coinciden.');
    return;
  }

  btn.disabled    = true;
  btn.innerHTML   = '<i class="ti ti-loader"></i> Verificando...';
  res.style.display = 'none';

  const data = {
    ci:       'V-' + document.getElementById('ci').value.trim(),
    password: pass,
    correo:   document.getElementById('correo').value.trim(),
    telefono: document.getElementById('telefono').value.trim(),
  };

  try {
    const resp = await fetch('<?= url("api/registro.php") ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const json = await resp.json();

    if (json.ok) {
      // Sólo se vuelve al login cuando ya se puede iniciar sesión.
      const redirigeAlLogin = (json.codigo === 'VIGENCIA_RENOVADA');
      const titulos = {
        VIGENCIA_RENOVADA:      '¡Vigencia renovada!',
        VERIFICACION_ENVIADA:   'Revisa tu correo',
        VERIFICACION_REENVIADA: 'Te reenviamos el enlace',
        PENDIENTE_APROBACION:   'Solicitud pendiente',
      };
      const tipos = {
        VIGENCIA_RENOVADA:    'warn',
        PENDIENTE_APROBACION: 'warn',
      };
      mostrarResultado(
        tipos[json.codigo] || 'ok',
        titulos[json.codigo] || 'Listo',
        json.msg
      );
      document.getElementById('formRegistro').style.display = 'none';
      if (redirigeAlLogin) {
        setTimeout(() => { window.location.href = '<?= url("login.php") ?>'; }, 3000);
      }
    } else {
      mostrarResultado('error',
        json.codigo === 'NO_AGREMIADO'        ? 'No encontrado en el padrón' :
        json.codigo === 'AGREMIADO_INACTIVO'  ? 'Agremiación inactiva' :
        json.codigo === 'VIGENCIA_ACTIVA'     ? 'Ya tienes acceso activo' :
        json.codigo === 'CUENTA_BLOQUEADA'    ? 'Cuenta bloqueada' :
        json.codigo === 'CORREO_DUPLICADO'    ? 'Correo ya registrado' : 'Error',
        json.msg
      );
    }
  } catch(err) {
    mostrarResultado('error', 'Error de conexión', 'No se pudo conectar con el servidor. Intenta de nuevo.');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = '<i class="ti ti-user-check"></i> Crear mi cuenta';
  }
});

function mostrarResultado(tipo, titulo, mensaje) {
  const el = document.getElementById('resultado');
  el.className = 'result-box result-' + (tipo==='error'?'err':tipo);
  el.innerHTML = '<h4>' + titulo + '</h4><p>' + mensaje + '</p>';
  el.style.display = 'block';
  el.scrollIntoView({ behavior:'smooth', block:'nearest' });
}
</script>
</body>
</html>
