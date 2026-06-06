<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header("Content-Type: application/json; charset=UTF-8");
$allowed_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: " . $allowed_origin);
header("Access-Control-Allow-Credentials: true");
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
$stmtG = mysqli_prepare($conexion, "SELECT activo, hora_inicio, hora_fin FROM horarios WHERE barbero_id = 0 AND dia_semana = ?");
mysqli_stmt_bind_param($stmtG, "i", $dia_semana);
mysqli_stmt_execute($stmtG);
$resG = mysqli_stmt_get_result($stmtG);
if ($rowG = mysqli_fetch_assoc($resG)) {
    $horario_global = $rowG;
}
mysqli_stmt_close($stmtG);

// 2. Obtener horario del barbero
$horario_barbero = $horario_global;
$stmtB = mysqli_prepare($conexion, "SELECT activo, hora_inicio, hora_fin FROM horarios WHERE barbero_id = ? AND dia_semana = ?");
mysqli_stmt_bind_param($stmtB, "ii", $barbero_id, $dia_semana);
mysqli_stmt_execute($stmtB);
$resB = mysqli_stmt_get_result($stmtB);
if ($rowB = mysqli_fetch_assoc($resB)) {
    $horario_barbero = $rowB;
}
mysqli_stmt_close($stmtB);

if ((int)$horario_barbero['activo'] === 0) {
    ob_clean();
    echo json_encode(["success" => true, "data" => ["slots" => []]]);
    exit;
}

// 3. Obtener citas del barbero para ese día
$citas = [];
$stmtC = mysqli_prepare($conexion, "SELECT hora_inicio, hora_fin FROM citas WHERE barbero_id = ? AND DATE(fecha_hora) = ? AND estado NOT IN ('cancelada', 'no_asistio')");
mysqli_stmt_bind_param($stmtC, "is", $barbero_id, $fecha);
mysqli_stmt_execute($stmtC);
$resC = mysqli_stmt_get_result($stmtC);
if ($resC) {
    while ($rowC = mysqli_fetch_assoc($resC)) {
        $citas[] = $rowC;
    }
}
mysqli_stmt_close($stmtC);

// 4. Generar slots cada 30 min
$slots = [];
$inicio_ts = strtotime($fecha . ' ' . $horario_barbero['hora_inicio']);
$fin_ts = strtotime($fecha . ' ' . $horario_barbero['hora_fin']);

for ($t = $inicio_ts; $t < $fin_ts; $t += 30 * 60) {
    $hora_str = date('H:i', $t);
    $hora_fin_str = date('H:i', $t + 30 * 60);
    
    // Comprobar colisiones
    $disponible = true;
    $retraso_minutos = 0;
    
    foreach ($citas as $c) {
        $ts_ini = strtotime($fecha . ' ' . substr($c['hora_inicio'], 0, 5));
        $ts_fin = strtotime($fecha . ' ' . substr($c['hora_fin'], 0, 5));
        $ts_slot_ini = $t;
        $ts_slot_fin = $t + 30 * 60;
        
        // Comprobar si hay superposición
        if ($ts_ini < $ts_slot_fin && $ts_fin > $ts_slot_ini) {
            $overlap_start = max($ts_ini, $ts_slot_ini);
            $overlap_end = min($ts_fin, $ts_slot_fin);
            $overlap_mins = ($overlap_end - $overlap_start) / 60;
            
            // Si la cita previa invade este slot por <= 10 mins
            if ($ts_ini <= $ts_slot_ini && $ts_fin <= $ts_slot_ini + 10 * 60) {
                $retraso_minutos = $overlap_mins;
            } else {
                $disponible = false;
                break;
            }
        }
    }
    
    $slots[] = [
        "hora_inicio" => $hora_str,
        "disponible" => $disponible,
        "retraso_minutos" => $retraso_minutos
    ];
}

ob_clean();
echo json_encode(["success" => true, "data" => ["slots" => $slots]]);
