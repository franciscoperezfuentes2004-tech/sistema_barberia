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
    const { cliente_id, barbero_id, servicios, servicio_id, fecha, hora_inicio, notas } = req.body;

    if (!barbero_id || !fecha || !hora_inicio) {
      return res.status(400).json({ error: 'Faltan campos obligatorios para asegurar la cita (barbero, fecha, hora)' });
    }

    try {
      // 1. Obtener detalles del cliente si se proporciona cliente_id, o usar los campos directos del body
      let clientName = '';
      let clientPhone = '';
      let clientEmail = '';
      
      if (cliente_id) {
        const clientDoc = await db.collection('clientes').doc(cliente_id).get();
        if (clientDoc.exists) {
          const clientData = clientDoc.data();
          clientName = clientData.nombre;
          clientPhone = clientData.telefono;
          clientEmail = clientData.email || '';
        }
      }

      const finalNombre = clientName || sanitize(req.body.cliente_nombre);
      const finalTelefono = clientPhone || sanitize(req.body.cliente_telefono);
      const finalEmail = clientEmail || sanitize(req.body.cliente_email);

      if (!finalNombre || !finalTelefono) {
        return res.status(400).json({ error: 'Es necesario proporcionar el nombre y teléfono del cliente' });
      }

      // 2. Extraer información de los servicios seleccionados (Soporta arreglo o id único para retrocompatibilidad)
      const serviceIds = Array.isArray(servicios) ? servicios : (servicio_id ? [servicio_id] : []);
      if (serviceIds.length === 0) {
        return res.status(400).json({ error: 'Es necesario seleccionar al menos un servicio para la reserva' });
      }

      let totalPrecio = 0;
      let totalDuracion = 0;
      const serviciosDetalle = [];
      const nombresServicios = [];

      for (const sId of serviceIds) {
        const svcDoc = await db.collection('servicios').doc(sId).get();
        if (svcDoc.exists) {
          const svcData = svcDoc.data();
          totalPrecio += parseFloat(svcData.precio) || 0;
          totalDuracion += parseInt(svcData.duracion_min) || 30;
          serviciosDetalle.push({
            id: sId,
            nombre: svcData.nombre,
            precio: svcData.precio,
            duracion_min: svcData.duracion_min
          });
          nombresServicios.push(svcData.nombre);
        }
      }

      // 3. Extraer información del barbero (Desnormalización)
      const barberoDoc = await db.collection('usuarios').doc(barbero_id).get();
      if (!barberoDoc.exists) {
        return res.status(404).json({ error: 'El barbero seleccionado no existe' });
      }
      const barberoData = barberoDoc.data();

      // 4. Calcular hora_fin
      const inicioDate = new Date(`1970-01-01T${hora_inicio}Z`);
      const finDate = new Date(inicioDate.getTime() + (totalDuracion * 60000));
      const hora_fin = finDate.toISOString().substring(11, 19);

      // 5. Crear documento de la cita
      const nuevaCita = {
        cliente_id: cliente_id || null,
        barbero_id,
        servicios: serviceIds,
        servicios_detalle: serviciosDetalle,
        cliente_nombre: finalNombre,
        cliente_telefono: finalTelefono,
        cliente_email: finalEmail || null,
        fecha,
        hora_inicio,
        hora_fin,
        notas: sanitize(notas) || null,
        estado: 'pendiente',
        // Datos desnormalizados para evitar JOINs y mantener compatibilidad
        servicio_nombre: nombresServicios.join(', '),
        duracion_min: totalDuracion,
        precio_total: totalPrecio,
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
