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

    # Seguridad: ocultar archivos sensibles
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

    # PHP-FPM
    location ~ \.php$ {
        include        fastcgi_params;
        fastcgi_pass   unix:/run/php/php8.2-fpm.sock;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    # Archivos estáticos con caché
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
```

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

## 9. Fail2ban (protección SSH + Nginx)

Crear `/etc/fail2ban/jail.local`:

```ini
[DEFAULT]
bantime  = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true

[nginx-http-auth]
enabled = true
```

```bash
sudo systemctl enable --now fail2ban
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
