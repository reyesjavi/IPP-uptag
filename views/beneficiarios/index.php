<?php
// views/beneficiarios/index.php — Gestión de carga familiar
// Variables disponibles (via extract en el controller):
//   $flash, $beneficiarios, $parentescos
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="wrap">
  <div class="mod-header">
    <div class="mod-icon" style="background:var(--primary-light)">
      <i class="ti ti-users" style="color:var(--primary)"></i>
    </div>
    <div>
      <h2>Mi Carga Familiar</h2>
      <p>Beneficiarios de tu plan (heredan tus beneficios)</p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="flash-msg <?= $flash['ok'] ? 'flash-ok' : 'flash-err' ?>">
      <i class="ti <?= $flash['ok'] ? 'ti-check' : 'ti-alert-circle' ?>"></i>
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <div class="sc">
    <h3>Agregar familiar</h3>
    <form method="POST" action="<?= url('beneficiarios.php') ?>">
      <input type="hidden" name="accion" value="agregar" />
      <?= campoCsrf() ?>
      <div class="fr">
        <div class="fl">
          <label>Nombre *</label>
          <input type="text" name="nombre" required maxlength="100" />
        </div>
        <div class="fl">
          <label>Apellido *</label>
          <input type="text" name="apellido" required maxlength="100" />
        </div>
      </div>
      <div class="fr">
        <div class="fl">
          <label>Parentesco *</label>
          <select name="parentesco" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($parentescos as $p): ?>
              <option value="<?= $p ?>"><?= $p === 'conyuge' ? 'Cónyuge' : ucfirst($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fl">
          <label>Cédula (opcional para menores)</label>
          <input type="text" name="ci" placeholder="V-12345678" maxlength="20" />
        </div>
      </div>
      <div class="fr">
        <div class="fl">
          <label>Fecha de nacimiento</label>
          <input type="date" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>" />
        </div>
        <div class="fl"></div>
      </div>
      <div class="btn-row">
        <button type="submit" class="btn btn-teal"><i class="ti ti-user-plus"></i> Agregar a mi carga familiar</button>
      </div>
    </form>
  </div>

  <div class="sc">
    <h3>Beneficiarios registrados</h3>
    <?php if (empty($beneficiarios)): ?>
      <p style="font-size:13px;color:var(--text-3);padding:1rem 0">No hay beneficiarios registrados.</p>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr><th>Nombre</th><th>Parentesco</th><th>C.I.</th><th>Nacimiento</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($beneficiarios as $b):
            $esTitular = $b['parentesco'] === 'titular';
            $parLbl    = $b['parentesco'] === 'conyuge' ? 'Cónyuge' : ucfirst($b['parentesco']);
          ?>
          <tr>
            <td style="font-weight:500"><?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?></td>
            <td>
              <span class="badge <?= $esTitular ? 'badge-blue' : '' ?>"><?= htmlspecialchars($parLbl) ?></span>
            </td>
            <td><?= htmlspecialchars($b['ci'] ?: 'Sin C.I.') ?></td>
            <td><?= $b['fecha_nacimiento'] ? date('d/m/Y', strtotime($b['fecha_nacimiento'])) : '—' ?></td>
            <td>
              <span class="badge <?= $b['activo'] ? 'badge-green' : 'badge-red' ?>">
                <?= $b['activo'] ? 'Activo' : 'Inactivo' ?>
              </span>
            </td>
            <td>
              <?php if (!$esTitular): ?>
              <form method="POST" action="<?= url('beneficiarios.php') ?>" style="display:inline"
                    onsubmit="return confirm('<?= $b['activo'] ? '¿Desactivar a este beneficiario? Dejará de aparecer al agendar citas.' : '¿Reactivar a este beneficiario?' ?>')">
                <?= campoCsrf() ?>
                <input type="hidden" name="accion" value="<?= $b['activo'] ? 'desactivar' : 'reactivar' ?>" />
                <input type="hidden" name="id_beneficiario" value="<?= $b['id_beneficiario'] ?>" />
                <button type="submit" class="btn btn-outline" style="padding:6px 12px;font-size:12px">
                  <i class="ti <?= $b['activo'] ? 'ti-user-off' : 'ti-user-check' ?>"></i>
                  <?= $b['activo'] ? 'Desactivar' : 'Reactivar' ?>
                </button>
              </form>
              <?php else: ?>
                <span style="font-size:12px;color:var(--text-3)">Tú (titular)</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="font-size:12px;color:var(--text-3);margin-top:.75rem">
      <i class="ti ti-info-circle"></i>
      Los beneficiarios desactivados conservan su historial de citas pero no pueden usar los beneficios.
      Si el plan tiene pool familiar de consultas, todos comparten el mismo saldo.
    </p>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
