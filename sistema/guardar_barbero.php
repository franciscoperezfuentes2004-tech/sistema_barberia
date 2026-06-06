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
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    ob_clean();
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/auth_middleware.php';
verificarJWT();

require_once __DIR__ . "/conexion.php";

$data = json_decode(file_get_contents("php://input"), true);

$nombre       = htmlspecialchars(trim($data['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$apellido     = htmlspecialchars(trim($data['apellido'] ?? ''), ENT_QUOTES, 'UTF-8');
$especialidad = htmlspecialchars(trim($data['especialidad'] ?? ''), ENT_QUOTES, 'UTF-8');
$activo       = isset($data['activo']) ? (int)$data['activo'] : 1;

// El frontend envía la foto como "imagen_url" (base64 comprimido)
$foto = trim($data['imagen_url'] ?? $data['foto'] ?? '');

// Campos opcionales de credenciales
$username_input = trim($data['username'] ?? '');
$password_input = trim($data['password'] ?? '');

if (empty($nombre)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "El nombre es requerido."]);
    exit;
}

$id = $_GET['id'] ?? null;

if ($id) {
    // ═══════════════════ UPDATE ═══════════════════
    $id = (int)$id;

    // Construir UPDATE dinámico para no sobreescribir campos que no se enviaron
    $updates = [];
    $types   = "";
    $params  = [];

    // Siempre se envían estos campos
    $updates[] = "`nombre` = ?";       $types .= "s"; $params[] = $nombre;
    $updates[] = "`apellido` = ?";     $types .= "s"; $params[] = $apellido;
    $updates[] = "`especialidad` = ?"; $types .= "s"; $params[] = $especialidad;
    $updates[] = "`activo` = ?";       $types .= "i"; $params[] = $activo;

    // Solo actualizar foto si el admin subió una nueva
    if (!empty($foto)) {
        $updates[] = "`foto` = ?";
        $types .= "s";
        $params[] = $foto;
    }

    // Solo actualizar usuario si se envió uno
    if (!empty($username_input)) {
        $updates[] = "`usuario` = ?";
        $types .= "s";
        $params[] = $username_input;
    }

    // Solo actualizar contraseña si se envió una nueva
    if (!empty($password_input)) {
        $updates[] = "`password` = ?";
        $types .= "s";
        $params[] = password_hash($password_input, PASSWORD_BCRYPT);
    }

    // Agregar la cláusula WHERE
    $types .= "i";
    $params[] = $id;

    $sql = "UPDATE `barberos` SET " . implode(", ", $updates) . " WHERE `id` = ?";
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error interno al preparar la consulta."]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        ob_clean();
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Barbero actualizado exitosamente."]);
    } else {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "No se pudo actualizar el barbero: $err"]);
    }

} else {
    // ═══════════════════ INSERT (Nuevo Barbero) ═══════════════════
    // Generar usuario automático si no se proporcionó
    $usuario = !empty($username_input) ? $username_input : strtolower(str_replace(' ', '', $nombre)) . rand(100, 999);
    
    // La contraseña es obligatoria al crear
    if (empty($password_input)) {
        $password_input = "1234"; // Default seguro
    }
    $password_hash = password_hash($password_input, PASSWORD_BCRYPT);

    $sql = "INSERT INTO `barberos` (`nombre`, `apellido`, `foto`, `especialidad`, `usuario`, `password`, `activo`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error interno al preparar la consulta."]);
        exit;
    }

    // Al crear, siempre activo = 1
    $activo_new = 1;
    mysqli_stmt_bind_param($stmt, "ssssssi", $nombre, $apellido, $foto, $especialidad, $usuario, $password_hash, $activo_new);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        ob_clean();
        http_response_code(200);
        echo json_encode(["success" => true, "message" => "Barbero guardado exitosamente.", "data" => ["username" => $usuario]]);
    } else {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        ob_clean();
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "No se pudo guardar el barbero: $err"]);
    }
}
