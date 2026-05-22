-- ============================================================
--  TABLAS COMPLEMENTARIAS PARA EL PORTAL PHP
--  Agregar al script uptag_base_datos.sql
-- ============================================================

-- Tabla de movimientos de caja de ahorros
CREATE TABLE IF NOT EXISTS MOVIMIENTO_CUENTA (
    id_movimiento  INT           PRIMARY KEY AUTO_INCREMENT,
    fecha          DATE          NOT NULL DEFAULT (CURRENT_DATE),
    concepto       VARCHAR(200)  NOT NULL,
    tipo           ENUM('credito','debito') NOT NULL,
    monto          DECIMAL(12,2) NOT NULL,
    saldo_despues  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    id_afiliado    INT           NOT NULL,
    FOREIGN KEY (id_afiliado) REFERENCES AFILIADO(id_afiliado)
        ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE INDEX idx_mov_afiliado ON MOVIMIENTO_CUENTA(id_afiliado);
CREATE INDEX idx_mov_fecha    ON MOVIMIENTO_CUENTA(fecha);


-- ============================================================
--  DATOS DE PRUEBA COMPLETOS
--  (ejecutar solo en ambiente de desarrollo)
-- ============================================================

-- Institución raíz
INSERT IGNORE INTO IPP_LUTAG (cod_a, id) VALUES ('UPTAG-2019', 1);

-- Plan médico
INSERT IGNORE INTO PLAN_MEDICO (cod_pm, costo, id_plan, cod_a)
VALUES ('PM-0042', 1800.00, 42, 'UPTAG-2019');

-- Servicios del plan
INSERT IGNORE INTO SERVICIO (id_servicio, tipo_servicio, cod_pm) VALUES
(1, 'Ambulatorio',   'PM-0042'),
(2, 'Hospitalario',  'PM-0042'),
(3, 'Dental',        'PM-0042'),
(4, 'Farmacia',      'PM-0042');

-- Afiliado de prueba
INSERT IGNORE INTO AFILIADO
  (id_afiliado, nombre, apellido, ci, fecha_nacimiento, correo, telefono, cod_a, cod_pm)
VALUES
  (1, 'José', 'Ramírez', 'V-12345678', '1985-03-15',
   'j.ramirez@uptag.edu.ve', '0414-555-0000', 'UPTAG-2019', 'PM-0042');

-- Médico de prueba
INSERT IGNORE INTO MEDICO
  (id_medico, nombre, apellido, especialidad, cedula, numero_contacto, id_servicio)
VALUES
  (1, 'Carlos', 'González', 'Cardiología',   'V-9876543',  '0255-621-0001', 1),
  (2, 'María',  'Ortega',   'Pediatría',     'V-8765432',  '0426-333-0002', 1),
  (3, 'Luis',   'Páez',     'Traumatología', 'V-7654321',  '0255-621-0003', 2);

-- Beneficiarios del afiliado
INSERT IGNORE INTO BENEFICIARIO
  (id_beneficiario, numero_beneficiario, ci, nombre, apellido, fecha_nacimiento, parentesco, id_afiliado)
VALUES
  (1, 1, 'V-14222333', 'Ana',    'Ramírez', '1987-06-20', 'Cónyuge', 1),
  (2, 2, NULL,         'Carlos', 'Ramírez', '2012-09-10', 'Hijo',    1),
  (3, 3, NULL,         'Laura',  'Ramírez', '2015-02-28', 'Hija',    1);

-- Usuario del portal (contraseña: uptag2026)
-- Para generar el hash en PHP: echo password_hash('uptag2026', PASSWORD_BCRYPT);
INSERT IGNORE INTO USUARIOS_REGISTRADOS
  (username, password_hash, rol, id_afiliado, cod_a)
VALUES
  ('V-12345678',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'afiliado', 1, 'UPTAG-2019');
-- NOTA: El hash de arriba corresponde a la contraseña "password"
-- Cambia la contraseña ejecutando este PHP una vez:
-- echo password_hash('uptag2026', PASSWORD_BCRYPT);

-- Movimientos de caja de ahorros de ejemplo
INSERT INTO MOVIMIENTO_CUENTA (fecha, concepto, tipo, monto, saldo_despues, id_afiliado) VALUES
('2026-05-01', 'Aporte patronal Mayo',   'credito', 4150.00, 84200.00, 1),
('2026-05-01', 'Aporte individual Mayo', 'credito', 2075.00, 80050.00, 1),
('2026-04-15', 'Intereses trimestrales', 'credito', 1240.00, 77975.00, 1),
('2026-04-01', 'Aporte patronal Abril',  'credito', 4150.00, 76735.00, 1),
('2026-03-10', 'Retiro parcial aprobado','debito',  5000.00, 72585.00, 1);

-- Reembolso de ejemplo
INSERT INTO REEMBOLSO
  (tipo_servicio, fecha_atencion, monto_solicitado, centro_medico, estado, id_afiliado)
VALUES
  ('Medicamentos', '2026-05-10', 850.00, 'Farmacia UPTAG', 'en_revision', 1),
  ('Consulta médica', '2026-05-02', 400.00, 'Policlínica Los Llanos', 'pendiente', 1),
  ('Exámenes', '2026-04-15', 1200.00, 'Lab. Clínico Central', 'aprobado', 1);

-- Carta aval de ejemplo
INSERT INTO CARTA_AVAL
  (medico_tratante, especialidad, centro_medico, procedimiento, monto_estimado, estado, id_afiliado)
VALUES
  ('Dr. Carlos González', 'Cardiología', 'Policlínica Los Llanos', 'Consulta cardiológica', 1200.00, 'aprobada', 1);
