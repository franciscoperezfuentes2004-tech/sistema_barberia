<?php
// Usamos el buffer de salida para evitar que advertencias rompan el JSON
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
    echo json_encode(["error" => "Método no permitido. Usa GET."]);
    exit;
}

// Incluimos la conexión a la base de datos
require_once __DIR__ . "/conexion.php";

// Validación de lista blanca para tablas permitidas
$tablas_permitidas = ['servicios', 'usuarios', 'citas', 'galeria', 'ajustes', 'barberos', 'horarios'];
$tabla = $_GET["tabla"] ?? "";

if (!in_array($tabla, $tablas_permitidas)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["error" => "Tabla no permitida. Opciones válidas: " . implode(", ", $tablas_permitidas)]);
    exit;
}

// Inicialización de la consulta SQL dinámica
$sql = "SELECT * FROM `" . $tabla . "` WHERE 1=1";

// Filtro: barbero_id
if (isset($_GET['barbero_id']) && trim($_GET['barbero_id']) !== '') {
    $barbero_id = intval($_GET['barbero_id']);
    $sql .= " AND barbero_id = $barbero_id";
}

// Filtro: fecha (Solo para tabla citas)
if (isset($_GET['fecha']) && trim($_GET['fecha']) !== '' && $tabla === 'citas') {
    $fecha = mysqli_real_escape_string($conexion, trim($_GET['fecha']));
    $sql .= " AND DATE(fecha_hora) = '$fecha'";
}

// Filtro: Activo por defecto en barberos y horarios
if ($tabla === 'barberos') {
    $sql .= " AND activo = 1";
}
if ($tabla === 'horarios') {
    $sql .= " AND activo = 1";
}

// Filtro extra: servicios activos
if ($tabla === 'servicios' && isset($_GET['activos']) && $_GET['activos'] === '1') {
    $sql .= " AND activo = 1";
}

// Ordenamiento global descendente por defecto para registros nuevos primero
if ($tabla !== 'horarios' && $tabla !== 'ajustes') {
    $sql .= " ORDER BY id DESC";
}

// Ejecución de la consulta
$resultado = mysqli_query($conexion, $sql);

if (!$resultado) {
    error_log("Error en obtener_datos: " . mysqli_error($conexion));
    ob_clean();
    http_response_code(500);
    echo json_encode(["error" => "Error al consultar la base de datos."]);
    exit;
}

// Recopilación de los datos
$datos = [];
while ($fila = mysqli_fetch_assoc($resultado)) {
    $datos[] = $fila;
}

mysqli_free_result($resultado);
mysqli_close($conexion);

// Se limpia el buffer antes de la salida final
ob_clean();

// MUY IMPORTANTE: Retornamos el array directamente para iterarlo en frontend
echo json_encode($datos, JSON_UNESCAPED_UNICODE);
