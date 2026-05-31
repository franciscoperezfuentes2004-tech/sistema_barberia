<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  CONEXIÓN Y AUTO-INSTALADOR MULTI-TABLA (CON SEEDERS)
 * ═══════════════════════════════════════════════════════════════════
 */

// ─── 1. CREDENCIALES EXACTAS DE PRODUCCIÓN PÚBLICA (RAILWAY) ──────
$db_host     = "zephyr.proxy.rlwy.net";
$db_user     = "root";
$db_password = "CFbVHwFQTWoAQWguiIHmPjRxmzwiLENb";
$db_name     = "railway";
$db_port     = "56694";

// ─── 2. ESTABLECER LA CONEXIÓN Y DESHABILITAR REPORTES EN PANTALLA ─
mysqli_report(MYSQLI_REPORT_OFF);

$conexion = @mysqli_connect($db_host, $db_user, $db_password, $db_name, $db_port);

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
    `logo` VARCHAR(255) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_ajustes)) error_log("Auto-Migración Fallida en 'ajustes': " . mysqli_error($conexion));

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
    `servicio_id` INT NOT NULL,
    `fecha_hora` DATETIME NOT NULL,
    `estado` VARCHAR(50) DEFAULT 'pendiente',
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
if (!mysqli_query($conexion, $query_citas)) error_log("Auto-Migración Fallida en 'citas': " . mysqli_error($conexion));

// ─── 5. INYECTORES INTELIGENTES POR DEFECTO (SEEDERS) ─────────

// Inyector de Administrador
$verificar_usuarios = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `usuarios`");
if ($verificar_usuarios) {
    $fila = mysqli_fetch_assoc($verificar_usuarios);
    if ((int)$fila['total'] === 0) {
        $pass_hash = password_hash("1234", PASSWORD_BCRYPT);
        $insert_admin = "INSERT INTO `usuarios` (`usuario`, `password`, `rol`) VALUES ('admin', '$pass_hash', 'admin')";
        mysqli_query($conexion, $insert_admin);
    }
}

// Inyector de Ajustes Globales (Nombre y Logo)
$verificar_ajustes = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `ajustes`");
if ($verificar_ajustes) {
    $fila = mysqli_fetch_assoc($verificar_ajustes);
    if ((int)$fila['total'] === 0) {
        // Sembrar con la ruta hacia un logo físico estándar de nuestro frontend
        $insert_ajustes = "INSERT INTO `ajustes` (`nombre_empresa`, `logo`) VALUES ('Barbería Premium', '../assets/img/logo.png')";
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
