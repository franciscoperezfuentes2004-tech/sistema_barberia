<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  CONEXIÓN UNIVERSAL A MYSQL — Barbería Premium
 * ═══════════════════════════════════════════════════════════════════
 *
 *  INSTRUCCIONES PARA EL COMPRADOR:
 *  ─────────────────────────────────
 *  1. Abre este archivo con cualquier editor de texto (Bloc de Notas, VS Code, etc.)
 *  2. Modifica ÚNICAMENTE las 4 variables de abajo con los datos de tu servidor:
 *     - $db_host     → Dirección del servidor MySQL (en XAMPP local es "localhost")
 *     - $db_user     → Tu usuario de MySQL (en XAMPP local es "root")
 *     - $db_password → Tu contraseña de MySQL (en XAMPP local suele estar vacía "")
 *     - $db_name     → El nombre de la base de datos que creaste en phpMyAdmin
 *  3. Guarda el archivo y listo. No toques nada más.
 *
 *  SEGURIDAD:
 *  ──────────
 *  - Usa contraseñas fuertes en producción.
 *  - NUNCA subas este archivo a un repositorio público con tus datos reales.
 */

// ┌─────────────────────────────────────────────────────────────────┐
// │  DATOS DE CONEXIÓN — EDITAR AQUÍ                               │
// └─────────────────────────────────────────────────────────────────┘
$db_host     = "localhost";       // Dirección del servidor MySQL
$db_user     = "root";            // Usuario de la base de datos
$db_password = "";                // Contraseña del usuario
$db_name     = "barberia_db";     // Nombre de la base de datos

// ┌─────────────────────────────────────────────────────────────────┐
// │  CONEXIÓN — NO MODIFICAR DESDE AQUÍ                            │
// └─────────────────────────────────────────────────────────────────┘

// Crear la conexión usando la extensión mysqli (la más compatible con XAMPP y hosting compartido)
$conexion = mysqli_connect($db_host, $db_user, $db_password, $db_name);

// Verificar si la conexión fue exitosa
if (!$conexion) {
    // Si falla, detener la ejecución y mostrar un mensaje descriptivo
    die(json_encode([
        "success" => false,
        "error"   => "Error de conexión a la base de datos. Verifica los datos en conexion.php.",
        "detalle" => "MySQL dice: " . mysqli_connect_error()
    ]));
}

// Establecer el juego de caracteres a UTF-8 con soporte completo (emojis, acentos, ñ, etc.)
mysqli_set_charset($conexion, "utf8mb4");

// (Opcional) Establecer la zona horaria de MySQL a la del servidor
// mysqli_query($conexion, "SET time_zone = '-06:00'");
