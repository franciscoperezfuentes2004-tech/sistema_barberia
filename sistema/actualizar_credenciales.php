<?php
/**
 * Actualiza las credenciales de administrador
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'conexion.php';
/** @var mysqli $conexion */

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit;
}

// 1. Extraer JWT (Misma lógica simple de auth.php)
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? '';
if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
}

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Token no proporcionado"]);
    exit;
}

$token = $matches[1];

// Simulación de decodificación básica del JWT (solo para propósitos de verificación si es admin)
$tokenParts = explode('.', $token);
if (count($tokenParts) !== 3) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Token inválido"]);
    exit;
}

$payload = json_decode(base64_decode($tokenParts[1]), true);
if (!$payload || !isset($payload['rol']) || $payload['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Acceso denegado. Se requiere rol de administrador."]);
    exit;
}

// 2. Leer datos POST JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "JSON inválido"]);
    exit;
}

$nuevoUsuario = trim($data['usuario'] ?? '');
$nuevaPassword = trim($data['password'] ?? '');

if (empty($nuevoUsuario) || empty($nuevaPassword)) {
    echo json_encode(["success" => false, "message" => "El usuario y la contraseña no pueden estar vacíos."]);
    exit;
}

// 3. Hashear nueva contraseña
$pass_hash = password_hash($nuevaPassword, PASSWORD_BCRYPT);

// 4. Actualizar tabla usuarios donde rol = 'admin'
$sql = "UPDATE usuarios SET usuario = ?, password = ? WHERE rol = 'admin'";
$stmt = mysqli_prepare($conexion, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $nuevoUsuario, $pass_hash);
    
    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo json_encode(["success" => true, "message" => "Credenciales actualizadas correctamente."]);
        } else {
            // Si el nombre de usuario y pass_hash son exactamente iguales, affected_rows = 0, pero es un éxito técnico
            // O si no hay usuario admin en absoluto (raro, porque el login fallaría)
            echo json_encode(["success" => true, "message" => "Sin cambios detectados o actualizadas correctamente."]);
        }
    } else {
        error_log("Error al actualizar credenciales: " . mysqli_error($conexion));
        echo json_encode(["success" => false, "message" => "Error interno al guardar los cambios"]);
    }
    mysqli_stmt_close($stmt);
} else {
    error_log("Error preparando update admin: " . mysqli_error($conexion));
    echo json_encode(["success" => false, "message" => "Error interno al preparar la consulta"]);
}
