<?php
// Usamos el buffer de salida para evitar que advertencias de PHP rompan la respuesta JSON
ob_start();

// Cabeceras estrictas para asegurar que el frontend interprete la respuesta como JSON y permitir CORS
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Pre-flight request para solicitudes CORS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

// Bloqueamos cualquier método que no sea GET
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    ob_clean();
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido. Usa GET."]);
    exit;
}

// Incluimos la conexión a la base de datos de manera segura
require_once __DIR__ . "/conexion.php";

// Lista blanca de las tablas expuestas. Previene SQL Injection a nivel de estructura.
$tablas_permitidas = ['servicios', 'usuarios', 'citas', 'galeria', 'ajustes', 'barberos', 'horarios'];
$tabla = $_GET["tabla"] ?? "";

// Si la tabla no está en la lista blanca, lanzamos error 400
if (!in_array($tabla, $tablas_permitidas)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["error" => "Tabla no permitida. Opciones válidas: " . implode(", ", $tablas_permitidas)]);
    exit;
}

// Inicialización de la consulta base para todas las tablas permitidas
$sql = "SELECT * FROM `" . $tabla . "` WHERE 1=1";

// ─── LÓGICA CONDICIONAL DE FILTROS SEGÚN TABLA ──────────────────────

if ($tabla === 'barberos' || $tabla === 'servicios') {
    // Estas tablas SÍ poseen la columna 'activo'. Aplicamos el filtro para traer solo los vigentes.
    $sql .= " AND activo = 1";
}

if ($tabla === 'horarios') {
    // Si se pide globalmente, aseguramos que traiga solo los horarios activos.
    if (isset($_GET['global']) && $_GET['global'] == '1') {
        $sql .= " AND activo = 1";
    }
    // Si se pasa el ID específico de un barbero, filtramos de forma segura.
    if (isset($_GET['barbero_id']) && trim($_GET['barbero_id']) !== '') {
        $barbero_id = intval($_GET['barbero_id']);
        $sql .= " AND barbero_id = $barbero_id";
    }
}

if ($tabla === 'citas') {
    // Filtro por barbero específico
    if (isset($_GET['barbero_id']) && trim($_GET['barbero_id']) !== '') {
        $barbero_id = intval($_GET['barbero_id']);
        $sql .= " AND barbero_id = $barbero_id";
    }
    // Filtro estricto por fecha extrayendo solo el día (Y-m-d) de la columna DATETIME
    if (isset($_GET['fecha']) && trim($_GET['fecha']) !== '') {
        $fecha = mysqli_real_escape_string($conexion, trim($_GET['fecha']));
        $sql .= " AND DATE(fecha_hora) = '$fecha'";
    }
}

// Ordenamiento global por ID descendente para traer siempre lo más reciente primero
// Se excluyen 'ajustes' y 'horarios' ya que no suelen requerir este orden
if ($tabla !== 'ajustes' && $tabla !== 'horarios') {
    $sql .= " ORDER BY id DESC";
}

// Ejecución de la consulta construida dinámicamente
$resultado = mysqli_query($conexion, $sql);

// Manejo de errores fatales en la consulta SQL
if (!$resultado) {
    error_log("Error en obtener_datos ($tabla): " . mysqli_error($conexion));
    ob_clean();
    http_response_code(500);
    echo json_encode(["error" => "Error al consultar la base de datos."]);
    exit;
}

// Recopilación de los registros en un array
$datos = [];
while ($fila = mysqli_fetch_assoc($resultado)) {
    $datos[] = $fila;
}

// Liberamos memoria y cerramos conexión
mysqli_free_result($resultado);
mysqli_close($conexion);

// ─── BLINDAJE DE LA TABLA AJUSTES ──────────────────────────────────
// Si la tabla solicitada es 'ajustes' y se encuentra completamente vacía (por ejemplo, antes del auto-insert)
// proveemos un objeto por defecto envuelto en un array para evitar que la interfaz explote buscando la marca.
if ($tabla === 'ajustes' && empty($datos)) {
    $datos = [
        [
            "nombre_empresa" => "Barbería Premium",
            "logo"           => ""
        ]
    ];
}

// Limpieza final de buffer antes del output JSON puro
ob_clean();

// Retornamos el array directamente. Frontend usará data.forEach() nativo sobre este Array.
echo json_encode($datos, JSON_UNESCAPED_UNICODE);
