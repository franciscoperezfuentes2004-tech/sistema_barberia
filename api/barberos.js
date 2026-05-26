const { db } = require('./_config/db.js');
const bcrypt = require('bcryptjs');
const { verifyToken, sanitize } = require('./_config/helpers.js');

module.exports = async function handler(req, res) {
  const usuariosRef = db.collection('usuarios');

  if (req.method === 'GET') {
    const { all } = req.query;
    
    // 🛡️ PROTECCIÓN: Si se solicitan 'todos' los barberos, requiere ser Admin
    if (all) {
      const auth = verifyToken(req, ['admin']);
      if (auth.error) {
        return res.status(403).json({ error: auth.error });
      }
    }

    try {
      let query = usuariosRef.where('rol', '==', 'barbero');

      if (!all) {
        query = query.where('activo', '==', true);
      }

      const snapshot = await query.get();
      
      let barberos = [];
      snapshot.forEach(doc => {
        const data = doc.data();
        // Excluimos el password_hash de la respuesta por seguridad
        delete data.password_hash;
        barberos.push({ 
          id: doc.id, 
          foto: data.imagen_url || '', 
          ...data 
        });
      });

      // Ordenar alfabéticamente en memoria (ya que Firestore requiere índices compuestos si mezclamos where y orderBy)
      barberos.sort((a, b) => a.nombre.localeCompare(b.nombre));

      return res.status(200).json({ success: true, data: barberos });
    } catch (error) {
      console.error('Error obteniendo la lista de barberos:', error);
      return res.status(500).json({ error: 'Error interno obteniendo personal' });
    }
  } 
  
  else if (req.method === 'POST') {
    // 🛡️ PROTECCIÓN: Solo Admin puede crear barberos
    const auth = verifyToken(req, ['admin']);
    if (auth.error) {
      return res.status(403).json({ error: auth.error });
    }
    
    const { username, password, nombre, apellido, especialidad, imagen_url, activo } = req.body;

    if (!username || !nombre || !apellido) {
      return res.status(400).json({ error: 'Faltan campos obligatorios: username, nombre, apellido' });
    }

    // 🛡️ SANITIZACIÓN CONTRA XSS
    const safeUsername = sanitize(username);
    const safeNombre = sanitize(nombre);
    const safeApellido = sanitize(apellido);
    const safeEspecialidad = sanitize(especialidad);

    try {
      // Validar que el username no exista ya
      const existSnapshot = await usuariosRef.where('username', '==', safeUsername).limit(1).get();
      if (!existSnapshot.empty) {
        return res.status(409).json({ error: 'El nombre de usuario ya está registrado en el sistema' });
      }

      const plainPassword = password ? password : '123456';
      const passHash = await bcrypt.hash(plainPassword, 10);

      const nuevoBarbero = {
        username: safeUsername,
        password_hash: passHash,
        nombre: safeNombre,
        apellido: safeApellido,
        rol: 'barbero',
        especialidad: safeEspecialidad || null,
        imagen_url: imagen_url || null,
        activo: activo !== undefined ? activo : true,
        creado_en: new Date().toISOString()
      };

      const docRef = await usuariosRef.add(nuevoBarbero);
      
      const respuesta = { id: docRef.id, ...nuevoBarbero };
      delete respuesta.password_hash;

      return res.status(201).json({ success: true, data: respuesta });
    } catch (error) {
      console.error('Error dando de alta al barbero:', error);
      return res.status(500).json({ error: 'Error interno al registrar al barbero' });
    }
  }

  else if (req.method === 'PUT') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) return res.status(403).json({ error: auth.error });

    const { id } = req.query;
    if (!id) return res.status(400).json({ error: 'Falta el ID del barbero' });

    const dataToUpdate = {};
    for (const [key, value] of Object.entries(req.body)) {
      if (value !== undefined && key !== 'id') {
        dataToUpdate[key] = typeof value === 'string' ? sanitize(value) : value;
      }
    }

    try {
      await usuariosRef.doc(id).update(dataToUpdate);
      return res.status(200).json({ success: true, message: 'Barbero actualizado' });
    } catch (error) {
      console.error('Error actualizando barbero:', error);
      return res.status(500).json({ error: 'Error actualizando barbero' });
    }
  }

  else if (req.method === 'DELETE') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) return res.status(403).json({ error: auth.error });

    const { id } = req.query;
    if (!id) return res.status(400).json({ error: 'Falta el ID del barbero' });

    try {
      await usuariosRef.doc(id).delete();
      return res.status(200).json({ success: true, message: 'Barbero eliminado' });
    } catch (error) {
      console.error('Error eliminando barbero:', error);
      return res.status(500).json({ error: 'Error eliminando barbero' });
    }
  }

  return res.status(405).json({ error: 'Método HTTP no permitido' });
};
