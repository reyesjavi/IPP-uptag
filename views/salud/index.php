<?php
// views/salud/index.php — Vista del módulo de salud
// Variables disponibles (via extract en el controller):
//   $tab, $flash, $reembolsos, $resumen, $avales, $beneficiarios, $afiliado
require_once __DIR__ . '/../../includes/header.php';
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

  <div class="tab-bar">
    <button class="tab <?= $tab==='reimb'?'active':'' ?>" onclick="switchTab(this,'st-reimb','salud-panels')">Reembolsos</button>
    <button class="tab <?= $tab==='aval' ?'active':'' ?>" onclick="switchTab(this,'st-aval','salud-panels')">Carta Aval</button>
    <button class="tab <?= $tab==='plan' ?'active':'' ?>" onclick="switchTab(this,'st-plan','salud-panels')">Mi Plan</button>
    <button class="tab <?= $tab==='srv'  ?'active':'' ?>" onclick="switchTab(this,'st-srv','salud-panels')">Mis Servicios</button>
  </div>

  <div id="salud-panels">

    <!-- ══ REEMBOLSOS ══ -->
    <div id="st-reimb" class="tab-panel <?= $tab==='reimb'?'active':'' ?>">
      <div class="stat-row">
        <div class="mini-stat"><div class="mv"><?= $resumen['pendientes'] ?></div><div class="ml">En revisión</div></div>
        <div class="mini-stat"><div class="mv"><?= $resumen['aprobados']  ?></div><div class="ml">Aprobados</div></div>
        <div class="mini-stat"><div class="mv">Bs. <?= number_format($resumen['reintegrado'], 2, ',', '.') ?></div><div class="ml">Reintegrado</div></div>
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
                <?php foreach ($serviciosHabilitados as $srv): ?>
                  <option value="<?= htmlspecialchars($srv['tipo_servicio']) ?>">
                    <?= htmlspecialchars($srv['tipo_servicio']) ?>
                  </option>
                <?php endforeach; ?>
                <?php if (empty($serviciosHabilitados)): ?>
                  <option value="" disabled>Sin servicios habilitados</option>
                <?php endif; ?>
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
              <label>Adjuntar factura / informe (PDF o imagen, máx. 5 MB)</label>
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
              <tr><th>Nro.</th><th>Tipo</th><th>Monto Solicitado</th><th>Monto Aprobado</th><th>Fecha</th><th>Estado</th><th>Adjunto</th></tr>
            </thead>
            <tbody>
              <?php foreach ($reembolsos as $r):
                $bCls = match($r['estado']) { 'aprobado'=>'badge-green','rechazado'=>'badge-red', default=>'badge-amber' };
              ?>
              <tr>
                <td><?= $r['id_reembolso'] ?></td>
                <td><?= htmlspecialchars($r['tipo_servicio']) ?></td>
                <td>Bs. <?= number_format($r['monto_solicitado'], 2, ',', '.') ?></td>
                <td><?= $r['monto_aprobado'] ? 'Bs. ' . number_format($r['monto_aprobado'], 2, ',', '.') : '—' ?></td>
                <td><?= date('d/m/Y', strtotime($r['fecha_solicitud'])) ?></td>
                <td><span class="badge <?= $bCls ?>"><?= ucfirst($r['estado']) ?></span></td>
                <td><?php if (!empty($r['archivo_adjunto'])): ?>
                  <a href="<?= url('ver_archivo.php') ?>?file=<?= urlencode($r['archivo_adjunto']) ?>"
                     target="_blank" style="font-size:12px;color:var(--primary)">
                    <i class="ti ti-paperclip"></i> Ver
                  </a>
                <?php else: ?>—<?php endif; ?></td>
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
                    <?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido'] . ' (' . $b['parentesco'] . ')') ?>
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
                <td>CA-<?= str_pad($a['id_aval'], 3, '0', STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($a['procedimiento']) ?></td>
                <td><?= htmlspecialchars($a['centro_medico']) ?></td>
                <td><?= $a['monto_estimado'] ? 'Bs. ' . number_format($a['monto_estimado'], 2, ',', '.') : '—' ?></td>
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
    <!-- TODO: La estructura completa de planes (coberturas, topes, servicios detallados)
         está pendiente de definición por parte del IPP. Por ahora se muestra cod_pm y costo. -->
    <div id="st-plan" class="tab-panel <?= $tab==='plan'?'active':'' ?>">
      <?php if (empty($afiliado['cod_pm'])): ?>
        <!-- Sin plan asignado -->
        <div class="sc" style="text-align:center;padding:2.5rem 1.5rem">
          <i class="ti ti-heart-rate-monitor" style="font-size:40px;color:var(--text-3);display:block;margin-bottom:1rem"></i>
          <h3 style="margin-bottom:.5rem;color:var(--text-2)">Sin plan médico asignado</h3>
          <p style="font-size:13px;color:var(--text-3);max-width:420px;margin:0 auto 1.5rem">
            Aún no tienes un plan médico asignado en el sistema.<br>
            Comunícate con la administración del IPP para más información sobre tu cobertura.
          </p>
          <div style="display:inline-flex;flex-direction:column;gap:6px;font-size:13px;color:var(--text-2);text-align:left">
            <span><i class="ti ti-phone"></i> <strong>Teléfono:</strong> (0269) 000-0000</span>
            <span><i class="ti ti-mail"></i> <strong>Correo:</strong> ipp@uptag.edu.ve</span>
            <span><i class="ti ti-map-pin"></i> <strong>Ubicación:</strong> Rectorado UPTAG, Coro, Falcón</span>
          </div>
        </div>
      <?php else: ?>
        <!-- Con plan asignado -->
        <div class="two-col">
          <div class="sc">
            <h3>Plan Médico</h3>
            <div class="info-row">
              <span class="lbl">Código de plan</span>
              <span class="val"><?= htmlspecialchars($afiliado['cod_pm']) ?></span>
            </div>
            <div class="info-row">
              <span class="lbl">Costo mensual</span>
              <span class="val">Bs. <?= number_format((float)($afiliado['costo'] ?? 0), 2, ',', '.') ?></span>
            </div>
            <div class="info-row">
              <span class="lbl">Estatus</span>
              <span class="val"><span class="badge badge-green">Activo</span></span>
            </div>
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
                  <td><?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?></td>
                  <td><?= htmlspecialchars($b['parentesco'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ══ MIS SERVICIOS ══ -->
    <div id="st-srv" class="tab-panel <?= $tab==='srv'?'active':'' ?>">
      <div class="sc" style="padding-bottom:.75rem;margin-bottom:1rem">
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div>
            <div style="font-size:14px;font-weight:600">Plan <?= htmlspecialchars($afiliado['cod_pm'] ?? '—') ?></div>
            <div style="font-size:12px;color:var(--text-3);margin-top:2px">
              Afiliado: <?= htmlspecialchars(($afiliado['nombre'] ?? '') . ' ' . ($afiliado['apellido'] ?? '')) ?>
            </div>
          </div>
          <span class="badge badge-green">Activo</span>
        </div>
      </div>

      <?php if (empty($serviciosHabilitados)): ?>
        <div class="sc" style="text-align:center;padding:2.5rem 1.5rem">
          <i class="ti ti-shield-off" style="font-size:38px;color:var(--text-3);display:block;margin-bottom:.75rem"></i>
          <h3 style="margin-bottom:.5rem;color:var(--text-2)">Sin servicios habilitados</h3>
          <p style="font-size:13px;color:var(--text-3);max-width:400px;margin:0 auto 1.2rem">
            Actualmente no tienes servicios habilitados.<br>
            Comunícate con la administración del IPP para más información sobre tu cobertura.
          </p>
          <div style="display:inline-flex;flex-direction:column;gap:5px;font-size:13px;color:var(--text-2);text-align:left">
            <span><i class="ti ti-phone"></i> <strong>Teléfono:</strong> (0269) 000-0000</span>
            <span><i class="ti ti-mail"></i> <strong>Correo:</strong> ipp@uptag.edu.ve</span>
          </div>
        </div>
      <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.75rem">
          <?php foreach ($serviciosHabilitados as $srv): ?>
          <div class="sc" style="padding:1.1rem 1.2rem;border-left:4px solid var(--primary)">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <i class="ti ti-shield-check" style="color:var(--primary);font-size:18px"></i>
              <span style="font-weight:700;font-size:14px;color:var(--text)"><?= htmlspecialchars($srv['tipo_servicio']) ?></span>
            </div>
            <div style="font-size:12px;color:var(--text-3)">Plan: <?= htmlspecialchars($srv['cod_pm']) ?></div>
            <span class="badge badge-green" style="margin-top:8px;font-size:10px">Habilitado</span>
          </div>
          <?php endforeach; ?>
        </div>
        <p style="font-size:12px;color:var(--text-3);margin-top:1rem;text-align:center">
          Solo puedes solicitar reembolsos de los servicios que aparecen habilitados.
        </p>
      <?php endif; ?>
    </div>

  </div><!-- /salud-panels -->
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
