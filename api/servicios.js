const { db, bucket } = require('./config/db.js');
const { verifyToken, sanitize } = require('./config/helpers.js');

module.exports = async function handler(req, res) {
  const serviciosRef = db.collection('servicios');

  if (req.method === 'GET') {
    // PÚBLICO: Cualquiera puede ver los servicios
    try {
      // Remover .orderBy('nombre', 'asc') para evitar el error de índice compuesto faltante en Firebase (Error 500)
      const snapshot = await serviciosRef.get();
      
      const servicios = [];
      snapshot.forEach(doc => {
        const data = doc.data();
        if (data.activo === false || data.activo === 0 || data.activo === '0' || data.activo === 'false') return;
        servicios.push({ 
          id: doc.id, 
          ...data,
          activo: 1 
        });
      });

      // Ordenar en memoria por nombre
      servicios.sort((a, b) => a.nombre.localeCompare(b.nombre));

      return res.status(200).json({ success: true, data: servicios });
    } catch (error) {
      console.error('Error obteniendo servicios:', error);
      return res.status(500).json({ error: 'Error interno obteniendo catálogo de servicios' });
    }
  } 
  
  else if (req.method === 'POST') {
    // 🛡️ PROTECCIÓN: Solo administradores pueden crear servicios
    const auth = verifyToken(req, ['admin']);
    if (auth.error) {
      return res.status(403).json({ error: auth.error });
    }
    
    const { nombre, descripcion, precio, duracion_min } = req.body;
    let { imagen_url } = req.body;

    if (!nombre || !precio || !duracion_min) {
      return res.status(400).json({ error: 'Faltan campos obligatorios: nombre, precio, duracion_min' });
    }

    // 🛡️ SANITIZACIÓN CONTRA XSS
    const safeNombre = sanitize(nombre);
    const safeDescripcion = sanitize(descripcion);

    if (imagen_url && imagen_url.startsWith('data:')) {
      try {
        const mimeType = imagen_url.match(/data:(.*?);base64/)[1];
        const ext = mimeType.split('/')[1] || 'jpg';
        const base64Data = imagen_url.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const fileName = `servicio_${Date.now()}.${ext}`;
        const filePath = `uploads/${fileName}`;
        const file = bucket.file(filePath);
        
        await file.save(buffer, {
          metadata: { contentType: mimeType }
        });
        finalImageUrl = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;
      } catch (err) {
        console.error('Error subiendo imagen de servicio:', err);
      }
    }

    try {
      const nuevoServicio = {
        nombre: safeNombre,
        descripcion: safeDescripcion || null,
        precio: Number(precio),
        duracion_min: Number(duracion_min),
        imagen_url: imagen_url || null,
        activo: true,
        creado_en: new Date().toISOString()
      };

      const docRef = await serviciosRef.add(nuevoServicio);
      
      return res.status(201).json({ success: true, data: { id: docRef.id, ...nuevoServicio } });
    } catch (error) {
      console.error('Error creando nuevo servicio:', error);
      return res.status(500).json({ error: 'Error interno al guardar el servicio en Firestore' });
    }
  }

  else if (req.method === 'PUT') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) return res.status(403).json({ error: auth.error });

    const { id } = req.query;
    if (!id) return res.status(400).json({ error: 'Falta el ID del servicio' });

    const dataToUpdate = {};
    for (const [key, value] of Object.entries(req.body)) {
      if (value !== undefined && key !== 'id') {
        if (typeof value === 'string' && value.startsWith('data:')) {
          dataToUpdate[key] = value;
        } else {
          dataToUpdate[key] = typeof value === 'string' ? sanitize(value) : value;
        }
      }
    }

    if (dataToUpdate.imagen_url && dataToUpdate.imagen_url.startsWith('data:')) {
      try {
        const mimeType = dataToUpdate.imagen_url.match(/data:(.*?);base64/)[1];
        const ext = mimeType.split('/')[1] || 'jpg';
        const base64Data = dataToUpdate.imagen_url.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const fileName = `servicio_${Date.now()}.${ext}`;
        const filePath = `uploads/${fileName}`;
        const file = bucket.file(filePath);
        
        await file.save(buffer, {
          metadata: { contentType: mimeType }
        });
        dataToUpdate.imagen_url = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;
      } catch (err) {
        console.error('Error subiendo imagen de servicio:', err);
      }
    }

    try {
      await serviciosRef.doc(id).update(dataToUpdate);
      return res.status(200).json({ success: true, message: 'Servicio actualizado' });
    } catch (error) {
      console.error('Error actualizando servicio:', error);
      return res.status(500).json({ error: 'Error actualizando servicio' });
    }
  }

  else if (req.method === 'DELETE') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) return res.status(403).json({ error: auth.error });

    const { id } = req.query;
    if (!id) return res.status(400).json({ error: 'Falta el ID del servicio' });

    try {
      await serviciosRef.doc(id).delete();
      return res.status(200).json({ success: true, message: 'Servicio eliminado' });
    } catch (error) {
      console.error('Error eliminando servicio:', error);
      return res.status(500).json({ error: 'Error eliminando servicio' });
    }
  }

  return res.status(405).json({ error: 'Método HTTP no permitido' });
};
