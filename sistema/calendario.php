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

// Parámetros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Fechas clave
$hoy_ymd = date('Y-m-d');
$primer_dia = sprintf("%04d-%02d-01", $anio, $mes);
$ultimo_dia_num = (int)date('t', strtotime($primer_dia));

$data = [];
for ($d = 1; $d <= $ultimo_dia_num; $d++) {
    $fecha_actual = sprintf("%04d-%02d-%02d", $anio, $mes, $d);
    
    // Estado por defecto: pasado, hoy, o disponible
    if ($fecha_actual < $hoy_ymd) {
        $estado = 'pasado';
    } elseif ($fecha_actual === $hoy_ymd) {
        $estado = 'hoy';
    } else {
        // Podríamos comprobar horarios en la DB para ver si está lleno
        // Pero para permitir selección, lo marcamos disponible
        $estado = 'disponible';
    }

    $data[] = [
        "fecha" => $fecha_actual,
        "estado" => $estado
    ];
}

ob_clean();
http_response_code(200);
echo json_encode([
    "success" => true,
    "data" => $data
]);
