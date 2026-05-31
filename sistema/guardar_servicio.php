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
$descripcion  = trim($_POST['descripcion'] ?? $data_json['descripcion'] ?? '');
$precio       = trim($_POST['precio'] ?? $data_json['precio'] ?? '');
$duracion_min = trim($_POST['duracion_min'] ?? $data_json['duracion_min'] ?? '');
$imagen       = trim($_POST['imagen'] ?? $data_json['imagen'] ?? '');

if (empty($nombre) || empty($precio) || empty($duracion_min)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Nombre, precio y duración son requeridos."]);
    exit;
}

$sql = "INSERT INTO `servicios` (`nombre`, `descripcion`, `precio`, `duracion_min`, `imagen`) VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    error_log("Error al preparar guardar_servicio: " . mysqli_error($conexion));
    ob_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error interno al preparar la consulta."]);
    exit;
}

mysqli_stmt_bind_param($stmt, "ssdis", $nombre, $descripcion, $precio, $duracion_min, $imagen);

if (mysqli_stmt_execute($stmt)) {
    ob_clean();
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Servicio guardado exitosamente."]);
} else {
    error_log("Error al ejecutar guardar_servicio: " . mysqli_stmt_error($stmt));
    ob_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "No se pudo guardar el servicio."]);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
