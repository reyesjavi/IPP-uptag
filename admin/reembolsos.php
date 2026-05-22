<?php
$pageTitle    = 'Reembolsos';
$pageSubtitle = 'Gestión y aprobación de solicitudes';
$activeAdmin  = 'reembolsos';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin','administrativo');
$pdo   = getDB();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Acción: aprobar o rechazar ──
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verificarCsrf();
    $id     = intval($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';
    $monto  = floatval($_POST['monto_aprobado'] ?? 0);

    if ($id && in_array($accion,['aprobar','rechazar'])) {
        // Obtener el reembolso para validar monto
        $rStmt = $pdo->prepare("SELECT monto_solicitado, estado FROM reembolso WHERE id_reembolso=:id");
        $rStmt->execute([':id'=>$id]);
        $reemb = $rStmt->fetch();

        if (!$reemb) {
            $_SESSION['flash'] = ['ok'=>false,'msg'=>'Reembolso no encontrado.'];
        } elseif (!in_array($reemb['estado'], ['pendiente','en_revision'])) {
            $_SESSION['flash'] = ['ok'=>false,'msg'=>'Este reembolso ya fue procesado por otro usuario.'];
        } elseif ($accion==='aprobar' && $monto > (float)$reemb['monto_solicitado']) {
            $_SESSION['flash'] = ['ok'=>false,'msg'=>'El monto aprobado no puede superar el solicitado (Bs. '.number_format($reemb['monto_solicitado'],2,',','.').').'];
        } elseif ($accion==='aprobar' && $monto <= 0) {
            $_SESSION['flash'] = ['ok'=>false,'msg'=>'El monto aprobado debe ser mayor a cero.'];
        } else {
            $estado = $accion==='aprobar' ? 'aprobado' : 'rechazado';
            // UPDATE condicional anti race-condition
            $stmt = $pdo->prepare("
                UPDATE reembolso SET estado=:estado, monto_aprobado=:monto, fecha_resolucion=NOW()
                WHERE id_reembolso=:id AND estado IN ('pendiente','en_revision')
            ");
            $stmt->execute([':estado'=>$estado, ':monto'=>($accion==='aprobar'?$monto:0), ':id'=>$id]);

            if ($stmt->rowCount() > 0) {
                registrarLog('reembolso_'.$estado, "Reembolso #$id $estado");
                $_SESSION['flash'] = ['ok'=>true,'msg'=>'Reembolso '.($accion==='aprobar'?'aprobado':'rechazado').' correctamente.'];
            } else {
                $_SESSION['flash'] = ['ok'=>false,'msg'=>'El reembolso ya había sido procesado.'];
            }
        }
    }
    header('Location: '.url('admin/reembolsos.php'));
    exit;
}

// ── Filtro con whitelist (previene SQL injection) ──
$estadosPermitidos = ['todos','pendiente','en_revision','aprobado','rechazado'];
$filtro = in_array($_GET['estado'] ?? 'todos', $estadosPermitidos) ? ($_GET['estado'] ?? 'todos') : 'todos';

$sql = "SELECT r.*, a.nombre, a.apellido, a.ci
        FROM reembolso r JOIN afiliado a ON a.id_afiliado=r.id_afiliado";
$params = [];
if ($filtro !== 'todos') {
    $sql .= " WHERE r.estado = :estado";
    $params[':estado'] = $filtro;
}
$sql .= " ORDER BY r.fecha_solicitud DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reembolsos = $stmt->fetchAll();

require_once __DIR__ . '/header.php';
?>

<?php if ($flash): ?>
  <div class="flash-msg <?= $flash['ok']?'flash-ok':'flash-err' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<!-- Filtros -->
<div style="display:flex;gap:8px;margin-bottom:1rem;flex-wrap:wrap">
  <?php foreach (['todos'=>'Todos','pendiente'=>'Pendiente','en_revision'=>'En revisión','aprobado'=>'Aprobado','rechazado'=>'Rechazado'] as $k=>$v): ?>
  <a href="<?= url('admin/reembolsos.php?estado='.$k) ?>"
     class="btn <?= $filtro===$k?'btn-teal':'btn-outline' ?>" style="padding:6px 14px;font-size:12px"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<div class="sc">
  <h3>Solicitudes de reembolso (<?= count($reembolsos) ?>)</h3>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Nro.</th><th>Afiliado</th><th>C.I.</th><th>Tipo</th><th>Monto Sol.</th><th>Monto Apr.</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr>
      </thead>
      <tbody>
      <?php foreach ($reembolsos as $r):
        $bCls = match($r['estado']) { 'aprobado'=>'badge-green','rechazado'=>'badge-red', default=>'badge-amber' };
      ?>
      <tr>
        <td>#<?= $r['id_reembolso'] ?></td>
        <td><?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?></td>
        <td><?= htmlspecialchars($r['ci']) ?></td>
        <td><?= htmlspecialchars($r['tipo_servicio']) ?></td>
        <td>Bs. <?= number_format($r['monto_solicitado'],2,',','.') ?></td>
        <td><?= $r['monto_aprobado'] ? 'Bs. '.number_format($r['monto_aprobado'],2,',','.') : '—' ?></td>
        <td><?= date('d/m/Y', strtotime($r['fecha_solicitud'])) ?></td>
        <td><span class="badge <?= $bCls ?>"><?= ucfirst($r['estado']) ?></span></td>
        <td>
          <?php if (in_array($r['estado'],['pendiente','en_revision'])): ?>
          <button class="btn-xs btn-approve" onclick="abrirModal(<?= $r['id_reembolso'] ?>,<?= $r['monto_solicitado'] ?>)">
            <i class="ti ti-check"></i> Aprobar
          </button>
          <form method="POST" style="display:inline" onsubmit="return confirm('¿Rechazar este reembolso?')">
            <?= campoCsrf() ?>
            <input type="hidden" name="id" value="<?= $r['id_reembolso'] ?>">
            <input type="hidden" name="accion" value="rechazar">
            <input type="hidden" name="monto_aprobado" value="0">
            <button type="submit" class="btn-xs btn-reject"><i class="ti ti-x"></i> Rechazar</button>
          </form>
          <?php else: ?>
          <span style="font-size:12px;color:var(--text-3)">Procesado</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($reembolsos)): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--text-3);padding:2rem">Sin registros</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal aprobación -->
