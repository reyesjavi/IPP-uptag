<?php
// noticias.php
$pageTitle = 'Noticias';
$activeNav = 'noticias';
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
requiereLogin();
require_once __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="mod-header">
    <div class="mod-icon" style="background:var(--blue-light)"><i class="ti ti-bell" style="color:var(--blue)"></i></div>
    <div><h2>Noticias y Cartelera Oficial</h2><p>Anuncios, convenios y asambleas</p></div>
  </div>
  <div class="two-col">
    <div>
      <div class="sc">
        <h3>Anuncios recientes</h3>
        <div class="news-list">
          <div class="news-item"><div class="news-title">Nuevo convenio con Policlínica Los Llanos</div><div class="news-meta">28 Abr 2026 · Administración</div></div>
          <div class="news-item"><div class="news-title">Asamblea ordinaria de afiliados — 20 Mayo 2026</div><div class="news-meta">25 Abr 2026 · Directiva</div></div>
          <div class="news-item"><div class="news-title">Actualización del reglamento de retiros parciales</div><div class="news-meta">15 Abr 2026 · Legal</div></div>
          <div class="news-item"><div class="news-title">Incremento del aporte patronal: resolución aprobada</div><div class="news-meta">01 Abr 2026 · Directiva</div></div>
        </div>
      </div>
    </div>
    <div>
      <div class="sc">
        <h3>Documentos y descargas</h3>
        <div class="doc-list">
          <div class="doc-item"><div class="doc-name"><i class="ti ti-file-type-pdf" style="color:var(--red);font-size:18px"></i>Estatutos UPTAG 2024</div><button class="btn btn-outline" style="padding:5px 10px;font-size:12px"><i class="ti ti-download"></i></button></div>
          <div class="doc-item"><div class="doc-name"><i class="ti ti-file-type-pdf" style="color:var(--red);font-size:18px"></i>Reglamento Caja Ahorros</div><button class="btn btn-outline" style="padding:5px 10px;font-size:12px"><i class="ti ti-download"></i></button></div>
          <div class="doc-item"><div class="doc-name"><i class="ti ti-file-type-pdf" style="color:var(--red);font-size:18px"></i>Planilla de Inscripción</div><button class="btn btn-outline" style="padding:5px 10px;font-size:12px"><i class="ti ti-download"></i></button></div>
          <div class="doc-item"><div class="doc-name"><i class="ti ti-file-type-pdf" style="color:var(--red);font-size:18px"></i>Formulario Reembolso</div><button class="btn btn-outline" style="padding:5px 10px;font-size:12px"><i class="ti ti-download"></i></button></div>
          <div class="doc-item"><div class="doc-name"><i class="ti ti-file-type-pdf" style="color:var(--red);font-size:18px"></i>Política de Privacidad</div><button class="btn btn-outline" style="padding:5px 10px;font-size:12px"><i class="ti ti-download"></i></button></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
