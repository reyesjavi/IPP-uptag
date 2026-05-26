-- ============================================================
--  MIGRACIONES v10 — IPP-UPTAG
--  Ejecutar en phpMyAdmin sobre la BD ippuptag
-- ============================================================

-- ── 1. Tabla medico: corregir campos NOT NULL y agregar dirección ──

-- Primero eliminar la FK para poder modificar id_servicio
ALTER TABLE medico DROP FOREIGN KEY medico_ibfk_1;

-- Hacer nullable los campos opcionales y agregar columna dirección
ALTER TABLE medico
  MODIFY especialidad  VARCHAR(100) DEFAULT NULL,
  MODIFY cedula        VARCHAR(20)  DEFAULT NULL,
  MODIFY id_servicio   INT          DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS direccion VARCHAR(200) DEFAULT NULL AFTER numero_contacto;

-- Re-añadir FK como nullable con ON DELETE SET NULL
ALTER TABLE medico
  ADD CONSTRAINT medico_ibfk_1
    FOREIGN KEY (id_servicio) REFERENCES servicio(id_servicio)
    ON UPDATE CASCADE ON DELETE SET NULL;

-- ── 2. Tabla afiliado: estatus laboral/previsional ──
-- "activo" (booleano) sigue controlando el acceso al sistema.
-- "situacion" es el estatus laboral: activo en nómina, jubilado, suspendido o egresado.
ALTER TABLE afiliado
  ADD COLUMN IF NOT EXISTS situacion
    ENUM('activo','jubilado','suspendido','egresado') NOT NULL DEFAULT 'activo'
    AFTER activo;
