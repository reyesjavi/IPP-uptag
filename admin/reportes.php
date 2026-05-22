<?php
$pageTitle   = 'Reportes';
$pageSubtitle= 'Estadísticas del sistema';
$activeAdmin = 'reportes';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin','administrativo');
$pdo = getDB();

// ── Reembolsos por estado ──
$reembEstado = $pdo->query("
    SELECT estado, COUNT(*) AS total, COALESCE(SUM(monto_solicitado),0) AS monto
    FROM reembolso GROUP BY estado
")->fetchAll();

// ── Reembolsos por tipo ──
$reembTipo = $pdo->query("
    SELECT tipo_servicio, COUNT(*) AS total
    FROM reembolso GROUP BY tipo_servicio ORDER BY total DESC LIMIT 5
")->fetchAll();

// ── Afiliados por plan ──
$porPlan = $pdo->query("
    SELECT cod_pm, COUNT(*) AS total FROM afiliado WHERE activo=1 GROUP BY cod_pm
")->fetchAll();

// ── Totales generales ──
$totReem  = $pdo->query("SELECT COUNT(*) FROM reembolso")->fetchColumn();
$totApro  = $pdo->query("SELECT COUNT(*) FROM reembolso WHERE estado='aprobado'")->fetchColumn();
$montoTot = $pdo->query("SELECT COALESCE(SUM(monto_aprobado),0) FROM reembolso WHERE estado='aprobado'")->fetchColumn();
$totAval  = $pdo->query("SELECT COUNT(*) FROM carta_aval WHERE estado='aprobada'")->fetchColumn();

require_once __DIR__ . '/header.php';
?>

<div class="admin-metrics">
  <div class="admin-metric">
    <div class="am-label">Total reembolsos</div>
    <div class="am-val"><?= $totReem ?></div>
    <div class="am-sub">Todas las solicitudes</div>
  </div>
  <div class="admin-metric">
    <div class="am-label">Reembolsos aprobados</div>
    <div class="am-val"><?= $totApro ?></div>
    <div class="am-sub up"><?= $totReem>0 ? round($totApro/$totReem*100) : 0 ?>% de aprobación</div>
  </div>
  <div class="admin-metric">
    <div class="am-label">Monto total reintegrado</div>
    <div class="am-val" style="font-size:18px">Bs. <?= number_format($montoTot,2,',','.') ?></div>
    <div class="am-sub up">Reembolsos aprobados</div>
  </div>
  <div class="admin-metric">
    <div class="am-label">Cartas aval aprobadas</div>
    <div class="am-val"><?= $totAval ?></div>
    <div class="am-sub up">Autorizaciones emitidas</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem">

  <!-- Reembolsos por estado -->
  <div class="report-box">
    <h3>Reembolsos por estado</h3>
    <?php
    $estados = ['pendiente'=>'Pendiente','en_revision'=>'En revisión','aprobado'=>'Aprobado','rechazado'=>'Rechazado'];
    $colores = ['pendiente'=>'var(--gold)','en_revision'=>'var(--blue)','aprobado'=>'var(--accent)','rechazado'=>'var(--red)'];
    $maxRe = max(1, ...array_column($reembEstado,'total'));
    $reembMap = array_column($reembEstado, null, 'estado');
    foreach ($estados as $k=>$v):
        $n = $reembMap[$k]['total'] ?? 0;
        $pct = round($n/$maxRe*100);
    ?>
    <div class="bar-row">
      <span class="bar-label"><?= $v ?></span>
      <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $colores[$k] ?>"></div></div>
      <span class="bar-val"><?= $n ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Reembolsos por tipo -->
  <div class="report-box">
    <h3>Top tipos de reembolso</h3>
    <?php
    $maxTipo = max(1, ...array_column($reembTipo ?: [['total'=>1]],'total'));
    foreach ($reembTipo as $t):
        $pct = round($t['total']/$maxTipo*100);
    ?>
    <div class="bar-row">
      <span class="bar-label"><?= htmlspecialchars($t['tipo_servicio']) ?></span>
      <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
      <span class="bar-val"><?= $t['total'] ?></span>
    </div>
    <?php endforeach; ?>
    <?php if(empty($reembTipo)): ?><p style="font-size:13px;color:var(--text-3)">Sin datos aún</p><?php endif; ?>
  </div>

</div>

<!-- Afiliados por plan -->
<div class="report-box">
  <h3>Afiliados por plan médico</h3>
  <?php if (empty($porPlan)): ?>
    <p style="font-size:13px;color:var(--text-3)">Sin datos aún</p>
  <?php else:
    $maxPlan = max(1, ...array_column($porPlan,'total'));
    foreach ($porPlan as $p):
      $pct = round($p['total']/$maxPlan*100);
  ?>
  <div class="bar-row">
    <span class="bar-label"><?= htmlspecialchars($p['cod_pm']??'Sin plan') ?></span>
    <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;background:var(--primary)"></div></div>
    <span class="bar-val"><?= $p['total'] ?></span>
  </div>
  <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
