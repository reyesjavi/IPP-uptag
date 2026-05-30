// app.js — Portal UPTAG

// ── Indicador de carga en formularios (doble clic prevenido) ──
document.addEventListener('DOMContentLoaded', function () {

  // Todos los formularios POST muestran estado de carga
  document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(function (form) {
    form.addEventListener('submit', function () {
      const btn = form.querySelector('button[type="submit"]');
      if (btn && !btn.disabled) {
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="ti ti-loader" style="animation:spin .8s linear infinite"></i> Procesando...';
        // Re-habilitar tras 10s como fallback por si algo falla
        setTimeout(function () {
          btn.disabled = false;
          btn.innerHTML = original;
        }, 10000);
      }
    });
  });

  // ── Auto-ocultar mensajes flash tras 5 segundos ──
  document.querySelectorAll('.flash-msg').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 5000);
  });

  // ── Tabs: cambiar pestaña activa ──
  window.switchTab = function (btn, targetId, groupClass) {
    // Desactivar todos los tabs del grupo
    if (groupClass) {
      document.querySelectorAll('.' + groupClass).forEach(function (p) {
        p.style.display = 'none';
      });
      btn.closest('.tab-bar').querySelectorAll('.tab').forEach(function (t) {
        t.classList.remove('active');
      });
    }
    // Activar el seleccionado
    const target = document.getElementById(targetId);
    if (target) target.style.display = 'block';
    btn.classList.add('active');
  };

  // Activar tab correcto al cargar (si hay ?tab= en la URL)
  const params = new URLSearchParams(window.location.search);
  const tabParam = params.get('tab');
  if (tabParam) {
    const tabBtn = document.querySelector('[data-tab="' + tabParam + '"]');
    if (tabBtn) tabBtn.click();
  }

  // ── Hamburger menu (mobile) ──
  const menuToggle = document.getElementById('menuToggle');
  if (menuToggle) {
    menuToggle.addEventListener('click', function () {
      const nav = document.querySelector('.nav');
      const open = nav.classList.toggle('nav-open');
      menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.querySelectorAll('.nav-links a').forEach(function (a) {
      a.addEventListener('click', function () {
        document.querySelector('.nav').classList.remove('nav-open');
        menuToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // ── Confirmar acciones destructivas ──
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  // ── Simulador de préstamo ──
  window.calcularPrestamo = function () {
    const monto  = parseFloat(document.getElementById('sim-monto')?.value  || 0);
    const plazo  = parseInt(document.getElementById('sim-plazo')?.value    || 0);
    const tasaA  = parseFloat(document.getElementById('sim-tasa')?.value   || 12);
    const tasaM  = tasaA / 12 / 100;

    if (!monto || !plazo || monto <= 0 || plazo <= 0) return;

    const cuota = monto * (tasaM * Math.pow(1 + tasaM, plazo)) / (Math.pow(1 + tasaM, plazo) - 1);
    const totalPagar = cuota * plazo;
    const totalInt   = totalPagar - monto;

    const fmt = function (n) { return 'Bs. ' + n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    const res = document.getElementById('sim-result');
    if (res) {
      res.style.display = 'block';
      res.innerHTML =
        '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-top:.5rem">' +
          '<div><div style="font-size:12px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px">Cuota mensual</div>' +
          '<div style="font-size:22px;font-weight:700;color:var(--primary);font-family:Syne,sans-serif">' + fmt(cuota) + '</div></div>' +
          '<div><div style="font-size:12px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px">Total a pagar</div>' +
          '<div style="font-size:22px;font-weight:700;color:var(--text);font-family:Syne,sans-serif">' + fmt(totalPagar) + '</div></div>' +
          '<div><div style="font-size:12px;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px">Total intereses</div>' +
          '<div style="font-size:22px;font-weight:700;color:var(--gold);font-family:Syne,sans-serif">' + fmt(totalInt) + '</div></div>' +
        '</div>';
    }
  };
});

// Animación de ícono de carga
const style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);
