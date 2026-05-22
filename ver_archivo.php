<?php
// ver_archivo.php — Servir archivos adjuntos con control de acceso por sesión
require_once __DIR__ . '/config/base.php';
require_once __DIR__ . '/includes/auth.php';
requiereLogin();

$file = basename($_GET['file'] ?? '');

// Solo permitir nombres con el patrón generado por salud.php
if (!$file || !preg_match('/^reemb_\d+_\d{14}_[0-9a-f]{8}\.(pdf|jpg|jpeg|png)$/i', $file)) {
    http_response_code(400);
    exit('Archivo no válido.');
}

$fullPath = UPLOAD_PATH . '/' . $file;
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

// Verificar propiedad: afiliados solo pueden ver sus propios archivos.
// Patrón: reemb_{id_afiliado}_{timestamp}_{random}.ext
$partes    = explode('_', $file);
$fileAfil  = isset($partes[1]) ? (int)$partes[1] : -1;
$sesionAfil = (int)($_SESSION['afiliado_id'] ?? 0);

if (!esAdministrativo() && $fileAfil !== $sesionAfil) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = match($ext) {
    'pdf'         => 'application/pdf',
    'jpg', 'jpeg' => 'image/jpeg',
    'png'         => 'image/png',
    default       => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($fullPath);
exit;
