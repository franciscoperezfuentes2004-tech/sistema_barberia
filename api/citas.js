const { db } = require('./_config/db.js');
const { verifyToken, sanitize } = require('./_config/helpers.js');

module.exports = async function handler(req, res) {
  const method = req.method;
  const citasRef = db.collection('citas');

  // GET: Listar citas
  if (method === 'GET') {
    // 🛡️ PROTECCIÓN: Solo personal autenticado puede ver la agenda
    const auth = verifyToken(req);
    if (auth.error) {
      return res.status(401).json({ error: auth.error });
    }

    const { barbero_id, fecha } = req.query;
    
    try {
      let query = citasRef;

      // REGLA: Si el rol es 'barbero', forzar el filtro a su propio ID
      if (auth.user.rol === 'barbero') {
        query = query.where('barbero_id', '==', auth.user.id);
      } else if (barbero_id) {
        query = query.where('barbero_id', '==', barbero_id);
      }
      
      if (fecha) {
        query = query.where('fecha', '==', fecha);
      }

      // Ordenamos por hora de inicio (la fecha ya está filtrada o no, Firestore manejará el sort en memoria si falta índice)
      // Nota: Si usas múltiples campos en .where() y .orderBy(), Firestore te pedirá crear un Índice Compuesto.
      
      const snapshot = await query.get();
      
      let citas = [];
      snapshot.forEach(doc => {
        citas.push({ id: doc.id, ...doc.data() });
      });

      // Ordenar manualmente en memoria para evitar errores de índice compuesto en Firebase si no están creados
      citas.sort((a, b) => {
        if (a.fecha !== b.fecha) return a.fecha.localeCompare(b.fecha);
        return a.hora_inicio.localeCompare(b.hora_inicio);
      });

      return res.status(200).json({ success: true, data: citas });
    } catch (error) {
      console.error('Error obteniendo historial de citas:', error);
      return res.status(500).json({ error: 'Error interno al consultar la agenda en Firestore' });
    }
  }

  // POST: Agendar nueva cita
  else if (method === 'POST') {
    // Endpoint PÚBLICO para clientes
    const { cliente_nombre, cliente_telefono, cliente_email, barbero_id, servicio_id, fecha, hora_inicio, notas } = req.body;

    if (!cliente_nombre || !cliente_telefono || !barbero_id || !servicio_id || !fecha || !hora_inicio) {
      return res.status(400).json({ error: 'Faltan campos obligatorios para asegurar la cita' });
    }

    // 🛡️ SANITIZACIÓN CONTRA XSS
    const safeNombre = sanitize(cliente_nombre);
    const safeTelefono = sanitize(cliente_telefono);
    const safeEmail = sanitize(cliente_email);
    const safeNotas = sanitize(notas);

    try {
      // 1. Extraer la información del servicio para la duración (Desnormalización)
      const servicioDoc = await db.collection('servicios').doc(servicio_id).get();
      if (!servicioDoc.exists) {
        return res.status(404).json({ error: 'El servicio solicitado no existe' });
      }
      const servicioData = servicioDoc.data();
      const duracionMin = Number(servicioData.duracion_min);

      // 2. Extraer información del barbero (Desnormalización)
      const barberoDoc = await db.collection('usuarios').doc(barbero_id).get();
      if (!barberoDoc.exists) {
        return res.status(404).json({ error: 'El barbero seleccionado no existe' });
      }
      const barberoData = barberoDoc.data();

      // 3. Calcular hora_fin
      const inicioDate = new Date(`1970-01-01T${hora_inicio}Z`);
      const finDate = new Date(inicioDate.getTime() + (duracionMin * 60000));
      const hora_fin = finDate.toISOString().substring(11, 19);

      // 4. Crear documento
      const nuevaCita = {
        barbero_id,
        servicio_id,
        cliente_nombre: safeNombre,
        cliente_telefono: safeTelefono,
        cliente_email: safeEmail || null,
        fecha,
        hora_inicio,
        hora_fin,
        notas: safeNotas || null,
        estado: 'pendiente',
        // Datos desnormalizados para evitar JOINs
        servicio_nombre: servicioData.nombre,
        duracion_min: duracionMin,
        barbero_nombre: barberoData.nombre,
        barbero_apellido: barberoData.apellido,
        creado_en: new Date().toISOString()
      };

      const docRef = await citasRef.add(nuevaCita);

      return res.status(201).json({ success: true, data: { id: docRef.id, ...nuevaCita } });
    } catch (error) {
      console.error('Error procesando reserva:', error);
      return res.status(500).json({ error: 'Error del servidor al confirmar tu reservación' });
    }
  }

  // PUT / PATCH: Cambiar estado del proceso
  else if (method === 'PUT' || method === 'PATCH') {
    // 🛡️ PROTECCIÓN: Solo personal autenticado puede modificar citas
    const auth = verifyToken(req);
    if (auth.error) {
      return res.status(401).json({ error: auth.error });
    }

    const { id, estado } = req.body;

    if (!id || !estado) {
      return res.status(400).json({ error: 'Es necesario el id de la cita y el nuevo estado' });
    }

    const estadosValidos = ['pendiente', 'confirmada', 'en_silla', 'completada', 'cancelada', 'no_asistio'];
    if (!estadosValidos.includes(estado)) {
      return res.status(400).json({ error: 'Transición a estado inválido o no reconocido' });
    }

    try {
      const citaRef = citasRef.doc(id);
      const citaDoc = await citaRef.get();

      if (!citaDoc.exists) {
        return res.status(404).json({ error: 'No se encontró la cita especificada' });
      }

      await citaRef.update({ estado, actualizado_en: new Date().toISOString() });

      return res.status(200).json({ success: true, data: { id, ...citaDoc.data(), estado } });
    } catch (error) {
      console.error('Error alterando estado de la cita:', error);
      return res.status(500).json({ error: 'Fallo al intentar actualizar el estado de atención' });
    }
  }

  return res.status(405).json({ error: 'Método HTTP no permitido' });
};
