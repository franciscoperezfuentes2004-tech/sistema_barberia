<?php
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/conexion.php";

$data_json = json_decode(file_get_contents("php://input"), true);

// Todos los campos posibles que envía el CMS
$campos = [
    'nombre_empresa' => trim($_POST['site_name'] ?? $data_json['site_name'] ?? ''),
    'site_phone' => trim($_POST['site_phone'] ?? $data_json['site_phone'] ?? ''),
    'site_email' => trim($_POST['site_email'] ?? $data_json['site_email'] ?? ''),
    'site_address' => trim($_POST['site_address'] ?? $data_json['site_address'] ?? ''),
    'site_map' => trim($_POST['site_map'] ?? $data_json['site_map'] ?? ''),
    'site_instagram' => trim($_POST['site_instagram'] ?? $data_json['site_instagram'] ?? ''),
    'site_facebook' => trim($_POST['site_facebook'] ?? $data_json['site_facebook'] ?? ''),
    'site_tiktok' => trim($_POST['site_tiktok'] ?? $data_json['site_tiktok'] ?? ''),
    'site_slogan' => trim($_POST['site_slogan'] ?? $data_json['site_slogan'] ?? ''),
    'site_hero_desc' => trim($_POST['site_hero_desc'] ?? $data_json['site_hero_desc'] ?? ''),
    'stat_exp' => trim($_POST['stat_exp'] ?? $data_json['stat_exp'] ?? ''),
    'stat_clientes' => trim($_POST['stat_clientes'] ?? $data_json['stat_clientes'] ?? ''),
    'site_lang' => trim($_POST['site_lang'] ?? $data_json['site_lang'] ?? 'es')
];

// Opcionales (Base64 largos)
$logo = trim($_POST['site_logo'] ?? $data_json['site_logo'] ?? '');
$hero_bg = trim($_POST['site_hero_bg'] ?? $data_json['site_hero_bg'] ?? '');

$check = mysqli_query($conexion, "SELECT COUNT(*) as total FROM `ajustes`");
if ($check) {
    $fila = mysqli_fetch_assoc($check);
    if ((int)$fila['total'] > 0) {
        // UPDATE
        $updates = [];
        $types = "";
        $params = [];
        foreach ($campos as $col => $val) {
            $updates[] = "`$col` = ?";
            $types .= "s";
            $params[] = $val;
        }
        if (!empty($logo)) {
            $updates[] = "`logo` = ?";
            $types .= "s";
            $params[] = $logo;
        }
        if (!empty($hero_bg)) {
            $updates[] = "`site_hero_bg` = ?";
            $types .= "s";
            $params[] = $hero_bg;
        }
        
        $sql = "UPDATE `ajustes` SET " . implode(", ", $updates);
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    } else {
        // INSERT
        $cols = array_keys($campos);
        $vals = array_values($campos);
        if (!empty($logo)) {
            $cols[] = 'logo';
            $vals[] = $logo;
        }
        if (!empty($hero_bg)) {
            $cols[] = 'site_hero_bg';
            $vals[] = $hero_bg;
        }
        
        $placeholders = implode(", ", array_fill(0, count($cols), "?"));
        $sql = "INSERT INTO `ajustes` (`" . implode("`, `", $cols) . "`) VALUES ($placeholders)";
        $stmt = mysqli_prepare($conexion, $sql);
        if ($stmt) {
            $types = str_repeat("s", count($vals));
            mysqli_stmt_bind_param($stmt, $types, ...$vals);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

mysqli_close($conexion);
ob_clean();

if (isset($success) && $success) {
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Ajustes guardados exitosamente."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "No se pudieron guardar los ajustes."]);
}
