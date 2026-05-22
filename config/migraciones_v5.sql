-- MIGRACIONES v5 — Recuperación de contraseña
-- Ejecutar en phpMyAdmin sobre ippuptag

CREATE TABLE IF NOT EXISTS recuperacion_password (
    id_recuperacion INT          PRIMARY KEY AUTO_INCREMENT,
    id_usuario      INT          NOT NULL,
    token           VARCHAR(64)  UNIQUE NOT NULL,
    expira_en       DATETIME     NOT NULL,
    usado           BOOLEAN      NOT NULL DEFAULT FALSE,
    ip_solicitud    VARCHAR(45),
    creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios_registrados(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_recuperacion_token  ON recuperacion_password(token);
CREATE INDEX IF NOT EXISTS idx_recuperacion_expira ON recuperacion_password(expira_en);
