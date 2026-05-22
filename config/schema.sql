-- ============================================================
--  IPP-UPTAG — Esquema completo de base de datos
--  Versión consolidada (reemplaza agremiados_schema.sql,
--  actualizacion_v3.sql, datos_prueba.sql, migraciones_v4..v7)
--
--  Base de datos: ippuptag
--  Motor: MySQL / MariaDB 10.4+
--  Todos los nombres de tabla en MINÚSCULAS (Linux case-sensitive).
-- ============================================================

CREATE DATABASE IF NOT EXISTS ippuptag
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ippuptag;

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. plan_medico ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS plan_medico (
    cod_pm   VARCHAR(20)   PRIMARY KEY,
    id_plan  INT,
    cod_a    VARCHAR(50),
    costo    DECIMAL(12,2) NOT NULL DEFAULT 0.00
);

-- ── 2. afiliado ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS afiliado (
    id_afiliado      INT           PRIMARY KEY AUTO_INCREMENT,
    id_agremiado     INT           DEFAULT NULL,
    nombre           VARCHAR(100)  NOT NULL,
    apellido         VARCHAR(100)  NOT NULL,
    ci               VARCHAR(20)   UNIQUE NOT NULL,
    fecha_nacimiento DATE          DEFAULT NULL,
    correo           VARCHAR(150)  DEFAULT NULL,
    telefono         VARCHAR(20)   DEFAULT NULL,
    cod_a            VARCHAR(50)   DEFAULT NULL,
    cod_pm           VARCHAR(20)   DEFAULT NULL,
    fecha_ingreso    DATE          DEFAULT NULL,
    activo           TINYINT(1)    NOT NULL DEFAULT 1,
    FOREIGN KEY (cod_pm) REFERENCES plan_medico(cod_pm)
        ON UPDATE CASCADE ON DELETE SET NULL
);

-- ── 3. beneficiario ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS beneficiario (
    id_beneficiario    INT          PRIMARY KEY AUTO_INCREMENT,
    numero_beneficiario INT         NOT NULL DEFAULT 1,
    ci                 VARCHAR(20)  DEFAULT NULL,
    nombre             VARCHAR(100) NOT NULL,
    apellido           VARCHAR(100) NOT NULL,
    fecha_nacimiento   DATE         DEFAULT NULL,
    parentesco         VARCHAR(50)  NOT NULL DEFAULT 'Titular',
    id_afiliado        INT          NOT NULL,
    UNIQUE KEY uk_afiliado_ci (id_afiliado, ci),
    FOREIGN KEY (id_afiliado) REFERENCES afiliado(id_afiliado)
        ON UPDATE CASCADE ON DELETE CASCADE
);

-- ── 4. usuarios_registrados ───────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios_registrados (
    id_usuario        INT          PRIMARY KEY AUTO_INCREMENT,
    username          VARCHAR(100) UNIQUE NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    rol               VARCHAR(30)  NOT NULL DEFAULT 'afiliado',
    activo            TINYINT(1)   NOT NULL DEFAULT 1,
    id_afiliado       INT          DEFAULT NULL,
    cod_a             VARCHAR(50)  DEFAULT NULL,
    intentos_fallidos TINYINT      NOT NULL DEFAULT 0,
    bloqueado         TINYINT(1)   NOT NULL DEFAULT 0,
    ultimo_acceso     TIMESTAMP    NULL DEFAULT NULL,
    FOREIGN KEY (id_afiliado) REFERENCES afiliado(id_afiliado)
        ON UPDATE CASCADE ON DELETE SET NULL
);