<div class="modal-bg" id="modalAprobar">
  <div class="modal">
    <button class="modal-close" onclick="cerrarModal()"><i class="ti ti-x"></i></button>
    <h3><i class="ti ti-check" style="color:var(--accent)"></i> Aprobar reembolso</h3>
    <form method="POST" action="<?= url('admin/reembolsos.php') ?>">
      <?= campoCsrf() ?>
      <input type="hidden" name="accion" value="aprobar">
      <input type="hidden" name="id" id="modal-id">
      <div class="fl" style="margin-bottom:1rem">
        <label>Monto a aprobar (Bs.) — máximo: <span id="modal-max"></span></label>
        <input type="number" name="monto_aprobado" id="modal-monto" step="0.01" min="0.01" required />
      </div>
      <div class="btn-row">
        <button type="submit" class="btn btn-teal"><i class="ti ti-check"></i> Confirmar aprobación</button>
        <button type="button" class="btn btn-outline" onclick="cerrarModal()">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirModal(id, monto) {
  document.getElementById('modal-id').value    = id;
  document.getElementById('modal-monto').value = monto;
  document.getElementById('modal-monto').max   = monto;
  document.getElementById('modal-max').textContent = 'Bs. ' + monto.toLocaleString('es-VE',{minimumFractionDigits:2});
  document.getElementById('modalAprobar').classList.add('open');
}
function cerrarModal() {
  document.getElementById('modalAprobar').classList.remove('open');
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
