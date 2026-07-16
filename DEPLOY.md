# Guía de Despliegue — IPP-UPTAG

Este documento cubre el despliegue automatizado vía GitHub Actions y el primer despliegue manual.  
Para la configuración inicial del servidor ver [`docs/vps-setup.md`](docs/vps-setup.md).

---

## Despliegues continuos (GitHub Actions)

Todo push a la rama `main` ejecuta automáticamente:

1. **Job `ci`** — Verificación de sintaxis PHP + controles de seguridad (no credentials hardcoded, `.env` no rastreado)
2. **Job `deploy`** (solo si `ci` pasa) — rsync al VPS + permisos + recarga PHP-FPM

El pipeline está definido en `.github/workflows/ci.yml`.

---

## Configurar los Secrets de GitHub

En el repositorio: **Settings → Secrets and variables → Actions → New repository secret**

| Secret       | Descripción                                              | Ejemplo                        |
|--------------|----------------------------------------------------------|--------------------------------|
| `SSH_HOST`   | IP o dominio del VPS                                     | `203.0.113.10`                 |
| `SSH_USER`   | Usuario SSH con acceso al directorio web                 | `deploy`                       |
| `SSH_KEY`    | Clave privada SSH (contenido de `~/.ssh/id_ed25519`)     | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `SSH_PORT`   | Puerto SSH (opcional, por defecto 22)                    | `22`                           |
| `DEPLOY_PATH`| Ruta absoluta del proyecto en el VPS                     | `/var/www/ippuptag`            |

### Generar clave SSH de deploy

```bash
# En tu máquina local
ssh-keygen -t ed25519 -C "github-actions-ipp" -f ~/.ssh/ipp_deploy

# Copiar la clave pública al VPS
ssh-copy-id -i ~/.ssh/ipp_deploy.pub deploy@tu-servidor.edu.ve

# El contenido de ipp_deploy (clave PRIVADA) va en el secret SSH_KEY
cat ~/.ssh/ipp_deploy
```

---

## Primer despliegue manual

Realizar solo una vez, antes de activar el pipeline automático.

### 1. Clonar el repositorio en el VPS

```bash
# Como usuario deploy en el VPS
cd /var/www
git clone https://github.com/reyesjavi/IPP-uptag ippuptag
cd ippuptag
```

### 2. Crear el archivo .env

```bash
cp .env.example .env
nano .env   # Rellenar con credenciales reales de producción
chmod 640 .env
chown deploy:www-data .env
```

### 3. Crear config/database.php (si existe en el repositorio como ejemplo)

El archivo `config/database.php` real **no está en el repositorio**. Lo genera automáticamente el sistema leyendo `.env` vía `config/env.php`. No hace falta crearlo manualmente.

### 4. Importar la base de datos

```bash
# Crear la BD y el usuario (como root de MariaDB)
mysql -u root -p << 'EOF'
CREATE DATABASE IF NOT EXISTS ippuptag CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ipp_app'@'localhost' IDENTIFIED BY 'contraseña_fuerte_aqui';
GRANT ALL PRIVILEGES ON ippuptag.* TO 'ipp_app'@'localhost';
FLUSH PRIVILEGES;
EOF

# Importar esquema y migraciones
mysql -u root -p ippuptag < config/schema.sql
mysql -u root -p ippuptag < config/migracion_p2.sql
mysql -u root -p ippuptag < config/migracion_p3.sql
mysql -u root -p ippuptag < config/migraciones_v10.sql
mysql -u root -p ippuptag < config/migraciones_v11.sql
mysql -u root -p ippuptag < config/migraciones_v12.sql
mysql -u root -p ippuptag < config/migraciones_v13.sql
mysql -u root -p ippuptag < config/migraciones_v14.sql
```

### 5. Crear directorio de uploads

```bash
sudo mkdir -p /var/ipp-uploads/reembolsos
sudo chown -R deploy:www-data /var/ipp-uploads
sudo chmod -R 750 /var/ipp-uploads
```

Asegurarse de que `UPLOAD_PATH=/var/ipp-uploads/reembolsos` esté en `.env`.

### 6. Permisos iniciales

```bash
find /var/www/ippuptag -type f -name "*.php" -exec chmod 644 {} \;
find /var/www/ippuptag -type d -exec chmod 755 {} \;
chmod 750 /var/www/ippuptag/config/
```

### 7. Verificar la instalación

```bash
# Sintaxis PHP
find /var/www/ippuptag -name "*.php" -not -path "*/lib/phpmailer/*" \
  | xargs -I{} php -l {} | grep -v "No syntax errors"

# Conexión a BD
php -r "require '/var/www/ippuptag/config/database.php'; getDB(); echo 'BD OK\n';"

# Aplicación accesible
curl -sI https://tu-dominio.edu.ve | head -5
```

---

## Checklist de primer despliegue

- [ ] VPS configurado según `docs/vps-setup.md`
- [ ] Secrets de GitHub configurados (`SSH_HOST`, `SSH_USER`, `SSH_KEY`, `SSH_PORT`, `DEPLOY_PATH`)
- [ ] `.env` creado en el VPS con credenciales de producción
- [ ] Base de datos creada e importada (`schema.sql` + migraciones)
- [ ] Directorio `UPLOAD_PATH` creado fuera del webroot
- [ ] Nginx apunta al directorio correcto
- [ ] SSL activo (Certbot)
- [ ] Primer push a `main` ejecuta el pipeline sin errores
- [ ] Backup automático configurado en cron
- [ ] Acceso a `.env` desde el exterior devuelve 404

---

## Rollback

Si el deploy rompe algo, revertir el último commit y hacer push:

```bash
git revert HEAD --no-edit
git push origin main
```

El pipeline se activará y desplegará la versión anterior automáticamente.

Para rollback de base de datos, restaurar el backup más reciente:

```bash
# En el VPS
gunzip -c /var/backups/ippuptag/db/ippuptag_YYYYMMDD_HHMMSS.sql.gz \
  | mysql -u root -p ippuptag
```
