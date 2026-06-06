# Guía de configuración VPS — IPP-UPTAG

Sistema operativo objetivo: **Ubuntu 22.04 LTS**  
Stack: Nginx + PHP 8.2-FPM + MariaDB 10.6

---

## 1. Preparación del servidor

```bash
# Actualizar el sistema
sudo apt update && sudo apt upgrade -y

# Instalar dependencias base
sudo apt install -y curl git unzip ufw fail2ban

# Crear usuario de deploy (sin shell interactiva)
sudo useradd -m -s /usr/sbin/nologin deploy
sudo mkdir -p /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
```

---

## 2. Nginx

```bash
sudo apt install -y nginx

# Verificar que arranca
sudo systemctl enable --now nginx
```

### Rate limiting — agregar al bloque `http` de `/etc/nginx/nginx.conf`

Abrir `/etc/nginx/nginx.conf` y dentro del bloque `http { ... }` añadir:

```nginx
# Zonas de rate limiting (se definen UNA sola vez en el bloque http)
# Zona general: 20 req/s por IP (burst 40) — protege contra floods
limit_req_zone $binary_remote_addr zone=ipp_general:10m rate=20r/s;

# Zona de autenticación: 10 req/min por IP — login, registro, recuperación
limit_req_zone $binary_remote_addr zone=ipp_auth:10m    rate=10r/m;

# Respuesta estándar al superar el límite
limit_req_status 429;
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### Configuración del virtual host

Crear `/etc/nginx/sites-available/ippuptag`:

```nginx
server {
    listen 80;
    server_name tu-dominio.edu.ve www.tu-dominio.edu.ve;

    # Redirigir todo HTTP a HTTPS (Certbot lo ajusta automáticamente)
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tu-dominio.edu.ve www.tu-dominio.edu.ve;

    root /var/www/ippuptag;
    index index.php;

    # Certificado SSL (Certbot rellena esto)
    ssl_certificate     /etc/letsencrypt/live/tu-dominio.edu.ve/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tu-dominio.edu.ve/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;

    # ── Rate limiting general (todas las páginas PHP) ────────────
    # 20 req/s con margen de 40 peticiones en ráfaga
    limit_req zone=ipp_general burst=40 nodelay;

    # ── Seguridad: ocultar archivos sensibles ────────────────────
    location ~ /\.(env|git|htaccess) {
        deny all;
        return 404;
    }
    location ~ ^/config/ {
        deny all;
        return 404;
    }
    location ~ ^/scripts/ {
        deny all;
        return 404;
    }

    # ── Uploads: NUNCA ejecutar PHP en esta carpeta ──────────────
    # Defensa en profundidad: el .htaccess de uploads/ sólo lo respeta
    # Apache, no Nginx. Lo ideal es que UPLOAD_PATH apunte FUERA del
    # webroot (ver sección 5). Este bloque cubre el caso en que la
    # carpeta uploads/ quede dentro de la raíz del sitio.
    # Los adjuntos sensibles se sirven con control de acceso por sesión
    # vía ver_archivo.php; evita enlaces directos a /uploads/.
    location ^~ /uploads/ {
        location ~ \.(php|phtml|phar|pl|py|cgi|sh)$ {
            deny all;
            return 403;
        }
    }

    # ── Endpoints de autenticación: límite estricto ──────────────
    # 10 req/min + burst de 5; aplica ADEMÁS del límite general
    location ~ ^/(login|registro|recuperar_password|cambiar_password|api/registro)\.php$ {
        limit_req zone=ipp_auth burst=5 nodelay;

        include        fastcgi_params;
        fastcgi_pass   unix:/run/php/php8.2-fpm.sock;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    # ── PHP-FPM (resto de páginas) ───────────────────────────────
    location ~ \.php$ {
        include        fastcgi_params;
        fastcgi_pass   unix:/run/php/php8.2-fpm.sock;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    # ── Archivos estáticos con caché (exentos de rate limit) ─────
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ =404;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/ippuptag /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 3. PHP 8.2-FPM

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-fileinfo \
                   php8.2-xml php8.2-curl php8.2-zip php8.2-intl

sudo systemctl enable --now php8.2-fpm
```

### Configuración del pool

Editar `/etc/php/8.2/fpm/pool.d/www.conf`:

```ini
user  = deploy
group = deploy

; Ajustar según RAM disponible (2 GB → 10–20 workers)
pm                   = dynamic
pm.max_children      = 20
pm.start_servers     = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 8
```

### php.ini de producción

Editar `/etc/php/8.2/fpm/php.ini`:

```ini
display_errors       = Off
log_errors           = On
error_log            = /var/log/php8.2-fpm.log
expose_php           = Off
upload_max_filesize  = 5M
post_max_size        = 6M
max_execution_time   = 30
session.cookie_httponly = 1
session.cookie_secure   = 1
session.use_strict_mode = 1
```

```bash
sudo systemctl restart php8.2-fpm
```

---

## 4. MariaDB

```bash
sudo apt install -y mariadb-server
sudo systemctl enable --now mariadb
sudo mariadb-secure-installation  # responder Y a todo
```

### Crear base de datos y usuario

```sql
-- Ejecutar como root de MariaDB
CREATE DATABASE ippuptag CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ipp_app'@'localhost' IDENTIFIED BY 'contraseña_fuerte_aqui';
GRANT ALL PRIVILEGES ON ippuptag.* TO 'ipp_app'@'localhost';
FLUSH PRIVILEGES;
```

### Importar esquema

```bash
mysql -u root -p ippuptag < /var/www/ippuptag/config/schema.sql
mysql -u root -p ippuptag < /var/www/ippuptag/config/migracion_p2.sql
mysql -u root -p ippuptag < /var/www/ippuptag/config/migracion_p3.sql

# Aplicar el resto de migraciones en orden de versión (incluye v12:
# verificación de correo, bloqueo temporal y eliminación de cuenta_web)
for f in $(ls /var/www/ippuptag/config/migraciones_v*.sql | sort -V); do
    echo "Aplicando $f"
    mysql -u root -p ippuptag < "$f"
done
```

> **Nota de seguridad:** define `UPLOAD_PATH` en `.env` apuntando FUERA del
> webroot (p. ej. `/var/ipp-uploads/reembolsos`, ver sección 5). Los adjuntos
> sólo deben servirse a través de `ver_archivo.php`, que valida sesión y
> propiedad del archivo.

---

## 5. Desplegar el proyecto

```bash
# Crear directorio web
sudo mkdir -p /var/www/ippuptag
sudo chown deploy:deploy /var/www/ippuptag

# Directorio de uploads FUERA del webroot
sudo mkdir -p /var/ipp-uploads/reembolsos
sudo chown -R deploy:www-data /var/ipp-uploads
sudo chmod -R 750 /var/ipp-uploads

# Directorio de backups
sudo mkdir -p /var/backups/ippuptag/{db,uploads}
sudo chown -R deploy:deploy /var/backups/ippuptag
```

El código se desplegará automáticamente vía GitHub Actions (rsync) en cada push a `main`.  
Para el primer despliegue manual ver `DEPLOY.md`.

---

## 6. Archivo .env en el VPS

Crear `/var/www/ippuptag/.env` (permisos 640, propietario deploy:www-data):

```bash
sudo nano /var/www/ippuptag/.env
```

```dotenv
DB_HOST=localhost
DB_NAME=ippuptag
DB_USER=ipp_app
DB_PASS=contraseña_fuerte_aqui
APP_ENV=production
UPLOAD_PATH=/var/ipp-uploads/reembolsos
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=noreply@uptag.edu.ve
MAIL_PASS=contraseña_smtp_aqui
MAIL_FROM_NAME=IPP UPTAG
```

```bash
sudo chmod 640 /var/www/ippuptag/.env
sudo chown deploy:www-data /var/www/ippuptag/.env
```

---

## 7. SSL con Certbot (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tu-dominio.edu.ve -d www.tu-dominio.edu.ve
```

La renovación automática ya está configurada por `certbot` via systemd timer.

---

## 8. Firewall (UFW)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp      # SSH (considera cambiar el puerto)
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable
```

---

## 9. Fail2ban (protección SSH + Nginx + aplicación web)

### Filtro personalizado para rate limiting HTTP

Crear `/etc/fail2ban/filter.d/nginx-req-limit.conf`:

```ini
[Definition]
# Banear IPs que reciban muchos 429 (rate limit superado) en Nginx
failregex = ^<HOST> .* "(GET|POST|HEAD) .* HTTP/[0-9.]+" 429
ignoreregex =
```

### Filtro para ataques a endpoints de autenticación

Crear `/etc/fail2ban/filter.d/ipp-auth.conf`:

```ini
[Definition]
# Detectar múltiples errores 4xx en endpoints de login/registro
failregex = ^<HOST> .* "(POST|GET) /(login|registro|recuperar_password|api/registro)\.php .* HTTP/[0-9.]+" 4[0-9]{2}
ignoreregex = ^<HOST> .* HTTP/[0-9.]+" 200
```

### Configuración de jails

Crear `/etc/fail2ban/jail.local`:

```ini
[DEFAULT]
bantime  = 3600   ; ban de 1 hora por defecto
findtime = 600    ; ventana de 10 minutos
maxretry = 5

[sshd]
enabled = true

[nginx-http-auth]
enabled = true

# Banear IPs que disparen rate limit (429) más de 20 veces en 5 minutos
[nginx-req-limit]
enabled   = true
filter    = nginx-req-limit
logpath   = /var/log/nginx/access.log
maxretry  = 20
findtime  = 300
bantime   = 7200  ; ban de 2 horas

# Banear IPs que ataquen endpoints de auth repetidamente
[ipp-auth]
enabled   = true
filter    = ipp-auth
logpath   = /var/log/nginx/access.log
maxretry  = 15
findtime  = 300
bantime   = 86400 ; ban de 24 horas para ataques de auth
```

```bash
sudo systemctl enable --now fail2ban

# Verificar que los jails están activos
sudo fail2ban-client status
sudo fail2ban-client status nginx-req-limit
sudo fail2ban-client status ipp-auth
```

---

## 10. Cron de backup automático

```bash
sudo crontab -u deploy -e
```

Añadir:

```cron
# Backup diario a las 2:00 AM
0 2 * * * /var/www/ippuptag/scripts/backup.sh >> /var/log/ipp-backup.log 2>&1
```

---

## 11. Permisos de sudo para deploy (recarga PHP-FPM)

Crear `/etc/sudoers.d/ipp-deploy`:

```
deploy ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.2-fpm
```

```bash
sudo chmod 440 /etc/sudoers.d/ipp-deploy
```

---

## 12. Verificación final

```bash
# PHP-FPM escucha
sudo systemctl status php8.2-fpm

# Nginx sin errores
sudo nginx -t

# Conexión a MariaDB
mysql -u ipp_app -p ippuptag -e "SELECT COUNT(*) FROM usuarios_registrados;"

# Verificar que .env NO es accesible desde el exterior
curl -I https://tu-dominio.edu.ve/.env   # debe devolver 404
curl -I https://tu-dominio.edu.ve/config/database.php  # debe devolver 404
```
