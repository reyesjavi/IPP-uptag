<?php
// config/base.php — Configuración base, rutas y seguridad
require_once __DIR__ . '/env.php';

define('BASE_PATH', __DIR__ . '/..');

// Ruta del sistema de archivos donde se almacenan los uploads.
// En producción, esta carpeta debe estar FUERA del webroot.
if (!defined('UPLOAD_PATH')) {
    $envUpload = getenv('UPLOAD_PATH');
    define('UPLOAD_PATH', ($envUpload !== false && $envUpload !== '')
        ? rtrim($envUpload, '/')
        : __DIR__ . '/../uploads/reembolsos');
}

// Forzar HTTPS en producción
if (getenv('APP_ENV') === 'production'
    && empty($_SERVER['HTTPS'])
    && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https'
    && !headers_sent()
) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

// ── Detectar raíz del proyecto (funciona desde cualquier subcarpeta) ──
function getRootUrl(): string {
    static $root = null;
    if ($root !== null) return $root;

    $docRoot   = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));

    if ($docRoot && str_starts_with($scriptDir, $docRoot)) {
        $relative = substr($scriptDir, strlen($docRoot)); // ej: /uptag_final/admin
        $parts    = array_values(array_filter(explode('/', $relative)));
        // La raíz del proyecto es el primer segmento (la carpeta del proyecto)
        $root = $parts ? '/' . $parts[0] : '';
    } else {
        // Fallback: subir desde /admin si aplica
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_ends_with($dir, '/admin')) $dir = dirname($dir);
        $root = rtrim($dir, '/');
    }
    return $root;
}

function url(string $path = ''): string {
    return getRootUrl() . '/' . ltrim($path, '/');
}

// URL de un recurso estático con versión (?v=mtime) para invalidar la caché
// del navegador automáticamente cada vez que el archivo cambia.
function assetUrl(string $path): string {
    $abs = BASE_PATH . '/' . ltrim($path, '/');
    $ver = is_file($abs) ? '?v=' . filemtime($abs) : '';
    return url($path) . $ver;
}

// ── Headers de seguridad HTTP (no aplicar a respuestas API/JSON) ──
if (!defined('API_REQUEST') && !headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; "
        . "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; "
        . "font-src fonts.gstatic.com cdn.jsdelivr.net; "
        . "img-src 'self' data:;");
}

// ── Configuración segura de sesión ──
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 1800);
    ini_set('session.cookie_lifetime', 0);
    if (getenv('APP_ENV') === 'production') {
        ini_set('session.cookie_secure', '1');
    }
}

// ── CSRF: generar y verificar token ──
function generarCsrf(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function campoCsrf(): string {
    return '<input type="hidden" name="csrf_token" value="' . generarCsrf() . '">';
}

function verificarCsrf(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Error de seguridad: token CSRF inválido. Vuelve a cargar la página e inténtalo de nuevo.');
    }
}
