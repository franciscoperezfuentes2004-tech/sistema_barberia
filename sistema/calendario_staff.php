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
verificarJWT(['admin', 'barbero']);

require_once __DIR__ . "/conexion.php";

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$barbero_id = isset($_GET['barbero_id']) ? (int)$_GET['barbero_id'] : 0;

$hoy_ts = strtotime(date('Y-m-d'));
$hoy_ymd = date('Y-m-d', $hoy_ts);

$primer_dia = sprintf("%04d-%02d-01", $anio, $mes);
$ultimo_dia_num = (int)date('t', strtotime($primer_dia));
$ultimo_dia = sprintf("%04d-%02d-%02d", $anio, $mes, $ultimo_dia_num);

// 1. Cargar horario global (barbero_id = 0) y personal
$horario_global = [];
$res_h = mysqli_query($conexion, "SELECT dia_semana, activo FROM horarios WHERE barbero_id = 0");
if ($res_h) {
    while ($row = mysqli_fetch_assoc($res_h)) {
        $horario_global[(int)$row['dia_semana']] = (int)$row['activo'];
    }
}

$horario_personal = [];
if ($barbero_id > 0) {
    $stmt_hp = mysqli_prepare($conexion, "SELECT dia_semana, activo FROM horarios WHERE barbero_id = ?");
    mysqli_stmt_bind_param($stmt_hp, "i", $barbero_id);
    mysqli_stmt_execute($stmt_hp);
    $res_hp = mysqli_stmt_get_result($stmt_hp);
    if ($res_hp && mysqli_num_rows($res_hp) > 0) {
        while ($row = mysqli_fetch_assoc($res_hp)) {
            $horario_personal[(int)$row['dia_semana']] = (int)$row['activo'];
        }
    }
    mysqli_stmt_close($stmt_hp);
}

// 2. Cargar citas del mes para este barbero (o todos si es 0)
$citas_por_dia = [];
$q_cond = $barbero_id > 0 ? "barbero_id = ? AND" : "";
$q_citas = "SELECT DATE(fecha_hora) as fecha_cita, COUNT(*) as total FROM citas 
            WHERE $q_cond 
            DATE(fecha_hora) >= ? AND DATE(fecha_hora) <= ?
            AND estado != 'cancelada'
            GROUP BY DATE(fecha_hora)";
$stmt_c = mysqli_prepare($conexion, $q_citas);

if ($barbero_id > 0) {
    mysqli_stmt_bind_param($stmt_c, "iss", $barbero_id, $primer_dia, $ultimo_dia);
} else {
    mysqli_stmt_bind_param($stmt_c, "ss", $primer_dia, $ultimo_dia);
}

mysqli_stmt_execute($stmt_c);
$res_c = mysqli_stmt_get_result($stmt_c);
if ($res_c) {
    while ($row = mysqli_fetch_assoc($res_c)) {
        $citas_por_dia[$row['fecha_cita']] = (int)$row['total'];
    }
}
mysqli_stmt_close($stmt_c);

$data = [];
for ($d = 1; $d <= $ultimo_dia_num; $d++) {
    $fecha_actual = sprintf("%04d-%02d-%02d", $anio, $mes, $d);
    $ts_actual = strtotime($fecha_actual);
    $dia_semana = (int)date('w', $ts_actual);
    
    // Determinar si está abierto según horario personal o global
    if (isset($horario_personal[$dia_semana])) {
        $esta_abierto = $horario_personal[$dia_semana] === 1;
    } else {
        $esta_abierto = isset($horario_global[$dia_semana]) ? ($horario_global[$dia_semana] === 1) : true;
    }

    $tiene_citas = isset($citas_por_dia[$fecha_actual]) && $citas_por_dia[$fecha_actual] > 0;
    
    // Asignar estado
    if (!$esta_abierto) {
        $estado = 'cerrado';
    } elseif ($tiene_citas && $fecha_actual >= $hoy_ymd) {
        $estado = 'con_citas_futuras';
    } elseif ($tiene_citas && $fecha_actual < $hoy_ymd) {
        $estado = 'con_citas_pasadas';
    } elseif ($fecha_actual === $hoy_ymd) {
        $estado = 'hoy';
    } else {
        $estado = 'disponible';
    }

    $data[] = [
        "fecha" => $fecha_actual,
        "estado" => $estado,
        "dia" => $d
    ];
}

mysqli_close($conexion);
ob_clean();
echo json_encode(["success" => true, "data" => $data]);
exit;
