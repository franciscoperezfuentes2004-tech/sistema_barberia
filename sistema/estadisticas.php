<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/conexion.php";

$desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-t');

// Solo citas completadas o agendadas (no canceladas) para contar como "actividad",
// pero para "ingresos" solo consideramos las pasadas o "completadas".
// Asumiremos estado != 'cancelada'
$where_dates = "DATE(c.fecha_hora) >= '" . mysqli_real_escape_string($conexion, $desde) . "' 
                AND DATE(c.fecha_hora) <= '" . mysqli_real_escape_string($conexion, $hasta) . "' 
                AND c.estado != 'cancelada'";

// 1. Tarjeta: Total Citas e Ingresos
$q_totales = "SELECT COUNT(*) as total_citas, SUM(c.precio_total) as total_ingresos 
              FROM citas c WHERE $where_dates";
$res_totales = mysqli_query($conexion, $q_totales);
$totales = mysqli_fetch_assoc($res_totales);

$total_citas = (int)$totales['total_citas'];
$total_ingresos = (float)$totales['total_ingresos'];

// 2. Tarjeta: Mejor Barbero
$q_mejor = "SELECT b.nombre, COUNT(*) as conteo 
            FROM citas c 
            JOIN barberos b ON c.barbero_id = b.id 
            WHERE $where_dates 
            GROUP BY c.barbero_id 
            ORDER BY conteo DESC LIMIT 1";
$res_mejor = mysqli_query($conexion, $q_mejor);
$mejor_barbero = "N/A";
if ($res_mejor && mysqli_num_rows($res_mejor) > 0) {
    $row_mejor = mysqli_fetch_assoc($res_mejor);
    $mejor_barbero = $row_mejor['nombre'] . " (" . $row_mejor['conteo'] . " citas)";
}

// 3. Gráfica: Citas por Día
$q_grafica = "SELECT DATE(c.fecha_hora) as fecha, COUNT(*) as conteo 
              FROM citas c WHERE $where_dates 
              GROUP BY DATE(c.fecha_hora) ORDER BY DATE(c.fecha_hora) ASC";
$res_grafica = mysqli_query($conexion, $q_grafica);

$grafica_map = [];
if ($res_grafica) {
    while ($row = mysqli_fetch_assoc($res_grafica)) {
        $grafica_map[$row['fecha']] = (int)$row['conteo'];
    }
}

$grafica = [];
$start_time = strtotime($desde);
$end_time = strtotime($hasta);
for ($t = $start_time; $t <= $end_time; $t += 86400) {
    $f = date('Y-m-d', $t);
    $grafica[] = [
        "fecha" => $f,
        "total" => isset($grafica_map[$f]) ? $grafica_map[$f] : 0
    ];
}

// 4. Tabla Detallada
$q_tabla = "SELECT c.id, c.cliente_nombre, c.fecha_hora, c.estado, c.precio_total, 
                   b.nombre as barbero_nombre, s.nombre as servicio_nombre
            FROM citas c
            LEFT JOIN barberos b ON c.barbero_id = b.id
            LEFT JOIN servicios s ON c.servicio_id = s.id
            WHERE $where_dates
            ORDER BY c.fecha_hora DESC";
$res_tabla = mysqli_query($conexion, $q_tabla);
$tabla = [];
if ($res_tabla) {
    while ($row = mysqli_fetch_assoc($res_tabla)) {
        $tabla[] = [
            "id" => $row['id'],
            "cliente" => $row['cliente_nombre'],
            "fecha_hora" => $row['fecha_hora'],
            "estado" => $row['estado'],
            "precio" => (float)$row['precio_total'],
            "barbero" => $row['barbero_nombre'],
            "servicio" => $row['servicio_nombre']
        ];
    }
}

mysqli_close($conexion);
ob_clean();

echo json_encode([
    "success" => true,
    "resumen" => [
        "total_citas" => $total_citas,
        "total_ingresos" => $total_ingresos,
        "mejor_barbero" => $mejor_barbero
    ],
    "grafica" => $grafica,
    "tabla" => $tabla
]);
exit;
