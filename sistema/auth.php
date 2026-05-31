<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AUTENTICACIÓN DE ADMINISTRADOR — Barbería Premium
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Recibe las credenciales, incluye la conexión (que detona la creación
 *  automática de tablas), verifica la contraseña e inicia sesión.
 * ═══════════════════════════════════════════════════════════════════
 */

// ─── 1. INICIAR SESIÓN Y CONFIGURAR CABECERAS ─────────────────────
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// ─── 2. ACTIVAR EL MOTOR INTELIGENTE DE BD ─────────────────────────
// Esto incluye las credenciales de Railway y crea las tablas si no existen
require_once __DIR__ . "/conexion.php";

// ─── 3. CAPTURAR Y SANITIZAR DATOS POR POST ────────────────────────
// Soporte para JSON crudo (fetch) o FormData tradicional
$usuario  = "";
$password = "";

$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true);

if (is_array($json_data) && isset($json_data['usuario']) && isset($json_data['password'])) {
    $usuario  = trim($json_data['usuario']);
    $password = trim($json_data['password']);
} elseif (isset($_POST['usuario']) && isset($_POST['password'])) {
    $usuario  = trim($_POST['usuario']);
    $password = trim($_POST['password']);
}

// Verificar que se enviaron credenciales
if (empty($usuario) || empty($password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Usuario y contraseña son requeridos"]);
    exit;
}

// ─── 4. BÚSQUEDA SEGURA DEL USUARIO ────────────────────────────────
$sql = "SELECT id, usuario, password, rol FROM `usuarios` WHERE usuario = ? LIMIT 1";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    error_log("Error preparando la consulta de autenticación: " . mysqli_error($conexion));
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno al consultar la base de datos"]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $usuario);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// ─── 5. VERIFICACIÓN DE CONTRASEÑA ─────────────────────────────────
if ($fila = mysqli_fetch_assoc($resultado)) {
    // Validamos el hash guardado en MySQL con el password ingresado
    if (password_verify($password, $fila['password'])) {
        
        // Iniciar sesión exitosamente
        $_SESSION['usuario_id']  = $fila['id'];
        $_SESSION['usuario']     = $fila['usuario'];
        $_SESSION['rol']         = $fila['rol'];
        $_SESSION['logged_in']   = true;

        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "¡Bienvenido!"]);
        
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Credenciales incorrectas"]);
    }
} else {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Credenciales incorrectas"]);
}

// ─── 6. CERRAR CONEXIONES ──────────────────────────────────────────
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>
