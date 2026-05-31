<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  CONEXIÓN Y AUTO-INSTALADOR MULTI-TABLA — Barbería Premium
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Establece la conexión a la base de datos MySQL en producción (Railway)
 *  y verifica/construye de forma silenciosa e inteligente la estructura 
 *  completa de tablas, incluyendo la inyección del primer administrador.
 * ═══════════════════════════════════════════════════════════════════
 */

// ─── 1. CREDENCIALES DE PRODUCCIÓN (RAILWAY) ──────────────────────
$db_host = "mysql.railway.internal";
$db_user = "root";
// Coloca la contraseña proporcionada por Railway aquí:
$db_password = "CFbVHwFQTWoAQWguiIHmPjRxmzwiLENb"; 
$db_name = "railway";
$db_port = "3306";

// ─── 2. ESTABLECER LA CONEXIÓN ────────────────────────────────────
$conexion = mysqli_connect($db_host, $db_user, $db_password, $db_name, $db_port);

if (!$conexion) {
    // Si la conexión falla, se registra en el log sin mostrar errores feos en pantalla
    error_log("Error crítico de conexión a la BD: " . mysqli_connect_error());
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno de servidor al conectar a BD."]);
    exit;
}

// ─── 3. CONFIGURAR EL CHARSET ─────────────────────────────────────
// Fundamental para soportar acentos, eñes y emojis en toda la aplicación
mysqli_set_charset($conexion, "utf8mb4");

// ─── 4. MOTOR MULTI-TABLA (AUTO-MIGRACIÓN SILENCIOSA) ─────────────
// Ejecutamos las creaciones de forma individual para aislar posibles errores

// A) Tabla 'servicios'
$sql_servicios = "CREATE TABLE IF NOT EXISTS `servicios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(150) NOT NULL,
    `descripcion` TEXT DEFAULT NULL,
    `precio` DECIMAL(10,2) NOT NULL,
    `duracion_min` INT NOT NULL,
    `imagen` VARCHAR(255) DEFAULT NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $sql_servicios)) {
    error_log("Auto-Migración Fallida en 'servicios': " . mysqli_error($conexion));
}

// B) Tabla 'galeria'
$sql_galeria = "CREATE TABLE IF NOT EXISTS `galeria` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ruta_imagen` VARCHAR(255) NOT NULL,
    `titulo` VARCHAR(100) DEFAULT NULL,
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $sql_galeria)) {
    error_log("Auto-Migración Fallida en 'galeria': " . mysqli_error($conexion));
}

// C) Tabla 'usuarios' (Para panel de control y administradores)
$sql_usuarios = "CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `rol` VARCHAR(20) DEFAULT 'admin',
    `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (!mysqli_query($conexion, $sql_usuarios)) {
    error_log("Auto-Migración Fallida en 'usuarios': " . mysqli_error($conexion));
}

// ─── 5. INYECTOR INTELIGENTE DE ADMINISTRADOR POR DEFECTO ─────────
// Una vez creada la tabla de usuarios, verificamos si está vacía.
// Esto permite crear un admin inicial para que el comprador pueda entrar al sistema inmediatamente.

$res_conteo = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM `usuarios`");
if ($res_conteo) {
    $fila = mysqli_fetch_assoc($res_conteo);
    
    // Si el total de usuarios es 0 (la tabla acaba de crearse limpia)
    if ($fila['total'] == 0) {
        
        // Generamos un hash ultra-seguro usando el estándar de PHP BCRYPT
        // La contraseña por defecto será "1234"
        $hash_admin = password_hash('1234', PASSWORD_BCRYPT);
        
        $sql_admin = "INSERT INTO `usuarios` (`usuario`, `password`, `rol`) 
                      VALUES ('admin', '$hash_admin', 'admin')";
                      
        if (!mysqli_query($conexion, $sql_admin)) {
            error_log("Fallo al inyectar el administrador por defecto: " . mysqli_error($conexion));
        } else {
            // (Opcional) Registrar que la semilla inicial se plantó correctamente
            error_log("Admin por defecto (usuario: admin, pass: 1234) inyectado exitosamente.");
        }
    }
} else {
    error_log("Fallo al verificar el conteo de la tabla 'usuarios': " . mysqli_error($conexion));
}

// Fin del archivo. La conexión queda abierta y lista para ser usada por otros scripts.
?>
