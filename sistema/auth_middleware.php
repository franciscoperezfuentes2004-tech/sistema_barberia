<?php
/**
 * Middleware de Autenticación JWT
 * Protege los endpoints del backend requiriendo un token JWT válido.
 */

function base64UrlDecode($data) {
    return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
}

function base64UrlEncodeMiddleware($data) {
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

/**
 * Verifica el JWT en el header Authorization.
 * Si es inválido, retorna HTTP 401 y termina la ejecución (exit).
 * Si es válido, retorna el payload decodificado.
 * 
 * @param array $roles_permitidos Lista de roles permitidos (ej: ['admin', 'barbero']). Si está vacío, cualquiera con token válido pasa.
 * @return array Payload decodificado
 */
function verificarJWT($roles_permitidos = ['admin']) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';
    if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        ob_clean();
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Acceso denegado: Token no proporcionado."]);
        exit;
    }

    $token = $matches[1];
    $tokenParts = explode('.', $token);
    
    if (count($tokenParts) !== 3) {
        ob_clean();
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Acceso denegado: Token malformado."]);
        exit;
    }

    list($header64, $payload64, $signature64) = $tokenParts;

    // Verificar firma HMAC-SHA256
    $clave_secreta = getenv('JWT_SECRET') ?: 'clave_secreta_barberia'; // Fallback a la clave por defecto
    $signature_check = hash_hmac('sha256', $header64 . "." . $payload64, $clave_secreta, true);
    $signature_check64 = base64UrlEncodeMiddleware($signature_check);

    // Comparación segura contra ataques de tiempo (timing attacks)
    if (!hash_equals($signature_check64, $signature64)) {
        ob_clean();
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Acceso denegado: Firma de token inválida."]);
        exit;
    }

    $payload = json_decode(base64UrlDecode($payload64), true);

    if (!$payload) {
        ob_clean();
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Acceso denegado: Payload inválido."]);
        exit;
    }

    // Verificar expiración si existe (iat/exp)
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        ob_clean();
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Acceso denegado: El token ha expirado."]);
        exit;
    }

    // Verificar Rol
    if (!empty($roles_permitidos)) {
        $rol_usuario = $payload['rol'] ?? '';
        if (!in_array($rol_usuario, $roles_permitidos)) {
            ob_clean();
            http_response_code(403);
            echo json_encode(["success" => false, "message" => "Acceso denegado: No tienes permisos suficientes."]);
            exit;
        }
    }

    return $payload;
}
?>
