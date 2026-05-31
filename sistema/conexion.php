<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  CONEXIÓN Y AUTO-INSTALADOR MULTI-TABLA — Barbería Premium
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

if (!mysqli_query($conexion, $query_servicios)) {
    error_log("Auto-Migración Fallida en 'servicios': " . mysqli_error($conexion));
}

$query_galeria = "CREATE TABLE IF NOT EXISTS `galeria` (
    `id` INT AUTO_INCREMENT,
    `ruta_imagen` VARCHAR(255) NOT NULL,
    `titulo` VARCHAR(100) NULL,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $query_galeria)) {
    error_log("Auto-Migración Fallida en 'galeria': " . mysqli_error($conexion));
}

$query_usuarios = "CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT,
    `usuario` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `rol` VARCHAR(20) DEFAULT 'admin',
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $query_usuarios)) {
    error_log("Auto-Migración Fallida en 'usuarios': " . mysqli_error($conexion));
}

// ─── 5. INYECTOR INTELIGENTE DE ADMINISTRADOR POR DEFECTO ─────────
$verificar = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `usuarios`");
if ($verificar) {
    $fila = mysqli_fetch_assoc($verificar);
    if ((int)$fila['total'] === 0) {
        $pass_hash = password_hash("1234", PASSWORD_BCRYPT);
        // Consulta limpia de inserción directa sin Prepared Statement complejo para evitar fallos de bindings
        $insert_sql = "INSERT INTO `usuarios` (`usuario`, `password`, `rol`) VALUES ('admin', '$pass_hash', 'admin')";
        mysqli_query($conexion, $insert_sql);
    }
}
