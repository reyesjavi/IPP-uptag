<?php
$pageTitle    = 'Directorio Médico';
$pageSubtitle = 'Gestión de médicos y centros en convenio';
$activeAdmin  = 'medicos';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/MedicoModel.php';
requiereRol('admin', 'administrativo');

$model  = new MedicoModel();
$pdo    = getDB();
$flash  = $_SESSION['flash_admin'] ?? null;
unset($_SESSION['flash_admin']);

$accion = $_GET['accion'] ?? 'listar';
$id     = intval($_GET['id'] ?? 0);

// ── POST: crear o editar ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $campos = [
        'tipo'           => in_array($_POST['tipo'] ?? '', ['medico', 'centro']) ? $_POST['tipo'] : 'medico',
        'nombre'         => trim($_POST['nombre']          ?? ''),
        'apellido'       => trim($_POST['apellido']        ?? ''),
        'especialidad'   => trim($_POST['especialidad']    ?? '') ?: null,
        'cedula'         => trim($_POST['cedula']          ?? '') ?: null,
        'numero_contacto'=> trim($_POST['numero_contacto'] ?? '') ?: null,
        'direccion'      => trim($_POST['direccion']       ?? '') ?: null,
        'horario'        => trim($_POST['horario']         ?? '') ?: null,
        'convenio'       => trim($_POST['convenio']        ?? '') ?: null,
        'servicios'      => trim($_POST['servicios']       ?? '') ?: null,
        'id_servicio'    => !empty($_POST['id_servicio']) ? intval($_POST['id_servicio']) : null,
    ];

    if (empty($campos['nombre'])) {
        $_SESSION['flash_admin'] = ['ok' => false, 'msg' => 'El nombre es obligatorio.'];
        header('Location: ' . url('admin/medicos.php') . '?accion=' . $_POST['modo'] . ($id ? "&id=$id" : ''));
        exit;
    }

    if ($_POST['modo'] === 'editar' && $id) {
        $model->actualizar($id, $campos);
        $_SESSION['flash_admin'] = ['ok' => true, 'msg' => 'Registro actualizado correctamente.'];
    } else {
        $model->crear($campos);
        $_SESSION['flash_admin'] = ['ok' => true, 'msg' => 'Registro creado correctamente.'];
    }
    registrarLog('medico_' . $_POST['modo'], "{$campos['tipo']}: {$campos['nombre']} {$campos['apellido']}");
    header('Location: ' . url('admin/medicos.php'));
    exit;
}

// ── GET: toggle activo ────────────────────────────────────────
if ($accion === 'toggle' && $id) {
    verificarCsrf();
    $model->toggleActivo($id);
    header('Location: ' . url('admin/medicos.php'));
    exit;
}

