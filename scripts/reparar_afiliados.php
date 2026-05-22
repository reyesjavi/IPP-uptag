<?php
/**
 * scripts/reparar_afiliados.php — Reparación única de registros huérfanos
 *
 * Vincula registros en usuarios_registrados (rol=afiliado) cuyo id_afiliado
 * es NULL con el registro correspondiente en la tabla afiliado (por CI).
 *
 * Ejecutar UNA sola vez desde línea de comandos o navegador (con sesión admin).
 * Elimina este archivo del servidor después de ejecutarlo.
 *
 * Uso CLI:  php scripts/reparar_afiliados.php
 * Uso web:  Acceder con rol admin. Protegido por autenticación.
 */

$esCli = PHP_SAPI === 'cli';

if (!$esCli) {
    require_once __DIR__ . '/../config/base.php';
    require_once __DIR__ . '/../includes/auth.php';
    requiereRol('admin');
} else {
    require_once __DIR__ . '/../config/base.php';
    require_once __DIR__ . '/../config/database.php';
}

$pdo = getDB();

function say(string $msg, bool $esCli): void {
    echo $esCli ? $msg . "\n" : "<p>$msg</p>";
}

if (!$esCli) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
       . '<title>Reparar Afiliados</title></head><body>';
    echo '<h2>Reparación de vínculos afiliado → usuario</h2>';
}

say('Buscando usuarios sin id_afiliado (rol=afiliado)...', $esCli);

$stmt = $pdo->query("
    SELECT u.id_usuario, u.username
    FROM usuarios_registrados u
    WHERE u.rol = 'afiliado' AND (u.id_afiliado IS NULL OR u.id_afiliado = 0)
");
$huerfanos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($huerfanos)) {
    say('No hay registros huérfanos. Todo está en orden.', $esCli);
} else {
    say('Se encontraron ' . count($huerfanos) . ' registros huérfanos:', $esCli);

    $reparados  = 0;
    $sinAfiliado = 0;

    foreach ($huerfanos as $u) {
        $ci = $u['username'];

        $af = $pdo->prepare("SELECT id_afiliado FROM afiliado WHERE ci = :ci LIMIT 1");
        $af->execute([':ci' => $ci]);
        $idAfil = $af->fetchColumn();

        if ($idAfil) {
            $pdo->prepare("UPDATE usuarios_registrados SET id_afiliado = :afil WHERE id_usuario = :uid")
                ->execute([':afil' => $idAfil, ':uid' => $u['id_usuario']]);
            say("  [OK] Usuario {$ci} vinculado a afiliado #{$idAfil}", $esCli);
            $reparados++;
        } else {
            say("  [--] Usuario {$ci} no tiene afiliado en la BD (pendiente de revisión manual)", $esCli);
            $sinAfiliado++;
        }
    }

    say("", $esCli);
    say("Resultado: $reparados reparados, $sinAfiliado sin afiliado encontrado.", $esCli);
}

if (!$esCli) {
    say('<br><strong>Elimina este archivo del servidor una vez ejecutado.</strong>', false);
    echo '</body></html>';
}
