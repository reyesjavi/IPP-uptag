<?php
$pageTitle = 'Directorio Médico';
$activeNav = 'directorio';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/models/MedicoModel.php';
requiereLogin();

$model    = new MedicoModel();
$busqueda = trim($_GET['q'] ?? '');

$medicos = $model->buscarConServicio($busqueda);
$centros = $model->getCentros();

require_once __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="mod-header">
    <div class="mod-icon" style="background:var(--primary-light)"><i class="ti ti-map-pin" style="color:var(--primary)"></i></div>
    <div>
      <h2>Directorio Médico</h2>
      <p>Clínicas, farmacias y especialistas en convenio</p>
    </div>
  </div>

  <!-- Buscador -->
  <form method="GET" action="<?= url('directorio.php') ?>">
    <div class="sc" style="padding:10px 14px;margin-bottom:1rem">
      <div style="display:flex;align-items:center;gap:10px">
        <i class="ti ti-search" style="font-size:18px;color:var(--text-3)"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($busqueda) ?>"
               placeholder="Buscar por nombre, especialidad..."
               style="border:none;background:none;font-size:13px;font-family:'Nunito',sans-serif;color:var(--text);outline:none;flex:1" />
        <button type="submit" class="btn btn-teal" style="padding:6px 14px">Buscar</button>
      </div>
    </div>
  </form>

  <!-- Especialistas -->
  <?php if (!empty($medicos)): ?>
  <div class="sc" style="margin-bottom:1.5rem">
    <h3>Especialistas<?= $busqueda ? ' — resultados para "' . htmlspecialchars($busqueda) . '"' : '' ?></h3>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Especialista</th><th>Especialidad</th><th>Horario</th><th>Servicios</th><th>Contacto</th></tr></thead>
        <tbody>
          <?php foreach ($medicos as $m): ?>
          <tr>
            <td><?= htmlspecialchars('Dr(a). ' . $m['nombre'] . ' ' . $m['apellido']) ?></td>
            <td><?= htmlspecialchars($m['especialidad'] ?? '—') ?></td>
            <td><?= htmlspecialchars($m['horario'] ?? '—') ?></td>
            <td><?= htmlspecialchars($m['servicios'] ?? '—') ?></td>
            <td><?= htmlspecialchars($m['numero_contacto'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php elseif ($busqueda): ?>
    <div class="sc" style="margin-bottom:1.5rem">
      <p style="font-size:13px;color:var(--text-3)">No se encontraron especialistas para "<?= htmlspecialchars($busqueda) ?>".</p>
    </div>
  <?php endif; ?>

  <!-- Centros en convenio -->
  <h3 style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:10px">Centros médicos en convenio</h3>
  <div class="dir-grid">
    <?php if (!empty($centros)): foreach ($centros as $c): ?>
    <div class="dir-card">
      <div class="dir-name"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido']) ?></div>
      <div class="dir-spec"><?= htmlspecialchars(($c['especialidad'] ?? '') . ($c['convenio'] ? ' · ' . $c['convenio'] : '')) ?></div>
      <?php if ($c['direccion']): ?><div class="dir-info"><i class="ti ti-map-pin"></i> <?= htmlspecialchars($c['direccion']) ?></div><?php endif; ?>
      <?php if ($c['numero_contacto']): ?><div class="dir-info"><i class="ti ti-phone"></i> <?= htmlspecialchars($c['numero_contacto']) ?></div><?php endif; ?>
      <?php if ($c['horario']): ?><div class="dir-info"><i class="ti ti-clock"></i> <?= htmlspecialchars($c['horario']) ?></div><?php endif; ?>
      <span class="badge badge-green" style="margin-top:6px">Activo</span>
    </div>
    <?php endforeach; else: ?>
      <div class="dir-card" style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--text-3)">
        <i class="ti ti-hospital" style="font-size:32px;display:block;margin-bottom:.5rem"></i>
        No hay centros en convenio registrados aún. Contacta a la administración del IPP para más información.
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
