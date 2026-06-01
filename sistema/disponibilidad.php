<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/conexion.php";

$barbero_id = isset($_GET['barbero_id']) ? (int)$_GET['barbero_id'] : 0;
$fecha = isset($_GET['fecha']) ? mysqli_real_escape_string($conexion, trim($_GET['fecha'])) : date('Y-m-d');
$duracion = isset($_GET['duracion']) ? (int)$_GET['duracion'] : 30;

$ts_fecha = strtotime($fecha);
$dia_semana = (int)date('w', $ts_fecha);

// 1. Obtener horario global
$horario_global = ['activo' => 1, 'hora_inicio' => '09:00:00', 'hora_fin' => '20:00:00'];
$resG = mysqli_query($conexion, "SELECT activo, hora_inicio, hora_fin FROM horarios WHERE barbero_id = 0 AND dia_semana = $dia_semana");
if ($rowG = mysqli_fetch_assoc($resG)) {
    $horario_global = $rowG;
}

// 2. Obtener horario del barbero
$horario_barbero = $horario_global;
$resB = mysqli_query($conexion, "SELECT activo, hora_inicio, hora_fin FROM horarios WHERE barbero_id = $barbero_id AND dia_semana = $dia_semana");
if ($rowB = mysqli_fetch_assoc($resB)) {
    $horario_barbero = $rowB;
}

if ((int)$horario_barbero['activo'] === 0) {
    ob_clean();
    echo json_encode(["success" => true, "data" => ["slots" => []]]);
    exit;
}

// 3. Obtener citas del barbero para ese día
$citas = [];
$resC = mysqli_query($conexion, "SELECT hora_inicio, hora_fin FROM citas WHERE barbero_id = $barbero_id AND DATE(fecha_hora) = '$fecha' AND estado NOT IN ('cancelada', 'no_asistio')");
if ($resC) {
    while ($rowC = mysqli_fetch_assoc($resC)) {
        $citas[] = $rowC;
    }
}

// 4. Generar slots cada 30 min
$slots = [];
$inicio_ts = strtotime($fecha . ' ' . $horario_barbero['hora_inicio']);
$fin_ts = strtotime($fecha . ' ' . $horario_barbero['hora_fin']);

for ($t = $inicio_ts; $t < $fin_ts; $t += 30 * 60) {
    $hora_str = date('H:i', $t);
    $hora_fin_str = date('H:i', $t + 30 * 60);
    
    // Comprobar colisiones
    $disponible = true;
    foreach ($citas as $c) {
        $c_ini = substr($c['hora_inicio'], 0, 5);
        $c_fin = substr($c['hora_fin'], 0, 5);
        
        // Si el slot se superpone con una cita
        if ($hora_str >= $c_ini && $hora_str < $c_fin) {
            $disponible = false;
            break;
        }
        // Si el slot contiene una cita (ej. cita de 15 mins dentro del slot, raro pero posible)
        if ($c_ini >= $hora_str && $c_ini < $hora_fin_str) {
            $disponible = false;
            break;
        }
    }
    
    $slots[] = [
        "hora_inicio" => $hora_str,
        "disponible" => $disponible
    ];
}

ob_clean();
echo json_encode(["success" => true, "data" => ["slots" => $slots]]);
