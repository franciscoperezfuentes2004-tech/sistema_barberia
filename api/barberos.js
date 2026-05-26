const { db } = require('./config/db.js');
const bcrypt = require('bcryptjs');
const { verifyToken, sanitize } = require('./config/helpers.js');

module.exports = async function handler(req, res) {
  const usuariosRef = db.collection('usuarios');

  if (req.method === 'GET') {
    const { all, fecha, duracion, hora_actual } = req.query;
    const showAll = all === 'true' || all === '1';
    
    // 🛡️ PROTECCIÓN: Si se solicitan 'todos' los barberos, requiere ser Admin
    if (showAll) {
      const auth = verifyToken(req, ['admin']);
      if (auth.error) {
        return res.status(403).json({ error: auth.error });
      }
    }

    try {
      const query = usuariosRef.where('rol', '==', 'barbero');
      const snapshot = await query.get();
      
      // Obtener todos los horarios para calcular disponible_hoy
      const horariosSnapshot = await db.collection('horarios').get();
      const horariosMap = {};
      horariosSnapshot.forEach(doc => {
        horariosMap[doc.id] = doc.data().schedule;
      });
      const globalSchedule = horariosMap['global'] || [];

      let barberos = [];
      const todayDay = new Date().getDay();

      for (const doc of snapshot.docs) {
        const data = doc.data();
        
        // Filtrar en memoria para soportar activo como boolean true, número 1 o string "1"
        const isActivo = data.activo === true || data.activo === 1 || data.activo === '1';
        if (!showAll && !isActivo) {
          continue;
        }

        delete data.password_hash;

        // Determinar disponible_hoy basado en su horario
        const schedule = horariosMap[doc.id] || globalSchedule;
        const todaySchedule = schedule.find(s => s.dia_semana === todayDay);
        const disponible_hoy = todaySchedule ? todaySchedule.activo === 1 || todaySchedule.activo === true : true;

        // Calcular slots_disponibles si se solicitó fecha y duración (para la página de agendar cita)
        let slots_disponibles = 0;
        if (fecha && duracion) {
          const duracionDeseada = parseInt(duracion, 10) || 30;
          
          // Obtener horario del barbero para el día solicitado
          const fechaDate = new Date(fecha + 'T12:00:00Z');
          const diaSemana = fechaDate.getUTCDay();
          
          const barberoSchedule = horariosMap[doc.id] || globalSchedule;
          const diaConfig = barberoSchedule.find(s => s.dia_semana === diaSemana);
          
          let apertura = '10:00:00';
          let cierre = '20:00:00';
          let diaActivo = true;
          
          if (diaConfig) {
            diaActivo = diaConfig.activo === 1 || diaConfig.activo === true;
            if (diaConfig.hora_inicio) apertura = diaConfig.hora_inicio.length === 5 ? diaConfig.hora_inicio + ':00' : diaConfig.hora_inicio;
            if (diaConfig.hora_fin) cierre = diaConfig.hora_fin.length === 5 ? diaConfig.hora_fin + ':00' : diaConfig.hora_fin;
          }
          
          if (!diaActivo) {
            slots_disponibles = 0;
          } else {
            // Obtener citas del barbero para la fecha
            const citasSnapshot = await db.collection('citas')
              .where('barbero_id', '==', doc.id)
              .where('fecha', '==', fecha)
              .get();

            const citas = [];
            citasSnapshot.forEach(c => {
              const cData = c.data();
              if (cData.estado !== 'cancelada' && cData.estado !== 'no_asistio') {
                citas.push(cData);
              }
            });

            // Calcular bloques disponibles
            let horaActual = new Date(`1970-01-01T${apertura}Z`);
            const horaCierre = new Date(`1970-01-01T${cierre}Z`);

            while (horaActual < horaCierre) {
              const bloqueInicioMs = horaActual.getTime();
              const bloqueFinMs = bloqueInicioMs + (duracionDeseada * 60000);
              const finDelDiaMs = horaCierre.getTime();
              const horaFormato = horaActual.toISOString().substring(11, 16);

              if (bloqueFinMs > finDelDiaMs) break;

              // Si se proporcionó hora_actual y este slot ya pasó, saltarlo
              if (hora_actual && horaFormato <= hora_actual) {
                horaActual = new Date(horaActual.getTime() + (30 * 60000));
                continue;
              }

              let bloqueOcupado = false;
              for (const cita of citas) {
                const citaInicio = cita.hora_inicio.length === 5 ? cita.hora_inicio + ':00' : cita.hora_inicio;
                const citaFin = cita.hora_fin.length === 5 ? cita.hora_fin + ':00' : cita.hora_fin;
                const citaInicioMs = new Date(`1970-01-01T${citaInicio}Z`).getTime();
                const citaFinMs = new Date(`1970-01-01T${citaFin}Z`).getTime();

                if (bloqueInicioMs < citaFinMs && bloqueFinMs > citaInicioMs) {
                  bloqueOcupado = true;
                  break;
                }
              }

              if (!bloqueOcupado) {
                slots_disponibles++;
              }
              horaActual = new Date(horaActual.getTime() + (30 * 60000));
            }
          }
        } else {
          // Si no se especifica fecha, se asume que para efectos de la vista de agendar 
          // tiene espacios disponibles si el barbero está activo y disponible hoy
          slots_disponibles = disponible_hoy ? 10 : 0;
        }

        barberos.push({ 
          id: doc.id, 
          foto: data.imagen_url || '', 
          disponible_hoy,
          slots_disponibles,
          ...data 
        });
      }

      barberos.sort((a, b) => a.nombre.localeCompare(b.nombre));
      return res.status(200).json({ success: true, data: barberos });
    } catch (error) {
      console.error('Error obteniendo la lista de barberos:', error);
      return res.status(500).json({ error: 'Error interno obteniendo personal' });
    }
  } 
  
  else if (req.method === 'POST') {
    // 🛡️ PROTECCIÓN: Solo Admin puede crear barberos
    const auth = verifyToken(req, ['admin']);
    if (auth.error) {
      return res.status(403).json({ error: auth.error });
    }
    
    const { username, password, nombre, apellido, especialidad, imagen_url, activo } = req.body;

    if (!username || !nombre || !apellido) {
      return res.status(400).json({ error: 'Faltan campos obligatorios: username, nombre, apellido' });
    }

    // 🛡️ SANITIZACIÓN CONTRA XSS
    const safeUsername = sanitize(username);
    const safeNombre = sanitize(nombre);
    const safeApellido = sanitize(apellido);
    const safeEspecialidad = sanitize(especialidad);

    try {
      // Validar que el username no exista ya
      const existSnapshot = await usuariosRef.where('username', '==', safeUsername).limit(1).get();
      if (!existSnapshot.empty) {
        return res.status(409).json({ error: 'El nombre de usuario ya está registrado en el sistema' });
      }

      const plainPassword = password ? password : '123456';
      const passHash = await bcrypt.hash(plainPassword, 10);

      const nuevoBarbero = {
        username: safeUsername,
        password_hash: passHash,
        nombre: safeNombre,
        apellido: safeApellido,
        rol: 'barbero',
        especialidad: safeEspecialidad || null,
        imagen_url: imagen_url || null,
        activo: activo !== undefined ? activo : true,
        creado_en: new Date().toISOString()
      };

      const docRef = await usuariosRef.add(nuevoBarbero);
      
      const respuesta = { id: docRef.id, ...nuevoBarbero };
      delete respuesta.password_hash;

      return res.status(201).json({ success: true, data: respuesta });
    } catch (error) {
      console.error('Error dando de alta al barbero:', error);
      return res.status(500).json({ error: 'Error interno al registrar al barbero' });
    }
  }

  else if (req.method === 'PUT') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) return res.status(403).json({ error: auth.error });

    const { id } = req.query;
    if (!id) return res.status(400).json({ error: 'Falta el ID del barbero' });

    const dataToUpdate = {};
    for (const [key, value] of Object.entries(req.body)) {
      if (value !== undefined && key !== 'id') {
        dataToUpdate[key] = typeof value === 'string' ? sanitize(value) : value;
      }
    }

    try {
      await usuariosRef.doc(id).update(dataToUpdate);
      return res.status(200).json({ success: true, message: 'Barbero actualizado' });
    } catch (error) {
      console.error('Error actualizando barbero:', error);
      return res.status(500).json({ error: 'Error actualizando barbero' });
    }
  }

  else if (req.method === 'DELETE') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) return res.status(403).json({ error: auth.error });

    const { id } = req.query;
    if (!id) return res.status(400).json({ error: 'Falta el ID del barbero' });

    try {
      await usuariosRef.doc(id).delete();
      return res.status(200).json({ success: true, message: 'Barbero eliminado' });
    } catch (error) {
      console.error('Error eliminando barbero:', error);
      return res.status(500).json({ error: 'Error eliminando barbero' });
    }
  }

  return res.status(405).json({ error: 'Método HTTP no permitido' });
};
