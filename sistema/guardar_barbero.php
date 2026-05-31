<?php
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

require_once __DIR__ . "/conexion.php";

$data_json = json_decode(file_get_contents("php://input"), true);

// Lectura híbrida
$nombre       = trim($_POST['nombre'] ?? $data_json['nombre'] ?? '');
$apellido     = trim($_POST['apellido'] ?? $data_json['apellido'] ?? '');
$foto         = trim($_POST['foto'] ?? $data_json['foto'] ?? '');
$especialidad = trim($_POST['especialidad'] ?? $data_json['especialidad'] ?? '');
$usuario      = trim($_POST['usuario'] ?? $data_json['usuario'] ?? '');
$password     = trim($_POST['password'] ?? $data_json['password'] ?? '');

if (empty($nombre)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "El nombre es requerido."]);
    exit;
}

$hash_password = null;
if (!empty($password)) {
    $hash_password = password_hash($password, PASSWORD_BCRYPT);
}

$sql = "INSERT INTO `barberos` (`nombre`, `apellido`, `foto`, `especialidad`, `usuario`, `password`) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    error_log("Error al preparar guardar_barbero: " . mysqli_error($conexion));
    ob_clean();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno al preparar la consulta."]);
    exit;
}

mysqli_stmt_bind_param($stmt, "ssssss", $nombre, $apellido, $foto, $especialidad, $usuario, $hash_password);

if (mysqli_stmt_execute($stmt)) {
    ob_clean();
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Barbero guardado exitosamente."]);
} else {
    error_log("Error al ejecutar guardar_barbero: " . mysqli_stmt_error($stmt));
    ob_clean();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "No se pudo guardar el barbero."]);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
