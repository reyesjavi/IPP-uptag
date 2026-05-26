<?php
// constancia.php — RF-04: Constancia de Afiliación en PDF
define('API_REQUEST', true);
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
requiereLogin();

$afilId = $_SESSION['afiliado_id'] ?? null;

if (!$afilId) {
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Sin expediente</title>'
       . '<style>body{font-family:sans-serif;padding:3rem;text-align:center;color:#333}</style></head><body>'
       . '<h2>Sin expediente de afiliado</h2>'
       . '<p>Tu cuenta no está vinculada a un afiliado. Contacta a la administración del IPP.</p>'
       . '<a href="' . url('perfil.php') . '">&larr; Volver al perfil</a></body></html>';
    exit;
}

$pdo  = getDB();
$stmt = $pdo->prepare("
    SELECT a.nombre, a.apellido, a.ci, a.fecha_ingreso, a.activo, a.cod_pm,
           p.costo
    FROM afiliado a
    LEFT JOIN plan_medico p ON p.cod_pm = a.cod_pm
    WHERE a.id_afiliado = :id
    LIMIT 1
");
$stmt->execute([':id' => $afilId]);
$af = $stmt->fetch();

if (!$af) {
    http_response_code(404);
    echo 'Datos del afiliado no encontrados.';
    exit;
}

require_once __DIR__ . '/lib/fpdf/fpdf.php';

class ConstanciaPDF extends FPDF
{
    function Header(): void
    {
        $this->SetFillColor(30, 54, 123);
        $this->Rect(0, 0, 210, 28, 'F');
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 7);
        $this->Cell(190, 7, 'INSTITUTO DE PREVISIÓN DEL PROFESORADO', 0, 1, 'C');
        $this->SetFont('Helvetica', '', 9);
        $this->SetXY(10, 15);
        $this->Cell(190, 6, 'Universidad Politécnica Territorial Alonso Gamero — UPTAG', 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->SetY(35);
    }

    function Footer(): void
    {
        $this->SetY(-18);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 5, 'Documento generado electrónicamente — IPP-UPTAG', 0, 1, 'C');
        $this->Cell(0, 5, 'Página ' . $this->PageNo() . ' de {nb}', 0, 0, 'C');
    }
}

// ── Helpers ───────────────────────────────────────────────────

function mesEs(int $n): string {
    return ['','enero','febrero','marzo','abril','mayo','junio',
            'julio','agosto','septiembre','octubre','noviembre','diciembre'][$n];
}

function fechaEs(string $fecha): string {
    $ts = strtotime($fecha);
    return date('d', $ts) . ' de ' . mesEs((int)date('n', $ts)) . ' de ' . date('Y', $ts);
}

// FPDF usa windows-1252; convertimos UTF-8 → windows-1252
function w(string $s): string {
    return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
}

// ── Datos ─────────────────────────────────────────────────────

$nombre       = mb_strtoupper(trim($af['nombre'] . ' ' . $af['apellido']), 'UTF-8');
$ci           = $af['ci'];
$fechaIngreso = !empty($af['fecha_ingreso']) ? fechaEs($af['fecha_ingreso']) : 'no registrada';
$fechaEmision = date('d') . ' de ' . mesEs((int)date('n')) . ' de ' . date('Y');
$refNum       = 'AFI-' . str_pad($afilId, 6, '0', STR_PAD_LEFT);

// ── Generar PDF ───────────────────────────────────────────────

