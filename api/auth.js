const { db } = require('./_config/db.js');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

module.exports = async function handler(req, res) {
  // Solo permitir solicitudes POST para el inicio de sesión
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Método no permitido' });
  }

  const { username, password } = req.body;

  // Validación básica de entrada
  if (!username || !password) {
    return res.status(400).json({ error: 'Usuario y contraseña son obligatorios' });
  }

  try {
    const usuariosRef = db.collection('usuarios');
    
    // Auto-inicialización del admin si la colección está vacía
    const allUsers = await usuariosRef.limit(1).get();
    if (allUsers.empty) {
      console.log('Colección de usuarios vacía. Creando usuario admin por defecto...');
      const defaultHash = await bcrypt.hash('admin123', 10); // Contraseña por defecto para el primer login
      await usuariosRef.doc('admin').set({
        username: 'admin',
        password_hash: defaultHash,
        nombre: 'Administrador',
        apellido: 'Principal',
        rol: 'admin',
        activo: true
      });
      // Importante: No detenemos la ejecución, ahora el snapshot de abajo encontrará a 'admin'
    }

    // 1. Buscar el usuario en la base de datos de Firestore
    const snapshot = await usuariosRef.where('username', '==', username).limit(1).get();

    // Si no existe el usuario
    if (snapshot.empty) {
      return res.status(401).json({ error: 'Credenciales inválidas' });
    }

    // Extraer datos del documento de Firestore
    const userDoc = snapshot.docs[0];
    const user = { id: userDoc.id, ...userDoc.data() };

    // Verificar que el usuario no haya sido dado de baja
    if (!user.activo) {
      return res.status(403).json({ error: 'El usuario se encuentra inactivo. Contacte al administrador.' });
    }

    // 2. Verificar la contraseña segura (hash)
    const isPasswordValid = await bcrypt.compare(password, user.password_hash);

    if (!isPasswordValid) {
      return res.status(401).json({ error: 'Credenciales inválidas' });
    }

    // 3. Autenticación exitosa: Generar JWT (JSON Web Token)
    const jwtSecret = process.env.JWT_SECRET || 'dev_secret_temporal';
    
    const token = jwt.sign(
      { 
        id: user.id, 
        rol: user.rol, 
        username: user.username 
      },
      jwtSecret,
      { expiresIn: '24h' }
    );

    // 4. Retornar el token y los datos esenciales del usuario para la UI
    return res.status(200).json({
      success: true,
      token,
      user: {
        id: user.id,
        username: user.username,
        nombre: user.nombre,
        apellido: user.apellido,
        rol: user.rol
      }
    });

  } catch (error) {
    console.error('Error durante la autenticación:', error);
    return res.status(500).json({ error: 'Error interno del servidor durante el inicio de sesión' });
  }
}
