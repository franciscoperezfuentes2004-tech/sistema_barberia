const { db } = require('./_config/db.js');
const { verifyToken, sanitize } = require('./_config/helpers.js');

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
        dataToSave[key] = typeof value === 'string' ? sanitize(value) : value;
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
