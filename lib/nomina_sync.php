<?php
// lib/nomina_sync.php — Integración futura con la nómina de la UPTAG
//
// ESTADO: PENDIENTE — la administración de la UPTAG no ha provisto acceso
// a la base de datos de nómina ni a una API. Este archivo es un PLACEHOLDER
// que documenta el plan de integración para la siguiente fase del proyecto.
//
// Cuando la integración esté disponible, esta función deberá:
//   1. Leer el origen de datos de nómina (Excel/CSV exportado por RRHH,
//      API REST institucional, o conexión directa a BD de nómina si se otorga acceso).
//   2. Comparar la lista de cédulas activas/jubiladas con la tabla `afiliado`.
//   3. Actualizar el campo `afiliado.situacion` de forma automática.
//   4. Generar un log con registrarLog() por cada cambio aplicado.
//   5. Retornar un array resumen con los cambios realizados.
//
// Esta función podría ser invocada manualmente desde un endpoint admin protegido
// o mediante un cron job periódico (lib/cron/sync_nomina.php).

/**
 * Sincroniza el campo `situacion` de la tabla `afiliado` con la nómina de la UPTAG.
 *
 * @param PDO $pdo Conexión activa a la base de datos ippuptag.
 * @return array Resumen de cambios: ['actualizados' => int, 'errores' => int, 'detalle' => array]
 *
 * TODO: Implementar cuando la UPTAG provea acceso a su origen de datos de nómina.
 *       Coordinar con la Dirección de RRHH/Nómina y la Dirección de Informática de la UPTAG.
 */
function sincronizarEstatusNomina(PDO $pdo): array
{
    // TODO: Reemplazar este stub con la implementación real.
    //
    // Ejemplo de flujo esperado:
    //
    // $origenNomina = cargarNominaDesdeOrigen(); // CSV, Excel, API, o BD externa
    // $cambios = [];
    // foreach ($origenNomina as $registro) {
    //     $ci        = $registro['cedula'];
    //     $situacion = mapearSituacionNomina($registro['estatus_nomina']);
    //     // 'ACTIVO' → 'activo', 'JUBILADO' → 'jubilado', etc.
    //
    //     $stmt = $pdo->prepare(
    //         "UPDATE afiliado SET situacion = :s WHERE ci = :ci AND situacion != :s"
    //     );
    //     if ($stmt->execute([':s' => $situacion, ':ci' => $ci]) && $stmt->rowCount() > 0) {
    //         registrarLog('sync_nomina', "CI $ci: situación → $situacion");
    //         $cambios[] = ['ci' => $ci, 'nueva_situacion' => $situacion];
    //     }
    // }
    // return ['actualizados' => count($cambios), 'errores' => 0, 'detalle' => $cambios];

    return [
        'actualizados' => 0,
        'errores'      => 0,
        'detalle'      => [],
        'nota'         => 'Integración con nómina UPTAG pendiente de implementación.',
    ];
}
