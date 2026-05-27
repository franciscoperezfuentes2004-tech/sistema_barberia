const { bucket } = require('./config/db.js');
const { verifyToken, sanitize } = require('./config/helpers.js');

// Aumentamos el límite de tamaño de payload a 10MB en Vercel Serverless.
const config = {
  api: {
    bodyParser: {
      sizeLimit: '10mb',
    },
  },
};

async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Solo se permite el método POST para subida de medios' });
  }

  // 🛡️ PROTECCIÓN: Solo el personal autenticado puede subir archivos
  const auth = verifyToken(req);
  if (auth.error) {
    return res.status(401).json({ error: 'No autorizado. Acceso denegado.' });
  }

  const { fileBase64, fileName, mimeType } = req.body;

  if (!fileBase64 || !fileName || !mimeType) {
    return res.status(400).json({ error: 'Parámetros incompletos: se requiere fileBase64, fileName y mimeType' });
  }

  // 🛡️ SANITIZACIÓN
  const safeFileName = sanitize(fileName).replace(/[^a-zA-Z0-9.\-_]/g, '_');

  try {
    const base64Data = fileBase64.replace(/^data:image\/\w+;base64,/, '');
    const buffer = Buffer.from(base64Data, 'base64');

    const uniqueFileName = `${Date.now()}_${safeFileName}`;
    const filePath = `barberia-media/${uniqueFileName}`;
    
    // Crear referencia al archivo en el bucket de Firebase Storage
    const file = bucket.file(filePath);

    // Subir el buffer al bucket
    await file.save(buffer, {
      metadata: {
        contentType: mimeType,
      },
      public: true // Hace que el archivo sea accesible públicamente por defecto (requiere que el bucket lo permita)
    });

    // La URL de lectura de Firebase es así:
    const publicUrl = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;

    return res.status(200).json({ 
      success: true, 
      url: publicUrl 
    });

  } catch (error) {
    console.error('Error interno grave durante la subida a Firebase Storage:', error);
    return res.status(500).json({ error: 'Servidor incapaz de procesar el archivo en Firebase Storage' });
  }
}

module.exports = handler;
module.exports.config = config;
