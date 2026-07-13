# IPP-UPTAG · Portal de Bienestar Institucional

Portal web **full-stack** para la gestión de los beneficios de bienestar social del profesorado universitario, desarrollado para el **Instituto de Previsión del Profesorado (IPP)** de la Universidad Politécnica Territorial Alonso Gamero (UPTAG), Venezuela.

> 🎓 **Proyecto de grado académico**, desarrollado en solitario. La base de datos incluida contiene **datos de ejemplo/ficticios** con fines demostrativos; no corresponde a información real de personas.

---

## 🧩 El problema que resuelve

La gestión de los beneficios del profesorado (reembolsos médicos, cartas aval, directorio de médicos, vigencia anual de afiliación) se llevaba de forma manual y dispersa, lo que generaba demoras, pérdida de expedientes y falta de trazabilidad.

Este portal **centraliza y digitaliza** ese proceso en una sola plataforma:

- El **profesor afiliado** consulta sus beneficios, agenda citas con especialistas del IPP, ve las consultas restantes de su plan, gestiona su carga familiar, solicita reembolsos y avales, y accede al directorio médico desde un panel personal.
- El personal **administrativo** procesa solicitudes, mantiene el directorio de médicos y genera reportes.
- La **administración** controla afiliados, roles, vigencias y auditoría del sistema.

Todo con validación de identidad contra el **padrón oficial de agremiados** y controles de seguridad de nivel producción.

---

## 📸 Capturas de pantalla

<!-- Coloca las imágenes en docs/screenshots/ y actualiza las rutas. -->

| Inicio de sesión | Panel del afiliado |
|:---:|:---:|
| ![Login](docs/screenshots/01-login.png) | ![Dashboard del afiliado](docs/screenshots/02-dashboard.png) |

| Panel administrativo | Verificación 2FA (TOTP) |
|:---:|:---:|
| ![Panel admin](docs/screenshots/03-admin.png) | ![2FA TOTP](docs/screenshots/04-2fa.png) |

---

## 🛠️ Stack técnico

| Capa | Tecnología |
|------|------------|
| **Backend** | PHP 8.2 con **PDO nativo** (sin framework, sin ORM) |
| **Base de datos** | MySQL 8.x / MariaDB 10.6 |
| **Frontend** | JavaScript (vanilla), HTML5, CSS3 |
| **Correo** | PHPMailer 6.9 (SMTP, para recuperación de contraseña) |
| **2FA** | TOTP RFC 6238 (Google Authenticator, Aegis, Authy) |
| **Entorno local** | XAMPP (Apache + PHP + MySQL) |

**Arquitectura sin framework, por decisión de diseño:** la aplicación se apoya en una capa MVC ligera propia (`models/`, `controllers/`, `views/`) para demostrar el dominio de los fundamentos —enrutado, capa de datos con PDO, seguridad— sin abstracciones de terceros.

**Fronteras con sistemas externos:** los datos que pertenecen a otros sistemas (nómina/padrón de agremiados del IPP y facturación de consultas) se consumen a través de **interfaces PHP con implementaciones mock** seleccionables por `.env` (`lib/integracion/`). El portal funciona hoy sin depender de nadie; integrar los sistemas reales es implementar la interfaz y cambiar una línea de configuración. Ver [`INTEGRACION.md`](INTEGRACION.md).

---

## 🔒 Seguridad

La seguridad se trató como requisito de primer nivel, no como añadido:

- **RBAC (control de acceso por roles):** tres roles —`admin`, `administrativo`, `afiliado`— con permisos verificados en el servidor en cada acción.
- **Autenticación en dos pasos (2FA):** TOTP RFC 6238 opcional por usuario, compatible con Google Authenticator, Aegis y Authy.
- **Contraseñas con bcrypt:** almacenadas con `password_hash()` (bcrypt, cost 12). Nunca en texto plano.
- **Consultas 100% preparadas con PDO:** todas las interacciones con la BD usan sentencias parametrizadas → protección contra inyección SQL.
- **Protección CSRF:** token por sesión validado en todos los formularios POST.
- **Anti fuerza bruta:** bloqueo temporal de la cuenta tras varios intentos fallidos de inicio de sesión.
- **Gestión de secretos:** credenciales exclusivamente en `.env` (fuera del control de versiones); `config/database.php` no se versiona.
- **Archivos protegidos:** los documentos subidos se sirven solo a través de un proxy PHP que valida sesión y pertenencia.
- **HTTPS forzado** cuando `APP_ENV=production`.

---

## 🚀 Instalación local (XAMPP)

### Requisitos

- [XAMPP](https://www.apachefriends.org/) con **PHP 8.2+** y **MySQL/MariaDB**
- Git

### 1. Clonar en el `htdocs` de XAMPP

```bash
git clone https://github.com/reyesjavi/IPP-uptag C:/xampp/htdocs/uptag_v9
```

### 2. Configurar variables de entorno

```bash
cp .env.example .env
```

Edita `.env` con tus credenciales locales (valores por defecto de XAMPP):

```dotenv
DB_HOST=localhost
DB_NAME=ippuptag
DB_USER=root
DB_PASS=
APP_ENV=development
```

### 3. Configurar la base de datos

```bash
cp config/database.example.php config/database.php
```

Crea la base de datos e importa el esquema, las migraciones y los datos de ejemplo:

```sql
CREATE DATABASE ippuptag CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -u root ippuptag < config/schema.sql
# Migraciones incrementales (en orden)
mysql -u root ippuptag < config/migracion_p2.sql
mysql -u root ippuptag < config/migracion_p3.sql
mysql -u root ippuptag < config/migraciones_v10.sql
mysql -u root ippuptag < config/migraciones_v11.sql
mysql -u root ippuptag < config/migraciones_v12.sql
mysql -u root ippuptag < config/migraciones_v13.sql
# Datos de ejemplo (agremiados y afiliados ficticios)
mysql -u root ippuptag < config/datos_prueba.sql
```

### 4. Abrir la aplicación

Inicia Apache y MySQL en el panel de XAMPP y visita:

```
http://localhost/uptag_v9/
```

---

## 📂 Estructura del proyecto

```
uptag_v9/
├── admin/          # Panel administrativo (afiliados, reembolsos, avales, reportes, 2FA)
├── api/            # Endpoints JSON (registro, etc.)
├── assets/         # CSS, JS e imágenes
├── config/         # base.php, database.php, env.php, schema.sql y migraciones
├── controllers/    # Lógica de negocio (capa MVC ligera)
├── includes/       # Autenticación, cabecera/pie compartidos
├── lib/            # PHPMailer y TOTP (integrados manualmente, sin Composer)
├── models/         # Acceso a datos con PDO
├── views/          # Plantillas de vistas
├── uploads/        # Archivos de usuarios (servidos vía proxy PHP)
├── .env.example    # Plantilla de variables de entorno
└── login.php       # Punto de entrada de autenticación
```

---

## 👥 Roles del sistema

| Rol | Descripción |
|-----|-------------|
| **admin** | Control total: usuarios, roles, vigencias, auditoría y configuración. |
| **administrativo** | Procesa solicitudes de reembolso y aval, gestiona el directorio médico y genera reportes. |
| **afiliado** | Profesor agremiado: consulta beneficios, solicita reembolsos/avales y gestiona su perfil y 2FA. |

---

## 📄 Licencia y autoría

Proyecto de grado con fines **académicos y demostrativos**. Los datos incluidos son ficticios.

Desarrollado en solitario como trabajo de grado.
