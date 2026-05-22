<?php
// salud.php — Módulo de Salud y Seguros
$pageTitle = 'Salud';
$activeNav = 'salud';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
requiereLogin(); // ← sincroniza $_SESSION['afiliado_id'] si estaba vacío
$pdo    = getDB();
$afilId = $_SESSION['afiliado_id'] ?? null; // leer DESPUÉS de requiereLogin

// Tab activa desde URL: ?tab=reimb|aval|plan|srv
$tab = $_GET['tab'] ?? 'reimb';

// ── Flash message ──
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// ── POST: Nueva solicitud de reembolso ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reembolso') {
    verificarCsrf();
    $tipo    = trim($_POST['tipo_servicio'] ?? '');
    $fecha   = trim($_POST['fecha_atencion'] ?? '');
    $monto   = floatval($_POST['monto'] ?? 0);
    $centro  = trim($_POST['centro_medico'] ?? '');
    $desc    = trim($_POST['descripcion'] ?? '');

    // Validar que el usuario tenga afiliado vinculado
    if (!$afilId) {
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>'Tu cuenta no está vinculada a un afiliado. Contacta a administración.'];
        header('Location: ' . url('salud.php') . '?tab=reimb');
        exit;
    }

    // ── Procesar archivo adjunto (opcional) ──
    $nombreArchivo = null;
    if (!empty($_FILES['archivo']['name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $permitidos    = ['application/pdf', 'image/jpeg', 'image/png'];
        $extPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $tipoReal = $finfo->file($_FILES['archivo']['tmp_name']);
        $ext      = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));

        if (!in_array($tipoReal, $permitidos) || !in_array($ext, $extPermitidas)) {
            $_SESSION['flash'] = ['ok'=>false, 'msg'=>'Solo se permiten archivos PDF, JPG o PNG.'];
            header('Location: ' . url('salud.php') . '?tab=reimb'); exit;
        }
        if ($_FILES['archivo']['size'] > 5 * 1024 * 1024) {
            $_SESSION['flash'] = ['ok'=>false, 'msg'=>'El archivo no puede superar los 5 MB.'];
            header('Location: ' . url('salud.php') . '?tab=reimb'); exit;
        }

        // Crear carpeta de uploads si no existe
        $carpeta = __DIR__ . '/uploads/reembolsos';
        if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

        // Nombre único: afiliado_fecha_random.ext
        $nombreArchivo = 'reemb_' . $afilId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $carpeta . '/' . $nombreArchivo;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            $_SESSION['flash'] = ['ok'=>false, 'msg'=>'Error al guardar el archivo. Intenta de nuevo.'];
            header('Location: ' . url('salud.php') . '?tab=reimb'); exit;
        }
    }

    if ($tipo && $fecha && $monto > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO reembolso (tipo_servicio, fecha_atencion, monto_solicitado, centro_medico, descripcion, archivo_adjunto, id_afiliado)
            VALUES (:tipo, :fecha, :monto, :centro, :desc, :archivo, :id)
        ");
        $stmt->execute([':tipo'=>$tipo,':fecha'=>$fecha,':monto'=>$monto,':centro'=>$centro,':desc'=>$desc,':archivo'=>$nombreArchivo,':id'=>$afilId]);
        registrarLog('reembolso_solicitado', "Reembolso $tipo por Bs. $monto");
        $_SESSION['flash'] = ['ok'=>true, 'msg'=>'Solicitud de reembolso enviada correctamente.'];
    } else {
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>'Por favor completa todos los campos obligatorios.'];
    }
    header('Location: ' . url('salud.php') . '?tab=reimb');
    exit;
}

// ── POST: Nueva carta aval ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'aval') {
    verificarCsrf();
    $medico     = trim($_POST['medico_tratante'] ?? '');
    $especial   = trim($_POST['especialidad'] ?? '');
    $centro     = trim($_POST['centro_medico'] ?? '');
    $proc       = trim($_POST['procedimiento'] ?? '');
    $monto      = floatval($_POST['monto_estimado'] ?? 0);
    $benId      = $_POST['id_beneficiario'] ? intval($_POST['id_beneficiario']) : null;

    if ($medico && $centro && $proc) {
        $stmt = $pdo->prepare("
            INSERT INTO carta_aval (medico_tratante, especialidad, centro_medico, procedimiento, monto_estimado, id_afiliado, id_beneficiario)
            VALUES (:medico, :esp, :centro, :proc, :monto, :id, :ben)
        ");
        $stmt->execute([':medico'=>$medico,':esp'=>$especial,':centro'=>$centro,':proc'=>$proc,':monto'=>$monto,':id'=>$afilId,':ben'=>$benId]);
        $_SESSION['flash'] = ['ok'=>true, 'msg'=>'Carta aval solicitada correctamente.'];
    } else {
        $_SESSION['flash'] = ['ok'=>false, 'msg'=>'Completa los campos obligatorios.'];
    }
    header('Location: ' . url('salud.php') . '?tab=aval');
    exit;
}

