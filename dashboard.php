<?php
$pageTitle = 'Inicio';
$activeNav = 'dashboard';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
requiereLogin();
$pdo      = getDB();
$rolActual = $_SESSION['usuario_rol'] ?? 'afiliado';

// requiereLogin() ya sincronizó afiliado_id si estaba vacío
$afilId   = $_SESSION['afiliado_id'] ?? null;
$afiliado = getAfiliado();

// Reembolsos pendientes
$reembPend = 0;
if ($afilId) {
    $r = $pdo->prepare("SELECT COUNT(*) FROM reembolso WHERE id_afiliado=:id AND estado IN ('pendiente','en_revision')");
    $r->execute([':id'=>$afilId]);
    $reembPend = $r->fetchColumn();
}

// Beneficiarios
$numBen = 0;
if ($afilId) {
    $b = $pdo->prepare("SELECT COUNT(*) FROM beneficiario WHERE id_afiliado=:id");
    $b->execute([':id'=>$afilId]);
    $numBen = $b->fetchColumn();
}

// Actividad reciente
$actividad = [];
if ($afilId) {
    $stmtAct = $pdo->prepare("
        SELECT 'reembolso' AS tipo,
               CONCAT('Reembolso Nro.',id_reembolso,' — ',tipo_servicio) AS descripcion,
               estado, fecha_solicitud AS fecha
        FROM reembolso WHERE id_afiliado=:id1
        UNION ALL
        SELECT 'aval',
               CONCAT('Carta aval: ',procedimiento),
               estado, fecha_solicitud
        FROM carta_aval WHERE id_afiliado=:id2
        ORDER BY fecha DESC LIMIT 5
    ");
    $stmtAct->execute([':id1'=>$afilId, ':id2'=>$afilId]);
    $actividad = $stmtAct->fetchAll();
}

// Nombre a mostrar — priorizar sesión actualizada, luego afiliado, luego CI
$nombreCompleto = trim(($afiliado['nombre'] ?? '') . ' ' . ($afiliado['apellido'] ?? ''));
if (!$nombreCompleto) {
    $nombreCompleto = trim(($_SESSION['afiliado_nombre'] ?? ''));
}
$nombreCompleto = $nombreCompleto ?: ($_SESSION['usuario_ci'] ?? 'Usuario');

require_once __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="dash-hello">
    <h2>Bienvenido, <?= htmlspecialchars($nombreCompleto) ?> 👋</h2>
    <p>
      <?php if ($rolActual === 'admin'): ?>
        <span style="color:var(--gold);font-weight:600">Administrador del sistema</span>
        &middot; <a href="<?= url('admin/dashboard.php') ?>" style="color:var(--accent)">Ir al panel admin →</a>
      <?php elseif ($rolActual === 'administrativo'): ?>
        <span style="color:var(--blue);font-weight:600">Personal Administrativo</span>
        &middot; <a href="<?= url('admin/dashboard.php') ?>" style="color:var(--accent)">Ir al panel admin →</a>
      <?php else: ?>
        Plan médico: <strong><?= htmlspecialchars($afiliado['cod_pm'] ?? 'Sin plan') ?></strong>
        <?php if ($afilId): ?>
          &middot; ID Afiliado: <strong>AFI-<?= str_pad($afilId, 5, '0', STR_PAD_LEFT) ?></strong>
        <?php endif; ?>
      <?php endif; ?>
    </p>
  </div>

  <div class="metrics">
    <div class="metric">
      <div class="metric-label">Reembolsos Pendientes</div>
      <div class="metric-val"><?= $reembPend ?></div>
      <div class="metric-sub <?= $reembPend>0?'down':'up' ?>">
        <i class="ti ti-clock"></i> <?= $reembPend>0?'En revisión':'Al día' ?>
      </div>
    </div>
    <div class="metric">
      <div class="metric-label">Beneficiarios</div>
      <div class="metric-val"><?= $numBen ?></div>
      <div class="metric-sub up"><span class="badge badge-green">Activos</span></div>
    </div>
    <div class="metric">
      <div class="metric-label">Plan Médico</div>
      <div class="metric-val" style="font-size:18px"><?= htmlspecialchars($afiliado['cod_pm'] ?? 'Sin plan') ?></div>
      <div class="metric-sub up"><i class="ti ti-heart-rate-monitor"></i> Cobertura</div>
    </div>
    <div class="metric">
      <div class="metric-label">Estado</div>
      <div class="metric-val" style="font-size:18px"><?= ($afiliado['activo'] ?? 1) ? 'Activo' : 'Inactivo' ?></div>
      <div class="metric-sub up"><span class="badge badge-green">Vigente</span></div>
    </div>
  </div>

  <div class="dash-grid">
    <div class="card">
      <div class="card-title">Accesos rápidos</div>
      <div class="module-grid">
        <a href="<?= url('salud.php?tab=reimb') ?>" class="module-btn">
          <div class="module-icon" style="background:var(--primary-light)"><i class="ti ti-stethoscope" style="color:var(--primary)"></i></div><p>Reembolso</p>
        </a>
        <a href="<?= url('salud.php?tab=aval') ?>" class="module-btn">
          <div class="module-icon" style="background:var(--primary-light)"><i class="ti ti-file-certificate" style="color:var(--primary)"></i></div><p>Carta Aval</p>
        </a>
        <a href="<?= url('salud.php?tab=srv') ?>" class="module-btn">
          <div class="module-icon" style="background:var(--primary-light)"><i class="ti ti-shield-check" style="color:var(--primary)"></i></div><p>Mis Servicios</p>
        </a>
        <a href="<?= url('directorio.php') ?>" class="module-btn">
          <div class="module-icon" style="background:var(--blue-light)"><i class="ti ti-address-book" style="color:var(--blue)"></i></div><p>Directorio</p>
        </a>
        <a href="<?= url('perfil.php') ?>" class="module-btn">
          <div class="module-icon" style="background:var(--primary-light)"><i class="ti ti-user" style="color:var(--primary)"></i></div><p>Mi Perfil</p>
        </a>
        <a href="<?= url('noticias.php') ?>" class="module-btn">
          <div class="module-icon" style="background:var(--blue-light)"><i class="ti ti-bell" style="color:var(--blue)"></i></div><p>Noticias</p>
        </a>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Actividad reciente <span>Últimos movimientos</span></div>
      <div class="activity-list">
        <?php if (empty($actividad)): ?>
          <p style="font-size:13px;color:var(--text-3);text-align:center;padding:1rem 0">
            <i class="ti ti-inbox" style="font-size:24px;display:block;margin-bottom:6px"></i>
            Sin actividad reciente
          </p>
        <?php else: ?>
          <?php foreach ($actividad as $item):
            $bCls = match($item['estado']) {
              'aprobado','aprobada'   => 'badge-green',
              'rechazado','rechazada' => 'badge-red',
              default => 'badge-amber'
            };
          ?>
          <div class="act-item">
            <div class="act-dot" style="background:<?= $item['tipo']==='aval'?'var(--blue)':'var(--accent)' ?>"></div>
            <div>
              <div class="act-text"><?= htmlspecialchars($item['descripcion']) ?></div>
              <div class="act-time">
                <?= date('d/m/Y', strtotime($item['fecha'])) ?>
                &middot; <span class="badge <?= $bCls ?>"><?= ucfirst($item['estado']) ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
