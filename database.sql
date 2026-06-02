-- ═══════════════════════════════════════════════════════════════════
--  BASE DE DATOS — Barbería Premium (MySQL / MariaDB)
-- ═══════════════════════════════════════════════════════════════════
--
--  INSTRUCCIONES:
--  ──────────────
--  1. Abre phpMyAdmin (http://localhost/phpmyadmin)
--  2. Crea una base de datos llamada "barberia_db" con cotejamiento utf8mb4_general_ci
--  3. Selecciona esa base de datos → pestaña "SQL" → pega este contenido → ejecutar
--
--  MOTOR: InnoDB (soporta claves foráneas y transacciones)
--  CHARSET: utf8mb4 (soporta emojis, acentos, ñ y caracteres especiales)
-- ═══════════════════════════════════════════════════════════════════

-- Crear la base de datos (si no existe)
CREATE DATABASE IF NOT EXISTS `barberia_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

-- Seleccionar la base de datos
USE `barberia_db`;

-- ─── 1. Tabla Usuarios (Barberos y Administradores) ──────────────
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50) UNIQUE NOT NULL     COMMENT 'Nombre de usuario para iniciar sesión',
    `password_hash` VARCHAR(255) NOT NULL            COMMENT 'Contraseña encriptada con password_hash()',
    `nombre`        VARCHAR(100) NOT NULL             COMMENT 'Nombre real del barbero',
    `apellido`      VARCHAR(100) NOT NULL             COMMENT 'Apellido del barbero',
    `rol`           ENUM('admin', 'barbero') NOT NULL DEFAULT 'barbero' COMMENT 'Rol del usuario',
    `especialidad`  VARCHAR(255) DEFAULT NULL         COMMENT 'Ej: Fades & Clásicos',
    `imagen_url`    TEXT DEFAULT NULL                  COMMENT 'Ruta relativa a la foto del barbero',
    `activo`        TINYINT(1) NOT NULL DEFAULT 1     COMMENT '1 = activo, 0 = inactivo',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─── 2. Tabla Servicios ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `servicios` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `nombre`        VARCHAR(100) NOT NULL              COMMENT 'Nombre del servicio (ej: Fade & Taper)',
    `descripcion`   TEXT DEFAULT NULL                   COMMENT 'Descripción detallada del servicio',
    `precio`        DECIMAL(10,2) NOT NULL              COMMENT 'Precio en moneda local',
    `duracion_min`  INT NOT NULL                        COMMENT 'Duración estimada en minutos',
    `imagen_url`    TEXT DEFAULT NULL                   COMMENT 'Ruta relativa a la imagen del servicio',
    `activo`        TINYINT(1) NOT NULL DEFAULT 1       COMMENT '1 = visible, 0 = oculto',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─── 3. Tabla Citas ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `citas` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `barbero_id`        INT NOT NULL                    COMMENT 'ID del barbero asignado',
    `servicio_id`       INT NOT NULL                    COMMENT 'ID del servicio solicitado',
    `cliente_nombre`    VARCHAR(100) NOT NULL            COMMENT 'Nombre completo del cliente',
    `cliente_email`     VARCHAR(150) DEFAULT NULL        COMMENT 'Email del cliente (opcional)',
    `cliente_telefono`  VARCHAR(20) DEFAULT NULL         COMMENT 'Teléfono / WhatsApp del cliente',
    `fecha`             DATE NOT NULL                    COMMENT 'Fecha de la cita (YYYY-MM-DD)',
    `hora_inicio`       TIME NOT NULL                    COMMENT 'Hora de inicio (HH:MM:SS)',
    `hora_fin`          TIME NOT NULL                    COMMENT 'Hora de fin calculada',
    `estado`            ENUM('pendiente','confirmada','en_silla','completada','cancelada','no_asistio')
                          NOT NULL DEFAULT 'pendiente'   COMMENT 'Estado actual de la cita',
    `notas`             TEXT DEFAULT NULL                 COMMENT 'Notas adicionales del cliente o barbero',
    `precio_total`      DECIMAL(10,2) DEFAULT 0.00       COMMENT 'Precio total cobrado',
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Claves foráneas (integridad referencial)
    CONSTRAINT `fk_citas_barbero`  FOREIGN KEY (`barbero_id`)  REFERENCES `usuarios`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_citas_servicio` FOREIGN KEY (`servicio_id`) REFERENCES `servicios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ─── 4. Índices de Alto Rendimiento ──────────────────────────────
-- Optimizan las consultas más frecuentes (agenda diaria, búsquedas por fecha)
CREATE INDEX `idx_citas_fecha`         ON `citas`(`fecha`);
CREATE INDEX `idx_citas_barbero_fecha` ON `citas`(`barbero_id`, `fecha`);
CREATE INDEX `idx_citas_estado`        ON `citas`(`estado`);

-- ─── 5. Usuario Admin por Defecto ────────────────────────────────
-- Contraseña: admin123 (encriptada con password_hash en PHP)
-- IMPORTANTE: Cambia esta contraseña después del primer login
INSERT INTO `usuarios` (`username`, `password_hash`, `nombre`, `apellido`, `rol`, `activo`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'General', 'admin', 1);
-- Nota: El hash de arriba corresponde a "password" con bcrypt. Cámbialo en producción.
