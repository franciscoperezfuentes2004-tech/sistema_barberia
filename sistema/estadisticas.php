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
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/auth_middleware.php';
verificarJWT();

require_once __DIR__ . "/conexion.php";

$desde = isset($_GET['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$hasta = isset($_GET['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta']) ? $_GET['hasta'] : date('Y-m-t');

// Solo citas completadas o agendadas (no canceladas) para contar como "actividad",
// pero para "ingresos" solo consideramos las pasadas o "completadas".
// Asumiremos estado != 'cancelada'

// 1. Tarjeta: Total Citas e Ingresos
$q_totales = "SELECT COUNT(*) as total_citas, SUM(c.precio_total) as total_ingresos 
              FROM citas c WHERE DATE(c.fecha_hora) >= ? 
              AND DATE(c.fecha_hora) <= ? 
              AND c.estado != 'cancelada'";
$stmt_tot = mysqli_prepare($conexion, $q_totales);
mysqli_stmt_bind_param($stmt_tot, "ss", $desde, $hasta);
mysqli_stmt_execute($stmt_tot);
$res_totales = mysqli_stmt_get_result($stmt_tot);
$totales = mysqli_fetch_assoc($res_totales);

$total_citas = (int)$totales['total_citas'];
$total_ingresos = (float)$totales['total_ingresos'];
mysqli_stmt_close($stmt_tot);

// 2. Tarjeta: Mejor Barbero
$q_mejor = "SELECT b.nombre, COUNT(*) as conteo 
            FROM citas c 
            JOIN barberos b ON c.barbero_id = b.id 
            WHERE DATE(c.fecha_hora) >= ? AND DATE(c.fecha_hora) <= ? AND c.estado != 'cancelada'
            GROUP BY c.barbero_id 
            ORDER BY conteo DESC LIMIT 1";
$stmt_mejor = mysqli_prepare($conexion, $q_mejor);
mysqli_stmt_bind_param($stmt_mejor, "ss", $desde, $hasta);
mysqli_stmt_execute($stmt_mejor);
$res_mejor = mysqli_stmt_get_result($stmt_mejor);
$mejor_barbero = "N/A";
if ($res_mejor && mysqli_num_rows($res_mejor) > 0) {
    $row_mejor = mysqli_fetch_assoc($res_mejor);
    $mejor_barbero = $row_mejor['nombre'] . " (" . $row_mejor['conteo'] . " citas)";
}
mysqli_stmt_close($stmt_mejor);

// 3. Gráfica: Citas por Día
$q_grafica = "SELECT DATE(c.fecha_hora) as fecha, COUNT(*) as conteo 
              FROM citas c WHERE DATE(c.fecha_hora) >= ? AND DATE(c.fecha_hora) <= ? AND c.estado != 'cancelada' 
              GROUP BY DATE(c.fecha_hora) ORDER BY DATE(c.fecha_hora) ASC";
$stmt_grafica = mysqli_prepare($conexion, $q_grafica);
mysqli_stmt_bind_param($stmt_grafica, "ss", $desde, $hasta);
mysqli_stmt_execute($stmt_grafica);
$res_grafica = mysqli_stmt_get_result($stmt_grafica);

$grafica_map = [];
if ($res_grafica) {
    while ($row = mysqli_fetch_assoc($res_grafica)) {
        $grafica_map[$row['fecha']] = (int)$row['conteo'];
    }
}
mysqli_stmt_close($stmt_grafica);

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
$q_servs = mysqli_query($conexion, "SELECT id, nombre FROM servicios");
$serv_map = [];
if ($q_servs) {
    while ($s = mysqli_fetch_assoc($q_servs)) {
        $serv_map[$s['id']] = $s['nombre'];
    }
}

$q_tabla = "SELECT c.id, c.cliente_nombre, c.fecha_hora, c.estado, c.precio_total, c.servicios_ids,
                   b.nombre as barbero_nombre
            FROM citas c
            LEFT JOIN barberos b ON c.barbero_id = b.id
            WHERE DATE(c.fecha_hora) >= ? AND DATE(c.fecha_hora) <= ? AND c.estado != 'cancelada'
            ORDER BY c.fecha_hora DESC";
$stmt_tabla = mysqli_prepare($conexion, $q_tabla);
mysqli_stmt_bind_param($stmt_tabla, "ss", $desde, $hasta);
mysqli_stmt_execute($stmt_tabla);
$res_tabla = mysqli_stmt_get_result($stmt_tabla);
$tabla = [];
if ($res_tabla) {
    while ($row = mysqli_fetch_assoc($res_tabla)) {
        $s_nombres = [];
        $raw_json = $row['servicios_ids'];
        $ids = json_decode($raw_json, true);
        if ($ids === null && !empty($raw_json)) {
            $ids = json_decode(stripslashes($raw_json), true);
        }
        
        if (is_array($ids)) {
            foreach($ids as $sid) {
                if(isset($serv_map[$sid])) $s_nombres[] = $serv_map[$sid];
            }
        }
        $servicio_nombre = count($s_nombres) > 0 ? implode(', ', $s_nombres) : 'N/A';
        
        $tabla[] = [
            "id" => $row['id'],
            "cliente" => $row['cliente_nombre'],
            "fecha_hora" => $row['fecha_hora'],
            "estado" => $row['estado'],
            "precio" => (float)$row['precio_total'],
            "barbero" => $row['barbero_nombre'],
            "servicio" => $servicio_nombre
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
