const { db, bucket } = require('../_config/db.js');
const { verifyToken, sanitize } = require('../_config/helpers.js');

module.exports = async function handler(req, res) {
  const { tipo } = req.query;

  switch (tipo) {

    case 'barberos': {
      
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
          const diaConfig = barberoSchedule?.find(s => s.dia_semana === diaSemana);
          
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

              // Calcular hora límite real
              let horaLimite = null;
              if (hora_actual) {
                horaLimite = hora_actual;
              } else {
                const tzOffset = -6 * 60; 
                const now = new Date();
                const localNow = new Date(now.getTime() + (now.getTimezoneOffset() + tzOffset) * 60000);
                const strHoy = localNow.getFullYear() + '-' + String(localNow.getMonth() + 1).padStart(2,'0') + '-' + String(localNow.getDate()).padStart(2,'0');
                if (fecha === strHoy) {
                  horaLimite = String(localNow.getHours()).padStart(2,'0') + ':' + String(localNow.getMinutes()).padStart(2,'0');
                }
              }

              if (horaLimite && horaFormato <= horaLimite) {
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
    
    const { username, password, nombre, apellido, especialidad, activo } = req.body;
    let { imagen_url } = req.body;

    if (!username || !nombre || !apellido) {
      return res.status(400).json({ error: 'Faltan campos obligatorios: username, nombre, apellido' });
    }

    // 🛡️ SANITIZACIÓN CONTRA XSS
    const safeUsername = sanitize(username);
    const safeNombre = sanitize(nombre);
    const safeApellido = sanitize(apellido);
    const safeEspecialidad = sanitize(especialidad);

    if (imagen_url && imagen_url.startsWith('data:')) {
      try {
        const mimeType = imagen_url.match(/data:(.*?);base64/)[1];
        const ext = mimeType.split('/')[1] || 'jpg';
        const base64Data = imagen_url.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const fileName = `barbero_${Date.now()}.${ext}`;
        const filePath = `uploads/${fileName}`;
        const file = bucket.file(filePath);
        
        await file.save(buffer, {
          metadata: { contentType: mimeType }
        });
        finalImageUrl = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;
      } catch (err) {
        console.error('Error subiendo foto de barbero:', err);
      }
    }

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
        especialidad: safeEspecialidad || 'Barbero',
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
        if (typeof value === 'string' && value.startsWith('data:')) {
          dataToUpdate[key] = value;
        } else {
          dataToUpdate[key] = typeof value === 'string' ? sanitize(value) : value;
        }
      }
    }

    if (dataToUpdate.imagen_url && dataToUpdate.imagen_url.startsWith('data:')) {
      try {
        const mimeType = dataToUpdate.imagen_url.match(/data:(.*?);base64/)[1];
        const ext = mimeType.split('/')[1] || 'jpg';
        const base64Data = dataToUpdate.imagen_url.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const fileName = `barbero_${Date.now()}.${ext}`;
        const filePath = `uploads/${fileName}`;
        const file = bucket.file(filePath);
        
        await file.save(buffer, {
          metadata: { contentType: mimeType }
        });
        dataToUpdate.imagen_url = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;
      } catch (err) {
        console.error('Error subiendo foto de barbero:', err);
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

      break;
    }
    
    case 'servicios': {
      
  const serviciosRef = db.collection('servicios');

  if (req.method === 'GET') {
    // PÚBLICO: Cualquiera puede ver los servicios
    try {
      // Remover .orderBy('nombre', 'asc') para evitar el error de índice compuesto faltante en Firebase (Error 500)
      const snapshot = await serviciosRef.get();
      
      const servicios = [];
      snapshot.forEach(doc => {
        const data = doc.data();
        if (data.activo === false || data.activo === 0 || data.activo === '0' || data.activo === 'false') return;
        servicios.push({ 
          id: doc.id, 
          ...data,
          activo: 1 
        });
      });

      // Ordenar en memoria por nombre
      servicios.sort((a, b) => a.nombre.localeCompare(b.nombre));

      return res.status(200).json({ success: true, data: servicios });
    } catch (error) {
      console.error('Error obteniendo servicios:', error);
      return res.status(500).json({ error: 'Error interno obteniendo catálogo de servicios' });
    }
  } 
  
  else if (req.method === 'POST') {
    // 🛡️ PROTECCIÓN: Solo administradores pueden crear servicios
    const auth = verifyToken(req, ['admin']);
    if (auth.error) {
      return res.status(403).json({ error: auth.error });
    }
    
    const { nombre, descripcion, precio, duracion_min } = req.body;
    let { imagen_url } = req.body;

    if (!nombre || !precio || !duracion_min) {
      return res.status(400).json({ error: 'Faltan campos obligatorios: nombre, precio, duracion_min' });
    }

    // 🛡️ SANITIZACIÓN CONTRA XSS
    const safeNombre = sanitize(nombre);
    const safeDescripcion = sanitize(descripcion);

    if (imagen_url && imagen_url.startsWith('data:')) {
      try {
        const mimeType = imagen_url.match(/data:(.*?);base64/)[1];
        const ext = mimeType.split('/')[1] || 'jpg';
        const base64Data = imagen_url.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const fileName = `servicio_${Date.now()}.${ext}`;
        const filePath = `uploads/${fileName}`;
        const file = bucket.file(filePath);
        
        await file.save(buffer, {
          metadata: { contentType: mimeType }
        });
        finalImageUrl = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;
      } catch (err) {
        console.error('Error subiendo imagen de servicio:', err);
      }
    }

    try {
      const nuevoServicio = {
        nombre: safeNombre,
        descripcion: safeDescripcion || null,
        precio: Number(precio),
        duracion_min: Number(duracion_min),
        imagen_url: imagen_url || null,
        activo: true,
        creado_en: new Date().toISOString()
      };

      const docRef = await serviciosRef.add(nuevoServicio);
      
      return res.status(201).json({ success: true, data: { id: docRef.id, ...nuevoServicio } });
    } catch (error) {
      console.error('Error creando nuevo servicio:', error);
      return res.status(500).json({ error: 'Error interno al guardar el servicio en Firestore' });
    }
  }

  else if (req.method === 'PUT') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) return res.status(403).json({ error: auth.error });

    const { id } = req.query;
    if (!id) return res.status(400).json({ error: 'Falta el ID del servicio' });

    const dataToUpdate = {};
    for (const [key, value] of Object.entries(req.body)) {
      if (value !== undefined && key !== 'id') {
        if (typeof value === 'string' && value.startsWith('data:')) {
          dataToUpdate[key] = value;
        } else {
          dataToUpdate[key] = typeof value === 'string' ? sanitize(value) : value;
        }
      }
    }

    if (dataToUpdate.imagen_url && dataToUpdate.imagen_url.startsWith('data:')) {
      try {
        const mimeType = dataToUpdate.imagen_url.match(/data:(.*?);base64/)[1];
        const ext = mimeType.split('/')[1] || 'jpg';
        const base64Data = dataToUpdate.imagen_url.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const fileName = `servicio_${Date.now()}.${ext}`;
        const filePath = `uploads/${fileName}`;
        const file = bucket.file(filePath);
        
        await file.save(buffer, {
          metadata: { contentType: mimeType }
        });
        dataToUpdate.imagen_url = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;
      } catch (err) {
        console.error('Error subiendo imagen de servicio:', err);
      }
    }

    try {
      await serviciosRef.doc(id).update(dataToUpdate);
      return res.status(200).json({ success: true, message: 'Servicio actualizado' });
    } catch (error) {
      console.error('Error actualizando servicio:', error);
      return res.status(500).json({ error: 'Error actualizando servicio' });
    }
  }

  else if (req.method === 'DELETE') {
    const auth = verifyToken(req, ['admin']);
    if (auth.error) return res.status(403).json({ error: auth.error });

    const { id } = req.query;
    if (!id) return res.status(400).json({ error: 'Falta el ID del servicio' });

    try {
      await serviciosRef.doc(id).delete();
      return res.status(200).json({ success: true, message: 'Servicio eliminado' });
    } catch (error) {
      console.error('Error eliminando servicio:', error);
      return res.status(500).json({ error: 'Error eliminando servicio' });
    }
  }

  return res.status(405).json({ error: 'Método HTTP no permitido' });

      break;
    }
    
    case 'galeria': {
      
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

    let { imagen, titulo } = req.body;

    if (imagen && imagen.startsWith('data:')) {
      try {
        const mimeType = imagen.match(/data:(.*?);base64/)[1];
        const ext = mimeType.split('/')[1] || 'jpg';
        const base64Data = imagen.replace(/^data:image\/\w+;base64,/, '');
        const buffer = Buffer.from(base64Data, 'base64');
        const fileName = `galeria_${Date.now()}.${ext}`;
        const filePath = `uploads/${fileName}`;
        const file = bucket.file(filePath);
        
        await file.save(buffer, {
          metadata: { contentType: mimeType }
        });
        imagen = `https://firebasestorage.googleapis.com/v0/b/${bucket.name}/o/${encodeURIComponent(filePath)}?alt=media`;
      } catch (err) {
        console.error('Error subiendo foto de galería:', err);
      }
    }



    try {
      const nuevaImagen = {
        imagen: imagen,
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

      break;
    }
    
    case 'resenas': {
      
  const resenasRef = db.collection('resenas');

  if (req.method === 'GET') {
    try {
      const snapshot = await resenasRef.orderBy('created_at', 'desc').limit(20).get();
      const resenas = [];
      snapshot.forEach(doc => {
        resenas.push({ id: doc.id, ...doc.data() });
      });
      return res.status(200).json({ success: true, data: resenas });
    } catch (error) {
      console.error('Error obteniendo reseñas:', error);
      // Fallback: si falla por falta de índice, intentar obtener sin ordenación y ordenar en memoria
      try {
        const snapshot = await resenasRef.limit(50).get();
        const resenas = [];
        snapshot.forEach(doc => {
          resenas.push({ id: doc.id, ...doc.data() });
        });
        resenas.sort((a, b) => {
          const dateA = a.created_at || '';
          const dateB = b.created_at || '';
          return dateB.localeCompare(dateA);
        });
        return res.status(200).json({ success: true, data: resenas.slice(0, 20) });
      } catch (innerError) {
        console.error('Error fallback obteniendo reseñas:', innerError);
        return res.status(500).json({ error: 'Error al cargar las reseñas' });
      }
    }
  }

  else if (req.method === 'POST') {
    const { nombre, estrellas, comentario } = req.body;

    if (!nombre || estrellas === undefined) {
      return res.status(400).json({ error: 'El nombre y la calificación son obligatorios' });
    }

    try {
      const nuevaResena = {
        nombre: sanitize(nombre),
        estrellas: parseInt(estrellas, 10),
        comentario: sanitize(comentario) || '',
        created_at: new Date().toISOString()
      };

      const docRef = await resenasRef.add(nuevaResena);
      return res.status(201).json({ success: true, data: { id: docRef.id, ...nuevaResena } });
    } catch (error) {
      console.error('Error guardando reseña:', error);
      return res.status(500).json({ error: 'Error al registrar tu reseña en el sistema' });
    }
  }

  return res.status(405).json({ error: 'Método no permitido' });

      break;
    }
    
    case 'clientes': {
      
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

      break;
    }
    
    
    case 'calendario': {
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

      const startDate = new Date(a, m - 1, 1);
      const endDate = new Date(a, m, 0); 
      const numDays = endDate.getDate();

      const tzOffset = -6 * 60; 
      const now = new Date();
      const localNow = new Date(now.getTime() + (now.getTimezoneOffset() + tzOffset) * 60000);
      
      const strHoy = localNow.getFullYear() + '-' + 
                     String(localNow.getMonth() + 1).padStart(2,'0') + '-' + 
                     String(localNow.getDate()).padStart(2,'0');

      const futureDate = new Date(localNow.getTime() + (30 * 24 * 3600000));
      const strMaxDate = futureDate.getFullYear() + '-' + 
                         String(futureDate.getMonth() + 1).padStart(2,'0') + '-' + 
                         String(futureDate.getDate()).padStart(2,'0');

      try {
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

        const horariosSnapshot = await db.collection('horarios').get();
        const horariosMap = {};
        horariosSnapshot.forEach(doc => {
          horariosMap[doc.id] = doc.data().schedule;
        });
        const globalSchedule = horariosMap['global'] || [];

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

        const resultado = [];

        for (let d = 1; d <= numDays; d++) {
          const fechaStr = `${a}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
          const fechaObj = new Date(fechaStr + 'T12:00:00Z'); 
          const diaSemana = fechaObj.getUTCDay();

          let estado = 'evaluar';
          
          if (fechaStr < strHoy) {
            estado = 'pasado';
          } else if (fechaStr > strMaxDate) {
            estado = 'pasado'; 
          }

          if (estado === 'pasado') {
            resultado.push({ fecha: fechaStr, estado: 'pasado' });
            continue;
          }

          let totalSlots = 0;
          let abierto = false;

          for (const barbero of barberos) {
            const schedule = horariosMap[barbero.id] || globalSchedule;
            const diaConfig = schedule?.find(s => s.dia_semana === diaSemana);
            
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
        console.error('Error crud calendario:', err);
        return res.status(500).json({ error: 'Error interno obteniendo calendario' });
      }
      break;
    }

    default:
      return res.status(404).json({ error: 'Endpoint CRUD no encontrado para: ' + tipo });
  }
};
