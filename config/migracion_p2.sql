-- ============================================================
--  MIGRACIÓN PRIORIDAD 2 — Ejecutar sobre ippuptag
--  Índices de rendimiento + constraint afiliado + carta_aval
-- ============================================================

-- ── 1. Columna id_beneficiario en carta_aval (si no existe) ──
-- La vista usa id_beneficiario para filtrar por integrante familiar
ALTER TABLE `carta_aval`
  ADD COLUMN IF NOT EXISTS `id_beneficiario` INT DEFAULT NULL AFTER `id_afiliado`,
  ADD CONSTRAINT IF NOT EXISTS fk_aval_beneficiario
      FOREIGN KEY (`id_beneficiario`) REFERENCES `beneficiario`(`id_beneficiario`)
      ON DELETE SET NULL ON UPDATE CASCADE;

-- ── 2. Índices de rendimiento faltantes ──────────────────────
CREATE INDEX IF NOT EXISTS idx_solretiro_afil    ON solicitud_retiro(id_afiliado);
CREATE INDEX IF NOT EXISTS idx_solretiro_estado  ON solicitud_retiro(estado);
CREATE INDEX IF NOT EXISTS idx_solreg_ci         ON solicitud_registro(ci);
CREATE INDEX IF NOT EXISTS idx_solreg_estado     ON solicitud_registro(estado);
CREATE INDEX IF NOT EXISTS idx_usuarios_username ON usuarios_registrados(username);
CREATE INDEX IF NOT EXISTS idx_usuarios_afil     ON usuarios_registrados(id_afiliado);
CREATE INDEX IF NOT EXISTS idx_afiliado_agremiado ON afiliado(id_agremiado);
CREATE INDEX IF NOT EXISTS idx_vigencia_agremiado  ON vigencia_anual(id_agremiado);

-- ── 3. Check constraint: afiliados deben tener id_afiliado ───
-- MariaDB 10.4.3+ / MySQL 8.0.16+ soportan CHECK constraints.
-- Si tu versión no lo soporta, esta instrucción fallará sin consecuencias.
ALTER TABLE `usuarios_registrados`
  ADD CONSTRAINT IF NOT EXISTS chk_afiliado_vinculado
      CHECK (rol != 'afiliado' OR id_afiliado IS NOT NULL);

-- ── 4. Asegurar que vigencia_activa se re-evalúe al login ────
-- (No hay cambio de esquema aquí; el cambio fue en includes/auth.php)
-- Para afiliados con vigencia vencida, el campo vigencia_anual.estado
-- debe actualizarse. Este script marca como 'vencida' vigencias pasadas:
UPDATE vigencia_anual
   SET estado = 'vencida'
 WHERE estado = 'activa'
   AND fecha_vencimiento < CURDATE();
