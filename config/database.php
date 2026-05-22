<?php
// ============================================================
//  CONFIGURACIÓN DE BASE DE DATOS
//  Sistema de Previsión Social - UPTAG
// ============================================================

define('DB_HOST',     'localhost');
define('DB_NAME',     'ippuptag');
define('DB_USER',     'root');        // Cambia por tu usuario MySQL
define('DB_PASS',     '');            // Cambia por tu contraseña MySQL
define('DB_CHARSET',  'utf8mb4');

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
            // En producción: loguear el error, no mostrarlo
            die(json_encode(['error' => 'Error de conexión a la base de datos.']));
        }
    }
    return $pdo;
}