$pdf = new ConstanciaPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(22, 37, 22);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// Título
$pdf->SetFont('Helvetica', 'B', 16);
$pdf->SetTextColor(30, 54, 123);
$pdf->Cell(0, 10, 'CONSTANCIA DE AFILIACIÓN', 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(110, 110, 110);
$pdf->Cell(0, 5, w("Nro. de referencia: $refNum"), 0, 1, 'C');
$pdf->Ln(5);

// Línea divisoria
$pdf->SetDrawColor(30, 54, 123);
$pdf->SetLineWidth(0.6);
$pdf->Line(22, $pdf->GetY(), 188, $pdf->GetY());
$pdf->Ln(8);

// Texto introductorio
$pdf->SetFont('Helvetica', '', 11);
$pdf->SetTextColor(30, 30, 30);
$intro = 'Quien suscribe, en su carácter de autoridad competente del Instituto de Previsión '
       . 'del Profesorado de la Universidad Politécnica Territorial Alonso Gamero (IPP-UPTAG), '
       . 'hace constar por medio del presente documento que:';
$pdf->MultiCell(0, 7, w($intro), 0, 'J');
$pdf->Ln(6);

// Bloque de datos del afiliado (recuadro)
$pdf->SetFillColor(238, 243, 255);
$pdf->SetDrawColor(30, 54, 123);
$pdf->SetLineWidth(0.3);
$yBloque = $pdf->GetY();
$pdf->Rect(22, $yBloque, 166, 40, 'DF');

$pdf->SetXY(30, $yBloque + 5);
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(30, 54, 123);
$pdf->Cell(0, 8, w($nombre), 0, 1);

$pdf->SetXY(30, $pdf->GetY());
$pdf->SetFont('Helvetica', '', 11);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell(55, 7, w('Cédula de Identidad:'), 0, 0);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(0, 7, $ci, 0, 1);

$pdf->SetXY(30, $pdf->GetY());
$pdf->SetFont('Helvetica', '', 11);
$pdf->Cell(55, 7, w('Afiliado desde:'), 0, 0);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(0, 7, w(ucfirst($fechaIngreso)), 0, 1);

$pdf->SetY($yBloque + 44);
$pdf->SetFont('Helvetica', '', 11);
$pdf->SetTextColor(30, 30, 30);
$pdf->Ln(4);

// Texto de certificación
$certif = '...se encuentra AFILIADO ACTIVO al Instituto de Previsión del Profesorado, '
        . 'habiendo cumplido con los requisitos de agremiación establecidos por la institución, '
        . 'con pleno derecho a los beneficios y servicios que ésta otorga.';
$pdf->MultiCell(0, 7, w($certif), 0, 'J');
$pdf->Ln(4);

// Información del plan médico (si tiene)
if (!empty($af['cod_pm'])) {
    $costo    = number_format((float)($af['costo'] ?? 0), 2, ',', '.');
    $textoPlan = "El afiliado tiene asignado el Plan Médico código \"{$af['cod_pm']}\" "
               . "(costo mensual: Bs. $costo), con cobertura para el titular y su carga familiar registrada.";
    $pdf->MultiCell(0, 7, w($textoPlan), 0, 'J');
    $pdf->Ln(4);
}

// Texto de cierre
$cierre = "La presente constancia se expide a solicitud del interesado, en la ciudad de Coro, "
        . "Estado Falcón, a los $fechaEmision.";
$pdf->MultiCell(0, 7, w($cierre), 0, 'J');

// ── Sección de firmas ─────────────────────────────────────────
$pdf->Ln(16);
$yFirma = $pdf->GetY();
$pdf->SetDrawColor(30, 54, 123);
$pdf->SetLineWidth(0.4);

$pdf->Line(30, $yFirma, 95, $yFirma);
$pdf->Line(115, $yFirma, 180, $yFirma);

$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetTextColor(30, 54, 123);
$pdf->SetXY(30, $yFirma + 3);
$pdf->Cell(65, 5, w('Director(a) del IPP-UPTAG'), 0, 0, 'C');
$pdf->SetXY(115, $yFirma + 3);
$pdf->Cell(65, 5, 'Sello Institucional', 0, 0, 'C');

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(130, 130, 130);
$pdf->SetXY(30, $yFirma + 9);
$pdf->Cell(65, 4, 'Firma y sello originales', 0, 0, 'C');

// ── Aviso de autenticidad ─────────────────────────────────────
$pdf->Ln(22);
$pdf->SetFillColor(255, 248, 220);
$pdf->SetDrawColor(200, 155, 0);
$pdf->SetLineWidth(0.3);
$pdf->SetFont('Helvetica', 'I', 8);
$pdf->SetTextColor(100, 75, 0);
$aviso = "Documento generado electrónicamente el $fechaEmision por el sistema del IPP-UPTAG. "
       . "Para verificar su autenticidad, comuníquese con la administración del instituto.";
$pdf->MultiCell(0, 5, w($aviso), 1, 'C', true);

registrarLog('constancia_descargada', "Constancia generada: $refNum");

$nombreArchivo = 'Constancia_' . str_replace('-', '', $ci) . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $nombreArchivo);
