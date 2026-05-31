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
// Prevenimos que cualquier warning interno de PHP escupa HTML y rompa el JSON
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

$sql_servicios = "CREATE TABLE IF NOT EXISTS `servicios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150),
    `descripcion` TEXT,
    `precio` DECIMAL(10,2),
    `duracion_min` INT,
    `imagen` VARCHAR(255),
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $sql_servicios)) {
    error_log("Auto-Migración Fallida en 'servicios': " . mysqli_error($conexion));
}

$sql_galeria = "CREATE TABLE IF NOT EXISTS `galeria` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ruta_imagen` VARCHAR(255),
    `titulo` VARCHAR(100),
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $sql_galeria)) {
    error_log("Auto-Migración Fallida en 'galeria': " . mysqli_error($conexion));
}

$sql_usuarios = "CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `rol` VARCHAR(20) DEFAULT 'admin',
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $sql_usuarios)) {
    error_log("Auto-Migración Fallida en 'usuarios': " . mysqli_error($conexion));
}

// ─── 5. INYECTOR INTELIGENTE DE ADMINISTRADOR POR DEFECTO ─────────
$res_conteo = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM `usuarios`");
if ($res_conteo) {
    $fila = mysqli_fetch_assoc($res_conteo);
    
    // Si la tabla de usuarios está vacía, inyectamos al administrador maestro
    if ($fila['total'] == 0) {
        $usuario_admin = 'admin';
        $hash_admin = password_hash('1234', PASSWORD_BCRYPT);
        
        $sql_admin = "INSERT INTO `usuarios` (`usuario`, `password`, `rol`) VALUES (?, ?, 'admin')";
        $stmt_admin = mysqli_prepare($conexion, $sql_admin);
        
        if ($stmt_admin) {
            mysqli_stmt_bind_param($stmt_admin, "ss", $usuario_admin, $hash_admin);
            if (!mysqli_stmt_execute($stmt_admin)) {
                error_log("Fallo al inyectar admin: " . mysqli_stmt_error($stmt_admin));
            } else {
                error_log("Admin por defecto inyectado exitosamente.");
            }
            mysqli_stmt_close($stmt_admin);
        } else {
            error_log("Fallo al preparar la inyección de admin: " . mysqli_error($conexion));
        }
    }
} else {
    error_log("Fallo al verificar el conteo de la tabla 'usuarios': " . mysqli_error($conexion));
}

// Omitimos la etiqueta de cierre PHP (?>) intencionalmente para evitar 
// espacios o saltos de línea ocultos al final del archivo que rompan el JSON.
