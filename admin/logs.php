<?php
$pageTitle   = 'Logs de Actividad';
$pageSubtitle= 'Registro de acciones en el sistema';
$activeAdmin = 'logs';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin');
$pdo = getDB();

$logs = $pdo->query("
    SELECT l.*, u.username, u.rol
    FROM log_actividad l
    LEFT JOIN usuarios_registrados u ON u.id_usuario = l.id_usuario
    ORDER BY l.fecha DESC LIMIT 100
")->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="sc">
  <h3>Últimas 100 acciones del sistema</h3>
  <?php if (empty($logs)): ?>
    <p style="font-size:13px;color:var(--text-3);padding:1rem 0">Sin registros de actividad aún.</p>
  <?php else: ?>
  <div style="max-height:600px;overflow-y:auto">
    <?php foreach ($logs as $l):
      $dotClass = str_contains($l['accion'],'login') ? 'login' : (str_contains($l['accion'],'logout') ? 'logout' : (str_contains($l['accion'],'elimina') ? 'error' : ''));
      $iconos = ['login'=>'ti-login','logout'=>'ti-logout','error'=>'ti-alert-triangle'];
    ?>
    <div class="log-item">
      <div class="log-dot <?= $dotClass ?>"></div>
      <div>
        <div style="font-weight:500;color:var(--text)"><?= htmlspecialchars($l['detalle']) ?></div>
        <div class="log-meta">
          <span><?= htmlspecialchars($l['username'] ?? 'Sistema') ?></span>
          &middot; <span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($l['accion']) ?></span>
          &middot; <span><?= date('d/m/Y H:i:s', strtotime($l['fecha'])) ?></span>
          &middot; IP: <code><?= htmlspecialchars($l['ip'] ?? '—') ?></code>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
