<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  GUARDAR SERVICIO — Barbería Premium
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Recibe un formulario por POST con los datos de un nuevo servicio
 *  y una imagen opcional. Valida, sanitiza, sube la imagen y guarda
 *  todo en MySQL de forma segura usando Prepared Statements.
 *
 *  CAMPOS ESPERADOS (POST):
 *  ────────────────────────
 *  - nombre       (string, obligatorio)  → Nombre del servicio
 *  - descripcion  (string, opcional)     → Descripción del servicio
 *  - precio       (decimal, obligatorio) → Precio en moneda local
 *  - duracion_min (entero, obligatorio)  → Duración en minutos
 *  - imagen       (archivo, opcional)    → Foto del servicio (.jpg, .png, .webp)
 */

// ─── Cabeceras para respuesta JSON y CORS ─────────────────────────
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// ─── Manejar preflight request de CORS (navegadores modernos) ─────
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// ─── Solo aceptar método POST ─────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "error"   => "Método no permitido. Usa POST."
    ]);
    exit;
}

// ─── Incluir la conexión a MySQL ──────────────────────────────────
require_once __DIR__ . "/conexion.php";

// ═══════════════════════════════════════════════════════════════════
//  1. RECIBIR Y SANITIZAR LOS DATOS DEL FORMULARIO
// ═══════════════════════════════════════════════════════════════════

// htmlspecialchars() convierte caracteres peligrosos (<, >, ", etc.) en entidades HTML
// Esto previene ataques XSS (Cross-Site Scripting) almacenados en la base de datos
$nombre       = htmlspecialchars(trim($_POST["nombre"] ?? ""), ENT_QUOTES, "UTF-8");
$descripcion  = htmlspecialchars(trim($_POST["descripcion"] ?? ""), ENT_QUOTES, "UTF-8");
$precio       = floatval($_POST["precio"] ?? 0);
$duracion_min = intval($_POST["duracion_min"] ?? 0);

// ─── Validaciones básicas ─────────────────────────────────────────
if (empty($nombre)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "El nombre del servicio es obligatorio."]);
    exit;
}

if ($precio <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "El precio debe ser mayor a 0."]);
    exit;
}

if ($duracion_min <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "La duración debe ser mayor a 0 minutos."]);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
//  2. PROCESAR LA IMAGEN (si se envió una)
// ═══════════════════════════════════════════════════════════════════

$imagen_url = "";  // Ruta relativa donde se guardará la imagen

// Verificar si se envió un archivo de imagen y no tiene errores
if (isset($_FILES["imagen"]) && $_FILES["imagen"]["error"] === UPLOAD_ERR_OK) {

    // Obtener información del archivo subido
    $archivo_tmp    = $_FILES["imagen"]["tmp_name"];     // Ruta temporal en el servidor
    $nombre_original = $_FILES["imagen"]["name"];         // Nombre original del archivo
    $tamano         = $_FILES["imagen"]["size"];           // Tamaño en bytes

    // ─── Validar el tamaño máximo (5 MB) ──────────────────────────
    $max_size = 5 * 1024 * 1024;  // 5 MB en bytes
    if ($tamano > $max_size) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "La imagen no debe superar los 5 MB."]);
        exit;
    }

    // ─── Extraer y validar la extensión del archivo ───────────────
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));

    // Lista blanca de extensiones permitidas (solo imágenes comunes)
    $extensiones_permitidas = ["jpg", "jpeg", "png", "webp"];

    if (!in_array($extension, $extensiones_permitidas)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error"   => "Formato de imagen no permitido. Usa: " . implode(", ", $extensiones_permitidas)
        ]);
        exit;
    }

    // ─── Generar un nombre único para evitar sobrescrituras ───────
    // uniqid() genera un ID basado en microsegundos del servidor
    $nombre_unico = uniqid("serv_", true) . "." . $extension;
    // Ejemplo resultado: "serv_665f3a2b8c4e91.23456789.webp"

    // ─── Definir la ruta de destino ───────────────────────────────
    $carpeta_destino = __DIR__ . "/img/";

    // Crear la carpeta si no existe (primera vez)
    if (!is_dir($carpeta_destino)) {
        mkdir($carpeta_destino, 0755, true);
    }

    $ruta_completa = $carpeta_destino . $nombre_unico;

    // ─── Mover el archivo de la ubicación temporal a la definitiva ─
    if (!move_uploaded_file($archivo_tmp, $ruta_completa)) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "No se pudo guardar la imagen en el servidor."]);
        exit;
    }

    // Guardar la ruta relativa (la que usará el frontend para mostrar la imagen)
    $imagen_url = "sistema/img/" . $nombre_unico;
}

// ═══════════════════════════════════════════════════════════════════
//  3. INSERTAR EN MYSQL CON PREPARED STATEMENTS
// ═══════════════════════════════════════════════════════════════════

// Prepared Statements previenen inyección SQL al separar la consulta de los datos
// Los "?" son marcadores de posición que se rellenan de forma segura con bind_param
$sql = "INSERT INTO servicios (nombre, descripcion, precio, duracion_min, imagen_url, activo, created_at)
        VALUES (?, ?, ?, ?, ?, 1, NOW())";

// Preparar la consulta
$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error"   => "Error al preparar la consulta SQL.",
        "detalle" => mysqli_error($conexion)
    ]);
    exit;
}

// Vincular los parámetros al statement preparado
// "ssdis" = tipos: s=string, s=string, d=double, i=integer, s=string
mysqli_stmt_bind_param($stmt, "ssdis", $nombre, $descripcion, $precio, $duracion_min, $imagen_url);

// Ejecutar la inserción
if (mysqli_stmt_execute($stmt)) {
    // Éxito: obtener el ID del nuevo registro
    $nuevo_id = mysqli_insert_id($conexion);

    http_response_code(201);
    echo json_encode([
        "success"  => true,
        "message"  => "Servicio guardado correctamente.",
        "data"     => [
            "id"           => $nuevo_id,
            "nombre"       => $nombre,
            "descripcion"  => $descripcion,
            "precio"       => $precio,
            "duracion_min" => $duracion_min,
            "imagen_url"   => $imagen_url
        ]
    ]);
} else {
    // Error en la ejecución
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error"   => "No se pudo guardar el servicio en la base de datos.",
        "detalle" => mysqli_stmt_error($stmt)
    ]);
}

// ─── Cerrar el statement y la conexión ────────────────────────────
mysqli_stmt_close($stmt);
mysqli_close($conexion);
