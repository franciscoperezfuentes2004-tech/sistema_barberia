<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  CONEXIÓN A BASE DE DATOS Y AUTO-INSTALADOR — Barbería Premium
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Este script establece la conexión con la base de datos de producción
 *  en Railway. Además, incluye un motor de auto-migración que verifica
 *  y crea las tablas necesarias (como 'servicios') automáticamente.
 * ═══════════════════════════════════════════════════════════════════
 */

// ─── 1. CREDENCIALES DE PRODUCCIÓN (RAILWAY) ──────────────────────
$db_host = "junction.proxy.rlwy.net";
$db_user = "root";
// Coloca la contraseña proporcionada por Railway aquí:
$db_password = "CFbVHwFQTWoAQWguiIHmPjRxmzwiLENb"; 
$db_name = "railway";
$db_port = "3306";

// ─── 2. ESTABLECER LA CONEXIÓN ────────────────────────────────────
// mysqli_connect requiere el host, usuario, contraseña, base de datos y puerto
$conexion = mysqli_connect($db_host, $db_user, $db_password, $db_name, $db_port);

// Verificar si hubo un error crítico al conectar
if (!$conexion) {
    // Registramos el error en el log interno del servidor
    error_log("Error crítico de conexión a la BD: " . mysqli_connect_error());
    // Terminamos la ejecución enviando un JSON para no romper el frontend
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno del servidor al conectar con la base de datos."]);
    exit;
}

// ─── 3. CONFIGURAR EL CHARSET ─────────────────────────────────────
// Asegura que las consultas manejen correctamente acentos, eñes y emojis
mysqli_set_charset($conexion, "utf8mb4");

// ─── 4. MOTOR INTELIGENTE DE AUTO-CREACIÓN (AUTO-MIGRACIÓN) ───────
// Esta consulta estructurada creará la tabla 'servicios' solo si no existe.
$sql_crear_servicios = "
    CREATE TABLE IF NOT EXISTS `servicios` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(100) NOT NULL,
        `descripcion` TEXT DEFAULT NULL,
        `precio` DECIMAL(10,2) NOT NULL,
        `duracion_min` INT NOT NULL,
        `imagen` VARCHAR(255) DEFAULT NULL,
        `activo` TINYINT(1) NOT NULL DEFAULT 1,
        `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// Ejecutamos la consulta silenciosamente
$resultado_creacion = mysqli_query($conexion, $sql_crear_servicios);

// Si falla la creación, no interrumpimos al usuario, pero lo registramos en el log
if (!$resultado_creacion) {
    // Usamos error_log() para que los desarrolladores puedan depurar problemas de permisos o sintaxis
    error_log("Auto-Migración Fallida en la tabla 'servicios': " . mysqli_error($conexion));
}

// Opcionalmente, puedes añadir más tablas aquí siguiendo el mismo patrón de CREATE TABLE IF NOT EXISTS.
// La conexión ($conexion) ya queda lista y disponible para los demás scripts que incluyan este archivo.
?>
