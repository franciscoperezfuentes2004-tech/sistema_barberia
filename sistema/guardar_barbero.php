<?php
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/conexion.php";

$data = json_decode(file_get_contents("php://input"), true);

$nombre = $data['nombre'] ?? '';
$apellido = $data['apellido'] ?? '';
$especialidad = $data['especialidad'] ?? '';
$foto = $data['foto'] ?? '';
$activo = isset($data['activo']) ? (int)$data['activo'] : 1;

if (empty($nombre)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "El nombre es requerido."]);
    exit;
}

$id = $_GET['id'] ?? null;

if ($id) {
    // UPDATE
    $id = (int)$id;
    if (!empty($foto)) {
        $sql = "UPDATE `barberos` SET `nombre`=?, `apellido`=?, `especialidad`=?, `foto`=?, `activo`=? WHERE `id`=?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ssssii", $nombre, $apellido, $especialidad, $foto, $activo, $id);
    } else {
        $sql = "UPDATE `barberos` SET `nombre`=?, `apellido`=?, `especialidad`=?, `activo`=? WHERE `id`=?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sssii", $nombre, $apellido, $especialidad, $activo, $id);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        ob_clean();
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Barbero actualizado exitosamente."]);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "No se pudo actualizar el barbero."]);
    }
    mysqli_stmt_close($stmt);

} else {
    // INSERT
    // Crear un usuario aleatorio único para el barbero
    $usuario = strtolower(str_replace(' ', '', $nombre)) . rand(100, 999);
    $password_hash = password_hash("1234", PASSWORD_BCRYPT);
    
    $sql = "INSERT INTO `barberos` (`nombre`, `apellido`, `foto`, `especialidad`, `usuario`, `password`, `activo`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    
    if (!$stmt) {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error interno al preparar la consulta."]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "ssssssi", $nombre, $apellido, $foto, $especialidad, $usuario, $password_hash, $activo);
    
    if (mysqli_stmt_execute($stmt)) {
        ob_clean();
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Barbero guardado exitosamente.", "data" => ["username" => $usuario]]);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "No se pudo guardar el barbero."]);
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conexion);
