-- ============================================================
--  MIGRACIONES v13 — IPP-UPTAG
--  Ejecutar en phpMyAdmin sobre la BD ippuptag
--
--  Tema: modelo de planes config-driven + fronteras con sistemas
--  externos (nómina/agremiados y facturación de consultas).
--
--  Cambios:
--   1. Nueva tabla plan (beneficios configurables; nada hardcodeado)
--   2. afiliado: id_plan + tipo_afiliado (situacion queda DEPRECADA)
--   3. agremiado: ref_nomina (ID estable del sistema de nómina)
--   4. beneficiario: activo + parentesco normalizado a ENUM
--   5. Nueva tabla estado_afiliacion_cache (mock/caché de nómina)
--   6. Nueva tabla consulta_ledger_cache (mock/caché de facturación)
--   7. Nueva tabla tarifa_cache (precio base; dueño: facturación)
--   8. Nueva tabla cita (agenda con especialistas IPP)
--   9. Nueva tabla integracion_log (auditoría de providers)
--  10. Centros en convenio desactivados (todas las consultas son en IPP)
--
--  NO se elimina ni renombra nada. plan_medico queda CONGELADA
--  (solo lectura; ningún flujo nuevo debe referenciarla).
-- ============================================================

-- ── 1. Tabla plan ─────────────────────────────────────────────
--
-- Config-driven: la lógica de consultas SIEMPRE lee de aquí.
-- descuento_posterior es fracción (0.50 = 50%). Es CONFIGURACIÓN
-- del mock/visualización: el descuento real lo aplica el sistema
-- de facturación (decisión #3, ver INTEGRACION.md).
-- consultas_compartidas = 1 → pool familiar por afiliado (decisión #1).

CREATE TABLE IF NOT EXISTS plan (
    id_plan               INT           PRIMARY KEY AUTO_INCREMENT,
    nombre                VARCHAR(80)   NOT NULL,
    consultas_incluidas   INT           NOT NULL DEFAULT 4,
    descuento_posterior   DECIMAL(4,2)  NOT NULL DEFAULT 0.50,
    consultas_compartidas TINYINT(1)    NOT NULL DEFAULT 1,
    activo                TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed idempotente: un único plan vigente hoy.
INSERT INTO plan (nombre, consultas_incluidas, descuento_posterior, consultas_compartidas, activo)
SELECT 'Plan IPP', 4, 0.50, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM plan);

-- ── 2. afiliado: id_plan + tipo_afiliado ──────────────────────
--
-- tipo_afiliado: condición previsional. Fuente preferida: feed de
-- nómina (EstadoAfiliacionProvider). Fallback: select en registro.
-- Si el feed lo provee, la UI lo muestra en solo lectura.
--
-- DEPRECADO: afiliado.situacion. Mezclaba condición (activo/jubilado
-- → ahora tipo_afiliado) con estado de pago (suspendido/moroso → ahora
-- lo reporta EstadoAfiliacionProvider). Se conserva durante la
-- transición y se eliminará en una migración futura.

ALTER TABLE afiliado
    ADD COLUMN IF NOT EXISTS id_plan INT DEFAULT NULL AFTER cod_pm,
    ADD COLUMN IF NOT EXISTS tipo_afiliado ENUM('profesor_activo','profesor_jubilado')
        NOT NULL DEFAULT 'profesor_activo' AFTER situacion;

-- MariaDB 10.4 no soporta IF NOT EXISTS en constraints FOREIGN KEY;
-- se emula la idempotencia consultando information_schema.
SET @fk_existe := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'afiliado'
      AND CONSTRAINT_NAME = 'fk_afiliado_plan'
);
SET @sql := IF(@fk_existe = 0,
    'ALTER TABLE afiliado ADD CONSTRAINT fk_afiliado_plan FOREIGN KEY (id_plan) REFERENCES plan(id_plan) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill: condición desde la situacion existente
UPDATE afiliado SET tipo_afiliado = 'profesor_jubilado' WHERE situacion = 'jubilado';

-- Backfill: todos los afiliados quedan en el plan único vigente
UPDATE afiliado
   SET id_plan = (SELECT MIN(id_plan) FROM plan WHERE activo = 1)
 WHERE id_plan IS NULL;

-- ── 3. agremiado: referencia estable del sistema de nómina ────
--
-- La CI es la clave de correlación hoy, pero se solicita a nómina
-- su ID estable (las CI se corrigen en la práctica). Ver INTEGRACION.md.

ALTER TABLE agremiado
    ADD COLUMN IF NOT EXISTS ref_nomina VARCHAR(50) DEFAULT NULL AFTER observaciones;

-- ── 4. beneficiario: activo + parentesco ENUM ─────────────────
--
-- La tabla ya existía (el titular se materializa como fila con
-- parentesco 'titular' desde el registro). Se normalizan los valores
-- libres existentes y se restringe a ENUM. 'otro' absorbe cualquier
-- valor histórico no mapeable.

ALTER TABLE beneficiario
    ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1 AFTER parentesco;

UPDATE beneficiario SET parentesco = LOWER(TRIM(parentesco));
UPDATE beneficiario SET parentesco = 'conyuge'
 WHERE parentesco IN ('cónyuge','conyugue','esposo','esposa','pareja');
UPDATE beneficiario SET parentesco = 'hijo'  WHERE parentesco IN ('hija','hijo(a)','hijastro','hijastra');
UPDATE beneficiario SET parentesco = 'padre' WHERE parentesco IN ('papá','papa');
UPDATE beneficiario SET parentesco = 'madre' WHERE parentesco IN ('mamá','mama');
UPDATE beneficiario SET parentesco = 'otro'
 WHERE parentesco NOT IN ('titular','padre','madre','hijo','conyuge');

ALTER TABLE beneficiario
    MODIFY parentesco ENUM('titular','padre','madre','hijo','conyuge','otro')
        NOT NULL DEFAULT 'otro';

-- ── 5. estado_afiliacion_cache ────────────────────────────────
--
-- Respaldo del MOCK de nómina hoy; caché de modo degradado cuando
-- llegue la integración real. Se llena a mano para pruebas.
-- El pago es por descuento de nómina del banco: este sistema NUNCA
-- calcula ni procesa pagos, solo muestra lo que diga el provider.

CREATE TABLE IF NOT EXISTS estado_afiliacion_cache (
    id_estado              INT          PRIMARY KEY AUTO_INCREMENT,
    ci                     VARCHAR(20)  UNIQUE NOT NULL,
    estado                 ENUM('activo','inactivo','moroso','suspendido') NOT NULL DEFAULT 'activo',
    periodo                VARCHAR(7)   DEFAULT NULL,   -- 'YYYY-MM' del último período procesado
    fecha_ultimo_descuento DATE         DEFAULT NULL,
    tipo_afiliado          ENUM('profesor_activo','profesor_jubilado') DEFAULT NULL,
    actualizado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 6. consulta_ledger_cache ──────────────────────────────────
--
-- Espejo local del ledger de consultas cuyo DUEÑO es el sistema de
-- facturación (otro equipo). Este sistema NO mantiene contador propio:
-- lee y consume. Hoy respalda al mock; mañana sirve de caché degradado.
-- Se habla en CI (clave de la frontera), no en IDs internos.
-- ci_beneficiario NULL = consumió el titular.
-- referencia es ÚNICA → registrar dos veces el mismo consumo no descuenta doble.

CREATE TABLE IF NOT EXISTS consulta_ledger_cache (
    id_movimiento   INT           PRIMARY KEY AUTO_INCREMENT,
    ci_afiliado     VARCHAR(20)   NOT NULL,
    ci_beneficiario VARCHAR(20)   DEFAULT NULL,
    tipo            VARCHAR(60)   NOT NULL DEFAULT 'consulta',
    fecha           DATE          NOT NULL DEFAULT (CURRENT_DATE),
    precio_aplicado DECIMAL(12,2) DEFAULT NULL,
    referencia      VARCHAR(64)   UNIQUE NOT NULL,
    creado_en       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX IF NOT EXISTS idx_ledger_afiliado_fecha ON consulta_ledger_cache(ci_afiliado, fecha);

-- ── 7. tarifa_cache ───────────────────────────────────────────
--
-- Precio base de la consulta. DUEÑO: sistema de facturación.
-- Aquí solo vive la copia que usa el mock / el modo degradado.

CREATE TABLE IF NOT EXISTS tarifa_cache (
    id_tarifa     INT           PRIMARY KEY AUTO_INCREMENT,
    tipo          VARCHAR(60)   NOT NULL,
    precio_base   DECIMAL(12,2) NOT NULL,
    moneda        VARCHAR(10)   NOT NULL DEFAULT 'VES',
    vigente_desde DATE          NOT NULL DEFAULT (CURRENT_DATE),
    vigente_hasta DATE          DEFAULT NULL,             -- NULL = vigente
    UNIQUE KEY uk_tarifa (tipo, vigente_desde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed de PRUEBA (valor ficticio; reemplazar con el precio real de facturación)
INSERT INTO tarifa_cache (tipo, precio_base, moneda)
SELECT 'consulta', 500.00, 'VES'
WHERE NOT EXISTS (SELECT 1 FROM tarifa_cache WHERE tipo = 'consulta');

-- ── 8. cita ───────────────────────────────────────────────────
--
-- Agenda con especialistas del IPP (medico.tipo = 'medico'; se valida
-- en código). Mismo patrón que carta_aval: id_beneficiario NULL = la
-- cita es del titular (decisión #6). Sin FK polimórficas.

CREATE TABLE IF NOT EXISTS cita (
    id_cita         INT          PRIMARY KEY AUTO_INCREMENT,
    id_afiliado     INT          NOT NULL,
    id_beneficiario INT          DEFAULT NULL,
    id_medico       INT          NOT NULL,
    fecha_hora      DATETIME     NOT NULL,
    estado          ENUM('pendiente','confirmada','cancelada','atendida','no_asistio')
                                 NOT NULL DEFAULT 'pendiente',
    notas           VARCHAR(255) DEFAULT NULL,
    creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_afiliado)     REFERENCES afiliado(id_afiliado)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (id_beneficiario) REFERENCES beneficiario(id_beneficiario)
        ON UPDATE CASCADE ON DELETE SET NULL,
    FOREIGN KEY (id_medico)       REFERENCES medico(id_medico)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX IF NOT EXISTS idx_cita_afiliado ON cita(id_afiliado, fecha_hora);
CREATE INDEX IF NOT EXISTS idx_cita_medico   ON cita(id_medico, fecha_hora);
CREATE INDEX IF NOT EXISTS idx_cita_estado   ON cita(estado);

-- ── 9. integracion_log ────────────────────────────────────────
--
-- Auditoría de cada llamada a los providers externos (mock o real).

CREATE TABLE IF NOT EXISTS integracion_log (
    id_log    INT          PRIMARY KEY AUTO_INCREMENT,
    sistema   ENUM('nomina','facturacion') NOT NULL,
    operacion VARCHAR(60)  NOT NULL,
    clave     VARCHAR(50)  DEFAULT NULL,        -- CI u otro identificador consultado
    resultado ENUM('ok','no_encontrado','error') NOT NULL,
    detalle   VARCHAR(255) DEFAULT NULL,
    fecha     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX IF NOT EXISTS idx_integracion_fecha   ON integracion_log(fecha);
CREATE INDEX IF NOT EXISTS idx_integracion_sistema ON integracion_log(sistema, operacion);

-- ── 10. Deprecar centros en convenio ──────────────────────────
--
-- Todas las consultas son en el IPP-UPTAG. Los centros dejan de
-- aparecer en cualquier flujo (decisión #5). No se borran filas.

UPDATE medico SET activo = 0 WHERE tipo = 'centro';
