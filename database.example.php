<?php
// Copia este archivo como database.php y configura tus credenciales
define('DB_HOST', 'localhost');
define('DB_NAME', 'ippuptag');
define('DB_USER', 'root');
define('DB_PASS', 'tu_contraseña_aqui');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}