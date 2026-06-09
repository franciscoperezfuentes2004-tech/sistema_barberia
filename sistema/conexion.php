<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  CONEXIÓN Y AUTO-INSTALADOR MULTI-TABLA (CON SEEDERS)
 * ═══════════════════════════════════════════════════════════════════
 */

function loadEnv($path) {
    if(!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($lines as $line) {
        if(strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if(!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load .env from project root
loadEnv(__DIR__ . '/../.env');

// ─── 1. CREDENCIALES EXACTAS DE PRODUCCIÓN PÚBLICA (RAILWAY) ──────
$db_host     = getenv('DB_HOST') ?: "zephyr.proxy.rlwy.net";
$db_user     = getenv('DB_USER') ?: "root";
$db_password = getenv('DB_PASS') ?: "CFbVHwFQTWoAQWguiIHmPjRxmzwiLENb";
$db_name     = getenv('DB_NAME') ?: "railway";
$db_port     = getenv('DB_PORT') ?: "56694"; 

// Configurar zona horaria local para evitar saltos de fecha UTC
date_default_timezone_set('America/Mexico_City');

mysqli_report(MYSQLI_REPORT_OFF);

$conexion = mysqli_connect($db_host, $db_user, $db_password, $db_name, $db_port);

if (!$conexion) {
    error_log("Error crítico de conexión a la BD: " . mysqli_connect_error());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno de servidor al conectar a BD."]);
    exit;
}

// ─── 3. CONFIGURAR EL CHARSET ─────────────────────────────────────
mysqli_set_charset($conexion, "utf8mb4");

// ─── 4. MOTOR MULTI-TABLA (AUTO-MIGRACIÓN SILENCIOSA) ─────────────

// Tabla Servicios
$query_servicios = "CREATE TABLE IF NOT EXISTS `servicios` (
    `id` INT AUTO_INCREMENT,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT NULL,
    `precio` DECIMAL(10,2) NOT NULL,
    `duracion_min` INT NOT NULL,
    `imagen` VARCHAR(255) NOT NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_servicios)) error_log("Auto-Migración Fallida en 'servicios': " . mysqli_error($conexion));

// Tabla Galeria
$query_galeria = "CREATE TABLE IF NOT EXISTS `galeria` (
    `id` INT AUTO_INCREMENT,
    `ruta_imagen` VARCHAR(255) NOT NULL,
    `titulo` VARCHAR(100) NULL,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_galeria)) error_log("Auto-Migración Fallida en 'galeria': " . mysqli_error($conexion));

// Tabla Usuarios
$query_usuarios = "CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT,
    `usuario` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `rol` VARCHAR(20) DEFAULT 'admin',
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_usuarios)) error_log("Auto-Migración Fallida en 'usuarios': " . mysqli_error($conexion));

// Tabla Ajustes
$query_ajustes = "CREATE TABLE IF NOT EXISTS `ajustes` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_ajustes)) error_log("Auto-Migración Fallida en 'ajustes': " . mysqli_error($conexion));

// Auto-migración de columnas faltantes en 'ajustes' (para tablas creadas en versiones anteriores)
$columnas_ajustes = [
    "ADD COLUMN `site_phone` VARCHAR(20) NULL",
    "ADD COLUMN `site_email` VARCHAR(100) NULL",
    "ADD COLUMN `site_address` VARCHAR(255) NULL",
    "ADD COLUMN `site_map` TEXT NULL",
    "ADD COLUMN `site_instagram` VARCHAR(150) NULL",
    "ADD COLUMN `site_facebook` VARCHAR(150) NULL",
    "ADD COLUMN `site_tiktok` VARCHAR(150) NULL",
    "ADD COLUMN `site_slogan` VARCHAR(150) NULL",
    "ADD COLUMN `site_hero_desc` TEXT NULL",
    "ADD COLUMN `stat_exp` INT DEFAULT 5",
    "ADD COLUMN `stat_clientes` VARCHAR(50) DEFAULT '+1000'",
    "ADD COLUMN `site_hero_bg` LONGTEXT NULL",
    "MODIFY COLUMN `logo` LONGTEXT NULL"
];
foreach ($columnas_ajustes as $alter) {
    @mysqli_query($conexion, "ALTER TABLE `ajustes` $alter");
}

// Auto-migración de columna 'activo' en 'servicios' (para tablas creadas sin ella)
@mysqli_query($conexion, "ALTER TABLE `servicios` ADD COLUMN `activo` TINYINT(1) DEFAULT 1");
// Cambiar campo imagen de servicios a LONGTEXT para soportar Base64
@mysqli_query($conexion, "ALTER TABLE `servicios` MODIFY COLUMN `imagen` LONGTEXT NULL");

// Auto-migración de 'ruta_imagen' en 'galeria' a LONGTEXT para soportar Base64
@mysqli_query($conexion, "ALTER TABLE `galeria` MODIFY COLUMN `ruta_imagen` LONGTEXT NOT NULL");

// Tabla Barberos
$query_barberos = "CREATE TABLE IF NOT EXISTS `barberos` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_barberos)) error_log("Auto-Migración Fallida en 'barberos': " . mysqli_error($conexion));

