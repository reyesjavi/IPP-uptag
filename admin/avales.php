<?php
// admin/avales.php — Gestión de cartas aval
$pageTitle   = 'Cartas Aval';
$pageSubtitle= 'Aprobación de autorizaciones médicas';
$activeAdmin = 'avales';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin','administrativo');
$pdo   = getDB();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verificarCsrf();
    $id     = intval($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';
    if ($id && in_array($accion,['aprobar','rechazar'])) {
        $estado = $accion==='aprobar' ? 'aprobada' : 'rechazada';
        $pdo->prepare("UPDATE carta_aval SET estado=:e, fecha_vencimiento=DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id_carta=:id")
            ->execute([':e'=>$estado,':id'=>$id]);
        registrarLog('aval_'.$estado, "Carta aval #$id $estado");
        $_SESSION['flash_admin'] = ['ok'=>true,'msg'=>'Carta aval '.$estado.' correctamente.'];
    }
    header('Location: '.url('admin/avales.php')); exit;
}

$avales = $pdo->query("
    SELECT c.*, a.nombre, a.apellido, a.ci
    FROM carta_aval c JOIN afiliado a ON a.id_afiliado=c.id_afiliado
    ORDER BY c.fecha_solicitud DESC
")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="sc">
  <h3>Cartas aval (<?= count($avales) ?>)</h3>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>ID</th><th>Afiliado</th><th>Médico</th><th>Procedimiento</th><th>Monto</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($avales as $c):
        $bCls = match($c['estado']) { 'aprobada'=>'badge-green','rechazada'=>'badge-red','vencida'=>'badge-red', default=>'badge-amber' };
      ?>
      <tr>
        <td>CA-<?= str_pad($c['id_carta'],3,'0',STR_PAD_LEFT) ?></td>
        <td><?= htmlspecialchars($c['nombre'].' '.$c['apellido']) ?></td>
        <td><?= htmlspecialchars($c['medico_tratante']) ?></td>
        <td><?= htmlspecialchars($c['procedimiento']) ?></td>
        <td><?= $c['monto_estimado']?'Bs. '.number_format($c['monto_estimado'],2,',','.'):'—' ?></td>
        <td><?= date('d/m/Y',strtotime($c['fecha_solicitud'])) ?></td>
        <td><span class="badge <?= $bCls ?>"><?= ucfirst($c['estado']) ?></span></td>
        <td>
          <?php if ($c['estado']==='pendiente'): ?>
          <form method="POST" style="display:inline">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $c['id_carta'] ?>">
            <?= campoCsrf() ?><input type="hidden" name="accion" value="aprobar">
            <button type="submit" class="btn-xs btn-approve"><i class="ti ti-check"></i> Aprobar</button>
          </form>
          <form method="POST" style="display:inline" onsubmit="return confirm('¿Rechazar esta carta aval?')">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $c['id_carta'] ?>">
            <?= campoCsrf() ?><input type="hidden" name="accion" value="rechazar">
            <button type="submit" class="btn-xs btn-reject"><i class="ti ti-x"></i> Rechazar</button>
          </form>
          <?php else: ?><span style="font-size:12px;color:var(--text-3)">Procesado</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($avales)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-3);padding:2rem">Sin registros</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
