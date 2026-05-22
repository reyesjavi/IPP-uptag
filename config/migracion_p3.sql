-- ============================================================
--  MIGRACIÓN PRIORIDAD 3 — Ejecutar sobre ippuptag
--  TOTP 2FA + extensión tabla medico
-- ============================================================

-- ── 1. TOTP en usuarios_registrados ──────────────────────────
ALTER TABLE `usuarios_registrados`
  ADD COLUMN IF NOT EXISTS `totp_secret`      VARCHAR(32)  DEFAULT NULL     AFTER `bloqueado`,
  ADD COLUMN IF NOT EXISTS `totp_habilitado`  TINYINT(1)   NOT NULL DEFAULT 0 AFTER `totp_secret`;

-- ── 2. Extender tabla medico para centros en convenio ────────
ALTER TABLE `medico`
  ADD COLUMN IF NOT EXISTS `tipo`       ENUM('medico','centro') NOT NULL DEFAULT 'medico' AFTER `id_medico`,
  ADD COLUMN IF NOT EXISTS `horario`    VARCHAR(100) DEFAULT NULL AFTER `direccion`,
  ADD COLUMN IF NOT EXISTS `convenio`   VARCHAR(80)  DEFAULT NULL AFTER `horario`,
  ADD COLUMN IF NOT EXISTS `activo`     TINYINT(1)   NOT NULL DEFAULT 1 AFTER `convenio`;

-- ── 3. Datos iniciales: centros en convenio (migrando los estáticos) ──
INSERT IGNORE INTO `medico` (tipo, nombre, apellido, especialidad, numero_contacto, direccion, horario, convenio, activo)
VALUES
  ('centro','Policlínica','Los Llanos','Clínica General','0255-621-0000','Acarigua, Portuguesa','Lun–Dom 24 horas','Convenio Full',1),
  ('centro','Farmacia','UPTAG','Farmacia','0255-621-0010','Campus UPTAG, Acarigua','Lun–Sáb 7am–7pm','Descuento 30%',1),
  ('centro','Centro Odontológico','UPTAG','Dental','0255-621-0020','Campus UPTAG','Lun–Vie 8am–4pm','Plan incluido',1),
  ('centro','Clínica','Guanare','Clínica General','0257-251-0000','Guanare, Portuguesa','Lun–Vie 7am–5pm','Convenio Parcial',1);

-- ── 4. Datos de prueba: médicos especialistas ────────────────
INSERT IGNORE INTO `medico` (tipo, nombre, apellido, especialidad, cedula, numero_contacto, activo)
VALUES
  ('medico','Carlos','González','Cardiología','V-9876543','0255-621-0001',1),
  ('medico','María','Ortega','Pediatría','V-8765432','0426-333-0002',1),
  ('medico','Luis','Páez','Traumatología','V-7654321','0255-621-0003',1);
