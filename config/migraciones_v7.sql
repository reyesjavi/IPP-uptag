-- ============================================================
--  MIGRACIONES v7 — Fix AUTO_INCREMENT en afiliado y beneficiario
--  Ejecutar en phpMyAdmin sobre ippuptag
-- ============================================================
--
-- BUG: afiliado.id_afiliado y beneficiario.id_beneficiario no
-- tenían AUTO_INCREMENT. Al insertar sin especificar el ID,
-- MySQL usaba 0 como valor por defecto. El primer registro creaba
-- id=0; el segundo fallaba con "Duplicate entry '0' for key PRIMARY".
--

ALTER TABLE `afiliado`
  MODIFY COLUMN `id_afiliado` INT NOT NULL AUTO_INCREMENT;

ALTER TABLE `beneficiario`
  MODIFY COLUMN `id_beneficiario` INT NOT NULL AUTO_INCREMENT;

-- ── Limpieza: registros con id=0 creados por el bug ──────────
-- Ejecutar ANTES de los ALTER si hay FK constraints activas.
-- Si los DELETE fallan por FK, usar:
--   SET FOREIGN_KEY_CHECKS = 0; ... DELETE ... SET FOREIGN_KEY_CHECKS = 1;
DELETE FROM `beneficiario` WHERE `id_beneficiario` = 0;
DELETE FROM `afiliado`     WHERE `id_afiliado`     = 0;
