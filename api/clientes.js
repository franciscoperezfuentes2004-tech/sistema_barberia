const { db } = require('./_config/db.js');
const { sanitize } = require('./_config/helpers.js');

module.exports = async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Método no permitido. Solo se acepta POST.' });
  }

  const { nombre, telefono, email } = req.body;

  if (!nombre || !telefono) {
    return res.status(400).json({ error: 'El nombre y el teléfono son obligatorios' });
  }

  const safeNombre = sanitize(nombre);
  const safeTelefono = sanitize(telefono).replace(/\D/g, ''); // Solo dígitos
  const safeEmail = email ? sanitize(email) : null;

  try {
    const clientesRef = db.collection('clientes');
    
    // Buscar si ya existe por teléfono
    const query = await clientesRef.where('telefono', '==', safeTelefono).limit(1).get();
    
    let clienteId;
    const clientData = {
      nombre: safeNombre,
      telefono: safeTelefono,
      email: safeEmail,
      actualizado_en: new Date().toISOString()
    };

    if (!query.empty) {
      // Cliente ya existe: actualizar datos
      const doc = query.docs[0];
      clienteId = doc.id;
      await clientesRef.doc(clienteId).update(clientData);
    } else {
      // Crear nuevo cliente
      clientData.creado_en = new Date().toISOString();
      const docRef = await clientesRef.add(clientData);
      clienteId = docRef.id;
    }

    return res.status(200).json({ 
      success: true, 
      data: { id: clienteId, ...clientData } 
    });

  } catch (error) {
    console.error('Error al registrar cliente:', error);
    return res.status(500).json({ error: 'Error del servidor al registrar los datos del cliente' });
  }
};
