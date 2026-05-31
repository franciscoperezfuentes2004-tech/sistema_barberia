<?php
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

// El frontend envía FormData (multipart), así que usamos $_POST y $_FILES
$id           = $_POST['id'] ?? '';
$nombre       = trim($_POST['nombre'] ?? '');
$descripcion  = trim($_POST['descripcion'] ?? '');
$precio       = trim($_POST['precio'] ?? '');
$duracion_min = trim($_POST['duracion_min'] ?? '');

if (empty($nombre) || empty($precio) || empty($duracion_min)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Nombre, precio y duración son requeridos."]);
    exit;
}

// Procesar imagen subida: convertir a Base64 para almacenar en LONGTEXT
$imagen_b64 = '';
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $tmpPath = $_FILES['imagen']['tmp_name'];
    $mimeType = $_FILES['imagen']['type'] ?: 'image/jpeg';
    $rawData = file_get_contents($tmpPath);
    $imagen_b64 = 'data:' . $mimeType . ';base64,' . base64_encode($rawData);
}

if (!empty($id)) {
    // ═══════════════════ UPDATE ═══════════════════
    $id = (int)$id;

    $updates = [];
    $types   = "";
    $params  = [];

    $updates[] = "`nombre` = ?";       $types .= "s"; $params[] = $nombre;
    $updates[] = "`descripcion` = ?";  $types .= "s"; $params[] = $descripcion;
    $updates[] = "`precio` = ?";       $types .= "d"; $params[] = $precio;
    $updates[] = "`duracion_min` = ?"; $types .= "i"; $params[] = $duracion_min;

    if (!empty($imagen_b64)) {
        $updates[] = "`imagen` = ?";
        $types .= "s";
        $params[] = $imagen_b64;
    }

    $types .= "i";
    $params[] = $id;

    $sql = "UPDATE `servicios` SET " . implode(", ", $updates) . " WHERE `id` = ?";
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al preparar consulta."]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        ob_clean();
        echo json_encode(["success" => true, "message" => "Servicio actualizado exitosamente."]);
    } else {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "No se pudo actualizar: $err"]);
    }

} else {
    // ═══════════════════ INSERT ═══════════════════
    $imagen_final = !empty($imagen_b64) ? $imagen_b64 : '../assets/img/services/fade.png';

    $sql = "INSERT INTO `servicios` (`nombre`, `descripcion`, `precio`, `duracion_min`, `imagen`) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al preparar consulta."]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "ssdis", $nombre, $descripcion, $precio, $duracion_min, $imagen_final);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        ob_clean();
        echo json_encode(["success" => true, "message" => "Servicio guardado exitosamente."]);
    } else {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "No se pudo guardar: $err"]);
    }
}
