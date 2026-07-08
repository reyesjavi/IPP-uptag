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

// Beneficios habilitados del afiliado + médicos/centros que los prestan, con su horario.
// LEFT JOIN: un beneficio sin proveedores activos también aparece (lista vacía).
$esAfiliado = ($rolActual !== 'admin' && $rolActual !== 'administrativo');
$beneficios = [];
if ($afilId && $esAfiliado) {
    $stmtBen = $pdo->prepare("
        SELECT s.id_servicio, s.tipo_servicio,
               m.id_medico, m.tipo AS prov_tipo, m.nombre, m.apellido,
               m.especialidad, m.numero_contacto, m.direccion, m.horario, m.convenio
        FROM afiliado_servicio afs
        JOIN servicio s    ON s.id_servicio = afs.id_servicio
        LEFT JOIN medico m ON m.id_servicio = s.id_servicio AND m.activo = 1
        WHERE afs.id_afiliado = :id AND afs.habilitado = 1
        ORDER BY s.tipo_servicio, m.tipo, m.apellido, m.nombre
    ");
    $stmtBen->execute([':id'=>$afilId]);
    foreach ($stmtBen->fetchAll() as $row) {
        $srv = $row['tipo_servicio'];
        if (!isset($beneficios[$srv])) { $beneficios[$srv] = []; }
        // id_medico null = el beneficio no tiene proveedores activos asignados todavía
        if ($row['id_medico'] !== null) { $beneficios[$srv][] = $row; }
    }
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

  <?php if ($esAfiliado && $afilId): ?>
  <div class="btn-row" style="margin-bottom:1.2rem">
    <a href="<?= url('constancia.php') ?>" class="btn btn-teal" target="_blank" style="display:inline-flex">
      <i class="ti ti-file-download"></i> Descargar constancia de afiliación (PDF)
    </a>
  </div>
  <?php endif; ?>

  <?php if ($esAfiliado): ?>
  <div class="card" style="margin-bottom:1.2rem">
    <div class="card-title">
      <span><i class="ti ti-shield-check" style="color:var(--primary);margin-right:6px"></i>Mis beneficios y horarios</span>
      <span>Servicios cubiertos por tu plan y disponibilidad</span>
    </div>

    <?php if (empty($beneficios)): ?>
      <div style="text-align:center;padding:2rem;color:var(--text-3)">
        <i class="ti ti-shield" style="font-size:32px;display:block;margin-bottom:.5rem"></i>
        Aún no tienes beneficios habilitados. Contacta a la administración del IPP para activarlos.
        <div style="margin-top:1rem">
          <a href="<?= url('directorio.php') ?>" class="btn btn-teal" style="display:inline-flex">
            <i class="ti ti-address-book"></i> Ver directorio médico
          </a>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($beneficios as $tipoSrv => $proveedores): ?>
        <div style="margin-bottom:1.25rem">
          <h3 style="font-size:15px;font-weight:700;color:var(--primary);margin-bottom:.75rem;display:flex;align-items:center;gap:8px">
            <i class="ti ti-heartbeat"></i> <?= htmlspecialchars($tipoSrv) ?>
          </h3>
          <?php if (empty($proveedores)): ?>
            <p style="font-size:14px;color:var(--text-3);padding-left:4px">
              <i class="ti ti-info-circle"></i> Beneficio activo — sin proveedores asignados aún.
            </p>
          <?php else: ?>
            <div class="dir-grid">
              <?php foreach ($proveedores as $p):
                $esCentro   = ($p['prov_tipo'] === 'centro');
                $nombreProv = trim(($p['nombre'] ?? '') . ' ' . ($p['apellido'] ?? ''));
                if (!$esCentro) { $nombreProv = 'Dr(a). ' . $nombreProv; }
                $spec = trim(($p['especialidad'] ?? '') . ($p['convenio'] ? ' · ' . $p['convenio'] : ''), " ·");
              ?>
              <div class="dir-card">
                <div class="dir-name">
                  <?= htmlspecialchars($nombreProv) ?>
                  <span class="badge <?= $esCentro ? 'badge-blue' : 'badge-green' ?>" style="margin-left:6px"><?= $esCentro ? 'Centro' : 'Médico' ?></span>
                </div>
                <?php if ($spec): ?><div class="dir-spec"><?= htmlspecialchars($spec) ?></div><?php endif; ?>
                <div class="dir-info">
                  <i class="ti ti-clock"></i>
                  <?= $p['horario'] ? htmlspecialchars($p['horario']) : '<span style="color:var(--text-3)">Horario no especificado</span>' ?>
                </div>
                <?php if (!empty($p['direccion'])): ?><div class="dir-info"><i class="ti ti-map-pin"></i> <?= htmlspecialchars($p['direccion']) ?></div><?php endif; ?>
                <?php if (!empty($p['numero_contacto'])): ?><div class="dir-info"><i class="ti ti-phone"></i> <?= htmlspecialchars($p['numero_contacto']) ?></div><?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="btn-row" style="margin-top:.25rem;padding-top:1rem;border-top:1px solid var(--border)">
        <a href="<?= url('directorio.php') ?>" class="btn btn-teal" style="display:inline-flex">
          <i class="ti ti-address-book"></i> Ver directorio completo
        </a>
        <a href="<?= url('salud.php?tab=srv') ?>" class="btn btn-outline" style="display:inline-flex">
          <i class="ti ti-shield-check"></i> Gestionar mis servicios
        </a>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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
      <?php
        $sit = $afiliado['situacion'] ?? 'activo';
        [$sitCls, $sitLbl] = match($sit) {
            'jubilado'   => ['badge-blue',  'Jubilado'],
            'suspendido' => ['badge-amber', 'Suspendido'],
            'egresado'   => ['badge',       'Egresado'],
            default      => ['badge-green', 'Activo'],
        };
      ?>
      <div class="metric-label">Situación</div>
      <div class="metric-val" style="font-size:18px"><?= $sitLbl ?></div>
      <div class="metric-sub up"><span class="badge <?= $sitCls ?>"><?= $sitLbl ?></span></div>
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
          <p style="font-size:15px;color:var(--text-3);text-align:center;padding:1rem 0">
            <i class="ti ti-inbox" style="font-size:28px;display:block;margin-bottom:6px"></i>
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
