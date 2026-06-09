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
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/auth_middleware.php";

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    // PROTECCIÓN DE ENDPOINT ADMIN/BARBERO (Componente 2)
    verificarJWT(['admin', 'barbero']);

    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $estado = isset($data['estado']) ? trim($data['estado']) : '';
    
    // Validación estricta de estado permitidos (Componente 3)
    $estados_permitidos = ['pendiente', 'confirmada', 'en_silla', 'completada', 'cancelada', 'no_asistio'];
    if (!in_array($estado, $estados_permitidos)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Estado inválido."]);
        exit;
    }

    if ($id <= 0 || empty($estado)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Faltan datos para actualizar."]);
        exit;
    }
    
    // PREPARED STATEMENT para SQL Injection (Componente 3)
    $query = "UPDATE citas SET estado=? WHERE id=?";
    $stmt = mysqli_prepare($conexion, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "si", $estado, $id);
        if (mysqli_stmt_execute($stmt)) {
            ob_clean();
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Cita actualizada."]);
        } else {
            ob_clean();
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Error al actualizar."]);
        }
        mysqli_stmt_close($stmt);
    } else {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error interno al preparar consulta."]);
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Endpoint público (no requiere JWT)
    
    // SANITIZACIÓN XSS (Componente 4)
    $nombre = htmlspecialchars($data['nombre'] ?? '', ENT_QUOTES, 'UTF-8');
    $telefono = htmlspecialchars($data['telefono'] ?? '', ENT_QUOTES, 'UTF-8');
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
    
    // El escape de mysql no es necesario con bind_param, pero no hace daño
    $fecha_hora = $fecha . ' ' . $hora_inicio;
    
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
