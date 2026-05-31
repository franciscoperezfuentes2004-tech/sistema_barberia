<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  CONEXIÓN Y AUTO-INSTALADOR MULTI-TABLA — Barbería Premium
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Establece la conexión a la base de datos MySQL en producción (Railway)
 *  usando las credenciales internas reales y verifica/construye la 
 *  estructura completa de tablas automáticamente.
 * ═══════════════════════════════════════════════════════════════════
 */

// ─── 1. CREDENCIALES EXACTAS DE PRODUCCIÓN (RAILWAY) ──────────────
$db_host     = "mysql.railway.internal";
$db_user     = "root";
$db_password = "CFbVHwFQTWoAQWguiIHmPjRxmzwiLENb";
$db_name     = "railway";
$db_port     = "3306";

// ─── 2. ESTABLECER LA CONEXIÓN ────────────────────────────────────
$conexion = mysqli_connect($db_host, $db_user, $db_password, $db_name, $db_port);

if (!$conexion) {
    // Si falla la conexión, registramos silenciosamente en los logs
    error_log("Error crítico de conexión a la BD: " . mysqli_connect_error());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno de servidor al conectar a BD."]);
    exit;
}

// ─── 3. CONFIGURAR EL CHARSET ─────────────────────────────────────
mysqli_set_charset($conexion, "utf8mb4");

// ─── 4. MOTOR MULTI-TABLA (AUTO-MIGRACIÓN SILENCIOSA) ─────────────

// A) Tabla 'servicios'
$sql_servicios = "CREATE TABLE IF NOT EXISTS `servicios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150),
    `precio` DECIMAL(10,2),
    `duracion_min` INT,
    `imagen` VARCHAR(255),
    `activo` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $sql_servicios)) {
    error_log("Auto-Migración Fallida en 'servicios': " . mysqli_error($conexion));
}

// B) Tabla 'galeria'
$sql_galeria = "CREATE TABLE IF NOT EXISTS `galeria` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ruta_imagen` VARCHAR(255),
    `titulo` VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $sql_galeria)) {
    error_log("Auto-Migración Fallida en 'galeria': " . mysqli_error($conexion));
}

// C) Tabla 'usuarios'
$sql_usuarios = "CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario` VARCHAR(50) UNIQUE,
    `password` VARCHAR(255),
    `rol` VARCHAR(20) DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $sql_usuarios)) {
    error_log("Auto-Migración Fallida en 'usuarios': " . mysqli_error($conexion));
}

// ─── 5. INYECTOR INTELIGENTE DE ADMINISTRADOR POR DEFECTO ─────────
// Verificamos si la tabla de usuarios está vacía
$res_conteo = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM `usuarios`");
if ($res_conteo) {
    $fila = mysqli_fetch_assoc($res_conteo);
    
    // Si el conteo es 0, inyectamos al usuario 'admin' automáticamente
    if ($fila['total'] == 0) {
        $hash_admin = password_hash('1234', PASSWORD_BCRYPT);
        
        $sql_admin = "INSERT INTO `usuarios` (`usuario`, `password`, `rol`) 
                      VALUES ('admin', '$hash_admin', 'admin')";
                      
        if (!mysqli_query($conexion, $sql_admin)) {
            error_log("Fallo al inyectar el administrador por defecto: " . mysqli_error($conexion));
        } else {
            error_log("Admin por defecto inyectado exitosamente.");
        }
    }
} else {
    error_log("Fallo al verificar el conteo de la tabla 'usuarios': " . mysqli_error($conexion));
}

// La conexión queda lista para ser utilizada por el archivo que la incluyó.
?>
