<?php
// admin/solicitudes.php — Aprobar/rechazar solicitudes de registro
$pageTitle   = 'Solicitudes de Registro';
$pageSubtitle= 'Aprobación de nuevos usuarios';
$activeAdmin = 'solicitudes';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin','administrativo');
$pdo   = getDB();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── Aprobar solicitud ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='aprobar') {
    verificarCsrf();
    $idSol = intval($_POST['id_solicitud'] ?? 0);
    if ($idSol) {
        // Obtener la solicitud
        $sol = $pdo->prepare("SELECT * FROM solicitud_registro WHERE id_solicitud=:id AND estado='pendiente'");
        $sol->execute([':id'=>$idSol]);
        $sol = $sol->fetch();

        if ($sol) {
            // Buscar agremiado por CI
            $agr = $pdo->prepare("SELECT id_agremiado FROM agremiado WHERE ci=:ci");
            $agr->execute([':ci'=>$sol['ci']]);
            $agr = $agr->fetch();

            if ($agr) {
                $idAgr = $agr['id_agremiado'];
                $anio  = (int) date('Y');

                try {
                    $pdo->beginTransaction();

                    // Crear cuenta web si no existe
                    $pdo->prepare("
                        INSERT IGNORE INTO cuenta_web (id_agremiado, username, password_hash)
                        VALUES (:id, :user, :hash)
                    ")->execute([':id'=>$idAgr, ':user'=>$sol['ci'], ':hash'=>$sol['password_hash']]);

                    // También crear en usuarios_registrados para compatibilidad con el portal actual
                    $pdo->prepare("
                        INSERT IGNORE INTO usuarios_registrados (username, password_hash, rol, activo, id_afiliado)
                        VALUES (:user, :hash, 'afiliado', 1,
                            (SELECT id_afiliado FROM afiliado WHERE ci=:ci LIMIT 1))
                    ")->execute([':user'=>$sol['ci'],':hash'=>$sol['password_hash'],':ci'=>$sol['ci']]);

                    // Registrar vigencia anual
                    $pdo->prepare("
                        INSERT INTO vigencia_anual (id_agremiado, anio, fecha_vencimiento, estado, registrado_por)
                        VALUES (:id, :anio, :venc, 'activa', :admin)
                        ON DUPLICATE KEY UPDATE estado='activa', fecha_vencimiento=:venc
                    ")->execute([':id'=>$idAgr,':anio'=>$anio,':venc'=>"$anio-12-31",':admin'=>$_SESSION['usuario_id']]);

                    // Marcar solicitud como aprobada
                    $pdo->prepare("
                        UPDATE solicitud_registro
                        SET estado='aprobada', procesado_por=:admin, fecha_resolucion=NOW()
                        WHERE id_solicitud=:id
                    ")->execute([':admin'=>$_SESSION['usuario_id'],':id'=>$idSol]);

                    $pdo->commit();
                    registrarLog('solicitud_aprobada',"Solicitud #$idSol aprobada para CI: {$sol['ci']}");
                    $_SESSION['flash'] = ['ok'=>true,'msg'=>"Solicitud aprobada. Usuario {$sol['ci']} puede acceder al portal."];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['flash'] = ['ok'=>false,'msg'=>'Error al aprobar: '.$e->getMessage()];
                }
            }
        }
    }
    header('Location: '.url('admin/solicitudes.php')); exit;
}

// ── Rechazar solicitud ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['accion']??'')==='rechazar') {
    verificarCsrf();
    $idSol  = intval($_POST['id_solicitud'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? 'Sin motivo especificado');
    if ($idSol) {
        $pdo->prepare("
            UPDATE solicitud_registro
            SET estado='rechazada', motivo_rechazo=:motivo,
                procesado_por=:admin, fecha_resolucion=NOW()
            WHERE id_solicitud=:id
        ")->execute([':motivo'=>$motivo,':admin'=>$_SESSION['usuario_id'],':id'=>$idSol]);
        registrarLog('solicitud_rechazada',"Solicitud #$idSol rechazada. Motivo: $motivo");
        $_SESSION['flash'] = ['ok'=>false,'msg'=>"Solicitud #$idSol rechazada."];
    }
    header('Location: '.url('admin/solicitudes.php')); exit;
}

// ── Listar solicitudes ─────────────────────────────────────
$filtro = $_GET['estado'] ?? 'pendiente';
$solicitudes = $pdo->prepare("
    SELECT s.*, a.nombre, a.apellido, a.fecha_agremiacion
    FROM solicitud_registro s
    LEFT JOIN agremiado a ON a.ci = s.ci
    WHERE s.estado = :estado
    ORDER BY s.fecha_solicitud DESC
");
$solicitudes->execute([':estado'=>$filtro]);
$solicitudes = $solicitudes->fetchAll();

$contadores = $pdo->query("
    SELECT estado, COUNT(*) AS n FROM solicitud_registro GROUP BY estado
")->fetchAll(PDO::FETCH_KEY_PAIR);

require_once __DIR__ . '/header.php';
?>

<?php if ($flash): ?>
  <div class="flash-msg <?= $flash['ok']?'flash-ok':'flash-err' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<!-- Filtros con contadores -->
<div style="display:flex;gap:8px;margin-bottom:1.2rem;flex-wrap:wrap">
  <a href="<?= url('admin/solicitudes.php?estado=pendiente') ?>"
     class="btn <?= $filtro==='pendiente'?'btn-teal':'btn-outline' ?>" style="padding:6px 14px;font-size:12px">
    Pendientes <?= isset($contadores['pendiente']) ? '<span style="background:rgba(255,255,255,.25);border-radius:10px;padding:1px 7px">'.$contadores['pendiente'].'</span>' : '' ?>
  </a>
  <a href="<?= url('admin/solicitudes.php?estado=aprobada') ?>"
     class="btn <?= $filtro==='aprobada'?'btn-teal':'btn-outline' ?>" style="padding:6px 14px;font-size:12px">
    Aprobadas <?= isset($contadores['aprobada']) ? '('.$contadores['aprobada'].')' : '' ?>
  </a>
  <a href="<?= url('admin/solicitudes.php?estado=rechazada') ?>"
     class="btn <?= $filtro==='rechazada'?'btn-teal':'btn-outline' ?>" style="padding:6px 14px;font-size:12px">
    Rechazadas <?= isset($contadores['rechazada']) ? '('.$contadores['rechazada'].')' : '' ?>
  </a>
</div>

<div class="sc">
  <h3>Solicitudes <?= $filtro ?> (<?= count($solicitudes) ?>)</h3>
  <?php if (empty($solicitudes)): ?>
    <p style="font-size:13px;color:var(--text-3);padding:1.5rem 0;text-align:center">
      <i class="ti ti-inbox" style="font-size:28px;display:block;margin-bottom:8px"></i>
      No hay solicitudes <?= $filtro ?>s
    </p>
  <?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>C.I.</th><th>Nombre en padrón</th><th>Agremiado desde</th><th>Correo</th><th>Fecha solicitud</th><th>Estado</th><th>Acciones</th></tr>
      </thead>
      <tbody>
      <?php foreach ($solicitudes as $s):
        $bCls = match($s['estado']) { 'aprobada'=>'badge-green','rechazada'=>'badge-red', default=>'badge-amber' };
        $enPadron = !empty($s['nombre']);
      ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($s['ci']) ?></td>
        <td>
          <?php if ($enPadron): ?>
            <?= htmlspecialchars($s['nombre'].' '.$s['apellido']) ?>
          <?php else: ?>
            <span style="color:var(--red);font-size:12px"><i class="ti ti-alert-triangle"></i> No en padrón</span>
          <?php endif; ?>
        </td>
        <td><?= $s['fecha_agremiacion'] ? date('d/m/Y',strtotime($s['fecha_agremiacion'])) : '—' ?></td>
        <td><?= htmlspecialchars($s['correo_contacto'] ?? '—') ?></td>
        <td><?= date('d/m/Y H:i', strtotime($s['fecha_solicitud'])) ?></td>
        <td><span class="badge <?= $bCls ?>"><?= ucfirst($s['estado']) ?></span></td>
        <td>
          <?php if ($s['estado']==='pendiente' && $enPadron): ?>
          <form method="POST" style="display:inline">
            <?= campoCsrf() ?><input type="hidden" name="accion" value="aprobar">
            <input type="hidden" name="id_solicitud" value="<?= $s['id_solicitud'] ?>">
            <button type="submit" class="btn-xs btn-approve"><i class="ti ti-check"></i> Aprobar</button>
          </form>
          <button class="btn-xs btn-reject" onclick="abrirRechazo(<?= $s['id_solicitud'] ?>)">
            <i class="ti ti-x"></i> Rechazar
          </button>
          <?php elseif ($s['motivo_rechazo']): ?>
            <span style="font-size:11px;color:var(--text-3)" title="<?= htmlspecialchars($s['motivo_rechazo']) ?>">
              <i class="ti ti-info-circle"></i> Ver motivo
            </span>
          <?php else: ?>
            <span style="font-size:12px;color:var(--text-3)">Procesado</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Modal rechazo -->
<div class="modal-bg" id="modalRechazo">
  <div class="modal">
    <button class="modal-close" onclick="cerrarRechazo()"><i class="ti ti-x"></i></button>
    <h3><i class="ti ti-x" style="color:var(--red)"></i> Rechazar solicitud</h3>
    <form method="POST" action="<?= url('admin/solicitudes.php') ?>">
      <?= campoCsrf() ?><input type="hidden" name="accion" value="rechazar">
      <input type="hidden" name="id_solicitud" id="rechazo-id">
      <div class="fl" style="margin-bottom:1rem">
        <label>Motivo del rechazo</label>
        <textarea name="motivo" rows="3" placeholder="Ej: No cumple con los requisitos de agremiación..." required></textarea>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn btn-teal" style="background:var(--red)"><i class="ti ti-x"></i> Confirmar rechazo</button>
        <button type="button" class="btn btn-outline" onclick="cerrarRechazo()">Cancelar</button>
      </div>
    </form>
  </div>
</div>
<script>
function abrirRechazo(id) { document.getElementById('rechazo-id').value=id; document.getElementById('modalRechazo').classList.add('open'); }
function cerrarRechazo() { document.getElementById('modalRechazo').classList.remove('open'); }
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
