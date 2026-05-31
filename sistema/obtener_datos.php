<?php
// Ocultamos cualquier advertencia de PHP para proteger la respuesta JSON
error_reporting(0);
ini_set('display_errors', 0);

// Controlamos la salida del buffer
ob_start();

// Cabeceras estrictas JSON y CORS
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
    echo json_encode([]); // Respuesta segura sin corromper iteradores
    exit;
}

require_once __DIR__ . "/conexion.php";

$tablas_permitidas = ['servicios', 'usuarios', 'citas', 'galeria', 'ajustes', 'barberos', 'horarios', 'resenas'];
$tabla = $_GET["tabla"] ?? "";

if (!in_array($tabla, $tablas_permitidas)) {
    ob_clean();
    http_response_code(400);
    echo json_encode([]);
    exit;
}

// 1. Consulta puramente limpia y robusta. 
// SE ELIMINAN todos los filtros conflictivos como "AND activo = 1" para evitar fallos de base de datos.
$sql = "SELECT * FROM `" . $tabla . "` WHERE 1=1";

// 2. Lógica Condicional de Filtros (Seguros: por ID o Fecha)
if ($tabla === 'horarios') {
    if (isset($_GET['barbero_id']) && trim($_GET['barbero_id']) !== '') {
        $barbero_id = intval($_GET['barbero_id']);
        $sql .= " AND barbero_id = $barbero_id";
    }
}

if ($tabla === 'citas') {
    if (isset($_GET['barbero_id']) && trim($_GET['barbero_id']) !== '') {
        $barbero_id = intval($_GET['barbero_id']);
        $sql .= " AND barbero_id = $barbero_id";
    }
    if (isset($_GET['fecha']) && trim($_GET['fecha']) !== '') {
        $fecha = mysqli_real_escape_string($conexion, trim($_GET['fecha']));
        // DATE(fecha_hora) extrae estrictamente YYYY-MM-DD
        $sql .= " AND DATE(fecha_hora) = '$fecha'";
    }
}

// Ordenamiento descendente para que lo más reciente aparezca primero en la Interfaz.
if ($tabla !== 'ajustes' && $tabla !== 'horarios') {
    $sql .= " ORDER BY id DESC";
}

$resultado = mysqli_query($conexion, $sql);

$datos = [];
if ($resultado) {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $datos[] = $fila;
    }
    mysqli_free_result($resultado);
}

mysqli_close($conexion);

// 3. Vaciado del buffer final
ob_clean();

// 4. Retornamos estrictamente el array directo con json_encode
echo json_encode($datos, JSON_UNESCAPED_UNICODE);
