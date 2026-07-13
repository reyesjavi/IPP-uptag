<?php
// lib/integracion/MockEstadoAfiliacionProvider.php
//
// Implementación MOCK de la frontera con nómina. Lee las tablas locales
// `agremiado` (padrón) y `estado_afiliacion_cache` (estado de pago),
// que se llenan a mano para pruebas. Cuando exista la integración real,
// se crea otra implementación de EstadoAfiliacionProvider y esta clase
// queda solo para desarrollo — ningún consumidor cambia.

require_once __DIR__ . '/EstadoAfiliacionProvider.php';

final class MockEstadoAfiliacionProvider implements EstadoAfiliacionProvider
{
    public function __construct(private PDO $pdo) {}

    public function buscarPorCedula(string $ci): ?AgremiadoPadron
    {
        $stmt = $this->pdo->prepare("
            SELECT a.id_agremiado, a.ci, a.nombre, a.apellido, a.fecha_nacimiento,
                   a.correo, a.telefono, a.activo, a.ref_nomina,
                   e.tipo_afiliado
            FROM agremiado a
            LEFT JOIN estado_afiliacion_cache e ON e.ci = a.ci
            WHERE a.ci = :ci
            LIMIT 1
        ");
        $stmt->execute([':ci' => $ci]);
        $fila = $stmt->fetch();

        Integraciones::log('nomina', 'buscarPorCedula', $ci, $fila ? 'ok' : 'no_encontrado');

        if (!$fila) return null;

        return new AgremiadoPadron(
            idAgremiadoLocal: (int) $fila['id_agremiado'],
            ci:               $fila['ci'],
            nombre:           $fila['nombre'],
            apellido:         $fila['apellido'],
            fechaNacimiento:  $fila['fecha_nacimiento'] ?: null,
            correo:           $fila['correo'] ?: null,
            telefono:         $fila['telefono'] ?: null,
            activo:           (bool) $fila['activo'],
            tipoAfiliado:     $fila['tipo_afiliado'] ?: null,
            refNomina:        $fila['ref_nomina'] ?: null,
        );
    }

    public function obtenerEstado(string $ci): ?EstadoAfiliacion
    {
        $stmt = $this->pdo->prepare("
            SELECT estado, periodo, fecha_ultimo_descuento, tipo_afiliado, actualizado_en
            FROM estado_afiliacion_cache
            WHERE ci = :ci
            LIMIT 1
        ");
        $stmt->execute([':ci' => $ci]);
        $fila = $stmt->fetch();

        Integraciones::log('nomina', 'obtenerEstado', $ci, $fila ? 'ok' : 'no_encontrado');

        if (!$fila) return null;

        return new EstadoAfiliacion(
            estado:               $fila['estado'],
            periodo:              $fila['periodo'] ?: null,
            fechaUltimoDescuento: $fila['fecha_ultimo_descuento'] ?: null,
            tipoAfiliado:         $fila['tipo_afiliado'] ?: null,
            desdeCache:           false,  // el mock ES la fuente; no hay degradación
            actualizadoEn:        $fila['actualizado_en'] ?: null,
        );
    }
}
