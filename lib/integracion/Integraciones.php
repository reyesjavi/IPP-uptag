<?php
// lib/integracion/Integraciones.php
//
// Punto único de acceso a los providers externos. Los consumidores
// solo hacen require de ESTE archivo y llaman:
//
//   Integraciones::estadoAfiliacion()->buscarPorCedula($ci);
//   Integraciones::consultas()->saldo($ci);
//
// La implementación se elige por .env (NOMINA_PROVIDER,
// FACTURACION_PROVIDER). Cambiar mock → real es cambiar una línea
// de configuración; ningún consumidor se entera.

require_once __DIR__ . '/EstadoAfiliacionProvider.php';
require_once __DIR__ . '/ConsultationLedgerProvider.php';
require_once __DIR__ . '/MockEstadoAfiliacionProvider.php';
require_once __DIR__ . '/MockConsultationLedgerProvider.php';

final class Integraciones
{
    private static ?EstadoAfiliacionProvider $nomina = null;
    private static ?ConsultationLedgerProvider $facturacion = null;

    public static function estadoAfiliacion(): EstadoAfiliacionProvider
    {
        if (self::$nomina === null) {
            $driver = getenv('NOMINA_PROVIDER') ?: 'mock';
            self::$nomina = match ($driver) {
                'mock'  => new MockEstadoAfiliacionProvider(getDB()),
                // 'api' => new ApiEstadoAfiliacionProvider(...),  ← integración real futura
                default => throw new RuntimeException("NOMINA_PROVIDER desconocido: $driver"),
            };
        }
        return self::$nomina;
    }

    public static function consultas(): ConsultationLedgerProvider
    {
        if (self::$facturacion === null) {
            $driver = getenv('FACTURACION_PROVIDER') ?: 'mock';
            self::$facturacion = match ($driver) {
                'mock'  => new MockConsultationLedgerProvider(getDB()),
                // 'api' => new ApiConsultationLedgerProvider(...),  ← integración real futura
                default => throw new RuntimeException("FACTURACION_PROVIDER desconocido: $driver"),
            };
        }
        return self::$facturacion;
    }

    /**
     * Auditoría de llamadas a la frontera (tabla integracion_log).
     * Nunca lanza: un fallo del log no debe tumbar la operación.
     */
    public static function log(
        string $sistema,
        string $operacion,
        ?string $clave,
        string $resultado,
        ?string $detalle = null
    ): void {
        try {
            getDB()->prepare("
                INSERT INTO integracion_log (sistema, operacion, clave, resultado, detalle)
                VALUES (:s, :o, :c, :r, :d)
            ")->execute([
                ':s' => $sistema,
                ':o' => $operacion,
                ':c' => $clave !== null ? substr($clave, 0, 50) : null,
                ':r' => $resultado,
                ':d' => $detalle !== null ? substr($detalle, 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            error_log('[UPTAG Integraciones] fallo al escribir integracion_log: ' . $e->getMessage());
        }
    }
}
