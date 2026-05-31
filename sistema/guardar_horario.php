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
$barbero_id  = trim($_POST['barbero_id'] ?? $data_json['barbero_id'] ?? '');
$dia_semana  = trim($_POST['dia_semana'] ?? $data_json['dia_semana'] ?? '');
$hora_inicio = trim($_POST['hora_inicio'] ?? $data_json['hora_inicio'] ?? '');
$hora_fin    = trim($_POST['hora_fin'] ?? $data_json['hora_fin'] ?? '');

if ($barbero_id === '' || $dia_semana === '' || empty($hora_inicio) || empty($hora_fin)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Todos los campos son requeridos para el horario."]);
    exit;
}

$sql = "INSERT INTO `horarios` (`barbero_id`, `dia_semana`, `hora_inicio`, `hora_fin`) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    error_log("Error al preparar guardar_horario: " . mysqli_error($conexion));
    ob_clean();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno al preparar la consulta."]);
    exit;
}

mysqli_stmt_bind_param($stmt, "iiss", $barbero_id, $dia_semana, $hora_inicio, $hora_fin);

if (mysqli_stmt_execute($stmt)) {
    ob_clean();
    http_response_code(200);
    echo json_encode(["status" => "success", "message" => "Horario guardado exitosamente."]);
} else {
    error_log("Error al ejecutar guardar_horario: " . mysqli_stmt_error($stmt));
    ob_clean();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "No se pudo guardar el horario."]);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
