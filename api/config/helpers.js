const jwt = require('jsonwebtoken');

/**
 * Verifica el token JWT en las cabeceras de la petición.
 * @param {Object} req - Objeto Request de Vercel/Express
 * @param {Array} rolesPermitidos - Array de strings con roles (ej: ['admin', 'barbero']). Si está vacío, cualquiera logueado pasa.
 * @returns {Object} { user: {...}, error: string | null }
 */
function verifyToken(req, rolesPermitidos = []) {
  const authHeader = req.headers.authorization || req.headers.Authorization;

  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return { user: null, error: 'Acceso denegado. No se proporcionó un token válido.' };
  }

  const token = authHeader.split(' ')[1];

  try {
    const jwtSecret = process.env.JWT_SECRET || 'dev_secret_temporal';
    const decoded = jwt.verify(token, jwtSecret);

    if (rolesPermitidos.length > 0 && !rolesPermitidos.includes(decoded.rol)) {
       return { user: null, error: 'No tienes permisos suficientes para realizar esta acción.' };
    }

    return { user: decoded, error: null };
  } catch (err) {
    if (err.name === 'TokenExpiredError') {
      return { user: null, error: 'La sesión ha expirado. Por favor, inicia sesión de nuevo.' };
    }
    return { user: null, error: 'Token inválido o corrupto.' };
  }
}

/**
 * Limpia una cadena de texto para evitar ataques XSS almacenados.
 * Elimina las etiquetas HTML peligrosas convirtiéndolas en texto inofensivo.
 * @param {string} str - La cadena a limpiar
 * @returns {string} La cadena limpia
 */
function sanitize(str) {
  if (!str) return str;
  // Primero decodificamos lo que ya estaba codificado para evitar acumulación
  let decoded = String(str)
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#x27;/g, "'");
    
  // Luego codificamos todo limpiamente
  return decoded
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;')
    .trim();
}

module.exports = { verifyToken, sanitize };
