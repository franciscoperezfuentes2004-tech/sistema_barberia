# 💈 Sistema de Gestión para Barberías Premium

Bienvenido al sistema definitivo para administrar tu barbería. Este sistema ha sido diseñado con altos estándares de calidad, seguridad (OWASP Top 10) y un diseño 100% responsivo para cualquier dispositivo.

A continuación, encontrarás todas las instrucciones necesarias para poner en marcha tu sistema, configurar la base de datos y comenzar a administrar tu negocio.

---

## 🚀 1. Credenciales de Acceso por Defecto

Una vez que el sistema esté instalado y conectado a la base de datos, puedes acceder al **Panel de Administrador** utilizando las siguientes credenciales predeterminadas. 

> ⚠️ **IMPORTANTE:** Por razones de seguridad, cambia esta contraseña inmediatamente después de tu primer inicio de sesión.

- **Usuario:** `admin`
- **Contraseña:** `123456`

🔗 **Ruta de Acceso al Panel:**
Para entrar al sistema, dirígete a la siguiente dirección en tu navegador:
`http://tu_dominio_o_localhost/pages/login.html`

*(Asegúrate de reemplazar `tu_dominio_o_localhost` con la dirección real de tu servidor, por ejemplo `http://localhost/barberia/pages/login.html` si estás en XAMPP).*

---

## 🗄️ 2. ¿Cómo conectar la Base de Datos?

Para conectar tu sistema a tu propia base de datos, debes modificar el archivo de conexión principal. 

**Ubicación del archivo:** Carpeta `sistema/` -> Archivo `conexion.php`

Abre ese archivo con cualquier editor de código (o el bloc de notas) y dirígete exactamente a las **Líneas 27 a la 32**. Allí encontrarás el siguiente código:

```php
// ─── 1. CREDENCIALES EXACTAS DE PRODUCCIÓN PÚBLICA
$db_host     = getenv('DB_HOST') ?: "localhost";
$db_user     = getenv('DB_USER') ?: "tu_usuario";
$db_password = getenv('DB_PASS') ?: "tu_contraseña";
$db_name     = getenv('DB_NAME') ?: "nombre_de_tu_base_de_datos";
$db_port     = getenv('DB_PORT') ?: "3306";
```

**¿Cómo y qué debes cambiar?**
Para poner tus propias credenciales, **solo debes modificar el texto que está entre comillas dobles (`""`) al final de cada línea** (después del símbolo `?:`).

- `$db_host`: Reemplaza `"localhost"` por la dirección de tu servidor si es distinta (casi siempre es `localhost`).
- `$db_user`: Reemplaza `"tu_usuario"` por tu nombre de usuario de la base de datos (en XAMPP suele ser `"root"`).
- `$db_password`: Reemplaza `"tu_contraseña"` por la contraseña de tu base de datos (en XAMPP por defecto se deja vacío `""`).
- `$db_name`: Reemplaza `"nombre_de_tu_base_de_datos"` por el nombre exacto de la base de datos que creaste en phpMyAdmin.
- `$db_port`: Si tu servidor usa el puerto por defecto, déjalo en `"3306"`.

---

## 📂 3. Estructura General del Sistema

El proyecto está organizado de una forma limpia para separar el diseño (Frontend) de la lógica de negocio (Backend):

```text
/ (Directorio Raíz)
 ├── index.html         # Página principal (Landing Page y Reserva para clientes)
 ├── .env               # Archivo maestro de configuración de la Base de Datos y Seguridad
 │
 ├── /assets            # Recursos visuales y de estilo
 │    ├── /css          # Hojas de estilo y diseño
 │    ├── /img          # Imágenes de la galería, barberos, servicios y logos
 │    └── /js           # Scripts del lado del cliente
 │
 ├── /pages             # Interfaces de administración y empleados
 │    ├── login.html    # Pantalla de inicio de sesión
 │    ├── admin.html    # Panel de control total (Estadísticas, Servicios, Personal)
 │    ├── staff.html    # Panel limitado para los barberos (ver agenda)
 │    ├── booking.html  # Interfaz paso a paso para agendar una cita
 │    └── confirm.html  # Pantalla de confirmación de cita para el cliente
 │
 └── /sistema           # Backend (Cerebro del Sistema)
      ├── conexion.php  # Motor que conecta a la Base de Datos y auto-crea las tablas
      ├── auth_middleware.php # Escudo de seguridad JWT
      └── *.php         # Endpoints de la API (Guardar, Eliminar, Obtener, etc.)
```

