<?php
$pageTitle   = 'Afiliados';
$pageSubtitle= 'Gestión del padrón de afiliados';
$activeAdmin = 'afiliados';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin','administrativo');
$pdo   = getDB();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$situacionesValidas = ['activo','jubilado','suspendido','egresado'];

// POST: activar/desactivar o cambiar situación
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verificarCsrf();
    $id     = intval($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($id && in_array($accion, ['activar','desactivar'])) {
        $activo = $accion === 'activar' ? 1 : 0;
        $pdo->prepare("UPDATE afiliado SET activo=:a WHERE id_afiliado=:id")->execute([':a'=>$activo,':id'=>$id]);
        registrarLog('afiliado_'.$accion, "Afiliado #$id $accion");
        $_SESSION['flash'] = ['ok'=>true,'msg'=>'Afiliado '.($activo?'activado':'desactivado').' correctamente.'];

    } elseif ($id && $accion === 'cambiar_situacion') {
        $nueva = $_POST['situacion'] ?? '';
        if (in_array($nueva, $situacionesValidas)) {
            // Obtener nombre del afiliado para el log
            $stmtNom = $pdo->prepare("SELECT nombre, apellido, situacion FROM afiliado WHERE id_afiliado=:id");
            $stmtNom->execute([':id' => $id]);
            $afDatos = $stmtNom->fetch();
            $anterior = $afDatos['situacion'] ?? 'activo';

            $pdo->prepare("UPDATE afiliado SET situacion=:s WHERE id_afiliado=:id")
                ->execute([':s'=>$nueva, ':id'=>$id]);

            $nombreAfil = trim(($afDatos['nombre'] ?? '') . ' ' . ($afDatos['apellido'] ?? '')) ?: "#$id";
            registrarLog(
                'afiliado_situacion',
                "Situación de $nombreAfil cambiada de '$anterior' a '$nueva' por " . ($_SESSION['usuario_ci'] ?? 'admin')
            );
            $_SESSION['flash'] = ['ok'=>true,'msg'=>"Situación de $nombreAfil actualizada a '$nueva'."];
        } else {
            $_SESSION['flash'] = ['ok'=>false,'msg'=>'Situación no válida.'];
        }
    }

    header('Location: '.url('admin/afiliados.php')); exit;
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT a.*, p.costo FROM afiliado a LEFT JOIN plan_medico p ON p.cod_pm=a.cod_pm";
$params = [];
if ($q) { $sql .= " WHERE a.nombre LIKE :q OR a.apellido LIKE :q OR a.ci LIKE :q"; $params[':q']="%$q%"; }
$sql .= " ORDER BY a.id_afiliado DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$afiliados = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>
<?php if ($flash): ?><div class="flash-msg <?= $flash['ok']?'flash-ok':'flash-err' ?>"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>

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
      <thead><tr><th>ID</th><th>Nombre</th><th>C.I.</th><th>Plan</th><th>Ingreso</th><th>Acceso</th><th>Situación</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($afiliados as $a):
        $sit = $a['situacion'] ?? 'activo';
        [$sitCls, $sitLbl] = match($sit) {
            'jubilado'   => ['badge-blue',  'Jubilado'],
            'suspendido' => ['badge-amber', 'Suspendido'],
            'egresado'   => ['badge',       'Egresado'],
            default      => ['badge-green', 'Activo'],
        };
      ?>
      <tr>
        <td>AFI-<?= str_pad($a['id_afiliado'],5,'0',STR_PAD_LEFT) ?></td>
        <td style="font-weight:500"><?= htmlspecialchars($a['nombre'].' '.$a['apellido']) ?><br>
          <small style="color:var(--text-3)"><?= htmlspecialchars($a['ci']) ?></small></td>
        <td><?= htmlspecialchars($a['correo']??'—') ?></td>
        <td><?= htmlspecialchars($a['cod_pm']??'Sin plan') ?></td>
        <td><?= $a['fecha_ingreso']?date('d/m/Y',strtotime($a['fecha_ingreso'])):'—' ?></td>
        <td><span class="badge <?= $a['activo']?'badge-green':'badge-red' ?>"><?= $a['activo']?'Activo':'Inactivo' ?></span></td>
        <td>
          <form method="POST" style="display:flex;align-items:center;gap:4px">
            <?= campoCsrf() ?>
            <input type="hidden" name="id" value="<?= $a['id_afiliado'] ?>">
            <input type="hidden" name="accion" value="cambiar_situacion">
            <select name="situacion" style="font-size:11px;padding:3px 5px;border-radius:6px;border:1.5px solid var(--border);background:var(--surface);color:var(--text);font-family:'Nunito',sans-serif">
              <?php foreach ($situacionesValidas as $sv): ?>
                <option value="<?= $sv ?>" <?= $sit===$sv?'selected':'' ?>><?= ucfirst($sv) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-xs btn-approve" title="Guardar situación"
              onclick="return confirm('¿Cambiar situación de este afiliado?')">
              <i class="ti ti-check"></i>
            </button>
          </form>
        </td>
        <td>
          <form method="POST" style="display:inline">
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
