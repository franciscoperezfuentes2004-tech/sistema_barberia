<?php
error_reporting(0);
ini_set('display_errors', 0);

// Cabeceras estrictas JSON y CORS
header("Content-Type: application/json; charset=UTF-8");
$allowed_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: " . $allowed_origin);
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Método no permitido"]);
    exit;
}

require_once __DIR__ . "/conexion.php";

$data_json = json_decode(file_get_contents("php://input"), true);

$nombre = htmlspecialchars(trim($_POST['nombre'] ?? $data_json['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$estrellas = intval($_POST['estrellas'] ?? $data_json['estrellas'] ?? 5);
$comentario = htmlspecialchars(trim($_POST['comentario'] ?? $data_json['comentario'] ?? ''), ENT_QUOTES, 'UTF-8');

if (empty($nombre) || empty($comentario)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "El nombre y el comentario son requeridos"]);
    exit;
}

if ($estrellas < 1) $estrellas = 1;
if ($estrellas > 5) $estrellas = 5;

// Mapeo a las columnas correctas en la tabla 'resenas'
// nombre -> cliente_nombre
// estrellas -> calificacion
// comentario -> comentario

$sql = "INSERT INTO `resenas` (`cliente_nombre`, `calificacion`, `comentario`, `activo`) VALUES (?, ?, ?, 1)";
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    error_log("Error al preparar guardar_resena: " . mysqli_error($conexion));
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error interno al guardar la reseña."]);
    exit;
}

mysqli_stmt_bind_param($stmt, "sis", $nombre, $estrellas, $comentario);

if (mysqli_stmt_execute($stmt)) {
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Reseña guardada exitosamente"]);
} else {
    error_log("Error al ejecutar guardar_resena: " . mysqli_stmt_error($stmt));
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "No se pudo guardar la reseña."]);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>
