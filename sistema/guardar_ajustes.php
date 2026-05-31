<?php
// Ocultamos cualquier advertencia de PHP
error_reporting(0);
ini_set('display_errors', 0);

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

$nombre_empresa = trim($_POST['nombre_empresa'] ?? $data_json['nombre_empresa'] ?? '');
$logo           = trim($_POST['logo'] ?? $data_json['logo'] ?? '');

if (empty($nombre_empresa)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "El nombre de la empresa es requerido."]);
    exit;
}

// Verificamos si ya existe un registro de ajustes
$check = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `ajustes`");
if ($check) {
    $fila = mysqli_fetch_assoc($check);
    if ((int)$fila['total'] > 0) {
        // ACTUALIZAR (UPDATE)
        $sql = "UPDATE `ajustes` SET `nombre_empresa` = ?, `logo` = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $nombre_empresa, $logo);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        // CREAR (INSERT)
        $sql = "INSERT INTO `ajustes` (`nombre_empresa`, `logo`) VALUES (?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $nombre_empresa, $logo);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

mysqli_close($conexion);

ob_clean();

if (isset($success) && $success) {
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Ajustes guardados exitosamente."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "No se pudieron guardar los ajustes."]);
}
