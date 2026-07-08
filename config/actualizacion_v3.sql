-- ============================================================
--  ACTUALIZACIÓN v3: Roles y logs
--  Ejecutar en phpMyAdmin sobre la base de datos ippuptag
-- ============================================================

-- 1. Tabla de logs de actividad
CREATE TABLE IF NOT EXISTS log_actividad (
    id_log    INT          PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT,
    accion    VARCHAR(60)  NOT NULL,
    detalle   VARCHAR(255),
    ip        VARCHAR(45),
    fecha     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios_registrados(id_usuario)
        ON DELETE SET NULL ON UPDATE CASCADE
);

-- 2. Agregar rol 'admin' y 'administrativo' al campo rol si aún no existe
-- (En MySQL el campo ya es VARCHAR, no ENUM, así que no requiere ALTER)

-- 3. Usuario Administrador (tú)
--    Contraseña: definida de forma privada (no se documenta en el repositorio).
--    Regenera el hash con: php -r "echo password_hash('TU_CLAVE', PASSWORD_BCRYPT);"
INSERT INTO usuarios_registrados (username, password_hash, rol, activo, id_afiliado, cod_a)
VALUES (
  'admin',
  '$2y$10$TKh8H1.PfQx37YgCFsvQ3.T4S4y8VZvY2Gq9U9rPhPRhV8A.LcLsS',
  'admin',
  1,
  NULL,
  'UPTAG-2019'
)
ON DUPLICATE KEY UPDATE rol='admin';

-- 4. Usuario Personal Administrativo de prueba
--    Contraseña: definida de forma privada (no se documenta en el repositorio).
INSERT INTO usuarios_registrados (username, password_hash, rol, activo, id_afiliado, cod_a)
VALUES (
  'administrativo',
  '$2y$10$TKh8H1.PfQx37YgCFsvQ3.T4S4y8VZvY2Gq9U9rPhPRhV8A.LcLsS',
  'administrativo',
  1,
  NULL,
  'UPTAG-2019'
)
ON DUPLICATE KEY UPDATE rol='administrativo';

-- NOTA: Si el hash no funciona, regenéralo con tu propia contraseña:
-- php -r "echo password_hash('TU_CLAVE', PASSWORD_BCRYPT);"
-- Y reemplaza el valor en los INSERT anteriores.
