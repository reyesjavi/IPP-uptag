<?php
$pageTitle = 'Mi Perfil';
$activeNav = '';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/integracion/Integraciones.php';
requiereLogin();
$pdo      = getDB();
$afilId   = $_SESSION['afiliado_id'] ?? null;
$afiliado = getAfiliado();
$rolActual = $_SESSION['usuario_rol'] ?? 'afiliado';

// Solo buscar beneficiarios si hay afiliado vinculado
$beneficiarios = [];
if ($afilId) {
    $b = $pdo->prepare("SELECT * FROM beneficiario WHERE id_afiliado=:id ORDER BY (parentesco='titular') DESC, nombre");
    $b->execute([':id' => $afilId]);
    $beneficiarios = $b->fetchAll();
}

// Estado de afiliación según nómina (informativo; nunca bloquea la página)
$estadoNomina = null;
if ($afilId && !empty($_SESSION['usuario_ci'])) {
    try {
        $estadoNomina = Integraciones::estadoAfiliacion()->obtenerEstado($_SESSION['usuario_ci']);
    } catch (Throwable $e) {
        error_log('[UPTAG Perfil] provider nómina no disponible: ' . $e->getMessage());
    }
}

$nombre   = $afiliado['nombre']   ?? '';
$apellido = $afiliado['apellido'] ?? '';
$iniciales = strtoupper(substr($nombre,0,1) . substr($apellido,0,1));
if (!$iniciales) {
    $ci = $_SESSION['usuario_ci'] ?? 'U';
    $iniciales = strtoupper(substr($ci, 0, 2));
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="mod-header">
    <div class="avatar" style="width:46px;height:46px;font-size:16px;border-radius:var(--radius)">
      <?= htmlspecialchars($iniciales) ?>
    </div>
    <div>
      <h2>Mi Expediente Digital</h2>
      <p>Datos personales, afiliación y carga familiar</p>
    </div>
  </div>

  <?php if (!$afilId || empty($afiliado)): ?>
    <!-- Usuario sin afiliado vinculado (admin, administrativo) -->
    <div class="sc" style="text-align:center;padding:3rem">
      <i class="ti ti-user-off" style="font-size:40px;color:var(--text-3);display:block;margin-bottom:1rem"></i>
      <h3 style="margin-bottom:.5rem">Sin expediente de afiliado</h3>
      <p style="font-size:13px;color:var(--text-3);margin-bottom:1.5rem">
        Tu cuenta (<?= htmlspecialchars($_SESSION['usuario_ci'] ?? '') ?>) tiene rol
        <strong><?= htmlspecialchars($rolActual) ?></strong> y no está vinculada a un afiliado.<br>
        Si eres docente y deseas ver tu expediente, contacta al administrador para vincular tu cuenta.
      </p>
      <?php if (in_array($rolActual, ['admin','administrativo'])): ?>
        <a href="<?= url('admin/dashboard.php') ?>" class="btn btn-teal">
          <i class="ti ti-layout-dashboard"></i> Ir al Panel Administrativo
        </a>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <!-- Usuario con afiliado vinculado -->
    <div class="two-col">
      <div>
        <div class="sc">
          <h3>Datos personales</h3>
          <div class="info-row">
            <span class="lbl">Nombre completo</span>
            <span class="val"><?= htmlspecialchars(trim($nombre.' '.$apellido)) ?></span>
          </div>
          <div class="info-row">
            <span class="lbl">Cédula</span>
            <span class="val"><?= htmlspecialchars($afiliado['ci'] ?? '—') ?></span>
          </div>
          <div class="info-row">
            <span class="lbl">Correo</span>
            <span class="val"><?= htmlspecialchars($afiliado['correo'] ?? '—') ?></span>
          </div>
          <div class="info-row">
            <span class="lbl">Teléfono</span>
            <span class="val"><?= htmlspecialchars($afiliado['telefono'] ?? '—') ?></span>
          </div>
          <div class="info-row">
            <span class="lbl">Fecha de nacimiento</span>
            <span class="val">
              <?= !empty($afiliado['fecha_nacimiento'])
                  ? date('d/m/Y', strtotime($afiliado['fecha_nacimiento']))
                  : '—' ?>
            </span>
          </div>
          <div class="info-row">
            <span class="lbl">Fecha de ingreso</span>
            <span class="val">
              <?= !empty($afiliado['fecha_ingreso'])
                  ? date('d/m/Y', strtotime($afiliado['fecha_ingreso']))
                  : '—' ?>
            </span>
          </div>
          <div class="info-row">
            <span class="lbl">Acceso al sistema</span>
            <span class="val">
              <span class="badge <?= ($afiliado['activo'] ?? 0) ? 'badge-green' : 'badge-red' ?>">
                <?= ($afiliado['activo'] ?? 0) ? 'Activo' : 'Inactivo' ?>
              </span>
            </span>
          </div>
          <?php
            // Condición previsional: manda el feed de nómina; el local es fallback
            $tipoAfil = $estadoNomina->tipoAfiliado ?? $afiliado['tipo_afiliado'] ?? 'profesor_activo';
            [$sitCls, $sitLbl] = $tipoAfil === 'profesor_jubilado'
                ? ['badge-blue',  'Profesor jubilado']
                : ['badge-green', 'Profesor activo'];
          ?>
          <div class="info-row">
            <span class="lbl">Condición</span>
            <span class="val"><span class="badge <?= $sitCls ?>"><?= $sitLbl ?></span></span>
          </div>
        </div>
      </div>

      <div>
        <div class="sc">
          <h3>Afiliación</h3>
          <div class="info-row">
            <span class="lbl">ID Afiliado</span>
            <span class="val">AFI-<?= str_pad($afilId, 5, '0', STR_PAD_LEFT) ?></span>
          </div>
          <div class="info-row">
            <span class="lbl">Código IPP</span>
            <span class="val"><?= htmlspecialchars($afiliado['cod_a'] ?? '—') ?></span>
          </div>
          <div class="info-row">
            <span class="lbl">Plan médico</span>
            <span class="val"><?= htmlspecialchars($afiliado['cod_pm'] ?? 'Sin plan') ?></span>
          </div>
          <div class="info-row">
            <span class="lbl">Afiliación (nómina)</span>
            <span class="val">
              <?php if ($estadoNomina): ?>
                <?php
                  [$enCls, $enLbl] = match($estadoNomina->estado) {
                      'moroso'     => ['badge-amber', 'Moroso'],
                      'suspendido' => ['badge-red',   'Suspendido'],
                      'inactivo'   => ['badge-red',   'Inactivo'],
                      default      => ['badge-green', 'Al día'],
                  };
                ?>
                <span class="badge <?= $enCls ?>"><?= $enLbl ?></span>
                <?php if ($estadoNomina->periodo): ?>
                  <span style="font-size:11px;color:var(--text-3)">Período <?= htmlspecialchars($estadoNomina->periodo) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge">Sin datos de nómina</span>
              <?php endif; ?>
            </span>
          </div>
          <div style="margin-top:1rem;display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-outline" onclick="abrirCambioPass()">
              <i class="ti ti-lock"></i> Cambiar contraseña
            </button>
          </div>
        </div>

        <div class="sc">
          <h3>Beneficiarios registrados</h3>
          <?php if (empty($beneficiarios)): ?>
            <p style="font-size:13px;color:var(--text-3)">No hay beneficiarios registrados.</p>
          <?php else: ?>
            <table>
              <thead>
                <tr><th>Nombre</th><th>Parentesco</th><th>C.I.</th><th>Estado</th></tr>
              </thead>
              <tbody>
                <?php foreach ($beneficiarios as $b):
                  $parLbl = ($b['parentesco'] ?? '') === 'conyuge' ? 'Cónyuge' : ucfirst($b['parentesco'] ?? '—');
                ?>
                <tr>
                  <td><?= htmlspecialchars($b['nombre'].' '.$b['apellido']) ?></td>
                  <td><?= htmlspecialchars($parLbl) ?></td>
                  <td><?= htmlspecialchars($b['ci'] ?? 'Sin C.I.') ?></td>
                  <td><span class="badge <?= ($b['activo'] ?? 1) ? 'badge-green' : 'badge-red' ?>"><?= ($b['activo'] ?? 1) ? 'Activo' : 'Inactivo' ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
          <div style="margin-top:1rem">
            <a href="<?= url('beneficiarios.php') ?>" class="btn btn-teal" style="display:inline-flex">
              <i class="ti ti-users"></i> Gestionar carga familiar
            </a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
