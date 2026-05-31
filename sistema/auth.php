<?php
// Usamos el buffer de salida para evitar que advertencias rompan el JSON
ob_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

// Incluimos la conexión que detona la instalación si no existe
require_once __DIR__ . "/conexion.php";

// LECTURA HÍBRIDA MULTI-LLAVE (God-Mode)
$data_json = json_decode(file_get_contents("php://input"), true);

// Evaluamos todas las posibles llaves usando el operador de coalescencia nula (??)
$user_input = trim($_POST['usuario'] ?? $_POST['username'] ?? $_POST['user'] ?? $data_json['usuario'] ?? $data_json['username'] ?? $data_json['user'] ?? '');
$pass_input = trim($_POST['password'] ?? $_POST['pass'] ?? $_POST['clave'] ?? $data_json['password'] ?? $data_json['pass'] ?? $data_json['clave'] ?? '');

// Verificación estricta de variables vacías
if (empty($user_input) || empty($pass_input)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Usuario y contraseña son requeridos."]);
    exit;
}

// Búsqueda del usuario de forma segura
$sql = "SELECT id, usuario, password, rol FROM `usuarios` WHERE usuario = ? LIMIT 1";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    error_log("Error al preparar auth: " . mysqli_error($conexion));
    ob_clean();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno en servidor."]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $user_input);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if ($fila = mysqli_fetch_assoc($resultado)) {
    // Verificamos el hash con password_verify
    if (password_verify($pass_input, $fila['password'])) {
        session_start();
        $_SESSION['usuario_id']  = $fila['id'];
        $_SESSION['usuario']     = $fila['usuario'];
        $_SESSION['rol']         = $fila['rol'];
        $_SESSION['logged_in']   = true;

        ob_clean();
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "¡Bienvenido!"]);
    } else {
        ob_clean();
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Credenciales incorrectas."]);
    }
} else {
    ob_clean();
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Credenciales incorrectas."]);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
