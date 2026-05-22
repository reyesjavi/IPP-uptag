<?php
// directorio.php
$pageTitle = 'Directorio Médico';
$activeNav = 'directorio';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
requiereLogin();
$pdo = getDB();

// Buscar médicos desde la BD
$busqueda = trim($_GET['q'] ?? '');
$filtro   = $_GET['filtro'] ?? 'todos';

$sql  = "SELECT m.*, s.tipo_servicio FROM medico m LEFT JOIN servicio s ON s.id_servicio = m.id_servicio WHERE 1=1";
$params = [];
if ($busqueda) {
    $sql .= " AND (m.nombre LIKE :q OR m.apellido LIKE :q OR m.especialidad LIKE :q)";
    $params[':q'] = "%$busqueda%";
}
$stmt = $pdo->prepare($sql . " ORDER BY m.apellido LIMIT 30");
$stmt->execute($params);
$medicos = $stmt->fetchAll();

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

  <!-- Médicos de la BD -->
  <?php if (!empty($medicos)): ?>
  <div class="sc">
    <h3>Especialistas registrados <?= $busqueda ? '— resultados para "'.htmlspecialchars($busqueda).'"' : '' ?></h3>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Nombre</th><th>Especialidad</th><th>Cédula</th><th>Contacto</th><th>Servicio</th></tr></thead>
        <tbody>
          <?php foreach ($medicos as $m): ?>
          <tr>
            <td><?= htmlspecialchars('Dr(a). '.$m['nombre'].' '.$m['apellido']) ?></td>
            <td><?= htmlspecialchars($m['especialidad']) ?></td>
            <td><?= htmlspecialchars($m['cedula']) ?></td>
            <td><?= htmlspecialchars($m['numero_contacto'] ?? '—') ?></td>
            <td><?= htmlspecialchars($m['tipo_servicio'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Centros en convenio (estáticos / pre-cargados) -->
  <h3 style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:10px">Centros médicos en convenio</h3>
  <div class="dir-grid">
    <div class="dir-card">
      <div class="dir-name">Policlínica Los Llanos</div>
      <div class="dir-spec">Clínica General · Convenio Full</div>
      <div class="dir-info"><i class="ti ti-map-pin"></i> Acarigua, Portuguesa</div>
      <div class="dir-info"><i class="ti ti-phone"></i> 0255-621-0000</div>
      <div class="dir-info"><i class="ti ti-clock"></i> Lun–Dom 24 horas</div>
      <span class="badge badge-green" style="margin-top:6px">Activo</span>
    </div>
    <div class="dir-card">
      <div class="dir-name">Farmacia UPTAG</div>
      <div class="dir-spec">Farmacia · Descuento 30%</div>
      <div class="dir-info"><i class="ti ti-map-pin"></i> Campus UPTAG, Acarigua</div>
      <div class="dir-info"><i class="ti ti-phone"></i> 0255-621-0010</div>
      <div class="dir-info"><i class="ti ti-clock"></i> Lun–Sáb 7am–7pm</div>
      <span class="badge badge-green" style="margin-top:6px">Activo</span>
    </div>
    <div class="dir-card">
      <div class="dir-name">Centro Odontológico UPTAG</div>
      <div class="dir-spec">Dental · Plan incluido</div>
      <div class="dir-info"><i class="ti ti-map-pin"></i> Campus UPTAG</div>
      <div class="dir-info"><i class="ti ti-phone"></i> 0255-621-0020</div>
      <div class="dir-info"><i class="ti ti-clock"></i> Lun–Vie 8am–4pm</div>
      <span class="badge badge-green" style="margin-top:6px">Activo</span>
    </div>
    <div class="dir-card">
      <div class="dir-name">Clínica Guanare</div>
      <div class="dir-spec">Clínica · Convenio Parcial</div>
      <div class="dir-info"><i class="ti ti-map-pin"></i> Guanare, Portuguesa</div>
      <div class="dir-info"><i class="ti ti-phone"></i> 0257-251-0000</div>
      <div class="dir-info"><i class="ti ti-clock"></i> Lun–Vie 7am–5pm</div>
      <span class="badge badge-amber" style="margin-top:6px">Citas previas</span>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
