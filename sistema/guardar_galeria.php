<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header("Content-Type: application/json; charset=UTF-8");
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/auth_middleware.php';
verificarJWT();

require_once __DIR__ . "/conexion.php";

$data = json_decode(file_get_contents("php://input"), true);
$titulo = htmlspecialchars(trim($data['titulo'] ?? ''), ENT_QUOTES, 'UTF-8');
$imagen = $data['imagen'] ?? '';

if (empty($imagen)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "La imagen es requerida."]);
    exit;
}

$sql = "INSERT INTO `galeria` (`titulo`, `ruta_imagen`) VALUES (?, ?)";
$stmt = mysqli_prepare($conexion, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $titulo, $imagen);
    if (mysqli_stmt_execute($stmt)) {
        ob_clean();
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Imagen añadida a la galería."]);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al guardar en BD."]);
    }
    mysqli_stmt_close($stmt);
} else {
    ob_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error interno al preparar inserción."]);
}

mysqli_close($conexion);
