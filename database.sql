-- Supabase PostgreSQL Schema para Barbería Paco Premium

-- Habilitar extensión para UUIDs (opcional, pero buena práctica en Postgres)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. Tabla Usuarios (Personal)
CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'barbero' CHECK (rol IN ('admin', 'barbero')),
    especialidad VARCHAR(255),
    imagen_url TEXT,
    activo BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabla Servicios
CREATE TABLE servicios (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio NUMERIC(10, 2) NOT NULL,
    duracion_min INTEGER NOT NULL,
    imagen_url TEXT,
    activo BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabla Citas
CREATE TABLE citas (
    id SERIAL PRIMARY KEY,
    barbero_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    servicio_id INTEGER NOT NULL REFERENCES servicios(id) ON DELETE CASCADE,
    cliente_nombre VARCHAR(100) NOT NULL,
    cliente_email VARCHAR(150),
    cliente_telefono VARCHAR(20),
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente' 
      CHECK (estado IN ('pendiente', 'confirmada', 'en_silla', 'completada', 'cancelada', 'no_asistio')),
    notas TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Índices de alto rendimiento (Optimizando consultas de la agenda diaria)
CREATE INDEX idx_citas_fecha ON citas(fecha);
CREATE INDEX idx_citas_barbero_fecha ON citas(barbero_id, fecha);
CREATE INDEX idx_citas_estado ON citas(estado);
