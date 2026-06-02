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

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $estado = isset($data['estado']) ? mysqli_real_escape_string($conexion, trim($data['estado'])) : '';
    
    if ($id <= 0 || empty($estado)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Faltan datos para actualizar."]);
        exit;
    }
    
    $query = "UPDATE citas SET estado='$estado' WHERE id=$id";
    if (mysqli_query($conexion, $query)) {
        ob_clean();
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Cita actualizada."]);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al actualizar."]);
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $data['nombre'] ?? '';
    $telefono = $data['telefono'] ?? '';
    $barbero_id = (int)($data['barbero_id'] ?? 0);
    $servicios = $data['servicios'] ?? [];
    $fecha = $data['fecha'] ?? '';
    $hora_inicio = $data['hora_inicio'] ?? '';
    
    if (empty($nombre) || empty($telefono) || $barbero_id <= 0 || empty($servicios) || empty($fecha) || empty($hora_inicio)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Faltan datos requeridos para la reserva."]);
        exit;
    }
    
    // Calcular duracion y precio total de los servicios
    $duracion_total = 0;
    $precio_total = 0;
    if (is_array($servicios) && count($servicios) > 0) {
        $ids = implode(',', array_map('intval', $servicios));
        $resSvc = mysqli_query($conexion, "SELECT SUM(duracion_min) as total_min, SUM(precio) as total_precio FROM servicios WHERE id IN ($ids)");
        if ($resSvc && $rowSvc = mysqli_fetch_assoc($resSvc)) {
            $duracion_total = (int)$rowSvc['total_min'];
            $precio_total = (float)$rowSvc['total_precio'];
        }
    }
    if ($duracion_total <= 0) $duracion_total = 30;
    
    $hora_inicio_format = date('H:i:s', strtotime($hora_inicio));
    $hora_fin_format = date('H:i:s', strtotime("+$duracion_total minutes", strtotime($fecha . ' ' . $hora_inicio)));
    
    $servicios_json = json_encode($servicios);
    $nombre = mysqli_real_escape_string($conexion, $nombre);
    $telefono = mysqli_real_escape_string($conexion, $telefono);
    $fecha_hora = mysqli_real_escape_string($conexion, $fecha . ' ' . $hora_inicio);
    
    $sql = "INSERT INTO citas (`cliente_nombre`, `cliente_telefono`, `barbero_id`, `servicios_ids`, `fecha_hora`, `hora_inicio`, `hora_fin`, `estado`, `precio_total`) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssissssd", $nombre, $telefono, $barbero_id, $servicios_json, $fecha_hora, $hora_inicio_format, $hora_fin_format, $precio_total);
        if (mysqli_stmt_execute($stmt)) {
            ob_clean();
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Cita guardada."]);
        } else {
            ob_clean();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error al insertar en DB."]);
        }
        mysqli_stmt_close($stmt);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de BD."]);
    }
}

mysqli_close($conexion);
