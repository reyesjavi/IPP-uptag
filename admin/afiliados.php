<?php
$pageTitle   = 'Afiliados';
$pageSubtitle= 'Gestión del padrón de afiliados';
$activeAdmin = 'afiliados';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin','administrativo');
$pdo = getDB();
// NOTA: el panel admin usa $_SESSION['flash_admin'] (lo renderiza admin/header.php).

$tiposValidos = ['profesor_activo','profesor_jubilado'];

// POST: activar/desactivar o cambiar condición (tipo_afiliado)
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verificarCsrf();
    $id     = intval($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($id && in_array($accion, ['activar','desactivar'])) {
        $activo = $accion === 'activar' ? 1 : 0;
        $pdo->prepare("UPDATE afiliado SET activo=:a WHERE id_afiliado=:id")->execute([':a'=>$activo,':id'=>$id]);
        registrarLog('afiliado_'.$accion, "Afiliado #$id $accion");
        $_SESSION['flash_admin'] = ['ok'=>true,'msg'=>'Afiliado '.($activo?'activado':'desactivado').' correctamente.'];

    } elseif ($id && $accion === 'cambiar_tipo') {
        // La condición (activo/jubilado) es dato de NÓMINA. El cambio manual
        // solo se permite como FALLBACK cuando el feed no la reporta para esa
        // CI — regla: no duplicar la verdad (ver INTEGRACION.md).
        $nueva = $_POST['tipo_afiliado'] ?? '';
        $stmtNom = $pdo->prepare("
            SELECT a.nombre, a.apellido, a.tipo_afiliado, e.tipo_afiliado AS tipo_nomina
            FROM afiliado a
            LEFT JOIN estado_afiliacion_cache e ON e.ci = a.ci
            WHERE a.id_afiliado = :id
        ");
        $stmtNom->execute([':id' => $id]);
        $afDatos = $stmtNom->fetch();

        if (!$afDatos || !in_array($nueva, $tiposValidos, true)) {
            $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'Condición no válida.'];
        } elseif (!empty($afDatos['tipo_nomina'])) {
            $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'La condición de este afiliado la reporta la nómina y no puede editarse manualmente.'];
        } else {
            $anterior = $afDatos['tipo_afiliado'] ?? 'profesor_activo';
            $pdo->prepare("UPDATE afiliado SET tipo_afiliado=:t WHERE id_afiliado=:id")
                ->execute([':t'=>$nueva, ':id'=>$id]);
            $nombreAfil = trim(($afDatos['nombre'] ?? '') . ' ' . ($afDatos['apellido'] ?? '')) ?: "#$id";
            registrarLog(
                'afiliado_tipo',
                "Condición de $nombreAfil cambiada de '$anterior' a '$nueva' por " . ($_SESSION['usuario_ci'] ?? 'admin')
            );
            $_SESSION['flash_admin'] = ['ok'=>true,'msg'=>"Condición de $nombreAfil actualizada."];
        }
    }

    header('Location: '.url('admin/afiliados.php')); exit;
}

$q = trim($_GET['q'] ?? '');
// El JOIN a estado_afiliacion_cache es lectura de la CACHÉ local (permitido
// para listados); las consultas autoritativas por CI van vía el provider.
$sql = "SELECT a.*, p.costo, e.tipo_afiliado AS tipo_nomina
        FROM afiliado a
        LEFT JOIN plan_medico p ON p.cod_pm=a.cod_pm
        LEFT JOIN estado_afiliacion_cache e ON e.ci = a.ci";
$params = [];
if ($q) { $sql .= " WHERE a.nombre LIKE :q OR a.apellido LIKE :q OR a.ci LIKE :q"; $params[':q']="%$q%"; }
$sql .= " ORDER BY a.id_afiliado DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$afiliados = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<form method="GET" action="<?= url('admin/afiliados.php') ?>">
  <div class="search-bar">
    <i class="ti ti-search"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por nombre, apellido o cédula..." />
    <button type="submit" class="btn btn-teal" style="padding:6px 14px;font-size:12px">Buscar</button>
    <?php if ($q): ?><a href="<?= url('admin/afiliados.php') ?>" class="btn btn-outline" style="padding:6px 14px;font-size:12px">Limpiar</a><?php endif; ?>
  </div>
