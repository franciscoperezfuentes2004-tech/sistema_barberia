const { db } = require('./config/db.js');
const { sanitize } = require('./config/helpers.js');

module.exports = async function handler(req, res) {
  const resenasRef = db.collection('resenas');

  if (req.method === 'GET') {
    try {
      const snapshot = await resenasRef.orderBy('created_at', 'desc').limit(20).get();
      const resenas = [];
      snapshot.forEach(doc => {
        resenas.push({ id: doc.id, ...doc.data() });
      });
      return res.status(200).json({ success: true, data: resenas });
    } catch (error) {
      console.error('Error obteniendo reseñas:', error);
      // Fallback: si falla por falta de índice, intentar obtener sin ordenación y ordenar en memoria
      try {
        const snapshot = await resenasRef.limit(50).get();
        const resenas = [];
        snapshot.forEach(doc => {
          resenas.push({ id: doc.id, ...doc.data() });
        });
        resenas.sort((a, b) => {
          const dateA = a.created_at || '';
          const dateB = b.created_at || '';
          return dateB.localeCompare(dateA);
        });
        return res.status(200).json({ success: true, data: resenas.slice(0, 20) });
      } catch (innerError) {
        console.error('Error fallback obteniendo reseñas:', innerError);
        return res.status(500).json({ error: 'Error al cargar las reseñas' });
      }
    }
  }

  else if (req.method === 'POST') {
    const { nombre, estrellas, comentario } = req.body;

    if (!nombre || estrellas === undefined) {
      return res.status(400).json({ error: 'El nombre y la calificación son obligatorios' });
    }

    try {
      const nuevaResena = {
        nombre: sanitize(nombre),
        estrellas: parseInt(estrellas, 10),
        comentario: sanitize(comentario) || '',
        created_at: new Date().toISOString()
      };

      const docRef = await resenasRef.add(nuevaResena);
      return res.status(201).json({ success: true, data: { id: docRef.id, ...nuevaResena } });
    } catch (error) {
      console.error('Error guardando reseña:', error);
      return res.status(500).json({ error: 'Error al registrar tu reseña en el sistema' });
    }
  }

  return res.status(405).json({ error: 'Método no permitido' });
};
