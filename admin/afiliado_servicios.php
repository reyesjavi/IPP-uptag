<?php
$pageTitle   = 'Servicios del Afiliado';
$pageSubtitle= 'Habilitar o deshabilitar servicios médicos';
$activeAdmin = 'afiliados';
require_once __DIR__ . '/../config/base.php';
require_once __DIR__ . '/../includes/auth.php';
requiereRol('admin', 'administrativo');
$pdo = getDB();

$afilId = intval($_GET['id'] ?? 0);
if (!$afilId) {
    header('Location: ' . url('admin/afiliados.php'));
    exit;
}

$stmtAf = $pdo->prepare("SELECT id_afiliado, nombre, apellido, ci, cod_pm FROM afiliado WHERE id_afiliado = :id LIMIT 1");
$stmtAf->execute([':id' => $afilId]);
$afiliado = $stmtAf->fetch();
if (!$afiliado) {
    header('Location: ' . url('admin/afiliados.php'));
    exit;
}

$flash = $_SESSION['flash_admin'] ?? null;
unset($_SESSION['flash_admin']);

// ── POST: guardar servicios ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    // ── Asignar plan médico (cod_pm) al afiliado ──────────────
    //    Esto actualiza el PLAN del afiliado, no sus servicios individuales.
    if (($_POST['accion'] ?? '') === 'asignar_plan') {
        $codPm = trim($_POST['cod_pm'] ?? '');
        $planesValidos = $pdo->query("SELECT cod_pm FROM plan_medico")->fetchAll(PDO::FETCH_COLUMN);
        if ($codPm !== '' && !in_array($codPm, $planesValidos, true)) {
            $_SESSION['flash_admin'] = ['ok' => false, 'msg' => 'El plan médico seleccionado no existe.'];
            header('Location: ' . url("admin/afiliado_servicios.php?id=$afilId"));
            exit;
        }
        $pdo->prepare("UPDATE afiliado SET cod_pm = :pm WHERE id_afiliado = :id")
            ->execute([':pm' => $codPm ?: null, ':id' => $afilId]);

        $nombreAfil = trim($afiliado['nombre'] . ' ' . $afiliado['apellido']);
        registrarLog('plan_afiliado', "Plan de $nombreAfil (AFI-$afilId) → " . ($codPm ?: 'Sin plan'));
        $_SESSION['flash_admin'] = ['ok' => true, 'msg' => "Plan médico de $nombreAfil actualizado a " . ($codPm ?: 'Sin plan') . '.'];
        header('Location: ' . url("admin/afiliado_servicios.php?id=$afilId"));
        exit;
    }

    $adminId = $_SESSION['usuario_id'] ?? null;

    // Todos los servicios del sistema
    $todosIds = $pdo->query("SELECT id_servicio FROM servicio")->fetchAll(PDO::FETCH_COLUMN);

    // Los marcados en el formulario
    $marcados = array_map('intval', (array)($_POST['servicios'] ?? []));

    // UPSERT por cada servicio — placeholders únicos para evitar HY093
    $stmt = $pdo->prepare("
        INSERT INTO afiliado_servicio (id_afiliado, id_servicio, habilitado, asignado_por)
        VALUES (:afil, :srv, :hab, :admin)
        ON DUPLICATE KEY UPDATE habilitado = :hab2, asignado_por = :admin2
    ");

    foreach ($todosIds as $srvId) {
        $esHab = in_array((int)$srvId, $marcados) ? 1 : 0;
        $stmt->execute([
            ':afil'   => $afilId,
            ':srv'    => $srvId,
            ':hab'    => $esHab,
            ':admin'  => $adminId,
            ':hab2'   => $esHab,
            ':admin2' => $adminId,
        ]);
    }

    // Construir detalle para bitácora
    $stmtLog = $pdo->prepare("
        SELECT s.tipo_servicio, afs.habilitado
        FROM afiliado_servicio afs
        JOIN servicio s ON s.id_servicio = afs.id_servicio
        WHERE afs.id_afiliado = :id
        ORDER BY s.tipo_servicio
    ");
    $stmtLog->execute([':id' => $afilId]);
    $filas     = $stmtLog->fetchAll();
    $habNom    = implode(', ', array_column(array_filter($filas, fn($r) => $r['habilitado']),  'tipo_servicio') ?: ['ninguno']);
    $deshabNom = implode(', ', array_column(array_filter($filas, fn($r) => !$r['habilitado']), 'tipo_servicio') ?: ['ninguno']);

    $nombreAfil = trim($afiliado['nombre'] . ' ' . $afiliado['apellido']);
    registrarLog(
        'servicios_afiliado',
        "Servicios de $nombreAfil (AFI-$afilId) — Hab: [$habNom] | Deshab: [$deshabNom]"
    );

    $_SESSION['flash_admin'] = ['ok' => true, 'msg' => "Servicios de $nombreAfil actualizados correctamente."];
    header('Location: ' . url("admin/afiliado_servicios.php?id=$afilId"));
    exit;
}

// ── GET: cargar planes, servicios y cuáles están habilitados ──
$planes = $pdo->query("SELECT cod_pm, costo FROM plan_medico ORDER BY cod_pm")->fetchAll();
$todosServicios = $pdo->query("SELECT id_servicio, tipo_servicio, cod_pm FROM servicio ORDER BY tipo_servicio")->fetchAll();

$stmtHab = $pdo->prepare("SELECT id_servicio FROM afiliado_servicio WHERE id_afiliado = :id AND habilitado = 1");
$stmtHab->execute([':id' => $afilId]);
$habSet = array_flip($stmtHab->fetchAll(PDO::FETCH_COLUMN)); // flip para O(1) lookup

require_once __DIR__ . '/header.php';
?>

<?php if ($flash): ?>
  <div class="flash-msg <?= $flash['ok'] ? 'flash-ok' : 'flash-err' ?>">
    <i class="ti <?= $flash['ok'] ? 'ti-check' : 'ti-alert-circle' ?>"></i>
    <?= htmlspecialchars($flash['msg']) ?>
  </div>
<?php endif; ?>

<!-- Encabezado del afiliado -->
<div class="sc" style="margin-bottom:1.2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
  <div>
    <div style="font-size:15px;font-weight:700;color:var(--text)">
      <?= htmlspecialchars($afiliado['nombre'] . ' ' . $afiliado['apellido']) ?>
    </div>
    <div style="font-size:13px;color:var(--text-3);margin-top:2px">
      C.I.: <?= htmlspecialchars($afiliado['ci']) ?>
      &middot; AFI-<?= str_pad($afilId, 5, '0', STR_PAD_LEFT) ?>
      &middot; Plan: <?= htmlspecialchars($afiliado['cod_pm'] ?? 'Sin plan') ?>
    </div>
  </div>
  <a href="<?= url('admin/afiliados.php') ?>" class="btn btn-outline" style="font-size:13px">
    <i class="ti ti-arrow-left"></i> Volver a Afiliados
  </a>
</div>

<!-- Asignación de plan médico -->
<div class="sc" style="margin-bottom:1.2rem">
  <h3 style="margin-bottom:.5rem">Plan médico</h3>
  <p style="font-size:13px;color:var(--text-3);margin-bottom:1rem">
    Asigna el <strong>plan de cobertura</strong> del afiliado. Esto actualiza su plan
    (campo <code>cod_pm</code>), distinto de los servicios individuales de abajo.
  </p>
  <form method="POST" action="<?= url("admin/afiliado_servicios.php?id=$afilId") ?>"
        style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
    <?= campoCsrf() ?>
    <input type="hidden" name="accion" value="asignar_plan">
    <select name="cod_pm" style="font-size:13px;padding:8px 10px;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);color:var(--text)">
      <option value="">Sin plan</option>
      <?php foreach ($planes as $pl): ?>
        <option value="<?= htmlspecialchars($pl['cod_pm']) ?>" <?= ($afiliado['cod_pm'] ?? '') === $pl['cod_pm'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($pl['cod_pm']) ?> — Bs. <?= number_format((float)$pl['costo'], 2, ',', '.') ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-teal" style="font-size:13px">
      <i class="ti ti-device-floppy"></i> Asignar plan
    </button>
  </form>
</div>

<!-- Formulario de servicios -->
<div class="sc">
  <h3 style="margin-bottom:1rem">Servicios disponibles en el sistema</h3>
  <p style="font-size:13px;color:var(--text-3);margin-bottom:1.2rem">
    Marca los servicios que este afiliado tiene habilitados. Solo podrá solicitar
    reembolsos de los servicios marcados.
  </p>

  <form method="POST" action="<?= url("admin/afiliado_servicios.php?id=$afilId") ?>">
    <?= campoCsrf() ?>

    <?php if (empty($todosServicios)): ?>
      <p style="font-size:13px;color:var(--text-3)">
        No hay servicios registrados en el sistema. Agrega servicios desde la base de datos.
      </p>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:.75rem;margin-bottom:1.5rem">
        <?php foreach ($todosServicios as $srv):
          $isHab = isset($habSet[$srv['id_servicio']]);
        ?>
        <label style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:10px;border:1.5px solid <?= $isHab ? 'var(--primary)' : 'var(--border)' ?>;background:<?= $isHab ? 'var(--primary-light)' : 'var(--surface)' ?>;cursor:pointer;transition:.15s"
               onmouseover="this.style.borderColor='var(--primary)'"
               onmouseout="this.style.borderColor='<?= $isHab ? 'var(--primary)' : 'var(--border)' ?>'">
          <input type="checkbox" name="servicios[]" value="<?= $srv['id_servicio'] ?>"
                 <?= $isHab ? 'checked' : '' ?>
                 style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer"
                 onchange="this.closest('label').style.borderColor=this.checked?'var(--primary)':'var(--border)';this.closest('label').style.background=this.checked?'var(--primary-light)':'var(--surface)'">
          <div>
            <div style="font-weight:600;font-size:13px;color:var(--text)"><?= htmlspecialchars($srv['tipo_servicio']) ?></div>
            <div style="font-size:11px;color:var(--text-3)">Plan: <?= htmlspecialchars($srv['cod_pm']) ?></div>
          </div>
          <?php if ($isHab): ?>
            <span class="badge badge-green" style="margin-left:auto;font-size:10px">Activo</span>
          <?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;gap:.75rem;align-items:center">
        <button type="submit" class="btn btn-teal">
          <i class="ti ti-device-floppy"></i> Guardar cambios
        </button>
        <a href="<?= url('admin/afiliados.php') ?>" class="btn btn-outline">Cancelar</a>
        <span style="font-size:12px;color:var(--text-3);margin-left:.5rem">
          Los cambios se registran en la bitácora del sistema.
        </span>
      </div>
    <?php endif; ?>
  </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
