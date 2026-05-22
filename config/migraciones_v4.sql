-- ============================================================
--  MIGRACIONES v4 — Aplicar sobre ippuptag
--  Incluye: seguridad, edge cases y nuevas funcionalidades
--  Ejecutar en phpMyAdmin en este orden
-- ============================================================

-- ── 1. Campos de seguridad en usuarios_registrados ──────────
ALTER TABLE usuarios_registrados
  ADD COLUMN IF NOT EXISTS intentos_fallidos TINYINT      NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS bloqueado         BOOLEAN      NOT NULL DEFAULT FALSE;

-- ── 2. Restricción de beneficiario duplicado por afiliado ───
-- Evita que el mismo afiliado agregue el mismo beneficiario dos veces
ALTER TABLE beneficiario
  ADD UNIQUE KEY IF NOT EXISTS uk_afiliado_ci (id_afiliado, ci);

-- ── 3. Tabla log_actividad (si no existe ya) ────────────────
CREATE TABLE IF NOT EXISTS log_actividad (
    id_log     INT          PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT,
    accion     VARCHAR(60)  NOT NULL,
    detalle    VARCHAR(255),
    ip         VARCHAR(45),
    fecha      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios_registrados(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- ── 4. Tabla solicitud_retiro (nueva) ───────────────────────
CREATE TABLE IF NOT EXISTS solicitud_retiro (
    id_retiro     INT           PRIMARY KEY AUTO_INCREMENT,
    id_afiliado   INT           NOT NULL,
    tipo_retiro   ENUM('Parcial','Total') NOT NULL DEFAULT 'Parcial',
    monto         DECIMAL(12,2) NOT NULL,
    motivo        TEXT,
    estado        ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    procesado_por INT,
    fecha_solicitud  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion TIMESTAMP  NULL DEFAULT NULL,
    FOREIGN KEY (id_afiliado)   REFERENCES afiliado(id_afiliado) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (procesado_por) REFERENCES usuarios_registrados(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
);

-- ── 5. Tablas del sistema de agremiados (si no existen) ─────
CREATE TABLE IF NOT EXISTS agremiado (
    id_agremiado     INT           PRIMARY KEY AUTO_INCREMENT,
    ci               VARCHAR(20)   UNIQUE NOT NULL,
    nombre           VARCHAR(100)  NOT NULL,
    apellido         VARCHAR(100)  NOT NULL,
    fecha_nacimiento DATE,
    correo           VARCHAR(150),
    telefono         VARCHAR(20),
    fecha_agremiacion DATE         NOT NULL DEFAULT (CURRENT_DATE),
    observaciones    TEXT,
    activo           BOOLEAN       NOT NULL DEFAULT TRUE,
    creado_en        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vigencia_anual (
    id_vigencia      INT           PRIMARY KEY AUTO_INCREMENT,
    id_agremiado     INT           NOT NULL,
    anio             YEAR          NOT NULL,
    fecha_registro   DATE          NOT NULL DEFAULT (CURRENT_DATE),
    fecha_vencimiento DATE         NOT NULL,
    estado           ENUM('activa','vencida','suspendida') NOT NULL DEFAULT 'activa',
    registrado_por   INT,
    observaciones    VARCHAR(255),
    UNIQUE KEY uk_agremiado_anio (id_agremiado, anio),
    FOREIGN KEY (id_agremiado)  REFERENCES agremiado(id_agremiado) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (registrado_por) REFERENCES usuarios_registrados(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS cuenta_web (
    id_cuenta         INT           PRIMARY KEY AUTO_INCREMENT,
    id_agremiado      INT           UNIQUE NOT NULL,
    username          VARCHAR(100)  UNIQUE NOT NULL,
    password_hash     VARCHAR(255)  NOT NULL,
    correo_verificado BOOLEAN       NOT NULL DEFAULT FALSE,
    ultimo_acceso     TIMESTAMP     NULL DEFAULT NULL,
    intentos_fallidos TINYINT       NOT NULL DEFAULT 0,
    bloqueado         BOOLEAN       NOT NULL DEFAULT FALSE,
    creado_en         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agremiado) REFERENCES agremiado(id_agremiado) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS solicitud_registro (
    id_solicitud     INT           PRIMARY KEY AUTO_INCREMENT,
    ci               VARCHAR(20)   NOT NULL,
    correo_contacto  VARCHAR(150),
    telefono         VARCHAR(20),
    password_hash    VARCHAR(255)  NOT NULL,
    estado           ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    motivo_rechazo   VARCHAR(255),
    procesado_por    INT,
    fecha_solicitud  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion TIMESTAMP     NULL DEFAULT NULL,
    FOREIGN KEY (procesado_por) REFERENCES usuarios_registrados(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
);

-- ── 6. Vista de estado de agremiado ─────────────────────────
CREATE OR REPLACE VIEW v_estado_agremiado AS
SELECT
    a.id_agremiado, a.ci, a.nombre, a.apellido,
    a.activo                       AS agremiado_activo,
    a.fecha_agremiacion,
    (c.id_cuenta IS NOT NULL)      AS tiene_cuenta_web,
    c.username, c.bloqueado,
    v.anio                         AS anio_vigencia,
    v.estado                       AS estado_vigencia,
    v.fecha_vencimiento,
    CASE
        WHEN a.activo = FALSE       THEN 'Inactivo'
        WHEN c.id_cuenta IS NULL    THEN 'Sin cuenta web'
        WHEN c.bloqueado = TRUE     THEN 'Cuenta bloqueada'
        WHEN v.id_vigencia IS NULL  THEN 'Sin vigencia este año'
        WHEN v.estado = 'activa'    THEN 'Activo'
        WHEN v.estado = 'vencida'   THEN 'Vigencia vencida'
        ELSE 'Suspendido'
    END AS estado_general
FROM agremiado a
LEFT JOIN cuenta_web c   ON c.id_agremiado = a.id_agremiado
LEFT JOIN vigencia_anual v
    ON v.id_agremiado = a.id_agremiado AND v.anio = YEAR(CURDATE());

-- ── 7. Índices de rendimiento ────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_reembolso_estado     ON reembolso(estado);
CREATE INDEX IF NOT EXISTS idx_reembolso_afiliado   ON reembolso(id_afiliado);
CREATE INDEX IF NOT EXISTS idx_carta_aval_estado     ON carta_aval(estado);
CREATE INDEX IF NOT EXISTS idx_log_fecha             ON log_actividad(fecha);
CREATE INDEX IF NOT EXISTS idx_vigencia_anio         ON vigencia_anual(anio);
CREATE INDEX IF NOT EXISTS idx_agremiado_ci          ON agremiado(ci);

-- ── 8. Usuarios de prueba (admin y administrativo) ──────────
-- IMPORTANTE: Estos hashes son para la contraseña "admin2026"
-- Si no funciona el login, ejecuta crear_admins.php para regenerarlos
INSERT IGNORE INTO usuarios_registrados (username, password_hash, rol, activo)
VALUES ('admin', '$2y$12$placeholder', 'admin', 1);

INSERT IGNORE INTO usuarios_registrados (username, password_hash, rol, activo)
VALUES ('administrativo', '$2y$12$placeholder', 'administrativo', 1);

-- NOTA: Después de importar este SQL, ejecuta crear_admins.php para
-- generar los hashes correctos con tu versión de PHP local.