// ── Datos ─────────────────────────────────────────────────────
$registros = $model->findAll('', [], 'tipo, apellido, nombre');
$editando  = ($accion === 'editar' && $id) ? $model->findById($id) : null;
$servicios = $pdo->query("SELECT id_servicio, tipo_servicio FROM servicio ORDER BY tipo_servicio")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<?php if ($flash): ?>
  <div class="flash-msg <?= $flash['ok'] ? 'flash-ok' : 'flash-err' ?>">
    <i class="ti <?= $flash['ok'] ? 'ti-check' : 'ti-alert-circle' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 340px;gap:1.2rem;align-items:start">

  <!-- Lista -->
  <div class="report-box">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
      <h3 style="margin:0">Registros (<?= count($registros) ?>)</h3>
      <a href="<?= url('admin/medicos.php') ?>?accion=nuevo" class="btn btn-teal" style="font-size:13px">
        <i class="ti ti-plus"></i> Nuevo
      </a>
    </div>

    <?php if (empty($registros)): ?>
      <p style="font-size:13px;color:var(--text-3)">No hay registros. Agrega médicos o centros con el botón "Nuevo".</p>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>Tipo</th><th>Nombre</th><th>Especialidad / Servicio</th><th>Contacto</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($registros as $r): ?>
          <tr style="<?= !$r['activo'] ? 'opacity:.55' : '' ?>">
            <td>
              <span class="badge <?= $r['tipo']==='centro' ? 'badge-blue' : 'badge-amber' ?>">
                <?= $r['tipo']==='centro' ? 'Centro' : 'Médico' ?>
              </span>
            </td>
            <td>
              <?= htmlspecialchars(($r['tipo']==='medico' ? 'Dr(a). ' : '') . $r['nombre'] . ' ' . $r['apellido']) ?>
              <?php if ($r['cedula']): ?><br><small style="color:var(--text-3)"><?= htmlspecialchars($r['cedula']) ?></small><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['especialidad'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['numero_contacto'] ?? '—') ?></td>
            <td><span class="badge <?= $r['activo'] ? 'badge-green' : 'badge-red' ?>"><?= $r['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= url('admin/medicos.php') ?>?accion=editar&id=<?= $r['id_medico'] ?>" style="font-size:12px;color:var(--primary);margin-right:8px"><i class="ti ti-edit"></i></a>
              <a href="<?= url('admin/medicos.php') ?>?accion=toggle&id=<?= $r['id_medico'] ?>&csrf=<?= generarCsrf() ?>"
                 onclick="return confirm('¿Cambiar estado?')"
                 style="font-size:12px;color:var(--text-3)"><i class="ti ti-toggle-left"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Formulario nuevo / editar -->
  <div class="report-box">
    <h3><?= $editando ? 'Editar registro' : 'Nuevo registro' ?></h3>
    <form method="POST" action="<?= url('admin/medicos.php') ?>">
      <?= campoCsrf() ?>
      <input type="hidden" name="modo" value="<?= $editando ? 'editar' : 'nuevo' ?>">

      <div class="form-group">
        <label>Tipo</label>
        <select name="tipo">
          <option value="medico" <?= ($editando['tipo'] ?? '') === 'medico' ? 'selected' : '' ?>>Médico especialista</option>
          <option value="centro" <?= ($editando['tipo'] ?? '') === 'centro' ? 'selected' : '' ?>>Centro / clínica / farmacia</option>
        </select>
      </div>

      <div class="form-group">
        <label>Nombre *</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($editando['nombre'] ?? '') ?>" required />
      </div>
      <div class="form-group">
        <label>Apellido / Razón social</label>
        <input type="text" name="apellido" value="<?= htmlspecialchars($editando['apellido'] ?? '') ?>" />
      </div>
      <div class="form-group">
        <label>Especialidad</label>
        <input type="text" name="especialidad" value="<?= htmlspecialchars($editando['especialidad'] ?? '') ?>" placeholder="Ej: Cardiología" />
      </div>
      <div class="form-group">
        <label>Cédula</label>
        <input type="text" name="cedula" value="<?= htmlspecialchars($editando['cedula'] ?? '') ?>" placeholder="V-12345678" />
      </div>
      <div class="form-group">
        <label>Teléfono</label>
        <input type="text" name="numero_contacto" value="<?= htmlspecialchars($editando['numero_contacto'] ?? '') ?>" placeholder="0255-000-0000" />
      </div>
      <div class="form-group">
        <label>Dirección</label>
        <input type="text" name="direccion" value="<?= htmlspecialchars($editando['direccion'] ?? '') ?>" placeholder="Ciudad, Estado" />
      </div>
      <div class="form-group">
        <label>Horario</label>
        <input type="text" name="horario" value="<?= htmlspecialchars($editando['horario'] ?? '') ?>" placeholder="Lun–Vie 8am–4pm" />
      </div>
      <div class="form-group">
        <label>Convenio</label>
        <input type="text" name="convenio" value="<?= htmlspecialchars($editando['convenio'] ?? '') ?>" placeholder="Convenio Full / Descuento 30%" />
      </div>
      <div class="form-group">
        <label>Servicios que ofrece</label>
        <textarea name="servicios" rows="3" placeholder="Ej: Valoración cardiovascular, electrocardiograma, Holter..."><?= htmlspecialchars($editando['servicios'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Servicio del plan</label>
        <select name="id_servicio">
          <option value="">Sin servicio asociado</option>
          <?php foreach ($servicios as $s): ?>
            <option value="<?= $s['id_servicio'] ?>" <?= ($editando['id_servicio'] ?? null) == $s['id_servicio'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['tipo_servicio']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex;gap:.5rem;margin-top:.5rem">
        <button type="submit" class="btn btn-teal" style="flex:1">
          <i class="ti <?= $editando ? 'ti-check' : 'ti-plus' ?>"></i>
          <?= $editando ? 'Guardar cambios' : 'Crear registro' ?>
        </button>
        <?php if ($editando): ?>
          <a href="<?= url('admin/medicos.php') ?>" class="btn" style="background:var(--surface-2);color:var(--text)">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
