<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/conexion.php";

$data = json_decode(file_get_contents("php://input"), true);
$horario = $data['horario'] ?? [];
$sync_all = $data['sync_all'] ?? false;
$is_global = isset($_GET['global']) && $_GET['global'] == 1;
$barbero_id = $_GET['barbero_id'] ?? ($is_global ? 0 : null);

if ($barbero_id === null || empty($horario)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Datos de horario inválidos."]);
    exit;
}

// Limpiar el horario existente para el barbero destino (0 para global)
$barbero_id = (int)$barbero_id;
$sql_del = "DELETE FROM `horarios` WHERE barbero_id = ?";
$stmt_del = mysqli_prepare($conexion, $sql_del);
mysqli_stmt_bind_param($stmt_del, "i", $barbero_id);
mysqli_stmt_execute($stmt_del);
mysqli_stmt_close($stmt_del);

// Insertar el nuevo horario
$sql_in = "INSERT INTO `horarios` (`barbero_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `activo`) VALUES (?, ?, ?, ?, ?)";
$stmt_in = mysqli_prepare($conexion, $sql_in);

foreach ($horario as $d) {
    $dia = (int)$d['dia_semana'];
    $hi = $d['hora_inicio'] ?: '09:00';
    $hf = $d['hora_fin'] ?: '18:00';
    $act = (int)$d['activo'];
    
    mysqli_stmt_bind_param($stmt_in, "iissi", $barbero_id, $dia, $hi, $hf, $act);
    mysqli_stmt_execute($stmt_in);
}
mysqli_stmt_close($stmt_in);

// Sincronizar todos si aplica (solo si es global)
if ($is_global && $sync_all) {
    // Buscar todos los barberos existentes
    $res_b = mysqli_query($conexion, "SELECT id FROM `barberos`");
    if ($res_b) {
        $stmt_del_all = mysqli_prepare($conexion, "DELETE FROM `horarios` WHERE barbero_id = ?");
        $stmt_in_all = mysqli_prepare($conexion, "INSERT INTO `horarios` (`barbero_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `activo`) VALUES (?, ?, ?, ?, ?)");
        
        while ($b = mysqli_fetch_assoc($res_b)) {
            $bid = (int)$b['id'];
            // Eliminar actual
            mysqli_stmt_bind_param($stmt_del_all, "i", $bid);
            mysqli_stmt_execute($stmt_del_all);
            
            // Insertar global
            foreach ($horario as $d) {
                $dia = (int)$d['dia_semana'];
                $hi = $d['hora_inicio'] ?: '09:00';
                $hf = $d['hora_fin'] ?: '18:00';
                $act = (int)$d['activo'];
                
                mysqli_stmt_bind_param($stmt_in_all, "iissi", $bid, $dia, $hi, $hf, $act);
                mysqli_stmt_execute($stmt_in_all);
            }
        }
        mysqli_stmt_close($stmt_del_all);
        mysqli_stmt_close($stmt_in_all);
    }
}

mysqli_close($conexion);
ob_clean();

http_response_code(200);
echo json_encode(["success" => true, "message" => "Horario guardado."]);
