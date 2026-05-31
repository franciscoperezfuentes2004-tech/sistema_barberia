<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  OBTENER DATOS — Barbería Premium
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Consulta la tabla de servicios en MySQL y devuelve todos los
 *  registros en formato JSON limpio para que el frontend los consuma
 *  con fetch() o XMLHttpRequest.
 *
 *  USO DESDE EL FRONTEND:
 *  ──────────────────────
 *  fetch("sistema/obtener_datos.php")
 *    .then(res => res.json())
 *    .then(data => console.log(data));
 *
 *  RESPUESTA EXITOSA:
 *  ──────────────────
 *  {
 *    "success": true,
 *    "total": 5,
 *    "data": [
 *      { "id": 10, "nombre": "Fade Premium", "precio": "150.00", ... },
 *      ...
 *    ]
 *  }
 */

// ─── Cabeceras para respuesta JSON y CORS ─────────────────────────
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// ─── Manejar preflight request de CORS ────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// ─── Solo aceptar método GET ──────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error"   => "Método no permitido. Usa GET."
    ]);
    exit;
}

// ─── Incluir la conexión a MySQL ──────────────────────────────────
require_once __DIR__ . "/conexion.php";

// ═══════════════════════════════════════════════════════════════════
//  1. PARÁMETROS OPCIONALES DE FILTRADO
// ═══════════════════════════════════════════════════════════════════

// Permite al frontend solicitar una tabla específica mediante ?tabla=servicios
// Por defecto consulta "servicios". Se puede extender para "barberos", "citas", etc.
$tabla_permitida = ["servicios", "usuarios", "citas"];
$tabla = $_GET["tabla"] ?? "servicios";

// Validar que la tabla solicitada esté en la lista blanca
// Esto previene inyección SQL a través del nombre de la tabla
if (!in_array($tabla, $tabla_permitida)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => "Tabla no permitida. Opciones válidas: " . implode(", ", $tabla_permitida)
    ]);
    exit;
}

// ─── Filtro opcional: solo registros activos ──────────────────────
// Uso: ?activos=1 → solo devuelve registros con activo = 1
$solo_activos = isset($_GET["activos"]) && $_GET["activos"] === "1";

// ═══════════════════════════════════════════════════════════════════
//  2. CONSULTAR LA BASE DE DATOS
// ═══════════════════════════════════════════════════════════════════

// Construir la consulta SQL de forma segura
// ORDER BY id DESC = los más recientes primero (orden descendente)
$sql = "SELECT * FROM `{$tabla}`";

// Agregar filtro de activos si se solicitó
if ($solo_activos) {
    $sql .= " WHERE activo = 1";
}

// Ordenar del más reciente al más antiguo
$sql .= " ORDER BY id DESC";

// Ejecutar la consulta
$resultado = mysqli_query($conexion, $sql);

// Verificar si la consulta fue exitosa
if (!$resultado) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error"   => "Error al consultar la base de datos.",
        "detalle" => mysqli_error($conexion)
    ]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
//  3. CONVERTIR LOS RESULTADOS A UN ARRAY PHP
// ═══════════════════════════════════════════════════════════════════

$registros = [];

// mysqli_fetch_assoc() devuelve una fila como array asociativo
// El while recorre cada fila hasta que no haya más (devuelve null)
while ($fila = mysqli_fetch_assoc($resultado)) {
    $registros[] = $fila;
}

// Liberar la memoria del resultado (buena práctica)
mysqli_free_result($resultado);

// ═══════════════════════════════════════════════════════════════════
//  4. RESPONDER EN JSON
// ═══════════════════════════════════════════════════════════════════

// JSON_UNESCAPED_UNICODE: mantiene acentos y ñ sin escapar (\u00e9 → é)
// JSON_PRETTY_PRINT: indenta el JSON para que sea legible (se puede quitar en producción)
echo json_encode([
    "success" => true,
    "total"   => count($registros),
    "data"    => $registros
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// ─── Cerrar la conexión ───────────────────────────────────────────
mysqli_close($conexion);
