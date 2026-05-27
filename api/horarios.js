const { db } = require('./_config/db.js');
const { verifyToken } = require('./_config/helpers.js');

module.exports = async function handler(req, res) {
  const horariosRef = db.collection('horarios');

  if (req.method === 'GET') {
    const { global, barbero_id, fecha, duracion } = req.query;

    // Si viene la fecha, asumimos que es una consulta de disponibilidad de turnos/slots
    if (fecha) {
      if (!barbero_id || !duracion) {
        return res.status(400).json({ error: 'Faltan parámetros requeridos para disponibilidad: barbero_id, duracion' });
      }
      
      const duracionDeseada = parseInt(duracion, 10);

      try {
        // Obtener horario del barbero o global para el día solicitado
        const fechaDate = new Date(fecha + 'T12:00:00Z');
        const diaSemana = fechaDate.getUTCDay();

        let schedule = null;
        const barberoHorarioDoc = await db.collection('horarios').doc(barbero_id).get();
        if (barberoHorarioDoc.exists && barberoHorarioDoc.data().schedule) {
          schedule = barberoHorarioDoc.data().schedule;
        } else {
          const globalHorarioDoc = await db.collection('horarios').doc('global').get();
          if (globalHorarioDoc.exists && globalHorarioDoc.data().schedule) {
            schedule = globalHorarioDoc.data().schedule;
          }
        }

        // Determinar horario de apertura y cierre del día
        let apertura = '10:00:00';
        let cierre = '20:00:00';
        let diaActivo = true;

        if (schedule && Array.isArray(schedule)) {
          const diaConfig = schedule.find(s => s.dia_semana === diaSemana);
          if (diaConfig) {
            diaActivo = diaConfig.activo === 1 || diaConfig.activo === true;
            if (diaConfig.hora_inicio) apertura = diaConfig.hora_inicio.length === 5 ? diaConfig.hora_inicio + ':00' : diaConfig.hora_inicio;
            if (diaConfig.hora_fin) cierre = diaConfig.hora_fin.length === 5 ? diaConfig.hora_fin + ':00' : diaConfig.hora_fin;
          }
        }

        if (!diaActivo) {
          return res.status(200).json({ success: true, data: { slots: [] } });
        }

        // Obtener citas existentes del barbero en ese día
        const citasRef = db.collection('citas');
        const snapshot = await citasRef
          .where('barbero_id', '==', barbero_id)
          .where('fecha', '==', fecha)
          .get();

        const citas = [];
        snapshot.forEach(doc => {
          const data = doc.data();
          if (data.estado !== 'cancelada' && data.estado !== 'no_asistio') {
            citas.push(data);
          }
        });
        citas.sort((a, b) => a.hora_inicio.localeCompare(b.hora_inicio));

        // Generar TODOS los bloques de 30 minutos y marcar disponibilidad
        const allSlots = [];
        let horaActual = new Date(`1970-01-01T${apertura}Z`);
        const horaCierre = new Date(`1970-01-01T${cierre}Z`);

        while (horaActual < horaCierre) {
          const bloqueInicioMs = horaActual.getTime();
          const bloqueFinMs = bloqueInicioMs + (duracionDeseada * 60000);
          const finDelDiaMs = horaCierre.getTime();
          const horaFormato = horaActual.toISOString().substring(11, 16);

          // Si el servicio no cabe antes del cierre, marcar como no disponible
          if (bloqueFinMs > finDelDiaMs) {
            allSlots.push({ hora_inicio: horaFormato, disponible: false });
            horaActual = new Date(horaActual.getTime() + (30 * 60000));
            continue;
          }

          let bloqueOcupado = false;

          for (const cita of citas) {
            const citaInicio = cita.hora_inicio.length === 5 ? cita.hora_inicio + ':00' : cita.hora_inicio;
            const citaFin = cita.hora_fin.length === 5 ? cita.hora_fin + ':00' : cita.hora_fin;
            const citaInicioMs = new Date(`1970-01-01T${citaInicio}Z`).getTime();
            const citaFinMs = new Date(`1970-01-01T${citaFin}Z`).getTime();

            // Verificar si el bloque completo del servicio colisiona con esta cita
            if (bloqueInicioMs < citaFinMs && bloqueFinMs > citaInicioMs) {
              bloqueOcupado = true;
              break;
            }
          }

          allSlots.push({ hora_inicio: horaFormato, disponible: !bloqueOcupado });
          horaActual = new Date(horaActual.getTime() + (30 * 60000));
        }

        return res.status(200).json({ success: true, data: { slots: allSlots } });

      } catch (error) {
        console.error('Error calculando disponibilidad:', error);
        return res.status(500).json({ error: 'Error interno calculando disponibilidad en Firestore' });
      }
    }

    // De lo contrario, es una consulta de configuración de horarios (global o barbero específico)
    try {
      if (global) {
        const doc = await horariosRef.doc('global').get();
        const data = doc.exists ? doc.data().schedule : getDefaultSchedule();
        return res.status(200).json({ success: true, data: data });
      } else if (barbero_id) {
        const doc = await horariosRef.doc(barbero_id).get();
        if (!doc.exists) {
          const globalDoc = await horariosRef.doc('global').get();
          const globalData = globalDoc.exists ? globalDoc.data().schedule : getDefaultSchedule();
          return res.status(200).json({ success: true, data: globalData });
        }
        return res.status(200).json({ success: true, data: doc.data().schedule });
      }
      return res.status(400).json({ error: 'Debes especificar ?global=1, ?barbero_id=X, o ?fecha=Y&barbero_id=X&duracion=Z' });
    } catch (error) {
      console.error('Error obteniendo horarios:', error);
      return res.status(500).json({ error: 'Error al cargar los horarios desde la base de datos' });
    }
  }

  else if (req.method === 'POST') {
    // 🛡️ PROTECCIÓN: Solo admins pueden cambiar los horarios
    const auth = verifyToken(req, ['admin']);
    if (auth.error) {
      return res.status(403).json({ error: auth.error });
    }

    const { global, barbero_id } = req.query;
    const scheduleData = req.body.horario ? req.body.horario : req.body;

    if (!scheduleData || !Array.isArray(scheduleData) || scheduleData.length === 0) {
      return res.status(400).json({ error: 'No se enviaron datos de horario válidos' });
    }

    try {
      if (global) {
        await horariosRef.doc('global').set({ schedule: scheduleData });
        return res.status(200).json({ success: true, message: 'Horario global actualizado exitosamente' });
      } else if (barbero_id) {
        await horariosRef.doc(barbero_id).set({ schedule: scheduleData });
        return res.status(200).json({ success: true, message: 'Horario del barbero actualizado exitosamente' });
      }
      return res.status(400).json({ error: 'Debes especificar ?global=1 o ?barbero_id=X para actualizar' });
    } catch (error) {
      console.error('Error guardando horario:', error);
      return res.status(500).json({ error: 'Error al guardar los horarios en Firestore' });
    }
  }

  return res.status(405).json({ error: 'Método HTTP no permitido' });
};

function getDefaultSchedule() {
  const arr = [];
  for (let i = 0; i <= 6; i++) {
    arr.push({
      dia_semana: i,
      activo: i === 0 ? 0 : 1, // Domingo cerrado (0), los demás abiertos (1)
      hora_inicio: '10:00',
      hora_fin: '20:00'
    });
  }
  return arr;
}
