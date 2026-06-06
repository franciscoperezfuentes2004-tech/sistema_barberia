<?php
/**
 * Actualiza las credenciales de administrador
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'conexion.php';
/** @var mysqli $conexion */

require_once 'auth_middleware.php';

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit;
}

// PROTECCIÓN DE ENDPOINT (Componente 2)
// Verificar JWT y requerir rol de administrador
$payload = verificarJWT(['admin']);

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
