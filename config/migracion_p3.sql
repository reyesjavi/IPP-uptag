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
  ADD COLUMN IF NOT EXISTS `activo`     TINYINT(1)   NOT NULL DEFAULT 1 AFTER `convenio`,
  ADD COLUMN IF NOT EXISTS `servicios`  TEXT         DEFAULT NULL AFTER `activo`;

-- ── 3. Datos iniciales: centros en convenio (migrando los estáticos) ──
INSERT IGNORE INTO `medico` (tipo, nombre, apellido, especialidad, numero_contacto, direccion, horario, convenio, activo)
VALUES
  ('centro','Policlínica','Los Llanos','Clínica General','0255-621-0000','Acarigua, Portuguesa','Lun–Dom 24 horas','Convenio Full',1),
  ('centro','Farmacia','UPTAG','Farmacia','0255-621-0010','Campus UPTAG, Acarigua','Lun–Sáb 7am–7pm','Descuento 30%',1),
  ('centro','Centro Odontológico','UPTAG','Dental','0255-621-0020','Campus UPTAG','Lun–Vie 8am–4pm','Plan incluido',1),
  ('centro','Clínica','Guanare','Clínica General','0257-251-0000','Guanare, Portuguesa','Lun–Vie 7am–5pm','Convenio Parcial',1);

-- ── 4. Directorio de médicos especialistas en convenio ───────
--    Contacto centralizado IPP para citas: 0268-2527955 / 0414-6823175 (WhatsApp)
INSERT IGNORE INTO `medico` (tipo, nombre, apellido, especialidad, numero_contacto, horario, servicios, activo)
VALUES
  ('medico','Dolinda','Barbera','Cardiología','0268-2527955 / 0414-6823175','Martes de 11:00 a 12:00 m.','Valoración cardiovascular, electrocardiograma, Holter de presión arterial',1),
  ('medico','Anniany','Acosta','Cirugía General','0268-2527955 / 0414-6823175','Lunes a jueves a partir de 9:00 am','Trombectomía por hemorroides trombosadas, drenaje de abscesos, excéresis de verrugas, lipomas y quiste sebáceo',1),
  ('medico','Víctor','Gutiérrez','Oftalmología','0268-2527955 / 0414-6823175','Una vez al mes, previo acuerdo','Cirugía de cataratas, córnea, queratocono y refractiva',1),
  ('medico','Gunner','Oviol','Otorrinolaringología','0268-2527955 / 0414-6823175','Lunes a viernes, 10:00 am a 12:00 m.','Valoración y tratamiento de oído, nariz, laringe y garganta',1),
  ('medico','Elena','Alviarez','Neurología','0268-2527955 / 0414-6823175','Lunes a viernes, 10:00 am a 12:00 m.','Consulta y tratamiento de trastornos del cerebro, sistema nervioso y enfermedades cardiovasculares',1),
  ('medico','Ángel','Laguna','Neumonología','0268-2527955 / 0414-6823175','Lunes, miércoles y viernes, a partir de 3:00 pm','Consulta, valoración neumonológica y espirometría',1),
  ('medico','José','Guarapana','Neurocirugía','0268-2527955 / 0414-6823175','Lunes a viernes, según disponibilidad','Consulta, tratamiento, valoración y cirugía de trastornos del sistema nervioso central y columna vertebral',1);
