-- ============================================================
--  MIGRACIONES v14 — IPP-UPTAG
--  Ejecutar en phpMyAdmin sobre la BD ippuptag
--
--  Fix: la vista v_estado_agremiado quedó ROTA tras la migración
--  v12 (eliminó la tabla cuenta_web que la vista referenciaba).
--  Esto rompía mysqldump y cualquier SELECT sobre la vista.
--  Se recrea contra usuarios_registrados (la tabla que consolidó
--  las cuentas; el vínculo es username = agremiado.ci).
-- ============================================================

CREATE OR REPLACE VIEW v_estado_agremiado AS
SELECT
    a.id_agremiado,
    a.ci,
    a.nombre,
    a.apellido,
    a.activo                        AS agremiado_activo,
    a.fecha_agremiacion,
    (u.id_usuario IS NOT NULL)      AS tiene_cuenta_web,
    u.username,
    u.bloqueado,
    v.anio                          AS anio_vigencia,
    v.estado                        AS estado_vigencia,
    v.fecha_vencimiento,
    CASE
        WHEN a.activo = FALSE        THEN 'Inactivo'
        WHEN u.id_usuario IS NULL    THEN 'Sin cuenta web'
        WHEN u.bloqueado = TRUE      THEN 'Cuenta bloqueada'
        WHEN v.id_vigencia IS NULL   THEN 'Sin vigencia este año'
        WHEN v.estado = 'activa'     THEN 'Activo'
        WHEN v.estado = 'vencida'    THEN 'Vigencia vencida'
        ELSE 'Suspendido'
    END AS estado_general
FROM agremiado a
LEFT JOIN usuarios_registrados u ON u.username = a.ci
LEFT JOIN vigencia_anual v
    ON v.id_agremiado = a.id_agremiado AND v.anio = YEAR(CURDATE());
