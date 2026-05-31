<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
    ob_clean();
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit;
}

require_once __DIR__ . "/conexion.php";

$tablas_permitidas = ['servicios', 'usuarios', 'citas', 'galeria', 'ajustes', 'barberos', 'horarios', 'resenas'];
$tabla = $_GET["tabla"] ?? "";
$id = $_GET["id"] ?? null;

if (!in_array($tabla, $tablas_permitidas) || !$id) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Tabla no permitida o ID faltante."]);
    exit;
}

$id = (int)$id;
$sql = "DELETE FROM `" . $tabla . "` WHERE id = ?";
$stmt = mysqli_prepare($conexion, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        ob_clean();
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Registro eliminado."]);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al eliminar."]);
    }
    mysqli_stmt_close($stmt);
} else {
    ob_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error interno al preparar eliminación."]);
}

mysqli_close($conexion);
