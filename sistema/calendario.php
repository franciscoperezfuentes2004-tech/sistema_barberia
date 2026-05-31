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

// Parámetros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Fechas clave
$hoy_ts = strtotime(date('Y-m-d'));
$hoy_ymd = date('Y-m-d', $hoy_ts);
$max_date_ts = strtotime('+30 days', $hoy_ts);
$max_date_ymd = date('Y-m-d', $max_date_ts);

$primer_dia = sprintf("%04d-%02d-01", $anio, $mes);
$ultimo_dia_num = (int)date('t', strtotime($primer_dia));

// Cargar horario global (barbero_id = 0)
$horario_global = [];
$res_h = mysqli_query($conexion, "SELECT dia_semana, activo FROM horarios WHERE barbero_id = 0");
if ($res_h) {
    while ($row = mysqli_fetch_assoc($res_h)) {
        $horario_global[(int)$row['dia_semana']] = (int)$row['activo'];
    }
}
// Si no hay horario global configurado, por defecto asumimos todo abierto (1)
for ($i = 0; $i <= 6; $i++) {
    if (!isset($horario_global[$i])) {
        $horario_global[$i] = 1; 
    }
}

$data = [];
for ($d = 1; $d <= $ultimo_dia_num; $d++) {
    $fecha_actual = sprintf("%04d-%02d-%02d", $anio, $mes, $d);
    $ts_actual = strtotime($fecha_actual);
    
    // Obtener día de la semana (0 = Domingo, 6 = Sábado)
    $dia_semana = (int)date('w', $ts_actual);
    $esta_abierto = $horario_global[$dia_semana] === 1;

    // Estado por defecto: pasado, hoy, cerrado o disponible
    if ($fecha_actual < $hoy_ymd) {
        $estado = 'pasado';
    } elseif ($fecha_actual > $max_date_ymd) {
        // Fuera de los 30 días permitidos
        $estado = 'pasado'; // Muestra gris/inactivo
    } elseif (!$esta_abierto) {
        $estado = 'cerrado';
    } elseif ($fecha_actual === $hoy_ymd) {
        $estado = 'hoy';
    } else {
        $estado = 'disponible';
    }

    $data[] = [
        "fecha" => $fecha_actual,
        "estado" => $estado
    ];
}

mysqli_close($conexion);

ob_clean();
http_response_code(200);
echo json_encode([
    "success" => true,
    "data" => $data
]);
