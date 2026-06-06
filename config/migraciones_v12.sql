-- ============================================================
--  MIGRACIONES v12 — IPP-UPTAG
--  Ejecutar en phpMyAdmin sobre la BD ippuptag
--
--  Cambios:
--   1. usuarios_registrados: verificación de correo + bloqueo temporal
--   2. Nueva tabla verificacion_correo (activación de cuenta por correo)
--   3. Eliminación de la tabla legacy cuenta_web (consolidación de cuentas)
-- ============================================================

-- ── 1. Columnas nuevas en usuarios_registrados ───────────────
--
-- correo_verificado: las cuentas existentes se dan por verificadas
-- (DEFAULT 1) para no romper logins actuales. Sólo los registros
-- nuevos nacen con 0 hasta confirmar el correo.
--
-- bloqueado_hasta: bloqueo TEMPORAL por intentos fallidos. Se
-- conserva la columna `bloqueado` para bloqueo MANUAL del admin.

ALTER TABLE usuarios_registrados
    ADD COLUMN IF NOT EXISTS correo_verificado TINYINT(1) NOT NULL DEFAULT 1 AFTER activo;

ALTER TABLE usuarios_registrados
    ADD COLUMN IF NOT EXISTS bloqueado_hasta TIMESTAMP NULL DEFAULT NULL AFTER bloqueado;

-- ── 2. Tabla verificacion_correo ─────────────────────────────
--
-- Espejo de recuperacion_password. El token se almacena HASHEADO
-- (sha256 → 64 hex). El enlace del correo lleva el token en claro.

CREATE TABLE IF NOT EXISTS verificacion_correo (
    id_verificacion INT          PRIMARY KEY AUTO_INCREMENT,
    id_usuario      INT          NOT NULL,
    token           VARCHAR(64)  UNIQUE NOT NULL,
    expira_en       DATETIME     NOT NULL,
    usado           TINYINT(1)   NOT NULL DEFAULT 0,
    ip_solicitud    VARCHAR(45)  DEFAULT NULL,
    creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios_registrados(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX IF NOT EXISTS idx_verificacion_token  ON verificacion_correo(token);
CREATE INDEX IF NOT EXISTS idx_verificacion_expira ON verificacion_correo(expira_en);

-- ── 3. Eliminar tabla legacy cuenta_web ──────────────────────
--
-- usuarios_registrados pasa a ser la única fuente de verdad de
-- autenticación. cuenta_web nunca se leía para login y ninguna
-- otra tabla la referencia por FK. Aplicar SÓLO después de que
-- el código (registro, solicitudes, cambiar_password) ya no la use.

DROP TABLE IF EXISTS cuenta_web;
