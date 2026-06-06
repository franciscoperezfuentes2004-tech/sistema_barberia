<?php
// Control del buffer para evitar que advertencias rompan el JSON
ob_start();

header("Content-Type: application/json; charset=UTF-8");
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

// Incluimos la conexión que detona la instalación si no existe
require_once __DIR__ . "/conexion.php";

// Función para codificar en Base64Url (Estándar JWT)
function base64UrlEncode($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

// LECTURA HÍBRIDA MULTI-LLAVE (God-Mode)
$data_json = json_decode(file_get_contents("php://input"), true);

$user_input = trim($_POST['usuario'] ?? $_POST['username'] ?? $_POST['user'] ?? $data_json['usuario'] ?? $data_json['username'] ?? $data_json['user'] ?? '');
$pass_input = trim($_POST['password'] ?? $_POST['pass'] ?? $_POST['clave'] ?? $data_json['password'] ?? $data_json['pass'] ?? $data_json['clave'] ?? '');

// Verificación estricta de variables vacías
if (empty($user_input) || empty($pass_input)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Usuario y contraseña son requeridos."]);
    exit;
}

// Búsqueda del usuario de forma segura en 'usuarios' (Admins)
$sql = "SELECT id, usuario, password, rol FROM `usuarios` WHERE BINARY usuario = ? LIMIT 1";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    ob_clean();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno en servidor."]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $user_input);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$logged_in = false;
$user_data = [];

if ($fila = mysqli_fetch_assoc($resultado)) {
    if (password_verify($pass_input, $fila['password'])) {
        $logged_in = true;
        $user_data = [
            'id' => $fila['id'],
            'usuario' => $fila['usuario'],
            'rol' => $fila['rol']
        ];
    }
}

mysqli_stmt_close($stmt);

// Si no es admin, buscar en la tabla 'barberos'
if (!$logged_in) {
    $sql_b = "SELECT id, usuario, password, nombre, foto FROM `barberos` WHERE BINARY usuario = ? LIMIT 1";
    $stmt_b = mysqli_prepare($conexion, $sql_b);
    if ($stmt_b) {
        mysqli_stmt_bind_param($stmt_b, "s", $user_input);
        mysqli_stmt_execute($stmt_b);
        $res_b = mysqli_stmt_get_result($stmt_b);
        if ($fila_b = mysqli_fetch_assoc($res_b)) {
            if (password_verify($pass_input, $fila_b['password'])) {
                $logged_in = true;
                $user_data = [
                    'id' => $fila_b['id'],
                    'usuario' => $fila_b['usuario'],
                    'rol' => 'barbero',
                    'foto' => $fila_b['foto'] ?? '',
                    'nombre' => $fila_b['nombre'] ?? ''
                ];
            }
        }
        mysqli_stmt_close($stmt_b);
    }
}

if ($logged_in) {
    // 1. Iniciamos sesión tradicional en el servidor por seguridad dual
    session_start();
    $_SESSION['usuario_id']  = $user_data['id'];
    $_SESSION['usuario']     = $user_data['usuario'];
    $_SESSION['rol']         = $user_data['rol'];
    $_SESSION['logged_in']   = true;

    // 2. Generamos el JWT nativo para el frontend (SOLO datos ligeros, NO fotos base64)
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'id' => $user_data['id'], 
        'usuario' => $user_data['usuario'], 
        'rol' => $user_data['rol'],
        'nombre' => $user_data['nombre'] ?? '',
        'iat' => time(),
        'exp' => time() + (8 * 3600) // 8 hours
    ]);
    
    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode($payload);
    
    // Creamos la firma con nuestra clave secreta
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, 'clave_secreta_barberia', true);
    $base64UrlSignature = base64UrlEncode($signature);
    
    // Concatenamos el token completo
    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    ob_clean();
    http_response_code(200);
    echo json_encode([
        "status" => "success", 
        "message" => "¡Bienvenido!", 
        "token" => $jwt, 
        "rol" => $user_data['rol'],
        "foto" => $user_data['foto'] ?? '',
        "nombre" => $user_data['nombre'] ?? ''
    ]);
} else {
    ob_clean();
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Credenciales incorrectas."]);
}

mysqli_close($conexion);
