<?php
// ============================================================
//  CONFIGURACIÓN DE BASE DE DATOS — PLANTILLA DE EJEMPLO
//  Copia este archivo como  config/database.php  y ajusta
//  los valores en tu archivo .env (no edites credenciales aquí).
//  Sistema de Previsión Social - UPTAG
// ============================================================
require_once __DIR__ . '/env.php';

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: '');
define('DB_USER',    getenv('DB_USER')    ?: '');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[UPTAG DB] ' . $e->getMessage());
            http_response_code(503);
            if (defined('API_REQUEST')) {
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['ok' => false, 'error' => 'Error de conexión a la base de datos.']));
            }
            header('Content-Type: text/html; charset=utf-8');
            die('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Servicio no disponible</title>'
              . '<style>body{font-family:sans-serif;padding:3rem;text-align:center;color:#333}</style></head><body>'
              . '<h2>Servicio temporalmente no disponible</h2>'
              . '<p>No pudimos conectar con la base de datos. Intenta de nuevo en unos minutos.</p>'
              . '</body></html>');
        }
    }
    return $pdo;
}
