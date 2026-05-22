<?php
// config/env.php — Cargador de variables de entorno desde .env
// Idempotente: solo carga una vez aunque se incluya múltiples veces.
if (defined('ENV_LOADED')) return;
define('ENV_LOADED', true);

$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) return;

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
    $linea = trim($linea);
    if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;
    [$clave, $valor] = explode('=', $linea, 2);
    $clave = trim($clave);
    $valor = trim($valor, " \t\"'");
    if ($clave !== '' && !array_key_exists($clave, $_ENV)) {
        putenv("$clave=$valor");
        $_ENV[$clave] = $valor;
    }
}
