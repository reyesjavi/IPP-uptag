<?php
$pageTitle   = 'Reportes';
$pageSubtitle= 'Estadísticas del sistema';
$activeAdmin = 'reportes';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin','administrativo');
$pdo = getDB();

// ── Exportación CSV ───────────────────────────────────────────
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    $tipo = $_GET['tipo'] ?? 'reembolsos';

    $exportes = [
        'reembolsos' => [
            'filename' => 'reembolsos_' . date('Ymd') . '.csv',
            'sql'      => "SELECT r.id_reembolso AS 'ID', a.ci AS 'Cédula',
                                  CONCAT(a.nombre,' ',a.apellido) AS 'Afiliado',
                                  r.tipo_servicio AS 'Tipo', r.fecha_atencion AS 'Fecha Atención',
                                  r.monto_solicitado AS 'Monto Solicitado',
                                  COALESCE(r.monto_aprobado,'') AS 'Monto Aprobado',
                                  r.estado AS 'Estado', r.fecha_solicitud AS 'Fecha Solicitud'
                           FROM reembolso r
                           JOIN afiliado a ON a.id_afiliado = r.id_afiliado
                           ORDER BY r.fecha_solicitud DESC",
        ],
        'afiliados' => [
            'filename' => 'afiliados_' . date('Ymd') . '.csv',
            'sql'      => "SELECT a.id_afiliado AS 'ID', a.ci AS 'Cédula',
                                  a.nombre AS 'Nombre', a.apellido AS 'Apellido',
                                  a.correo AS 'Correo', a.telefono AS 'Teléfono',
                                  a.cod_pm AS 'Plan', a.activo AS 'Activo',
                                  a.fecha_ingreso AS 'Fecha Ingreso'
                           FROM afiliado a ORDER BY a.apellido",
        ],
        'avales' => [
            'filename' => 'avales_' . date('Ymd') . '.csv',
            'sql'      => "SELECT ca.id_aval AS 'ID', a.ci AS 'Cédula',
                                  CONCAT(a.nombre,' ',a.apellido) AS 'Afiliado',
                                  ca.medico_tratante AS 'Médico', ca.especialidad AS 'Especialidad',
                                  ca.centro_medico AS 'Centro', ca.procedimiento AS 'Procedimiento',
                                  ca.monto_estimado AS 'Monto Estimado', ca.estado AS 'Estado',
                                  ca.fecha_solicitud AS 'Fecha Solicitud'
                           FROM carta_aval ca
                           JOIN afiliado a ON a.id_afiliado = ca.id_afiliado
                           ORDER BY ca.fecha_solicitud DESC",
        ],
    ];

    if (!isset($exportes[$tipo])) {
        http_response_code(400); exit('Tipo de exportación no válido.');
    }

    $cfg  = $exportes[$tipo];
    $rows = $pdo->query($cfg['sql'])->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $cfg['filename'] . '"');
    header('Pragma: no-cache');
    // BOM UTF-8 para Excel
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

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

<!-- Exportar CSV -->
<div class="report-box" style="margin-top:1.2rem">
  <h3 style="margin-bottom:.75rem">Exportar datos</h3>
  <div style="display:flex;flex-wrap:wrap;gap:.6rem">
    <a href="<?= url('admin/reportes.php') ?>?export=csv&tipo=reembolsos" class="btn btn-teal">
      <i class="ti ti-table-export"></i> Reembolsos CSV
    </a>
    <a href="<?= url('admin/reportes.php') ?>?export=csv&tipo=afiliados" class="btn btn-teal">
      <i class="ti ti-table-export"></i> Afiliados CSV
    </a>
    <a href="<?= url('admin/reportes.php') ?>?export=csv&tipo=avales" class="btn btn-teal">
      <i class="ti ti-table-export"></i> Avales CSV
    </a>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
