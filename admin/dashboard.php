<?php
$pageTitle    = 'Dashboard';
$pageSubtitle = 'Resumen general del sistema';
$activeAdmin  = 'dashboard';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin','administrativo');
$pdo = getDB();

// ── Métricas globales ──
$totalAfiliados  = $pdo->query("SELECT COUNT(*) FROM afiliado WHERE activo=1")->fetchColumn();
$totalPendReemb  = $pdo->query("SELECT COUNT(*) FROM reembolso WHERE estado IN ('pendiente','en_revision')")->fetchColumn();
$totalPendAval   = $pdo->query("SELECT COUNT(*) FROM carta_aval WHERE estado='pendiente'")->fetchColumn();
$totalUsuarios   = $pdo->query("SELECT COUNT(*) FROM usuarios_registrados WHERE activo=1")->fetchColumn();

// ── Reembolsos recientes ──
$reembolsos = $pdo->query("
    SELECT r.id_reembolso, r.tipo_servicio, r.monto_solicitado, r.estado, r.fecha_solicitud,
           a.nombre, a.apellido
    FROM reembolso r
    JOIN afiliado a ON a.id_afiliado = r.id_afiliado
    ORDER BY r.fecha_solicitud DESC LIMIT 8
")->fetchAll();

// ── Afiliados recientes ──
$afiliados = $pdo->query("
    SELECT id_afiliado, nombre, apellido, ci, cod_pm, fecha_ingreso, activo
    FROM afiliado ORDER BY id_afiliado DESC LIMIT 6
")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="admin-metrics">
  <div class="admin-metric">
    <div class="am-label">Afiliados activos</div>
    <div class="am-val"><?= $totalAfiliados ?></div>
    <div class="am-sub up"><i class="ti ti-users"></i> En el sistema</div>
  </div>
  <div class="admin-metric">
    <div class="am-label">Reembolsos pendientes</div>
    <div class="am-val"><?= $totalPendReemb ?></div>
    <div class="am-sub <?= $totalPendReemb>0?'down':'up' ?>"><i class="ti ti-clock"></i> Por revisar</div>
  </div>
  <div class="admin-metric">
    <div class="am-label">Cartas aval pendientes</div>
    <div class="am-val"><?= $totalPendAval ?></div>
    <div class="am-sub <?= $totalPendAval>0?'down':'up' ?>"><i class="ti ti-file-certificate"></i> Por aprobar</div>
  </div>
  <div class="admin-metric">
    <div class="am-label">Usuarios activos</div>
    <div class="am-val"><?= $totalUsuarios ?></div>
    <div class="am-sub up"><i class="ti ti-shield-check"></i> Con acceso</div>
  </div>
</div>

<div class="admin-grid">
  <!-- Reembolsos recientes -->
  <div class="sc">
    <h3>Reembolsos recientes
      <a href="<?= url('admin/reembolsos.php') ?>" style="font-size:12px;font-weight:400;color:var(--accent);float:right;margin-top:2px">Ver todos →</a>
    </h3>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Afiliado</th><th>Tipo</th><th>Monto</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($reembolsos as $r):
          $bCls = match($r['estado']) { 'aprobado'=>'badge-green','rechazado'=>'badge-red', default=>'badge-amber' };
        ?>
        <tr>
          <td><?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?></td>
          <td><?= htmlspecialchars($r['tipo_servicio']) ?></td>
          <td>Bs. <?= number_format($r['monto_solicitado'],2,',','.') ?></td>
          <td><?= date('d/m/Y', strtotime($r['fecha_solicitud'])) ?></td>
          <td><span class="badge <?= $bCls ?>"><?= ucfirst($r['estado']) ?></span></td>
          <td>
            <?php if (in_array($r['estado'],['pendiente','en_revision'])): ?>
            <a href="<?= url('admin/reembolsos.php?id='.$r['id_reembolso']) ?>" class="btn-xs btn-view"><i class="ti ti-eye"></i> Revisar</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($reembolsos)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--text-3);padding:1.5rem">Sin reembolsos registrados</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Afiliados recientes -->
  <div class="sc">
    <h3>Afiliados recientes
      <a href="<?= url('admin/afiliados.php') ?>" style="font-size:12px;font-weight:400;color:var(--accent);float:right;margin-top:2px">Ver todos →</a>
    </h3>
    <?php foreach ($afiliados as $a): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border);font-size:13px">
      <div>
        <div style="font-weight:500;color:var(--text)"><?= htmlspecialchars($a['nombre'].' '.$a['apellido']) ?></div>
        <div style="font-size:11px;color:var(--text-3)"><?= htmlspecialchars($a['ci']) ?> &middot; <?= htmlspecialchars($a['cod_pm']??'Sin plan') ?></div>
      </div>
      <span class="badge <?= $a['activo']?'badge-green':'badge-red' ?>"><?= $a['activo']?'Activo':'Inactivo' ?></span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($afiliados)): ?>
      <p style="font-size:13px;color:var(--text-3);padding:1rem 0;text-align:center">Sin afiliados registrados</p>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