</form>

<div class="sc">
  <h3>Afiliados registrados (<?= count($afiliados) ?>)</h3>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>ID</th><th>Nombre</th><th>C.I.</th><th>Plan</th><th>Ingreso</th><th>Acceso</th><th>Condición</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($afiliados as $a):
        // Condición: manda el feed de nómina; el valor local es el fallback
        $delFeed = !empty($a['tipo_nomina']);
        $tipo    = $a['tipo_nomina'] ?: ($a['tipo_afiliado'] ?? 'profesor_activo');
        [$sitCls, $sitLbl] = $tipo === 'profesor_jubilado'
            ? ['badge-blue',  'Jubilado']
            : ['badge-green', 'Activo'];
      ?>
      <tr>
        <td>AFI-<?= str_pad($a['id_afiliado'],5,'0',STR_PAD_LEFT) ?></td>
        <td style="font-weight:500"><?= htmlspecialchars($a['nombre'].' '.$a['apellido']) ?></td>
        <td><?= htmlspecialchars($a['ci']) ?></td>
        <td><?= htmlspecialchars($a['cod_pm']??'Sin plan') ?></td>
        <td><?= $a['fecha_ingreso']?date('d/m/Y',strtotime($a['fecha_ingreso'])):'—' ?></td>
        <td><span class="badge <?= $a['activo']?'badge-green':'badge-red' ?>"><?= $a['activo']?'Activo':'Inactivo' ?></span></td>
        <td>
          <?php if ($delFeed): ?>
            <!-- Dato reportado por nómina: solo lectura (no duplicar la verdad) -->
            <span class="badge <?= $sitCls ?>"><?= $sitLbl ?></span>
            <span style="font-size:10px;color:var(--text-3);display:block" title="Reportado por el sistema de nómina; no editable">
              <i class="ti ti-plug-connected"></i> nómina
            </span>
          <?php else: ?>
            <form method="POST" style="display:flex;align-items:center;gap:4px">
              <?= campoCsrf() ?>
              <input type="hidden" name="id" value="<?= $a['id_afiliado'] ?>">
              <input type="hidden" name="accion" value="cambiar_tipo">
              <select name="tipo_afiliado" style="font-size:11px;padding:3px 5px;border-radius:6px;border:1.5px solid var(--border);background:var(--surface);color:var(--text);font-family:'Nunito',sans-serif">
                <?php foreach ($tiposValidos as $tv): ?>
                  <option value="<?= $tv ?>" <?= $tipo===$tv?'selected':'' ?>><?= $tv==='profesor_jubilado'?'Jubilado':'Activo' ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn-xs btn-approve" title="Guardar condición"
                onclick="return confirm('¿Cambiar la condición de este afiliado?')">
                <i class="ti ti-check"></i>
              </button>
            </form>
          <?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <a href="<?= url('admin/afiliado_servicios.php') ?>?id=<?= $a['id_afiliado'] ?>"
             class="btn-xs btn-approve" title="Gestionar servicios habilitados">
            <i class="ti ti-shield-check"></i> Servicios
          </a>
          <form method="POST" style="display:inline;margin-left:4px">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $a['id_afiliado'] ?>">
            <input type="hidden" name="accion" value="<?= $a['activo']?'desactivar':'activar' ?>">
            <button type="submit" class="btn-xs <?= $a['activo']?'btn-reject':'btn-approve' ?>"
              onclick="return confirm('¿<?= $a['activo']?'Desactivar':'Activar' ?> el acceso de este afiliado?')">
              <i class="ti <?= $a['activo']?'ti-user-off':'ti-user-check' ?>"></i>
              <?= $a['activo']?'Desactivar':'Activar' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($afiliados)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-3);padding:2rem">Sin resultados</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
