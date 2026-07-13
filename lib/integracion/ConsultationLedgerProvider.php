<?php
// lib/integracion/ConsultationLedgerProvider.php
//
// FRONTERA 2: sistema de FACTURACIÓN de consultas (otro equipo).
//
// DECISIÓN DE ARQUITECTURA: ese equipo es la fuente de verdad del
// contador de consultas Y del precio base. Este sistema NO mantiene
// contador propio (divergirían): lee y consume a través de este
// contrato. El descuento posterior también lo aplica facturación
// (decisión #3); plan.descuento_posterior local es solo configuración
// del mock/visualización.
//
// La clave de correlación en la frontera es la CI del afiliado
// (y la CI del beneficiario cuando el pool no es compartido).
//
// Implementaciones:
//   - MockConsultationLedgerProvider (hoy): simula el ledger sobre
//     `consulta_ledger_cache` + `tarifa_cache` + `plan` locales.
//   - Implementación real (futuro): consume la API/feed de facturación;
//     las tablas *_cache pasan a ser caché de modo degradado.

interface ConsultationLedgerProvider
{
    /**
     * Saldo de consultas del plan.
     * Si el pool es compartido, $ciBeneficiario se ignora (el saldo es
     * del grupo familiar del afiliado). Si no, null = saldo del titular.
     */
    public function saldo(string $ciAfiliado, ?string $ciBeneficiario = null): SaldoConsultas;

    /**
     * Notifica un consumo al ledger y devuelve el saldo resultante.
     *
     * $referencia es la clave de IDEMPOTENCIA: repetir la misma
     * referencia NO descuenta dos veces (ej. 'cita-123').
     * $ciBeneficiario null = consumió el titular.
     */
    public function registrarConsumo(
        string $ciAfiliado,
        ?string $ciBeneficiario,
        string $tipo,
        string $referencia
    ): SaldoConsultas;

    /**
     * Movimientos del ledger del afiliado en un año.
     * @return ConsultaLedgerItem[]
     */
    public function historial(string $ciAfiliado, int $anio): array;

    /**
     * Precio base vigente para un tipo de consulta.
     * @return Tarifa|null  null = sin tarifa definida para ese tipo.
     */
    public function tarifa(string $tipo): ?Tarifa;
}

final class SaldoConsultas
{
    public function __construct(
        public readonly int  $incluidas,       // del plan
        public readonly int  $usadas,
        public readonly int  $restantes,
        public readonly bool $poolCompartido,
        public readonly bool $desdeCache,      // true = dato posiblemente viejo (modo degradado)
    ) {}
}

final class ConsultaLedgerItem
{
    public function __construct(
        public readonly string  $fecha,           // 'Y-m-d'
        public readonly string  $tipo,
        public readonly ?string $ciBeneficiario,  // null = titular
        public readonly ?float  $precioAplicado,  // 0.00 = dentro del plan
        public readonly string  $referencia,
    ) {}
}

final class Tarifa
{
    public function __construct(
        public readonly float   $precioBase,
        public readonly string  $moneda,              // 'VES'
        public readonly ?float  $descuentoAplicable,  // fracción (0.50); null = lo resuelve facturación
        public readonly bool    $desdeCache,
    ) {}
}