-- ── 5. agremiado ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS agremiado (
    id_agremiado      INT           PRIMARY KEY AUTO_INCREMENT,
    ci                VARCHAR(20)   UNIQUE NOT NULL,
    nombre            VARCHAR(100)  NOT NULL,
    apellido          VARCHAR(100)  NOT NULL,
    fecha_nacimiento  DATE          DEFAULT NULL,
    correo            VARCHAR(150)  DEFAULT NULL,
    telefono          VARCHAR(20)   DEFAULT NULL,
    fecha_agremiacion DATE          NOT NULL DEFAULT (CURRENT_DATE),
    observaciones     TEXT          DEFAULT NULL,
    activo            TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── 6. vigencia_anual ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vigencia_anual (
    id_vigencia       INT           PRIMARY KEY AUTO_INCREMENT,
    id_agremiado      INT           NOT NULL,
    anio              YEAR          NOT NULL,
    fecha_registro    DATE          NOT NULL DEFAULT (CURRENT_DATE),
    fecha_vencimiento DATE          NOT NULL,
    estado            ENUM('activa','vencida','suspendida') NOT NULL DEFAULT 'activa',
    registrado_por    INT           DEFAULT NULL,
    observaciones     VARCHAR(255)  DEFAULT NULL,
    UNIQUE KEY uk_agremiado_anio (id_agremiado, anio),
    FOREIGN KEY (id_agremiado)  REFERENCES agremiado(id_agremiado) ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (registrado_por) REFERENCES usuarios_registrados(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
);

-- ── 7. cuenta_web ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cuenta_web (
    id_cuenta         INT           PRIMARY KEY AUTO_INCREMENT,
    id_agremiado      INT           UNIQUE NOT NULL,
    username          VARCHAR(100)  UNIQUE NOT NULL,
    password_hash     VARCHAR(255)  NOT NULL,
    correo_verificado TINYINT(1)    NOT NULL DEFAULT 0,
    ultimo_acceso     TIMESTAMP     NULL DEFAULT NULL,
    intentos_fallidos TINYINT       NOT NULL DEFAULT 0,
    bloqueado         TINYINT(1)    NOT NULL DEFAULT 0,
    creado_en         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agremiado) REFERENCES agremiado(id_agremiado) ON UPDATE CASCADE ON DELETE RESTRICT
);

-- ── 8. solicitud_registro ────────────────────────────────────
CREATE TABLE IF NOT EXISTS solicitud_registro (
    id_solicitud      INT           PRIMARY KEY AUTO_INCREMENT,
    ci                VARCHAR(20)   NOT NULL,
    correo_contacto   VARCHAR(150)  DEFAULT NULL,
    telefono          VARCHAR(20)   DEFAULT NULL,
    password_hash     VARCHAR(255)  NOT NULL,
    estado            ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    motivo_rechazo    VARCHAR(255)  DEFAULT NULL,
    procesado_por     INT           DEFAULT NULL,
    fecha_solicitud   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion  TIMESTAMP     NULL DEFAULT NULL,
    FOREIGN KEY (procesado_por) REFERENCES usuarios_registrados(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
);

-- ── 9. log_actividad ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS log_actividad (
    id_log     INT          PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT          DEFAULT NULL,
    accion     VARCHAR(60)  NOT NULL,
    detalle    VARCHAR(255) DEFAULT NULL,
    ip         VARCHAR(45)  DEFAULT NULL,
    fecha      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios_registrados(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- ── 10. reembolso ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reembolso (
    id_reembolso      INT           PRIMARY KEY AUTO_INCREMENT,
    tipo_servicio     VARCHAR(60)   NOT NULL,
    fecha_atencion    DATE          NOT NULL,
    monto_solicitado  DECIMAL(12,2) NOT NULL,
    monto_aprobado    DECIMAL(12,2) DEFAULT NULL,
    centro_medico     VARCHAR(120)  DEFAULT NULL,
    descripcion       TEXT          DEFAULT NULL,
    archivo_adjunto   VARCHAR(255)  DEFAULT NULL,
    estado            ENUM('pendiente','en_revision','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    fecha_solicitud   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_afiliado       INT           NOT NULL,
    FOREIGN KEY (id_afiliado) REFERENCES afiliado(id_afiliado) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ── 11. carta_aval ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS carta_aval (
    id_aval           INT           PRIMARY KEY AUTO_INCREMENT,
    medico_tratante   VARCHAR(100)  NOT NULL,
    especialidad      VARCHAR(80)   DEFAULT NULL,
    centro_medico     VARCHAR(120)  NOT NULL,
    procedimiento     TEXT          NOT NULL,
    monto_estimado    DECIMAL(12,2) DEFAULT NULL,
    estado            ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    fecha_solicitud   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_afiliado       INT           NOT NULL,
    FOREIGN KEY (id_afiliado) REFERENCES afiliado(id_afiliado) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ── 12. movimiento_cuenta ────────────────────────────────────
CREATE TABLE IF NOT EXISTS movimiento_cuenta (
    id_movimiento  INT           PRIMARY KEY AUTO_INCREMENT,
    fecha          DATE          NOT NULL DEFAULT (CURRENT_DATE),
    concepto       VARCHAR(200)  NOT NULL,
    tipo           ENUM('credito','debito') NOT NULL,
    monto          DECIMAL(12,2) NOT NULL,
    saldo_despues  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    id_afiliado    INT           NOT NULL,
    FOREIGN KEY (id_afiliado) REFERENCES afiliado(id_afiliado) ON UPDATE CASCADE ON DELETE CASCADE
);

-- ── 13. solicitud_retiro ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS solicitud_retiro (
    id_retiro         INT           PRIMARY KEY AUTO_INCREMENT,
    id_afiliado       INT           NOT NULL,
    tipo_retiro       ENUM('Parcial','Total') NOT NULL DEFAULT 'Parcial',
    monto             DECIMAL(12,2) NOT NULL,
    motivo            TEXT          DEFAULT NULL,
    estado            ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    procesado_por     INT           DEFAULT NULL,
    fecha_solicitud   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion  TIMESTAMP     NULL DEFAULT NULL,
    FOREIGN KEY (id_afiliado)   REFERENCES afiliado(id_afiliado) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (procesado_por) REFERENCES usuarios_registrados(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
);

-- ── 14. recuperacion_password ────────────────────────────────
CREATE TABLE IF NOT EXISTS recuperacion_password (
    id_recuperacion INT          PRIMARY KEY AUTO_INCREMENT,
    id_usuario      INT          NOT NULL,
    token           VARCHAR(64)  UNIQUE NOT NULL,
    expira_en       DATETIME     NOT NULL,
    usado           TINYINT(1)   NOT NULL DEFAULT 0,
    ip_solicitud    VARCHAR(45)  DEFAULT NULL,
    creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios_registrados(id_usuario)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ── 15. servicio ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS servicio (
    id_servicio   INT          PRIMARY KEY AUTO_INCREMENT,
    tipo_servicio VARCHAR(80)  NOT NULL,
    cod_pm        VARCHAR(20)  DEFAULT NULL,
    FOREIGN KEY (cod_pm) REFERENCES plan_medico(cod_pm) ON UPDATE CASCADE ON DELETE SET NULL
);

-- ── 16. medico ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS medico (
    id_medico       INT          PRIMARY KEY AUTO_INCREMENT,
    nombre          VARCHAR(100) NOT NULL,
    apellido        VARCHAR(100) NOT NULL,
    especialidad    VARCHAR(80)  DEFAULT NULL,
    cedula          VARCHAR(20)  DEFAULT NULL,
    numero_contacto VARCHAR(30)  DEFAULT NULL,
    direccion       VARCHAR(200) DEFAULT NULL,
    id_servicio     INT          DEFAULT NULL,
    FOREIGN KEY (id_servicio) REFERENCES servicio(id_servicio) ON UPDATE CASCADE ON DELETE SET NULL
);

SET FOREIGN_KEY_CHECKS = 1;

-- ── Índices de rendimiento ────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_afiliado_ci          ON afiliado(ci);
CREATE INDEX IF NOT EXISTS idx_beneficiario_afil    ON beneficiario(id_afiliado);
CREATE INDEX IF NOT EXISTS idx_reembolso_afiliado   ON reembolso(id_afiliado);
CREATE INDEX IF NOT EXISTS idx_reembolso_estado     ON reembolso(estado);
CREATE INDEX IF NOT EXISTS idx_carta_aval_afiliado  ON carta_aval(id_afiliado);
CREATE INDEX IF NOT EXISTS idx_carta_aval_estado    ON carta_aval(estado);
CREATE INDEX IF NOT EXISTS idx_movimiento_afiliado  ON movimiento_cuenta(id_afiliado);
CREATE INDEX IF NOT EXISTS idx_movimiento_fecha     ON movimiento_cuenta(fecha);
CREATE INDEX IF NOT EXISTS idx_log_fecha            ON log_actividad(fecha);
CREATE INDEX IF NOT EXISTS idx_agremiado_ci         ON agremiado(ci);
CREATE INDEX IF NOT EXISTS idx_vigencia_anio        ON vigencia_anual(anio);
CREATE INDEX IF NOT EXISTS idx_vigencia_estado      ON vigencia_anual(estado);
CREATE INDEX IF NOT EXISTS idx_cuenta_username      ON cuenta_web(username);
CREATE INDEX IF NOT EXISTS idx_recuperacion_token   ON recuperacion_password(token);
CREATE INDEX IF NOT EXISTS idx_recuperacion_expira  ON recuperacion_password(expira_en);
CREATE INDEX IF NOT EXISTS idx_solicitud_ci         ON solicitud_registro(ci);
CREATE INDEX IF NOT EXISTS idx_solicitud_estado     ON solicitud_registro(estado);
CREATE INDEX IF NOT EXISTS idx_retiro_afiliado      ON solicitud_retiro(id_afiliado);
CREATE INDEX IF NOT EXISTS idx_retiro_estado        ON solicitud_retiro(estado);

-- ── Vista: estado completo de agremiado ───────────────────────
CREATE OR REPLACE VIEW v_estado_agremiado AS
SELECT
    a.id_agremiado,
    a.ci,
    a.nombre,
    a.apellido,
    a.activo                        AS agremiado_activo,
    a.fecha_agremiacion,
    (c.id_cuenta IS NOT NULL)       AS tiene_cuenta_web,
    c.username,
    c.bloqueado,
    v.anio                          AS anio_vigencia,
    v.estado                        AS estado_vigencia,
    v.fecha_vencimiento,
    CASE
        WHEN a.activo = FALSE        THEN 'Inactivo'
        WHEN c.id_cuenta IS NULL     THEN 'Sin cuenta web'
        WHEN c.bloqueado = TRUE      THEN 'Cuenta bloqueada'
        WHEN v.id_vigencia IS NULL   THEN 'Sin vigencia este año'
        WHEN v.estado = 'activa'     THEN 'Activo'
        WHEN v.estado = 'vencida'    THEN 'Vigencia vencida'
        ELSE 'Suspendido'
    END AS estado_general
FROM agremiado a
LEFT JOIN cuenta_web c ON c.id_agremiado = a.id_agremiado
LEFT JOIN vigencia_anual v
    ON v.id_agremiado = a.id_agremiado AND v.anio = YEAR(CURDATE());