// ── Datos: historial de reembolsos ──
$reembolsos = $pdo->prepare("SELECT * FROM reembolso WHERE id_afiliado=:id ORDER BY fecha_solicitud DESC");
$reembolsos->execute([':id'=>$afilId]);
$reembolsos = $reembolsos->fetchAll();

// ── Datos: resumen reembolsos ──
$resumen = $pdo->prepare("
    SELECT
      SUM(CASE WHEN estado IN ('pendiente','en_revision') THEN 1 ELSE 0 END) AS pendientes,
      SUM(CASE WHEN estado='aprobado' THEN 1 ELSE 0 END) AS aprobados,
      COALESCE(SUM(CASE WHEN estado='aprobado' THEN monto_aprobado ELSE 0 END),0) AS reintegrado
    FROM reembolso WHERE id_afiliado=:id
");
$resumen->execute([':id'=>$afilId]);
$resumen = $resumen->fetch();

// ── Datos: historial cartas aval ──
$avales = $pdo->prepare("SELECT * FROM carta_aval WHERE id_afiliado=:id ORDER BY fecha_solicitud DESC");
$avales->execute([':id'=>$afilId]);
$avales = $avales->fetchAll();

// ── Datos: beneficiarios (para selector) ──
$bens = $pdo->prepare("SELECT id_beneficiario, nombre, apellido, parentesco FROM beneficiario WHERE id_afiliado=:id");
$bens->execute([':id'=>$afilId]);
$beneficiarios = $bens->fetchAll();

// ── Datos: plan médico ──
$afiliado = getAfiliado();

require_once __DIR__ . '/includes/header.php';
?>

<div class="wrap">
  <div class="mod-header">
    <div class="mod-icon" style="background:var(--primary-light)">
      <i class="ti ti-heart-rate-monitor" style="color:var(--primary)"></i>
    </div>
    <div>
      <h2>Módulo de Salud y Seguros</h2>
      <p>Reembolsos, cartas aval, plan médico y servicios disponibles</p>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="flash-msg <?= $flash['ok'] ? 'flash-ok' : 'flash-err' ?>">
      <i class="ti <?= $flash['ok'] ? 'ti-check' : 'ti-alert-circle' ?>"></i>
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <!-- TABS -->
  <div class="tab-bar">
    <button class="tab <?= $tab==='reimb' ?'active':'' ?>" onclick="switchTab(this,'st-reimb','salud-panels')">Reembolsos</button>
    <button class="tab <?= $tab==='aval'  ?'active':'' ?>" onclick="switchTab(this,'st-aval','salud-panels')">Carta Aval</button>
    <button class="tab <?= $tab==='plan'  ?'active':'' ?>" onclick="switchTab(this,'st-plan','salud-panels')">Mi Plan</button>
    <button class="tab <?= $tab==='srv'   ?'active':'' ?>" onclick="switchTab(this,'st-srv','salud-panels')">Mis Servicios</button>
  </div>

  <div id="salud-panels">

    <!-- ══ REEMBOLSOS ══ -->
    <div id="st-reimb" class="tab-panel <?= $tab==='reimb'?'active':'' ?>">
      <div class="stat-row">
        <div class="mini-stat"><div class="mv"><?= $resumen['pendientes'] ?? 0 ?></div><div class="ml">En revisión</div></div>
        <div class="mini-stat"><div class="mv"><?= $resumen['aprobados']  ?? 0 ?></div><div class="ml">Aprobados</div></div>
        <div class="mini-stat"><div class="mv">Bs. <?= number_format($resumen['reintegrado'] ?? 0, 2, ',', '.') ?></div><div class="ml">Reintegrado</div></div>
      </div>

      <div class="sc">
        <h3>Nueva solicitud de reembolso</h3>
        <form method="POST" action="<?= url('salud.php') ?>" enctype="multipart/form-data">
          <input type="hidden" name="accion" value="reembolso" />
          <?= campoCsrf() ?>
          <div class="fr">
            <div class="fl">
              <label>Tipo de servicio *</label>
              <select name="tipo_servicio" required>
                <option value="">Seleccionar...</option>
                <option>Consulta médica</option>
                <option>Medicamentos</option>
                <option>Exámenes</option>
                <option>Hospitalización</option>
                <option>Dental</option>
                <option>Fisioterapia</option>
              </select>
            </div>
            <div class="fl">
              <label>Fecha de atención *</label>
              <input type="date" name="fecha_atencion" required max="<?= date('Y-m-d') ?>" />
            </div>
          </div>
          <div class="fr">
            <div class="fl">
              <label>Monto (Bs.) *</label>
              <input type="number" name="monto" step="0.01" min="0.01" placeholder="0.00" required />
            </div>
            <div class="fl">
              <label>Centro médico</label>
              <input type="text" name="centro_medico" placeholder="Nombre de la clínica" />
            </div>
          </div>
          <div class="fr full">
            <div class="fl">
              <label>Descripción</label>
              <textarea name="descripcion" placeholder="Describe brevemente el servicio recibido..."></textarea>
            </div>
          </div>
          <div class="fr full">
            <div class="fl">
              <label>Adjuntar factura / informe (PDF o imagen, máx. 5MB)</label>
              <div class="file-drop" onclick="this.querySelector('input').click()">
                <i class="ti ti-cloud-upload"></i>
                <p id="file-label">Haz clic para seleccionar el archivo</p>
                <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png" style="display:none"
                       onchange="document.getElementById('file-label').textContent = this.files[0] ? this.files[0].name : 'Haz clic para seleccionar el archivo'" />
              </div>
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-teal"><i class="ti ti-send"></i> Enviar solicitud</button>
          </div>
        </form>
      </div>

      <div class="sc">
        <h3>Historial de solicitudes</h3>
        <?php if (empty($reembolsos)): ?>
          <p style="font-size:13px;color:var(--text-3);padding:1rem 0">No hay solicitudes registradas aún.</p>
        <?php else: ?>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr><th>Nro.</th><th>Tipo</th><th>Monto Solicitado</th><th>Monto Aprobado</th><th>Fecha</th><th>Estado</th></tr>
            </thead>
            <tbody>
              <?php foreach ($reembolsos as $r):
                $bCls = match($r['estado']) { 'aprobado'=>'badge-green','rechazado'=>'badge-red', default=>'badge-amber' };
              ?>
              <tr>
                <td><?= $r['id_reembolso'] ?></td>
                <td><?= htmlspecialchars($r['tipo_servicio']) ?></td>
                <td>Bs. <?= number_format($r['monto_solicitado'],2,',','.') ?></td>
                <td><?= $r['monto_aprobado'] ? 'Bs. '.number_format($r['monto_aprobado'],2,',','.') : '—' ?></td>
                <td><?= date('d/m/Y', strtotime($r['fecha_solicitud'])) ?></td>
                <td><span class="badge <?= $bCls ?>"><?= ucfirst($r['estado']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══ CARTA AVAL ══ -->
    <div id="st-aval" class="tab-panel <?= $tab==='aval'?'active':'' ?>">
      <div class="sc">
        <h3>Solicitar carta aval</h3>
        <form method="POST" action="<?= url('salud.php') ?>">
          <input type="hidden" name="accion" value="aval" />
          <?= campoCsrf() ?>
          <div class="fr">
            <div class="fl">
              <label>Médico tratante *</label>
              <input type="text" name="medico_tratante" placeholder="Nombre del especialista" required />
            </div>
            <div class="fl">
              <label>Especialidad</label>
              <select name="especialidad">
                <option>Cardiología</option><option>Traumatología</option>
                <option>Oncología</option><option>Neurología</option><option>Pediatría</option>
              </select>
            </div>
          </div>
          <div class="fr">
            <div class="fl">
              <label>Centro médico *</label>
              <input type="text" name="centro_medico" placeholder="Clínica en convenio" required />
            </div>
            <div class="fl">
              <label>Procedimiento *</label>
              <input type="text" name="procedimiento" placeholder="Ej: Cirugía de rodilla" required />
            </div>
          </div>
          <div class="fr">
            <div class="fl">
              <label>Monto estimado (Bs.)</label>
              <input type="number" name="monto_estimado" step="0.01" placeholder="0.00" />
            </div>
            <div class="fl">
              <label>Beneficiario</label>
              <select name="id_beneficiario">
                <option value="">Titular (yo)</option>
                <?php foreach ($beneficiarios as $b): ?>
                  <option value="<?= $b['id_beneficiario'] ?>">
                    <?= htmlspecialchars($b['nombre'].' '.$b['apellido'].' ('.$b['parentesco'].')') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="btn-row">
            <button type="submit" class="btn btn-teal"><i class="ti ti-send"></i> Solicitar aval</button>
          </div>
        </form>
      </div>

      <div class="sc">
        <h3>Historial de cartas aval</h3>
        <?php if (empty($avales)): ?>
          <p style="font-size:13px;color:var(--text-3);padding:1rem 0">No hay cartas aval registradas.</p>
        <?php else: ?>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>ID</th><th>Procedimiento</th><th>Centro</th><th>Monto</th><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
              <?php foreach ($avales as $a):
                $bCls = match($a['estado']) { 'aprobada'=>'badge-green','rechazada'=>'badge-red','vencida'=>'badge-red', default=>'badge-amber' };
              ?>
              <tr>
                <td>CA-<?= str_pad($a['id_carta'],3,'0',STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($a['procedimiento']) ?></td>
                <td><?= htmlspecialchars($a['centro_medico']) ?></td>
                <td><?= $a['monto_estimado'] ? 'Bs. '.number_format($a['monto_estimado'],2,',','.') : '—' ?></td>
                <td><?= date('d/m/Y', strtotime($a['fecha_solicitud'])) ?></td>
                <td><span class="badge <?= $bCls ?>"><?= ucfirst($a['estado']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══ MI PLAN ══ -->
    <div id="st-plan" class="tab-panel <?= $tab==='plan'?'active':'' ?>">
      <div class="two-col">
        <div class="sc">
          <h3>Plan Médico</h3>
          <div class="info-row"><span class="lbl">Código de plan</span><span class="val"><?= htmlspecialchars($afiliado['cod_pm'] ?? '—') ?></span></div>
          <div class="info-row"><span class="lbl">Costo mensual</span><span class="val">Bs. <?= number_format($afiliado['costo'] ?? 0, 2,',','.') ?></span></div>
          <div class="info-row"><span class="lbl">Estatus</span><span class="val"><span class="badge badge-green">Activo</span></span></div>
        </div>
        <div class="sc">
          <h3>Carga familiar cubierta</h3>
          <?php if (empty($beneficiarios)): ?>
            <p style="font-size:13px;color:var(--text-3)">No hay beneficiarios registrados.</p>
          <?php else: ?>
          <table>
            <thead><tr><th>Nombre</th><th>Parentesco</th></tr></thead>
            <tbody>
              <?php foreach ($beneficiarios as $b): ?>
              <tr>
                <td><?= htmlspecialchars($b['nombre'].' '.$b['apellido']) ?></td>
                <td><?= htmlspecialchars($b['parentesco'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ══ MIS SERVICIOS ══ -->
    <div id="st-srv" class="tab-panel <?= $tab==='srv'?'active':'' ?>">
      <div class="sc" style="padding-bottom:.75rem;margin-bottom:1rem">
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div>
            <div style="font-size:14px;font-weight:600">Plan <?= htmlspecialchars($afiliado['cod_pm'] ?? '—') ?></div>
            <div style="font-size:12px;color:var(--text-3);margin-top:2px">Afiliado: <?= htmlspecialchars($afiliado['nombre'].' '.$afiliado['apellido']) ?></div>
          </div>
          <span class="badge badge-green">Activo</span>
        </div>
      </div>

      <div class="srv-filter">
        <button class="active" onclick="filterSrv('todos',this)">Todos</button>
        <button onclick="filterSrv('ambulatorio',this)">Ambulatorio</button>
        <button onclick="filterSrv('hospitalario',this)">Hospitalario</button>
        <button onclick="filterSrv('dental',this)">Dental</button>
        <button onclick="filterSrv('farmacia',this)">Farmacia</button>
        <button onclick="filterSrv('none',this)">No cubiertos</button>
      </div>

      <div id="srv-container"></div>

      <hr />
      <div class="legend">
        <div class="legend-item"><div class="legend-dot" style="background:var(--accent)"></div>Cubierto al 100%</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--gold)"></div>Cobertura parcial</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--red)"></div>No cubierto</div>
      </div>
    </div>

  </div><!-- /salud-panels -->
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
