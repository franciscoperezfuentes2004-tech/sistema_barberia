const { db } = require('./config/db.js');

module.exports = async function handler(req, res) {
  if (req.method !== 'GET') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  const { mes, anio, duracion } = req.query;
  if (!mes || !anio || !duracion) {
    return res.status(400).json({ error: 'Faltan parámetros: mes, anio, duracion' });
  }

  const duracionDeseada = parseInt(duracion, 10);
  const m = parseInt(mes, 10);
  const a = parseInt(anio, 10);

  // Fechas límite del mes
  const startDate = new Date(a, m - 1, 1);
  const endDate = new Date(a, m, 0); // último día del mes
  const numDays = endDate.getDate();

  // Fecha de hoy (Ajuste simplificado para UTC-6, CDMX)
  const tzOffset = -6 * 60; // offset en minutos para CDMX
  const now = new Date();
  const localNow = new Date(now.getTime() + (now.getTimezoneOffset() + tzOffset) * 60000);
  
  const strHoy = localNow.getFullYear() + '-' + 
                 String(localNow.getMonth() + 1).padStart(2,'0') + '-' + 
                 String(localNow.getDate()).padStart(2,'0');

  // Límite de 30 días a futuro
  const futureDate = new Date(localNow.getTime() + (30 * 24 * 3600000));
  const strMaxDate = futureDate.getFullYear() + '-' + 
                     String(futureDate.getMonth() + 1).padStart(2,'0') + '-' + 
                     String(futureDate.getDate()).padStart(2,'0');

  try {
    // 1. Obtener barberos activos
    const barberosSnapshot = await db.collection('usuarios').where('rol', '==', 'barbero').get();
    const barberos = [];
    barberosSnapshot.forEach(doc => {
      const data = doc.data();
      if (data.activo === true || data.activo === 1 || data.activo === '1') {
        barberos.push({ id: doc.id });
      }
    });

    if (barberos.length === 0) {
      return res.status(200).json({ success: true, data: [] });
    }

    // 2. Obtener horarios (global y por barbero)
    const horariosSnapshot = await db.collection('horarios').get();
    const horariosMap = {};
    horariosSnapshot.forEach(doc => {
      horariosMap[doc.id] = doc.data().schedule;
    });
    const globalSchedule = horariosMap['global'] || [];

    // 3. Obtener todas las citas del mes
    const startStr = `${a}-${String(m).padStart(2,'0')}-01`;
    const endStr = `${a}-${String(m).padStart(2,'0')}-${String(numDays).padStart(2,'0')}`;
    
    const citasSnapshot = await db.collection('citas')
      .where('fecha', '>=', startStr)
      .where('fecha', '<=', endStr)
      .get();
      
    const citasList = [];
    citasSnapshot.forEach(doc => {
      const c = doc.data();
      if (c.estado !== 'cancelada' && c.estado !== 'no_asistio') {
        citasList.push(c);
      }
    });

    // 4. Calcular estado por día
    const resultado = [];

    for (let d = 1; d <= numDays; d++) {
      const fechaStr = `${a}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const fechaObj = new Date(fechaStr + 'T12:00:00Z'); // Mediodía UTC para evitar shifts de día
      const diaSemana = fechaObj.getUTCDay();

      let estado = 'evaluar';
      
      // Validaciones
      if (fechaStr < strHoy) {
        estado = 'pasado';
      } else if (fechaStr > strMaxDate) {
        estado = 'pasado'; // Supera los 30 días, se marca como gris/pasado
      }

      if (estado === 'pasado') {
        resultado.push({ fecha: fechaStr, estado: 'pasado' });
        continue;
      }

      // Evaluar disponibilidad
      let totalSlots = 0;
      let abierto = false;

      for (const barbero of barberos) {
        const schedule = horariosMap[barbero.id] || globalSchedule;
        const diaConfig = schedule.find(s => s.dia_semana === diaSemana);
        
        if (!diaConfig) continue;
        const diaActivo = diaConfig.activo === 1 || diaConfig.activo === true;
        if (!diaActivo) continue;

        abierto = true;
        
        let apertura = diaConfig.hora_inicio.length === 5 ? diaConfig.hora_inicio + ':00' : diaConfig.hora_inicio;
        let cierre = diaConfig.hora_fin.length === 5 ? diaConfig.hora_fin + ':00' : diaConfig.hora_fin;

        let horaActual = new Date(`1970-01-01T${apertura}Z`);
        const horaCierre = new Date(`1970-01-01T${cierre}Z`);

        const citasBarbero = citasList.filter(c => c.barbero_id === barbero.id && c.fecha === fechaStr);

        let horaLimite = null;
        if (fechaStr === strHoy) {
            horaLimite = String(localNow.getHours()).padStart(2,'0') + ':' + String(localNow.getMinutes()).padStart(2,'0');
        }

        while (horaActual < horaCierre) {
          const bloqueInicioMs = horaActual.getTime();
          const bloqueFinMs = bloqueInicioMs + (duracionDeseada * 60000);
          const finDelDiaMs = horaCierre.getTime();
          const horaFormato = horaActual.toISOString().substring(11, 16);

          if (bloqueFinMs > finDelDiaMs) break;

          if (horaLimite && horaFormato <= horaLimite) {
            horaActual = new Date(horaActual.getTime() + (30 * 60000));
            continue;
          }

          let ocupado = false;
          for (const cita of citasBarbero) {
            const citaInicio = cita.hora_inicio.length === 5 ? cita.hora_inicio + ':00' : cita.hora_inicio;
            const citaFin = cita.hora_fin.length === 5 ? cita.hora_fin + ':00' : cita.hora_fin;
            const citaInicioMs = new Date(`1970-01-01T${citaInicio}Z`).getTime();
            const citaFinMs = new Date(`1970-01-01T${citaFin}Z`).getTime();

            if (bloqueInicioMs < citaFinMs && bloqueFinMs > citaInicioMs) {
              ocupado = true; break;
            }
          }

          if (!ocupado) {
            totalSlots++;
          }
          horaActual = new Date(horaActual.getTime() + (30 * 60000));
        }
      }

      if (!abierto) {
         resultado.push({ fecha: fechaStr, estado: 'cerrado' });
      } else {
         if (fechaStr === strHoy) {
           resultado.push({ fecha: fechaStr, estado: 'hoy', slots: totalSlots });
         } else if (totalSlots > 0) {
           resultado.push({ fecha: fechaStr, estado: 'disponible', slots: totalSlots });
         } else {
           resultado.push({ fecha: fechaStr, estado: 'lleno', slots: 0 });
         }
      }
    }

    return res.status(200).json({ success: true, data: resultado });

  } catch (err) {
    console.error('Error api/calendario:', err);
    return res.status(500).json({ error: 'Error interno obteniendo calendario' });
  }
};
