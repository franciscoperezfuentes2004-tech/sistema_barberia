const { db, bucket } = require('./config/db.js');
const { verifyToken, sanitize } = require('./config/helpers.js');

module.exports = async function handler(req, res) {
  const galeriaRef = db.collection('galeria');

  if (req.method === 'GET') {
    try {
      const snapshot = await galeriaRef.orderBy('creado_en', 'desc').get();
      const imagenes = [];
      snapshot.forEach(doc => {
        imagenes.push({ id: doc.id, ...doc.data() });
      });
      return res.status(200).json({ success: true, data: imagenes });
    } catch (error) {
      console.error('Error obteniendo galería:', error);
      return res.status(500).json({ error: 'Error al cargar la galería de imágenes' });
    }
  }

  else if (req.method === 'POST') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) {
      return res.status(403).json({ error: auth.error });
    }

    const { imagen, titulo, imagen_b64 } = req.body;

    let finalImageUrl = imagen || null;

    if (imagen_b64 && imagen_b64.startsWith('data:')) {
      try {
        const mimeType = imagen_b64.match(/data:(.*?);base64/)[1];
        const ext = mimeType.split('/')[1] || 'jpg';
        const base64Data = imagen_b64.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const fileName = `galeria_${Date.now()}.${ext}`;
        const filePath = `uploads/${fileName}`;
        const file = bucket.file(filePath);
        
        await file.save(buffer, {
          metadata: { contentType: mimeType },
          public: true
        });
        finalImageUrl = `https://storage.googleapis.com/${bucket.name}/${filePath}`;
      } catch (err) {
        console.error('Error subiendo foto de galería:', err);
      }
    }

    if (!finalImageUrl) {
      return res.status(400).json({ error: 'Se requiere una imagen (base64 o URL)' });
    }

    try {
      const nuevaImagen = {
        imagen: finalImageUrl,
        titulo: sanitize(titulo) || '',
        creado_en: new Date().toISOString()
      };
      const docRef = await galeriaRef.add(nuevaImagen);
      return res.status(201).json({ success: true, data: { id: docRef.id, ...nuevaImagen } });
    } catch (error) {
      console.error('Error guardando en galería:', error);
      return res.status(500).json({ error: 'Error al agregar imagen a la galería' });
    }
  }

  else if (req.method === 'DELETE') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) {
      return res.status(403).json({ error: auth.error });
    }

    const { id } = req.query;
    if (!id) {
      return res.status(400).json({ error: 'Falta el ID de la imagen a eliminar' });
    }

    try {
      await galeriaRef.doc(id).delete();
      return res.status(200).json({ success: true, message: 'Imagen eliminada correctamente' });
    } catch (error) {
      console.error('Error eliminando de galería:', error);
      return res.status(500).json({ error: 'Error al eliminar imagen de la galería' });
    }
  }

  return res.status(405).json({ error: 'Método no permitido' });
};
