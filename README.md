# IPP-UPTAG — Portal de Servicios del Profesorado

Sistema de gestión de beneficios sociales para el **Instituto de Previsión del Profesorado** de la Universidad Politécnica Territorial Alonso Gamero (UPTAG), Venezuela.

---

## Funcionalidades

- **Afiliados**: dashboard personal, solicitudes de reembolso médico, cartas aval, directorio médico
- **Caja de ahorros**: movimientos, saldo, solicitudes de retiro
- **Administración**: gestión de afiliados, reembolsos, avales, reportes CSV, directorio médico (CRUD)
- **Seguridad**: autenticación con TOTP 2FA, control de roles (admin / administrativo / afiliado), vigencia anual, protección CSRF
- **Recuperación de contraseña**: vía SMTP (PHPMailer)
- **Deploy automatizado**: GitHub Actions → rsync → VPS con PHP-FPM

---

## Stack técnico

| Capa        | Tecnología                              |
|-------------|------------------------------------------|
| Lenguaje    | PHP 8.2                                  |
| Base de datos | MariaDB 10.6 / MySQL 8.x (PDO)        |
| Servidor web | Nginx + PHP 8.2-FPM (producción)       |
| Dev local   | XAMPP (Apache + PHP 8.2 + MySQL)        |
| Email       | PHPMailer 6.9.3 (SMTP)                  |
| 2FA         | TOTP RFC 6238 (Google Authenticator, Aegis, Authy) |
| CI/CD       | GitHub Actions (sintaxis + rsync deploy) |
| Sin framework | Sin Composer (PHPMailer instalado manualmente) |

---

## Estructura del proyecto

```
uptag_v8.2/
├── admin/              # Panel administrativo (dashboard, afiliados, reembolsos, avales, reportes, 2FA)
├── assets/             # CSS, JS, imágenes
├── config/             # Configuración (base.php, database.php, env.php, schema.sql, migraciones)
├── controllers/        # Lógica de negocio (SaludController, FinanzasController)
├── docs/               # Documentación técnica (vps-setup.md)
├── includes/           # Auth, header/footer compartidos
├── lib/                # PHPMailer (manual), TOTP
├── models/             # Acceso a datos (Model base, ReembolsoModel, FinanzasModel, MedicoModel)
├── scripts/            # backup.sh, reparar_afiliados.php
├── views/              # Plantillas de vistas (salud/, finanzas/)
├── .env.example        # Plantilla de variables de entorno
├── .github/workflows/  # CI/CD pipeline
├── DEPLOY.md           # Guía de despliegue
└── login.php           # Punto de entrada de autenticación
```

---

## Configuración local (XAMPP)

### 1. Clonar el repositorio

```bash
git clone https://github.com/reyesjavi/IPP-uptag C:/xampp/htdocs/uptag_v8.2
```

### 2. Crear el archivo .env

```bash
cp .env.example .env
```

Editar `.env` con las credenciales locales:

```dotenv
DB_HOST=localhost
DB_NAME=ippuptag
DB_USER=root
DB_PASS=
APP_ENV=development
UPLOAD_PATH=
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=tu@email.com
MAIL_PASS=app_password_aqui
```

### 3. Crear la base de datos

```sql
CREATE DATABASE ippuptag CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -u root ippuptag < config/schema.sql
mysql -u root ippuptag < config/migracion_p2.sql
mysql -u root ippuptag < config/migracion_p3.sql
```

### 4. Acceder a la aplicación

Abrir `http://localhost/uptag_v8.2/`

---

## Variables de entorno

Ver `.env.example` para la lista completa. Las variables críticas:

| Variable      | Descripción                                      | Valor dev por defecto |
|---------------|--------------------------------------------------|-----------------------|
| `DB_HOST`     | Host de la base de datos                         | `localhost`           |
| `DB_NAME`     | Nombre de la base de datos                       | `ippuptag`            |
| `DB_USER`     | Usuario de la BD                                 | `root`                |
| `DB_PASS`     | Contraseña de la BD                              | _(vacío)_             |
| `APP_ENV`     | Entorno (`development` / `production`)           | `development`         |
| `UPLOAD_PATH` | Ruta de archivos subidos (vacío = dentro del webroot) | _(vacío)_        |
| `MAIL_*`      | Configuración SMTP para recuperación de contraseña | —                  |

---

## Despliegue en producción

Ver [`DEPLOY.md`](DEPLOY.md) para instrucciones completas.

Resumen:
1. Configurar secrets en GitHub (`SSH_HOST`, `SSH_USER`, `SSH_KEY`, `DEPLOY_PATH`)
2. Configurar el VPS según [`docs/vps-setup.md`](docs/vps-setup.md)
3. Crear `.env` en el VPS con credenciales de producción
4. Hacer push a `main` — el pipeline despliega automáticamente

---

## Seguridad

- Credenciales exclusivamente en `.env` (no rastreado por git)
- `config/database.php` no está en el repositorio
- Archivos subidos servidos únicamente a través de `ver_archivo.php` (valida sesión y pertenencia)
- 2FA TOTP opcional por usuario administrador
- CSRF en todos los formularios POST
- Protección anti fuerza bruta: bloqueo tras 3 intentos fallidos
- HTTPS forzado en `APP_ENV=production`
