<?php
// lib/integracion/EstadoAfiliacionProvider.php
//
// FRONTERA 1: sistema de NÓMINA / padrón de agremiados del IPP-UPTAG.
//
// Contrato de lo que ESTE sistema necesita de nómina; no asume nada
// sobre cómo el sistema externo entrega los datos (ver INTEGRACION.md).
// La clave de correlación en la frontera es la CI — nunca IDs internos.
//
// Implementaciones:
//   - MockEstadoAfiliacionProvider (hoy): lee las tablas locales
//     `agremiado` y `estado_afiliacion_cache`, que se llenan a mano.
//   - Implementación real (futuro): consume el feed de nómina y hace
//     upsert en esas mismas tablas, que pasan a ser caché degradado.

interface EstadoAfiliacionProvider
{
    /**
     * Búsqueda en el padrón de agremiados (usada por el registro).
     * @return AgremiadoPadron|null  null = la CI no está en el padrón.
     */
    public function buscarPorCedula(string $ci): ?AgremiadoPadron;

    /**
     * Estado de afiliación por descuento de nómina del banco.
     * Este sistema NO calcula ni procesa pagos: muestra lo que se
     * devuelva aquí, sin inferir nada.
     * @return EstadoAfiliacion|null  null = el sistema de nómina no
     *                                reporta estado para esta CI.
     */
    public function obtenerEstado(string $ci): ?EstadoAfiliacion;
}

/**
 * Registro del padrón de agremiados.
 *
 * $idAgremiadoLocal: id de la fila espejo en la tabla local `agremiado`.
 * Toda implementación (mock o real) DEBE materializar/actualizar esa fila
 * y devolver su id, porque las FKs internas (vigencia_anual,
 * afiliado.id_agremiado) dependen de ella.
 */
final class AgremiadoPadron
{
    public function __construct(
        public readonly int     $idAgremiadoLocal,
        public readonly string  $ci,
        public readonly string  $nombre,
        public readonly string  $apellido,
        public readonly ?string $fechaNacimiento,   // 'Y-m-d'
        public readonly ?string $correo,
        public readonly ?string $telefono,
        public readonly bool    $activo,            // agremiación vigente en el padrón
        public readonly ?string $tipoAfiliado,      // 'profesor_activo'|'profesor_jubilado'|null si el feed no lo trae
        public readonly ?string $refNomina,         // ID estable en el sistema de nómina (a confirmar)
    ) {}
}

/**
 * Estado de pago de la afiliación según nómina.
 */
final class EstadoAfiliacion
{
    public const ESTADOS = ['activo', 'inactivo', 'moroso', 'suspendido'];

    public function __construct(
        public readonly string  $estado,               // uno de self::ESTADOS
        public readonly ?string $periodo,              // 'YYYY-MM' último período procesado
        public readonly ?string $fechaUltimoDescuento, // 'Y-m-d'
        public readonly ?string $tipoAfiliado,         // 'profesor_activo'|'profesor_jubilado'|null
        public readonly bool    $desdeCache,           // true = dato de caché (modo degradado)
        public readonly ?string $actualizadoEn,        // timestamp del dato
    ) {}
}
