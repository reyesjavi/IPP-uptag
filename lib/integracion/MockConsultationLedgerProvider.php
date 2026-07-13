<?php
// lib/integracion/MockConsultationLedgerProvider.php
//
// Implementación MOCK de la frontera con facturación. Simula el ledger
// sobre las tablas locales `consulta_ledger_cache` + `tarifa_cache`,
// parametrizado por la tabla `plan` (nada hardcodeado: consultas
// incluidas, descuento y modalidad de pool salen de ahí).
//
// En la integración real, el sistema de facturación es quien lleva el
// contador y aplica el descuento; este mock reproduce esa lógica solo
// para que el portal funcione hoy.

require_once __DIR__ . '/ConsultationLedgerProvider.php';

final class MockConsultationLedgerProvider implements ConsultationLedgerProvider
{
    public function __construct(private PDO $pdo) {}

    public function saldo(string $ciAfiliado, ?string $ciBeneficiario = null): SaldoConsultas
    {
        $plan   = $this->planDe($ciAfiliado);
        $usadas = $this->contarUsadas($ciAfiliado, $ciBeneficiario, (bool) $plan['consultas_compartidas']);

        Integraciones::log('facturacion', 'saldo', $ciAfiliado, 'ok');

        return new SaldoConsultas(
            incluidas:      (int) $plan['consultas_incluidas'],
            usadas:         $usadas,
            restantes:      max(0, (int) $plan['consultas_incluidas'] - $usadas),
            poolCompartido: (bool) $plan['consultas_compartidas'],
            desdeCache:     false,
        );
    }

    public function registrarConsumo(
        string $ciAfiliado,
        ?string $ciBeneficiario,
        string $tipo,
        string $referencia
    ): SaldoConsultas {
        $plan  = $this->planDe($ciAfiliado);
        $saldo = $this->saldo($ciAfiliado, $ciBeneficiario);

        // Precio que aplicaría facturación: gratis dentro del plan,
        // precio base con descuento una vez agotado.
        $precio = 0.00;
        if ($saldo->restantes <= 0) {
            $tarifa = $this->tarifa($tipo);
            $base   = $tarifa ? $tarifa->precioBase : 0.00;
            $precio = round($base * (1 - (float) $plan['descuento_posterior']), 2);
        }

        // INSERT IGNORE + UNIQUE(referencia) = idempotencia: la misma
        // referencia registrada dos veces no descuenta doble.
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO consulta_ledger_cache
                (ci_afiliado, ci_beneficiario, tipo, fecha, precio_aplicado, referencia)
            VALUES (:cia, :cib, :tipo, CURDATE(), :precio, :ref)
        ");
        $stmt->execute([
            ':cia'    => $ciAfiliado,
            ':cib'    => $ciBeneficiario,
            ':tipo'   => $tipo,
            ':precio' => $precio,
            ':ref'    => $referencia,
        ]);

        $duplicado = $stmt->rowCount() === 0;
        Integraciones::log(
            'facturacion', 'registrarConsumo', $ciAfiliado,
            'ok', $duplicado ? "referencia repetida (ignorada): $referencia" : "referencia: $referencia"
        );

        return $this->saldo($ciAfiliado, $ciBeneficiario);
    }

    public function historial(string $ciAfiliado, int $anio): array
    {
        $stmt = $this->pdo->prepare("
            SELECT fecha, tipo, ci_beneficiario, precio_aplicado, referencia
            FROM consulta_ledger_cache
            WHERE ci_afiliado = :ci AND YEAR(fecha) = :anio
            ORDER BY fecha DESC, id_movimiento DESC
        ");
        $stmt->execute([':ci' => $ciAfiliado, ':anio' => $anio]);

        Integraciones::log('facturacion', 'historial', $ciAfiliado, 'ok');

        return array_map(
            fn(array $f) => new ConsultaLedgerItem(
                fecha:          $f['fecha'],
                tipo:           $f['tipo'],
                ciBeneficiario: $f['ci_beneficiario'] ?: null,
                precioAplicado: $f['precio_aplicado'] !== null ? (float) $f['precio_aplicado'] : null,
                referencia:     $f['referencia'],
            ),
            $stmt->fetchAll()
        );
    }

    public function tarifa(string $tipo): ?Tarifa
    {
        $stmt = $this->pdo->prepare("
            SELECT precio_base, moneda
            FROM tarifa_cache
            WHERE tipo = :tipo
              AND vigente_desde <= CURDATE()
              AND (vigente_hasta IS NULL OR vigente_hasta >= CURDATE())
            ORDER BY vigente_desde DESC
            LIMIT 1
        ");
        $stmt->execute([':tipo' => $tipo]);
        $fila = $stmt->fetch();

        Integraciones::log('facturacion', 'tarifa', $tipo, $fila ? 'ok' : 'no_encontrado');

        if (!$fila) return null;

        // El descuento informativo sale del plan único activo; en la
        // integración real lo resuelve facturación (decisión #3).
        $desc = $this->pdo->query(
            "SELECT descuento_posterior FROM plan WHERE activo = 1 ORDER BY id_plan LIMIT 1"
        )->fetchColumn();

        return new Tarifa(
            precioBase:         (float) $fila['precio_base'],
            moneda:             $fila['moneda'],
            descuentoAplicable: $desc !== false ? (float) $desc : null,
            desdeCache:         false,
        );
    }

    // ── Internos ─────────────────────────────────────────────

    /** Plan del afiliado por CI; cae al plan activo si no tiene asignado. */
    private function planDe(string $ciAfiliado): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.consultas_incluidas, p.descuento_posterior, p.consultas_compartidas
            FROM afiliado a
            JOIN plan p ON p.id_plan = a.id_plan
            WHERE a.ci = :ci
            LIMIT 1
        ");
        $stmt->execute([':ci' => $ciAfiliado]);
        $plan = $stmt->fetch();

        if (!$plan) {
            $plan = $this->pdo->query("
                SELECT consultas_incluidas, descuento_posterior, consultas_compartidas
                FROM plan WHERE activo = 1 ORDER BY id_plan LIMIT 1
            ")->fetch();
        }

        // Sin ningún plan configurado: comportamiento seguro (0 incluidas)
        return $plan ?: ['consultas_incluidas' => 0, 'descuento_posterior' => 0.00, 'consultas_compartidas' => 1];
    }

    private function contarUsadas(string $ciAfiliado, ?string $ciBeneficiario, bool $compartido): int
    {
        if ($compartido) {
            // Pool familiar: cuentan todos los consumos del grupo (decisión #1)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM consulta_ledger_cache
                WHERE ci_afiliado = :ci AND YEAR(fecha) = YEAR(CURDATE())
            ");
            $stmt->execute([':ci' => $ciAfiliado]);
        } elseif ($ciBeneficiario === null) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM consulta_ledger_cache
                WHERE ci_afiliado = :ci AND ci_beneficiario IS NULL AND YEAR(fecha) = YEAR(CURDATE())
            ");
            $stmt->execute([':ci' => $ciAfiliado]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM consulta_ledger_cache
                WHERE ci_afiliado = :ci AND ci_beneficiario = :cib AND YEAR(fecha) = YEAR(CURDATE())
            ");
            $stmt->execute([':ci' => $ciAfiliado, ':cib' => $ciBeneficiario]);
        }
        return (int) $stmt->fetchColumn();
    }
}
