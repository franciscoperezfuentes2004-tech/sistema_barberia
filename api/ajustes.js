const { db, bucket } = require('./config/db.js');
const { verifyToken, sanitize } = require('./config/helpers.js');

module.exports = async function handler(req, res) {
  const ajustesRef = db.collection('ajustes').doc('global');

  if (req.method === 'GET') {
    try {
      const doc = await ajustesRef.get();
      if (!doc.exists) {
        // Valores por defecto
        return res.status(200).json({
          success: true,
          data: {
            site_name: 'Barbería Premium',
            hero_title: 'Estilo que habla por ti',
            hero_subtitle: 'Cortes clásicos, fades, barba y tratamientos capilares.',
            logo_url: '',
            primary_color: '#d4af37',
            bg_color: '#111111'
          }
        });
      }
      return res.status(200).json({ success: true, data: doc.data() });
    } catch (error) {
      console.error('Error obteniendo ajustes:', error);
      return res.status(500).json({ error: 'Error al obtener los ajustes globales' });
    }
  }

  else if (req.method === 'PUT' || req.method === 'POST') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) {
      return res.status(403).json({ error: auth.error });
    }

    const dataToSave = {};
    for (const [key, value] of Object.entries(req.body)) {
      if (value !== undefined) {
        // No sanitizar campos base64 — contienen datos binarios
        if (key.endsWith('_b64')) {
          dataToSave[key] = value;
        } else {
          dataToSave[key] = typeof value === 'string' ? sanitize(value) : value;
        }
      }
    }

    // Subir logo a Firebase Storage si se envía base64
    if (req.body.logo_b64 && req.body.logo_b64.startsWith('data:')) {
      try {
        const mimeType = req.body.logo_b64.match(/data:(.*?);base64/)[1];
        const extension = mimeType.split('/')[1] || 'png';
        const base64Data = req.body.logo_b64.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const uniqueFileName = `logo_${Date.now()}.${extension}`;
        const filePath = `barberia-media/${uniqueFileName}`;
        const file = bucket.file(filePath);
        await file.save(buffer, {
          metadata: { contentType: mimeType },
          public: true
        });
        dataToSave.site_logo = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;
        delete dataToSave.logo_b64;
      } catch (uploadErr) {
        console.error('Error al subir logo:', uploadErr);
        return res.status(500).json({ error: 'No se pudo guardar la imagen del logo en Storage' });
      }
    }

    // Subir hero background a Firebase Storage si se envía base64
    if (req.body.hero_b64 && req.body.hero_b64.startsWith('data:')) {
      try {
        const mimeType = req.body.hero_b64.match(/data:(.*?);base64/)[1];
        const extension = mimeType.split('/')[1] || 'jpg';
        const base64Data = req.body.hero_b64.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const uniqueFileName = `hero_${Date.now()}.${extension}`;
        const filePath = `barberia-media/${uniqueFileName}`;
        const file = bucket.file(filePath);
        await file.save(buffer, {
          metadata: { contentType: mimeType },
          public: true
        });
        dataToSave.site_hero_bg = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;
        delete dataToSave.hero_b64;
      } catch (uploadErr) {
        console.error('Error al subir hero background:', uploadErr);
        return res.status(500).json({ error: 'No se pudo guardar la imagen de portada en Storage' });
      }
    }

    dataToSave.actualizado_en = new Date().toISOString();

    try {
      await ajustesRef.set(dataToSave, { merge: true });
      return res.status(200).json({ success: true, data: dataToSave });
    } catch (error) {
      console.error('Error guardando ajustes:', error);
      return res.status(500).json({ error: 'Error al guardar los ajustes globales' });
    }
  }

  return res.status(405).json({ error: 'Método no permitido' });
};
