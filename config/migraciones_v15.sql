-- ============================================================
--  MIGRACIONES v15 — IPP-UPTAG
--  Ejecutar en phpMyAdmin sobre la BD ippuptag
--
--  Fix: carta_aval.id_beneficiario tenía DOS claves foráneas
--  idénticas hacia beneficiario: la generada por schema.sql
--  (carta_aval_ibfk_2) y la con nombre de migracion_p2
--  (fk_aval_beneficiario). Se conserva la que tiene nombre.
--  Idempotente: solo borra si existen ambas.
-- ============================================================

SET @dup := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'carta_aval'
      AND CONSTRAINT_NAME IN ('carta_aval_ibfk_2', 'fk_aval_beneficiario')
);
SET @sql := IF(@dup = 2,
    'ALTER TABLE carta_aval DROP FOREIGN KEY carta_aval_ibfk_2',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
