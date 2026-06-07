<?php
// Ocultamos cualquier advertencia de PHP para proteger la respuesta JSON
error_reporting(0);
ini_set('display_errors', 0);

// Controlamos la salida del buffer
ob_start();

// Cabeceras estrictas JSON y CORS
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

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    ob_clean();
    http_response_code(405);
    echo json_encode([]); // Respuesta segura sin corromper iteradores
    exit;
}

require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/auth_middleware.php";

$tablas_permitidas = ['servicios', 'usuarios', 'citas', 'galeria', 'ajustes', 'barberos', 'horarios', 'resenas'];
$tabla = $_GET["tabla"] ?? "";

if (!in_array($tabla, $tablas_permitidas)) {
    ob_clean();
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$es_publico = false;
if (in_array($tabla, ['ajustes', 'servicios', 'galeria', 'resenas', 'horarios'])) {
    $es_publico = true;
} else if ($tabla === 'barberos' && isset($_GET['activos']) && $_GET['activos'] == '1') {
    $es_publico = true;
}

if (!$es_publico) {
    verificarJWT();
}

// 1. Consulta puramente limpia y robusta. 
// SE ELIMINAN todos los filtros conflictivos como "AND activo = 1" para evitar fallos de base de datos.
$sql = "SELECT * FROM `" . $tabla . "` WHERE 1=1";
$params = [];
$types = "";

if ($tabla === 'barberos') {
    if (isset($_GET['activos']) && $_GET['activos'] == '1') {
        $sql .= " AND activo = 1";
    }
}
if ($tabla === 'horarios') {
    if (isset($_GET['global']) && $_GET['global'] == '1') {
        $sql .= " AND barbero_id = 0";
    } elseif (isset($_GET['barbero_id']) && trim($_GET['barbero_id']) !== '') {
        $sql .= " AND barbero_id = ?";
        $params[] = intval($_GET['barbero_id']);
        $types .= "i";
    }
}

if ($tabla === 'citas') {
    if (isset($_GET['barbero_id']) && trim($_GET['barbero_id']) !== '') {
        $sql .= " AND barbero_id = ?";
        $params[] = intval($_GET['barbero_id']);
        $types .= "i";
    }
    if (isset($_GET['fecha']) && trim($_GET['fecha']) !== '') {
        $sql .= " AND DATE(fecha_hora) = ?";
        $params[] = trim($_GET['fecha']);
        $types .= "s";
    }
}

if ($tabla !== 'ajustes' && $tabla !== 'horarios') {
    $sql .= " ORDER BY id DESC";
}

$stmt = mysqli_prepare($conexion, $sql);
if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$datos = [];
if ($resultado) {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        // Calcular slots si se pide barberos
        if ($tabla === 'barberos') {
            $f = isset($_GET['fecha']) && trim($_GET['fecha']) !== '' ? mysqli_real_escape_string($conexion, trim($_GET['fecha'])) : date('Y-m-d');
            $dia_sem = (int)date('w', strtotime($f));
            $b_id = (int)$fila['id'];
            
            // Ver si trabaja ese día
            $qH = mysqli_query($conexion, "SELECT activo, hora_inicio, hora_fin FROM horarios WHERE barbero_id=$b_id AND dia_semana=$dia_sem");
            $trabaja = false;
            if ($qH && $rowH = mysqli_fetch_assoc($qH)) {
                $trabaja = (int)$rowH['activo'] === 1;
            } else {
                // Fallback global
                $qG = mysqli_query($conexion, "SELECT activo, hora_inicio, hora_fin FROM horarios WHERE barbero_id=0 AND dia_semana=$dia_sem");
                if ($qG && $rowG = mysqli_fetch_assoc($qG)) {
                    $trabaja = (int)$rowG['activo'] === 1;
                } else {
                    $trabaja = true;
                }
            }

            $slots_disponibles = 0;
            if ($trabaja) {
                $h_ini = $rowH['hora_inicio'] ?? ($rowG['hora_inicio'] ?? '09:00:00');
                $h_fin = $rowH['hora_fin'] ?? ($rowG['hora_fin'] ?? '20:00:00');
                
                $citas = [];
                $qC = mysqli_query($conexion, "SELECT hora_inicio, hora_fin FROM citas WHERE barbero_id=$b_id AND DATE(fecha_hora)='$f' AND estado NOT IN ('cancelada', 'no_asistio')");
                if ($qC) {
                    while ($rC = mysqli_fetch_assoc($qC)) $citas[] = $rC;
                }
                
                $inicio_ts = strtotime($f . ' ' . $h_ini);
                $fin_ts = strtotime($f . ' ' . $h_fin);
                $hoy_ymd = date('Y-m-d');
                $ahora_hi = date('H:i');
                
                for ($t = $inicio_ts; $t < $fin_ts; $t += 30 * 60) {
                    $hora_str = date('H:i', $t);
                    $hora_fin_str = date('H:i', $t + 30 * 60);
                    
                    // Si es hoy, ignorar slots pasados
                    if ($f === $hoy_ymd && $hora_str <= $ahora_hi) continue;
                    
                    $disp = true;
                    foreach ($citas as $c) {
                        $ts_ini = strtotime($f . ' ' . substr($c['hora_inicio'], 0, 5));
                        $ts_fin = strtotime($f . ' ' . substr($c['hora_fin'], 0, 5));
                        $ts_slot_ini = $t;
                        $ts_slot_fin = $t + 30 * 60;
                        
                        if ($ts_ini < $ts_slot_fin && $ts_fin > $ts_slot_ini) {
                            if (!($ts_ini <= $ts_slot_ini && $ts_fin <= $ts_slot_ini + 10 * 60)) {
                                $disp = false;
                                break;
                            }
                        }
                    }
                    if ($disp) $slots_disponibles++;
                }
            }
            $fila['slots_disponibles'] = $slots_disponibles;
            $fila['disponible_hoy'] = $slots_disponibles > 0;
        }
        $datos[] = $fila;
    }
    mysqli_free_result($resultado);
}

