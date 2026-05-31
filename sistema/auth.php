<?php
// Usamos el buffer de salida para evitar que cualquier advertencia (Warning) 
// o espacio en blanco en otros archivos rompa nuestra respuesta JSON.
ob_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean(); // Limpiamos la basura antes de responder
    http_response_code(200);
    exit;
}

// Activamos la sesión
session_start();

// ─── 1. INCLUIR CONEXIÓN (DISPARA EL MOTOR DE TABLAS) ──────────────
require_once __DIR__ . "/conexion.php";

// ─── 2. OBTENER DATOS POST Y VALIDARLOS ────────────────────────────
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

// Si llegan vacíos, abortamos de inmediato con un JSON válido
if (empty($usuario) || empty($password)) {
    http_response_code(400);
    ob_clean(); // Limpiamos la basura antes de responder
    echo json_encode(["status" => "error", "message" => "Usuario y contraseña son requeridos"]);
    exit;
}

// ─── 3. BÚSQUEDA DEL USUARIO USANDO PREPARED STATEMENTS ────────────
$sql = "SELECT id, usuario, password, rol FROM `usuarios` WHERE usuario = ? LIMIT 1";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    error_log("Error al preparar la consulta de auth: " . mysqli_error($conexion));
    http_response_code(500);
    ob_clean(); // Limpiamos la basura antes de responder
    echo json_encode(["status" => "error", "message" => "Error interno al consultar la base de datos"]);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $usuario);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// ─── 4. VERIFICAR CONTRASEÑA ───────────────────────────────────────
if ($fila = mysqli_fetch_assoc($resultado)) {
    // Si la contraseña coincide con el hash en la BD
    if (password_verify($password, $fila['password'])) {
        
        // Configuramos la sesión del usuario
        $_SESSION['usuario_id']  = $fila['id'];
        $_SESSION['usuario']     = $fila['usuario'];
        $_SESSION['rol']         = $fila['rol'];
        $_SESSION['logged_in']   = true;

        http_response_code(200);
        ob_clean(); // Limpiamos la basura antes de responder
        echo json_encode(["status" => "success", "message" => "¡Bienvenido!"]);
        
    } else {
        // Falló password_verify
        http_response_code(401);
        ob_clean(); // Limpiamos la basura antes de responder
        echo json_encode(["status" => "error", "message" => "Credenciales incorrectas"]);
    }
} else {
    // Usuario no existe
    http_response_code(401);
    ob_clean(); // Limpiamos la basura antes de responder
    echo json_encode(["status" => "error", "message" => "Credenciales incorrectas"]);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
