-- ============================================================
--  MIGRACIONES v6 — Auto-creación de afiliado al registrarse
--  + soporte de adjuntos en reembolso
--  Ejecutar en phpMyAdmin sobre ippuptag
-- ============================================================

-- ── 1. Permitir cod_a NULL en afiliado ──────────────────────
-- Los nuevos afiliados que se registran solos no tienen código
-- institucional asignado todavía.
ALTER TABLE `afiliado`
  MODIFY `cod_a` VARCHAR(50) DEFAULT NULL;

-- ── 2. Vincular afiliado con su agremiado de origen ─────────
-- Permite saber de qué agremiado proviene cada afiliado.
ALTER TABLE `afiliado`
  ADD COLUMN IF NOT EXISTS `id_agremiado` INT(11) DEFAULT NULL AFTER `id_afiliado`;

-- ── 3. Campo de archivo adjunto en reembolso ────────────────
ALTER TABLE `reembolso`
  ADD COLUMN IF NOT EXISTS `archivo_adjunto` VARCHAR(255) DEFAULT NULL AFTER `descripcion`;

-- ── 4. Evitar beneficiarios duplicados por afiliado ─────────
-- (si ya lo agregaste en v4, este comando dará error que puedes ignorar)
ALTER TABLE `beneficiario`
  ADD UNIQUE KEY `uk_afiliado_ci` (`id_afiliado`, `ci`);

-- ── 5. Hacer numero_beneficiario auto-incrementable por afiliado ──
-- No es estrictamente necesario; el backend lo calculará.

-- ============================================================
-- LISTO. El backend (api/registro.php) se encarga del resto.
-- ============================================================