mysqli_close($conexion);

// 3. Vaciado del buffer final
ob_clean();

// 4. Formateo especial según la tabla
$output = $datos;

// Para 'ajustes': el frontend espera un objeto plano (no array) con aliases
if ($tabla === 'ajustes' && count($datos) > 0) {
    $row = $datos[0];
    $output = [
        'site_name'      => $row['nombre_empresa'] ?? '',
        'site_logo'      => $row['logo'] ?? '',
        'site_phone'     => $row['site_phone'] ?? '',
        'site_email'     => $row['site_email'] ?? '',
        'site_address'   => $row['site_address'] ?? '',
        'site_map'       => $row['site_map'] ?? '',
        'site_instagram' => $row['site_instagram'] ?? '',
        'site_facebook'  => $row['site_facebook'] ?? '',
        'site_tiktok'    => $row['site_tiktok'] ?? '',
        'site_slogan'    => $row['site_slogan'] ?? '',
        'site_hero_desc' => $row['site_hero_desc'] ?? '',
        'stat_exp'       => $row['stat_exp'] ?? '',
        'stat_clientes'  => $row['stat_clientes'] ?? '',
        'site_hero_bg'   => $row['site_hero_bg'] ?? '',
        'horario_apertura' => $row['horario_apertura'] ?? '09:00:00',
        'horario_cierre'   => $row['horario_cierre'] ?? '20:00:00'
    ];
}

// Para 'servicios': aliasear el campo imagen para que el frontend lo encuentre
if ($tabla === 'servicios') {
    foreach ($output as &$svc) {
        if (!empty($svc['imagen']) && strpos($svc['imagen'], 'data:') === 0) {
            $svc['imagen_b64'] = $svc['imagen'];
        } else {
            $svc['imagen_url'] = $svc['imagen'] ?? '';
        }
    }
    unset($svc);
}

echo json_encode([
    "success" => true,
    "data"    => $output
], JSON_UNESCAPED_UNICODE);
