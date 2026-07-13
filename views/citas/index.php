<?php
// views/citas/index.php — Agenda de citas con especialistas del IPP
// Variables disponibles (via extract en el controller):
//   $flash, $citas, $especialistas, $familiares, $saldo, $tarifa
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="wrap">
  <div class="mod-header">
    <div class="mod-icon" style="background:var(--primary-light)">
      <i class="ti ti-calendar-heart" style="color:var(--primary)"></i>
    </div>
    <div>
      <h2>Citas con Especialistas</h2>
      <p>Agenda tu consulta en el IPP-UPTAG</p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="flash-msg <?= $flash['ok'] ? 'flash-ok' : 'flash-err' ?>">
      <i class="ti <?= $flash['ok'] ? 'ti-check' : 'ti-alert-circle' ?>"></i>
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <?php if ($saldo): ?>
  <div class="stat-row">
    <div class="mini-stat">
      <div class="mv" style="color:<?= $saldo->restantes > 0 ? 'var(--primary)' : 'var(--text-3)' ?>"><?= $saldo->restantes ?></div>
      <div class="ml">Consultas restantes<?= $saldo->poolCompartido ? ' (grupo familiar)' : '' ?></div>
    </div>
    <div class="mini-stat">
      <div class="mv"><?= $saldo->usadas ?> / <?= $saldo->incluidas ?></div>
      <div class="ml">Usadas este año</div>
    </div>
    <div class="mini-stat">
      <?php if ($saldo->restantes > 0): ?>
        <div class="mv" style="font-size:20px;color:var(--primary)">Sin costo</div>
        <div class="ml">Incluida en tu plan</div>
      <?php elseif ($tarifa): ?>
        <div class="mv" style="font-size:20px">Bs. <?= number_format($tarifa->precioBase * (1 - ($tarifa->descuentoAplicable ?? 0)), 2, ',', '.') ?></div>
        <div class="ml">Por consulta (<?= $tarifa->descuentoAplicable !== null ? round($tarifa->descuentoAplicable * 100) . '% de descuento aplicado' : 'tarifa vigente' ?>)</div>
      <?php else: ?>
        <div class="mv" style="font-size:20px">—</div>
        <div class="ml">Tarifa por confirmar</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="sc">
    <h3>Agendar nueva cita</h3>
    <form method="POST" action="<?= url('citas.php') ?>">
      <input type="hidden" name="accion" value="agendar" />
      <?= campoCsrf() ?>
      <div class="fr">
        <div class="fl">
          <label>Especialista *</label>
          <select name="id_medico" required id="selEspecialista" onchange="mostrarHorario()">
            <option value="">Seleccionar...</option>
            <?php foreach ($especialistas as $e): ?>
              <option value="<?= $e['id_medico'] ?>" data-horario="<?= htmlspecialchars($e['horario'] ?? '') ?>">
                Dr(a). <?= htmlspecialchars($e['nombre'] . ' ' . $e['apellido']) ?><?= $e['especialidad'] ? ' — ' . htmlspecialchars($e['especialidad']) : '' ?>
              </option>
            <?php endforeach; ?>
            <?php if (empty($especialistas)): ?>
              <option value="" disabled>Sin especialistas disponibles</option>
            <?php endif; ?>
          </select>
          <p id="horarioHint" style="display:none;font-size:12px;color:var(--text-3);margin-top:4px">
            <i class="ti ti-clock"></i> Horario: <span></span>
          </p>
        </div>
        <div class="fl">
          <label>¿Para quién es la cita?</label>
          <select name="id_beneficiario">
            <option value="">Titular (yo)</option>
            <?php foreach ($familiares as $b): ?>
              <option value="<?= $b['id_beneficiario'] ?>">
                <?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido'] . ' (' . ucfirst($b['parentesco']) . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="fr">
        <div class="fl">
          <label>Fecha *</label>
          <input type="date" name="fecha" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>" />
        </div>
        <div class="fl">
          <label>Hora *</label>
          <input type="time" name="hora" required min="07:00" max="17:00" />
        </div>
      </div>
      <div class="fr full">
        <div class="fl">
          <label>Motivo / notas (opcional)</label>
          <textarea name="notas" maxlength="255" placeholder="Describe brevemente el motivo de la consulta..."></textarea>
        </div>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn btn-teal"><i class="ti ti-calendar-plus"></i> Solicitar cita</button>
      </div>
    </form>
  </div>

  <div class="sc">
    <h3>Mis citas</h3>
    <?php if (empty($citas)): ?>
      <p style="font-size:13px;color:var(--text-3);padding:1rem 0">No tienes citas registradas aún.</p>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>Nro.</th><th>Especialista</th><th>Paciente</th><th>Fecha y hora</th><th>Estado</th><th></th></tr>
        </thead>
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
              ? $c['ben_nombre'] . ' ' . $c['ben_apellido'] . ' (' . ucfirst($c['parentesco']) . ')'
              : 'Titular';
          ?>
          <tr>
            <td>CT-<?= str_pad($c['id_cita'], 4, '0', STR_PAD_LEFT) ?></td>
            <td>
              Dr(a). <?= htmlspecialchars($c['medico_nombre'] . ' ' . $c['medico_apellido']) ?>
              <?php if ($c['especialidad']): ?>
                <div style="font-size:12px;color:var(--text-3)"><?= htmlspecialchars($c['especialidad']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($paciente) ?></td>
            <td><?= date('d/m/Y h:i A', strtotime($c['fecha_hora'])) ?></td>
            <td><span class="badge <?= $bCls ?>"><?= $bLbl ?></span></td>
            <td>
              <?php if (in_array($c['estado'], ['pendiente', 'confirmada'])): ?>
              <form method="POST" action="<?= url('citas.php') ?>" style="display:inline"
                    onsubmit="return confirm('¿Cancelar esta cita?')">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="cancelar" />
                <input type="hidden" name="id_cita" value="<?= $c['id_cita'] ?>" />
                <button type="submit" class="btn btn-outline" style="padding:6px 12px;font-size:12px">
                  <i class="ti ti-x"></i> Cancelar
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function mostrarHorario() {
  const sel  = document.getElementById('selEspecialista');
  const hint = document.getElementById('horarioHint');
  const h    = sel.options[sel.selectedIndex]?.dataset?.horario || '';
  hint.style.display = h ? 'block' : 'none';
  hint.querySelector('span').textContent = h;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