// Tabla Horarios
$query_horarios = "CREATE TABLE IF NOT EXISTS `horarios` (
    `id` INT AUTO_INCREMENT,
    `barbero_id` INT NOT NULL,
    `dia_semana` INT NOT NULL,
    `hora_inicio` TIME NOT NULL,
    `hora_fin` TIME NOT NULL,
    `activo` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_horarios)) error_log("Auto-Migración Fallida en 'horarios': " . mysqli_error($conexion));

// Tabla Citas
$query_citas = "CREATE TABLE IF NOT EXISTS `citas` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_citas)) error_log("Auto-Migración Fallida en 'citas': " . mysqli_error($conexion));

// Migrar columnas en caso de que ya exista la tabla vieja
@mysqli_query($conexion, "ALTER TABLE `citas` ADD COLUMN `hora_inicio` TIME NOT NULL DEFAULT '00:00:00'");
@mysqli_query($conexion, "ALTER TABLE `citas` ADD COLUMN `hora_fin` TIME NOT NULL DEFAULT '00:00:00'");
@mysqli_query($conexion, "ALTER TABLE `citas` ADD COLUMN `servicios_ids` VARCHAR(255) NOT NULL DEFAULT '[]'");
@mysqli_query($conexion, "ALTER TABLE `citas` ADD COLUMN `precio_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00");


// Tabla Reseñas
$query_resenas = "CREATE TABLE IF NOT EXISTS `resenas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cliente_nombre` VARCHAR(100),
    `comentario` TEXT,
    `calificacion` INT DEFAULT 5,
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_resenas)) error_log("Auto-Migración Fallida en 'resenas': " . mysqli_error($conexion));

// ─── 5. INYECTORES INTELIGENTES POR DEFECTO (SEEDERS) ─────────

// Inyector de Administrador
$verificar_usuarios = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `usuarios`");
if ($verificar_usuarios) {
    $fila = mysqli_fetch_assoc($verificar_usuarios);
    if ((int)$fila['total'] === 0) {
        $pass_hash = password_hash("123456", PASSWORD_BCRYPT);
        $insert_admin = "INSERT INTO `usuarios` (`usuario`, `password`, `rol`) VALUES ('admin', '$pass_hash', 'admin')";
        mysqli_query($conexion, $insert_admin);
    }
}

// Inyector de Ajustes Globales (Todos los campos)
$verificar_ajustes = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `ajustes`");
if ($verificar_ajustes) {
    $fila = mysqli_fetch_assoc($verificar_ajustes);
    if ((int)$fila['total'] === 0) {
        $insert_ajustes = "INSERT INTO `ajustes` (
            `nombre_empresa`, `logo`, `site_phone`, `site_email`, `site_address`, 
            `site_slogan`, `site_hero_desc`, `stat_exp`, `stat_clientes`
        ) VALUES (
            'Barbería Premium', '../assets/img/logo.png', '+52 123 456 7890', 'contacto@barberiapremium.com', 
            'Av. Principal 123, Ciudad', 'Estilo que habla por ti', 
            'Experimenta el arte del cuidado masculino. Tradición y vanguardia.', 10, '+2000'
        )";
        mysqli_query($conexion, $insert_ajustes);
    }
}

// Inyector de Servicios Semilla
$verificar_servicios = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `servicios`");
if ($verificar_servicios) {
    $fila = mysqli_fetch_assoc($verificar_servicios);
    if ((int)$fila['total'] === 0) {
        $insert_servicio_1 = "INSERT INTO `servicios` (`nombre`, `descripcion`, `precio`, `duracion_min`, `imagen`) VALUES ('Corte Clásico', 'Corte de cabello estilizado para hombre', 150.00, 30, '../assets/img/corte.png')";
        mysqli_query($conexion, $insert_servicio_1);
        
        $insert_servicio_2 = "INSERT INTO `servicios` (`nombre`, `descripcion`, `precio`, `duracion_min`, `imagen`) VALUES ('Afeitado Premium', 'Afeitado relajante con toalla caliente', 100.00, 20, '../assets/img/afeitado.png')";
        mysqli_query($conexion, $insert_servicio_2);
        
        $insert_servicio_3 = "INSERT INTO `servicios` (`nombre`, `descripcion`, `precio`, `duracion_min`, `imagen`) VALUES ('Corte + Barba', 'Servicio completo VIP', 220.00, 50, '../assets/img/completo.png')";
        mysqli_query($conexion, $insert_servicio_3);
    }
}

// Inyector de Reseñas Semilla
$verificar_resenas = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `resenas`");
if ($verificar_resenas) {
    $fila = mysqli_fetch_assoc($verificar_resenas);
    if ((int)$fila['total'] === 0) {
        $insert_resena_1 = "INSERT INTO `resenas` (`cliente_nombre`, `comentario`, `calificacion`) VALUES ('Juan Pérez', 'Excelente servicio', 5)";
        mysqli_query($conexion, $insert_resena_1);
        
        $insert_resena_2 = "INSERT INTO `resenas` (`cliente_nombre`, `comentario`, `calificacion`) VALUES ('María López', 'Muy buena atención y ambiente', 5)";
        mysqli_query($conexion, $insert_resena_2);
    }
}
