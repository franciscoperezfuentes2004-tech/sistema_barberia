<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  AUTENTICACIÓN DE ADMINISTRADOR — Barbería Premium
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Recibe las credenciales desde el formulario de login, verifica
 *  su existencia y contraseña en la base de datos de forma segura,
 *  y genera una sesión para mantener el estado del usuario.
 * ═══════════════════════════════════════════════════════════════════
 */

// ─── 1. CONFIGURACIÓN INICIAL ──────────────────────────────────────
// Permitir el uso de variables de sesión en todo el servidor
session_start();

// Configurar las cabeceras para que la respuesta siempre sea JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Si es una petición OPTIONS (Preflight de CORS), salimos exitosamente
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// ─── 2. ACTIVAR EL MOTOR INTELIGENTE DE BD ─────────────────────────
// Esto invoca la conexión y si no existen las tablas, las creará 
// al vuelo antes de procesar el inicio de sesión.
require_once __DIR__ . "/conexion.php";

// ─── 3. OBTENER Y SANITIZAR DATOS DEL FORMULARIO ───────────────────
// Capturamos los datos (pueden venir por form-data nativo o por JSON Payload del frontend)
// Dado que el frontend modificado enviaba JSON usando fetch anteriormente, leeremos el cuerpo crudo si no hay $_POST.

$usuario  = "";
$password = "";

// Si la petición viene por Content-Type: application/json (fetch)
$raw_input = file_get_contents("php://input");
$json_data = json_decode($raw_input, true);

if (is_array($json_data) && isset($json_data['usuario']) && isset($json_data['password'])) {
    $usuario  = trim($json_data['usuario']);
    $password = trim($json_data['password']);
} 
// Si la petición viene por FormData clásico nativo ($_POST)
elseif (isset($_POST['usuario']) && isset($_POST['password'])) {
    $usuario  = trim($_POST['usuario']);
    $password = trim($_POST['password']);
}

// Verificamos que no vengan vacíos
if (empty($usuario) || empty($password)) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "error", "message" => "Usuario y contraseña son requeridos"]);
    exit;
}

// ─── 4. VERIFICACIÓN SEGURA CON PREPARED STATEMENTS ────────────────
// Preparamos la consulta para evitar SQL Injection. Seleccionamos al usuario por su nombre.
$sql = "SELECT id, usuario, password, rol FROM `usuarios` WHERE usuario = ? LIMIT 1";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno al preparar la consulta"]);
    exit;
}

// Vinculamos el parámetro $usuario (s = string) a la consulta
mysqli_stmt_bind_param($stmt, "s", $usuario);
mysqli_stmt_execute($stmt);

// Obtenemos los resultados de la búsqueda
$resultado = mysqli_stmt_get_result($stmt);

// Si existe una fila, evaluamos el hash
if ($fila = mysqli_fetch_assoc($resultado)) {
    // ─── 5. VALIDACIÓN DEL HASH CON PASSWORD_VERIFY ────────────────
    // Comparamos el string plano con el hash guardado en MySQL
    if (password_verify($password, $fila['password'])) {
        
        // ¡Credenciales correctas! Guardamos los datos vitales en la Sesión
        $_SESSION['usuario_id']  = $fila['id'];
        $_SESSION['usuario']     = $fila['usuario'];
        $_SESSION['rol']         = $fila['rol'];
        $_SESSION['logged_in']   = true;

        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "¡Bienvenido!"]);
        
    } else {
        // Contraseña incorrecta
        http_response_code(401); // Unauthorized
        echo json_encode(["status" => "error", "message" => "Credenciales incorrectas"]);
    }
} else {
    // El usuario no existe en la BD
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Credenciales incorrectas"]);
}

// ─── 6. LIMPIEZA FINAL ─────────────────────────────────────────────
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>
