<?php
// Usamos el buffer de salida para asegurar JSON limpios
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

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    ob_clean();
    http_response_code(405);
    echo json_encode(["success" => false, "error" => "Método no permitido. Usa GET."]);
    exit;
}

require_once __DIR__ . "/conexion.php";

// Validación de lista blanca para tablas permitidas
$tabla_permitida = ['servicios', 'usuarios', 'citas', 'galeria', 'ajustes'];
$tabla = $_GET["tabla"] ?? "servicios";

if (!in_array($tabla, $tabla_permitida)) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => "Tabla no permitida. Opciones válidas: " . implode(", ", $tabla_permitida)
    ]);
    exit;
}

$solo_activos = isset($_GET["activos"]) && $_GET["activos"] === "1";

$sql = "SELECT * FROM `{$tabla}`";
if ($solo_activos) {
    $sql .= " WHERE activo = 1";
}
$sql .= " ORDER BY id DESC";

$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    error_log("Error en obtener_datos: " . mysqli_error($conexion));
    ob_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Error al consultar la base de datos."]);
    exit;
}

$registros = [];
while ($fila = mysqli_fetch_assoc($resultado)) {
    $registros[] = $fila;
}

mysqli_free_result($resultado);
mysqli_close($conexion);

ob_clean();
echo json_encode([
    "success" => true,
    "total"   => count($registros),
    "data"    => $registros
], JSON_UNESCAPED_UNICODE);