---

## ⚙️ 4. Script de la Base de Datos (Respaldo)

El sistema tiene una función de **Auto-Migración**. Esto significa que al configurar el `.env` e intentar abrir la página por primera vez, el archivo `conexion.php` creará automáticamente todas las tablas por ti.

Sin embargo, si tu servidor tiene restricciones de permisos y la auto-creación falla, puedes **copiar y pegar** el siguiente script SQL en la pestaña "SQL" de tu phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS `servicios` (
    `id` INT AUTO_INCREMENT,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `precio` DECIMAL(10,2) NOT NULL,
    `duracion_min` INT NOT NULL,
    `imagen` VARCHAR(255) NOT NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `galeria` (
    `id` INT AUTO_INCREMENT,
    `ruta_imagen` VARCHAR(255) NOT NULL,
    `titulo` VARCHAR(100) NULL,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT,
    `usuario` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `rol` VARCHAR(20) DEFAULT 'admin',
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ajustes` (
    `id` INT AUTO_INCREMENT,
    `nombre_empresa` VARCHAR(100) DEFAULT 'Barbería',
    `logo` LONGTEXT NULL,
    `site_phone` VARCHAR(20) NULL,
    `site_email` VARCHAR(100) NULL,
    `site_address` VARCHAR(255) NULL,
    `site_map` TEXT NULL,
    `site_instagram` VARCHAR(150) NULL,
    `site_facebook` VARCHAR(150) NULL,
    `site_tiktok` VARCHAR(150) NULL,
    `site_slogan` VARCHAR(150) NULL,
    `site_hero_desc` TEXT NULL,
    `stat_exp` INT DEFAULT 5,
    `stat_clientes` VARCHAR(50) DEFAULT '+1000',
    `site_hero_bg` LONGTEXT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `barberos` (
    `id` INT AUTO_INCREMENT,
    `nombre` VARCHAR(100) NOT NULL,
    `apellido` VARCHAR(100) NULL,
    `foto` LONGTEXT NULL,
    `especialidad` VARCHAR(150) NULL,
    `usuario` VARCHAR(50) NULL,
    `password` VARCHAR(255) NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_usuario_barbero` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `horarios` (
    `id` INT AUTO_INCREMENT,
    `barbero_id` INT NOT NULL,
    `dia_semana` INT NOT NULL,
    `hora_inicio` TIME NOT NULL,
    `hora_fin` TIME NOT NULL,
    `activo` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `citas` (
    `id` INT AUTO_INCREMENT,
    `cliente_nombre` VARCHAR(150) NOT NULL,
    `cliente_telefono` VARCHAR(20) NULL,
    `barbero_id` INT NOT NULL,
    `servicios_ids` VARCHAR(255) NOT NULL,
    `fecha_hora` DATETIME NOT NULL,
    `hora_inicio` TIME NOT NULL,
    `hora_fin` TIME NOT NULL,
    `estado` VARCHAR(50) DEFAULT 'pendiente',
    `precio_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `resenas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_nombre` VARCHAR(100),
    `comentario` TEXT,
    `calificacion` INT DEFAULT 5,
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 💡 5. Notas Importantes para el Comprador

1. **Protección Anti-Ataques:** El sistema viene con validaciones estrictas y protección contra `XSS` (Inyección de Scripts) y `SQLi`. Tu información y la de tus clientes viaja protegida con Web Tokens (JWT). Es vital no modificar la lógica del archivo `auth_middleware.php` a menos que seas un desarrollador experto.
2. **Caché del Navegador:** Si llegaras a modificar imágenes o colores desde el código fuente y notas que no cambian en la página, presiona `Ctrl + F5` en Windows o `Cmd + Shift + R` en Mac para vaciar la memoria caché de tu navegador.
3. **Imágenes en Base64:** Muchas de las configuraciones como el Hero Image o el Logo se guardan en la base de datos como una cadena larga de texto (`Base64`). Esto facilita que puedas migrar el sistema de un servidor a otro sin perder los archivos ni preocuparte por carpetas rotas de imágenes subidas.
4. **Diseño "Responsive":** El sistema garantiza un funcionamiento sin fallas gráficas en monitores ultra-anchos y teléfonos tan pequeños como 320px de ancho. Si vas a insertar nuevos servicios o textos, intenta no usar párrafos gigantes sin espacios para mantener la armonía del diseño original.
