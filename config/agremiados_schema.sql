-- ============================================================
--  UPTAG — Sistema de Agremiados y Registro Anual
--  Ejecutar sobre la base de datos: ippuptag
-- ============================================================

-- ============================================================
-- TABLA 1: AGREMIADOS (Padrón maestro vitalicio)
-- Solo el administrador puede insertar aquí.
-- Esta tabla NO la toca el portal web, es la fuente de verdad.
-- ============================================================
CREATE TABLE IF NOT EXISTS agremiado (
    id_agremiado     INT           PRIMARY KEY AUTO_INCREMENT,
    ci               VARCHAR(20)   UNIQUE NOT NULL,   -- Cédula de identidad
    nombre           VARCHAR(100)  NOT NULL,
    apellido         VARCHAR(100)  NOT NULL,
    fecha_nacimiento DATE,
    correo           VARCHAR(150),
    telefono         VARCHAR(20),
    fecha_agremiacion DATE         NOT NULL DEFAULT (CURRENT_DATE), -- cuando fue admitido
    observaciones    TEXT,
    -- Estatus vitalicio: solo se desactiva en casos excepcionales (fallecimiento, etc.)
    activo           BOOLEAN       NOT NULL DEFAULT TRUE,
    creado_en        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLA 2: VIGENCIA_ANUAL (Renovación cada año)
-- Registra si el agremiado pagó/renovó su acceso para el año.
-- Un agremiado puede tener múltiples filas, una por año.
-- ============================================================
CREATE TABLE IF NOT EXISTS vigencia_anual (
    id_vigencia      INT           PRIMARY KEY AUTO_INCREMENT,
    id_agremiado     INT           NOT NULL,
    anio             YEAR          NOT NULL,           -- Año de vigencia: 2024, 2025, 2026...
    fecha_registro   DATE          NOT NULL DEFAULT (CURRENT_DATE),
    fecha_vencimiento DATE         NOT NULL,           -- Generalmente 31/12 del año en curso
    estado           ENUM('activa','vencida','suspendida') NOT NULL DEFAULT 'activa',
    registrado_por   INT,                              -- ID del admin que procesó la renovación
    observaciones    VARCHAR(255),
    UNIQUE KEY uk_agremiado_anio (id_agremiado, anio),   -- Un solo registro por año
    FOREIGN KEY (id_agremiado) REFERENCES agremiado(id_agremiado)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (registrado_por) REFERENCES usuarios_registrados(id_usuario)
        ON UPDATE CASCADE ON DELETE SET NULL
);

-- ============================================================
-- TABLA 3: CUENTA_WEB (Credenciales de acceso al portal)
-- Se crea UNA sola vez. Las credenciales no expiran,
-- pero el ACCESO depende de que haya vigencia_anual activa.
-- ============================================================
CREATE TABLE IF NOT EXISTS cuenta_web (
    id_cuenta        INT           PRIMARY KEY AUTO_INCREMENT,
    id_agremiado     INT           UNIQUE NOT NULL,    -- 1 agremiado = 1 cuenta web
    username         VARCHAR(100)  UNIQUE NOT NULL,    -- Generalmente la CI
    password_hash    VARCHAR(255)  NOT NULL,
    correo_verificado BOOLEAN      NOT NULL DEFAULT FALSE,
    ultimo_acceso    TIMESTAMP     NULL DEFAULT NULL,
    intentos_fallidos TINYINT      NOT NULL DEFAULT 0,
    bloqueado        BOOLEAN       NOT NULL DEFAULT FALSE,
    creado_en        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_agremiado) REFERENCES agremiado(id_agremiado)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

-- ============================================================
-- TABLA 4: SOLICITUD_REGISTRO (Cola de solicitudes pendientes)
-- Cuando un agremiado quiere registrarse, su solicitud queda
-- aquí hasta que un admin la aprueba o rechaza.
-- ============================================================
CREATE TABLE IF NOT EXISTS solicitud_registro (
    id_solicitud     INT           PRIMARY KEY AUTO_INCREMENT,
    ci               VARCHAR(20)   NOT NULL,
    correo_contacto  VARCHAR(150),
    telefono         VARCHAR(20),
    password_hash    VARCHAR(255)  NOT NULL,           -- ya hasheada al recibir
    estado           ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    motivo_rechazo   VARCHAR(255),
    procesado_por    INT,
    fecha_solicitud  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion TIMESTAMP     NULL DEFAULT NULL,
    FOREIGN KEY (procesado_por) REFERENCES usuarios_registrados(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- ============================================================
-- ÍNDICES para consultas frecuentes
-- ============================================================
CREATE INDEX idx_agremiado_ci       ON agremiado(ci);
CREATE INDEX idx_vigencia_anio      ON vigencia_anual(anio);
CREATE INDEX idx_vigencia_estado    ON vigencia_anual(estado);
CREATE INDEX idx_cuenta_username    ON cuenta_web(username);
CREATE INDEX idx_solicitud_ci       ON solicitud_registro(ci);
CREATE INDEX idx_solicitud_estado   ON solicitud_registro(estado);

-- ============================================================
-- VISTA ÚTIL: Estado completo de un agremiado
-- Muestra de un vistazo si está activo, tiene cuenta y vigencia
-- ============================================================
CREATE OR REPLACE VIEW v_estado_agremiado AS
SELECT
    a.id_agremiado,
    a.ci,
    a.nombre,
    a.apellido,
    a.activo                                           AS agremiado_activo,
    a.fecha_agremiacion,
    c.id_cuenta                                        IS NOT NULL AS tiene_cuenta_web,
    c.username,
    c.bloqueado,
    v.anio                                             AS anio_vigencia,
    v.estado                                           AS estado_vigencia,
    v.fecha_vencimiento,
    CASE
        WHEN a.activo = FALSE                          THEN 'Inactivo (sin agremiación)'
        WHEN c.id_cuenta IS NULL                       THEN 'Sin cuenta web'
        WHEN c.bloqueado = TRUE                        THEN 'Cuenta bloqueada'
        WHEN v.id_vigencia IS NULL                     THEN 'Sin vigencia este año'
        WHEN v.estado = 'activa'                       THEN 'Activo'
        WHEN v.estado = 'vencida'                      THEN 'Vigencia vencida'
        WHEN v.estado = 'suspendida'                   THEN 'Suspendido'
        ELSE 'Desconocido'
    END                                                AS estado_general
FROM agremiado a
LEFT JOIN cuenta_web c ON c.id_agremiado = a.id_agremiado
LEFT JOIN vigencia_anual v
    ON v.id_agremiado = a.id_agremiado
    AND v.anio = YEAR(CURDATE());


-- ============================================================
-- DATOS DE PRUEBA
-- ============================================================

-- Agremiados vitalicios (padrón maestro)
INSERT IGNORE INTO agremiado (ci, nombre, apellido, fecha_nacimiento, correo, fecha_agremiacion) VALUES
('V-12345678', 'José',    'Ramírez', '1985-03-15', 'j.ramirez@uptag.edu.ve',  '2010-01-15'),
('V-22334455', 'María',   'González','1990-07-22', 'm.gonzalez@uptag.edu.ve', '2015-03-01'),
('V-33445566', 'Carlos',  'Pérez',   '1978-11-08', 'c.perez@uptag.edu.ve',    '2008-09-10'),
('V-44556677', 'Ana',     'Torres',  '1995-02-14', 'a.torres@uptag.edu.ve',   '2020-06-05'),
('V-55667788', 'Luis',    'Medina',  '1982-05-30', 'l.medina@uptag.edu.ve',   '2012-02-20');
