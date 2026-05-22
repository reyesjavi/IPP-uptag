<?php
// views/finanzas/index.php — Vista del módulo financiero
// Variables disponibles: $tab, $flash, $movimientos, $saldo
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="mod-header">
    <div class="mod-icon" style="background:var(--gold-light)"><i class="ti ti-wallet" style="color:var(--gold)"></i></div>
    <div>
      <h2>Módulo Financiero — Caja de Ahorros</h2>
      <p>Aportes, movimientos, préstamos y retiros</p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="flash-msg <?= $flash['ok'] ? 'flash-ok' : 'flash-err' ?>">
      <i class="ti <?= $flash['ok'] ? 'ti-check' : 'ti-alert-circle' ?>"></i>
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <div class="tab-bar">
    <button class="tab <?= $tab==='cuenta'?'active':'' ?>" onclick="switchTab(this,'ft-cuenta','fin-panels')">Estado de Cuenta</button>
    <button class="tab <?= $tab==='sim'   ?'active':'' ?>" onclick="switchTab(this,'ft-sim','fin-panels')">Simulador de Préstamo</button>
    <button class="tab <?= $tab==='retiro'?'active':'' ?>" onclick="switchTab(this,'ft-retiro','fin-panels')">Retiros</button>
  </div>

  <div id="fin-panels">

    <!-- ESTADO DE CUENTA -->
    <div id="ft-cuenta" class="tab-panel <?= $tab==='cuenta'?'active':'' ?>">
      <div class="stat-row">
        <div class="mini-stat"><div class="mv">Bs. <?= number_format($saldo, 2, ',', '.') ?></div><div class="ml">Saldo total</div></div>
        <div class="mini-stat"><div class="mv"><?= count($movimientos) ?></div><div class="ml">Movimientos recientes</div></div>
        <div class="mini-stat"><div class="mv">Activo</div><div class="ml">Estado de cuenta</div></div>
      </div>
      <div class="sc">
        <h3>Movimientos recientes</h3>
        <?php if (empty($movimientos)): ?>
          <p style="font-size:13px;color:var(--text-3);padding:1rem 0">Sin movimientos registrados aún.</p>
        <?php else: ?>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Fecha</th><th>Descripción</th><th>Tipo</th><th>Monto</th><th>Saldo</th></tr></thead>
            <tbody>
              <?php foreach ($movimientos as $m):
                $bCls    = $m['tipo'] === 'credito' ? 'badge-green' : 'badge-red';
                $bTxt    = $m['tipo'] === 'credito' ? 'Crédito' : 'Débito';
                $montoFmt = ($m['tipo'] === 'debito' ? '-' : '') . 'Bs. ' . number_format($m['monto'], 2, ',', '.');
              ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                <td><?= htmlspecialchars($m['concepto']) ?></td>
                <td><span class="badge <?= $bCls ?>"><?= $bTxt ?></span></td>
                <td><?= $montoFmt ?></td>
                <td>Bs. <?= number_format($m['saldo_despues'] ?? 0, 2, ',', '.') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- SIMULADOR -->
    <div id="ft-sim" class="tab-panel <?= $tab==='sim'?'active':'' ?>">
      <div class="sc">
        <h3>Simula tu préstamo</h3>
        <div class="fr">
          <div class="fl"><label>Monto solicitado (Bs.)</label><input type="number" id="loanAmt" value="20000" oninput="calcLoan()" /></div>
          <div class="fl"><label>Plazo</label>
            <select id="loanMonths" onchange="calcLoan()">
              <option value="6">6 meses</option>
              <option value="12" selected>12 meses</option>
              <option value="18">18 meses</option>
              <option value="24">24 meses</option>
              <option value="36">36 meses</option>
            </select>
          </div>
        </div>
        <div class="fr full">
          <div class="fl"><label>Tasa de interés anual (%)</label><input type="number" id="loanRate" value="12" step="0.5" oninput="calcLoan()" /></div>
        </div>
        <div class="loan-result">
          <div class="loan-sub">Cuota mensual estimada</div>
          <div class="loan-cuota" id="loanCuota">Bs. 1,777</div>
          <div class="loan-sub" id="loanTotal">Total: Bs. 21,324 · Intereses: Bs. 1,324</div>
        </div>
        <div class="btn-row" style="margin-top:1rem">
          <button class="btn btn-teal"><i class="ti ti-send"></i> Solicitar préstamo</button>
        </div>
      </div>
    </div>

    <!-- RETIROS -->
    <div id="ft-retiro" class="tab-panel <?= $tab==='retiro'?'active':'' ?>">
      <div class="alert-bar"><span><strong>Nota:</strong> Solo se permiten retiros parciales después de 6 meses de afiliación.</span></div>
      <div class="sc">
        <h3>Solicitar retiro</h3>
        <form method="POST" action="<?= url('finanzas.php') ?>">
          <input type="hidden" name="accion" value="retiro" />
          <?= campoCsrf() ?>
          <div class="fr">
            <div class="fl"><label>Tipo de retiro</label><select name="tipo_retiro"><option>Parcial</option><option>Total</option></select></div>
            <div class="fl"><label>Monto (Bs.)</label><input type="number" name="monto" step="0.01" placeholder="Máx. 30% del saldo" /></div>
          </div>
          <div class="fr full">
            <div class="fl"><label>Motivo</label>
              <select name="motivo">
                <option>Gastos médicos</option><option>Educación</option><option>Vivienda</option><option>Otro</option>
              </select>
            </div>
          </div>
          <button type="submit" class="btn btn-teal"><i class="ti ti-send"></i> Enviar solicitud</button>
        </form>
      </div>
    </div>

  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
