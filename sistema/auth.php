<?php
// Control del buffer para evitar que basuras (warnings o espacios) rompan la salida
ob_start();

// Configuración obligatoria para responder JSON limpio
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

// Incluimos la conexión, que activa el motor inteligente
require_once __DIR__ . "/conexion.php";

// LECTURA HÍBRIDA (Capturamos JSON crudo de fetch o $_POST tradicional)
$data_json = json_decode(file_get_contents("php://input"), true);

// Evaluar variable usuario
$user_input = "";
if (isset($_POST['usuario']) && trim($_POST['usuario']) !== "") {
    $user_input = trim($_POST['usuario']);
} elseif (is_array($data_json) && isset($data_json['usuario']) && trim($data_json['usuario']) !== "") {
    $user_input = trim($data_json['usuario']);
}

// Evaluar variable password
$pass_input = "";
if (isset($_POST['password']) && trim($_POST['password']) !== "") {
    $pass_input = trim($_POST['password']);
} elseif (is_array($data_json) && isset($data_json['password']) && trim($data_json['password']) !== "") {
    $pass_input = trim($data_json['password']);
}

// Verificamos si quedaron vacíos
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
    // Verificar si el password ingresado coincide con el hash en BCRYPT
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
