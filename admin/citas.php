<?php
// admin/citas.php — Gestión de citas con especialistas
$pageTitle    = 'Citas';
$pageSubtitle = 'Agenda de consultas con especialistas del IPP';
$activeAdmin  = 'citas';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/CitaModel.php';
require_once __DIR__ . '/../lib/integracion/Integraciones.php';
requiereRol('admin','administrativo');

// NOTA: el panel admin usa $_SESSION['flash_admin'] — admin/header.php
// lo lee y lo renderiza él mismo (y pisa cualquier variable $flash local).
$model = new CitaModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $id     = intval($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($id && $accion === 'atender') {
        // Marcar atendida = notificar el consumo al sistema de facturación
        // (dueño del contador). La referencia "cita-{id}" es idempotente:
        // reintentar tras un fallo nunca descuenta dos veces.
        $cita = $model->getParaConsumo($id);
        if ($cita && in_array($cita['estado'], ['pendiente','confirmada'])) {
            try {
                $saldo = Integraciones::consultas()->registrarConsumo(
                    $cita['afil_ci'],
                    $cita['ben_ci'] ?: null,   // beneficiario sin CI cuenta contra el pool del titular
                    'consulta',
                    'cita-' . $id
                );
                $model->cambiarEstado($id, 'atendida');
                registrarLog('cita_atendida', "Cita #$id atendida; consumo notificado (restantes: {$saldo->restantes})");
                $_SESSION['flash_admin'] = ['ok'=>true,'msg'=>"Cita marcada como atendida. Consultas restantes del afiliado: {$saldo->restantes} de {$saldo->incluidas}."];
            } catch (Throwable $e) {
                // El estado NO cambia: al reintentar, la idempotencia protege.
                error_log('[UPTAG Citas admin] fallo al notificar consumo: ' . $e->getMessage());
                $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'No se pudo notificar el consumo al sistema de facturación. La cita sigue pendiente; intenta de nuevo.'];
            }
        } else {
            $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'La cita no está en un estado que permita marcarla como atendida.'];
        }
    } elseif ($id && in_array($accion, ['confirmar','no_asistio','cancelar'])) {
        $nuevo = match($accion) { 'confirmar'=>'confirmada', 'no_asistio'=>'no_asistio', default=>'cancelada' };
        if ($model->cambiarEstado($id, $nuevo)) {
            registrarLog('cita_' . $nuevo, "Cita #$id → $nuevo");
            $_SESSION['flash_admin'] = ['ok'=>true,'msg'=>"Cita actualizada a \"$nuevo\"."];
        } else {
            $_SESSION['flash_admin'] = ['ok'=>false,'msg'=>'Transición de estado no válida.'];
        }
    }
    header('Location: ' . url('admin/citas.php' . (!empty($_GET['estado']) ? '?estado=' . urlencode($_GET['estado']) : ''))); exit;
}

$filtro = $_GET['estado'] ?? '';
$estadosValidos = ['pendiente','confirmada','atendida','no_asistio','cancelada'];
if (!in_array($filtro, $estadosValidos, true)) $filtro = '';
$citas = $model->getTodas($filtro);

require_once __DIR__ . '/header.php';
?>
<div class="sc">
  <h3>Citas (<?= count($citas) ?>)</h3>

  <div style="margin-bottom:1rem;display:flex;gap:6px;flex-wrap:wrap">
    <a href="<?= url('admin/citas.php') ?>" class="btn-xs <?= $filtro===''?'btn-approve':'' ?>" style="text-decoration:none">Todas</a>
    <?php foreach ($estadosValidos as $e): ?>
      <a href="<?= url('admin/citas.php?estado='.$e) ?>" class="btn-xs <?= $filtro===$e?'btn-approve':'' ?>" style="text-decoration:none"><?= ucfirst(str_replace('_',' ',$e)) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Nro.</th><th>Afiliado</th><th>Paciente</th><th>Especialista</th><th>Fecha y hora</th><th>Estado</th><th>Acciones</th></tr></thead>
      <tbody>
      <?php foreach ($citas as $c):
        [$bCls, $bLbl] = match($c['estado']) {
          'confirmada' => ['badge-green', 'Confirmada'],
          'atendida'   => ['badge-blue',  'Atendida'],
          'cancelada'  => ['badge-red',   'Cancelada'],
          'no_asistio' => ['badge-red',   'No asistió'],
          default      => ['badge-amber', 'Pendiente'],
        };
        $paciente = $c['id_beneficiario']
          ? $c['ben_nombre'].' '.$c['ben_apellido'].' ('.ucfirst($c['parentesco']).')'
          : 'Titular';
      ?>
      <tr>
        <td>CT-<?= str_pad($c['id_cita'],4,'0',STR_PAD_LEFT) ?></td>
        <td>
          <?= htmlspecialchars($c['afil_nombre'].' '.$c['afil_apellido']) ?>
          <div style="font-size:11px;color:var(--text-3)"><?= htmlspecialchars($c['afil_ci']) ?></div>
        </td>
        <td><?= htmlspecialchars($paciente) ?></td>
        <td>
          Dr(a). <?= htmlspecialchars($c['medico_nombre'].' '.$c['medico_apellido']) ?>
          <?php if ($c['especialidad']): ?><div style="font-size:11px;color:var(--text-3)"><?= htmlspecialchars($c['especialidad']) ?></div><?php endif; ?>
        </td>
        <td>
          <?= date('d/m/Y h:i A', strtotime($c['fecha_hora'])) ?>
          <?php if ($c['notas']): ?><div style="font-size:11px;color:var(--text-3)" title="<?= htmlspecialchars($c['notas']) ?>"><i class="ti ti-note"></i> <?= htmlspecialchars(mb_strimwidth($c['notas'],0,40,'…')) ?></div><?php endif; ?>
        </td>
        <td><span class="badge <?= $bCls ?>"><?= $bLbl ?></span></td>
        <td>
          <?php if ($c['estado']==='pendiente'): ?>
            <form method="POST" style="display:inline">
              <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $c['id_cita'] ?>"><input type="hidden" name="accion" value="confirmar">
              <button type="submit" class="btn-xs btn-approve"><i class="ti ti-check"></i> Confirmar</button>
            </form>
          <?php endif; ?>
          <?php if (in_array($c['estado'], ['pendiente','confirmada'])): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Marcar como atendida? Se notificará el consumo de una consulta al sistema de facturación.')">
              <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $c['id_cita'] ?>"><input type="hidden" name="accion" value="atender">
              <button type="submit" class="btn-xs btn-approve"><i class="ti ti-stethoscope"></i> Atendida</button>
            </form>
            <form method="POST" style="display:inline">
              <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $c['id_cita'] ?>"><input type="hidden" name="accion" value="no_asistio">
              <button type="submit" class="btn-xs btn-reject"><i class="ti ti-user-x"></i> No asistió</button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Cancelar esta cita?')">
              <?= campoCsrf() ?><input type="hidden" name="id" value="<?= $c['id_cita'] ?>"><input type="hidden" name="accion" value="cancelar">
              <button type="submit" class="btn-xs btn-reject"><i class="ti ti-x"></i> Cancelar</button>
            </form>
          <?php else: ?>
            <span style="font-size:12px;color:var(--text-3)">Procesada</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($citas)): ?><tr><td colspan="7" style="text-align:center;color:var(--text-3);padding:2rem">Sin citas<?= $filtro ? ' en estado "'.htmlspecialchars($filtro).'"' : '' ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
