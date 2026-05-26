-- ============================================================
--  MIGRACIONES v11 — IPP-UPTAG
--  Ejecutar en phpMyAdmin sobre la BD ippuptag
-- ============================================================

-- ── Tabla afiliado_servicio ──────────────────────────────────
--
-- REGLA DE NEGOCIO: un servicio está HABILITADO para un afiliado
-- únicamente si existe una fila en esta tabla con habilitado = 1.
-- Si no existe ninguna fila para el par (id_afiliado, id_servicio),
-- el servicio está deshabilitado por defecto para ese afiliado.
-- Esto permite arrancar sin configurar nada (opt-in por admin).

CREATE TABLE IF NOT EXISTS afiliado_servicio (
    id_afiliado_servicio INT           NOT NULL AUTO_INCREMENT,
    id_afiliado          INT           NOT NULL,
    id_servicio          INT           NOT NULL,
    habilitado           TINYINT(1)    NOT NULL DEFAULT 1,
    fecha_asignacion     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    asignado_por         INT           DEFAULT NULL,   -- id_usuario del admin que hizo el cambio
    PRIMARY KEY (id_afiliado_servicio),
    UNIQUE KEY uk_afiliado_servicio (id_afiliado, id_servicio),
    FOREIGN KEY (id_afiliado) REFERENCES afiliado(id_afiliado)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_servicio) REFERENCES servicio(id_servicio)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
