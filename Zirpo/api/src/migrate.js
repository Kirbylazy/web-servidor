import 'dotenv/config'
import pool from './db.js'

const createTables = async () => {
  const conn = await pool.getConnection()

  try {
    await conn.query(`ALTER DATABASE ${process.env.DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`)

    await conn.query(`
      CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100),
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        telefono VARCHAR(20),
        foto VARCHAR(255),
        valoracion_media DECIMAL(3,2) DEFAULT 0.00,
        verificado BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `)

    await conn.query(`
      ALTER TABLE users ADD COLUMN IF NOT EXISTS apellidos VARCHAR(100) AFTER nombre
    `)

    await conn.query(`
      CREATE TABLE IF NOT EXISTS trips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conductor_id INT NOT NULL,
        origen VARCHAR(150) NOT NULL,
        destino VARCHAR(150) NOT NULL,
        origen_lat DECIMAL(10,7),
        origen_lng DECIMAL(10,7),
        destino_lat DECIMAL(10,7),
        destino_lng DECIMAL(10,7),
        fecha DATE NOT NULL,
        hora TIME NOT NULL,
        asientos_totales INT NOT NULL,
        asientos_disponibles INT NOT NULL,
        precio_asiento DECIMAL(6,2) NOT NULL,
        precio_maximo DECIMAL(6,2),
        distancia_km INT,
        duracion_min INT,
        ruta_polyline TEXT,
        descripcion VARCHAR(255),
        estado ENUM('activo','completado','cancelado') DEFAULT 'activo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conductor_id) REFERENCES users(id) ON DELETE CASCADE
      )
    `)

    // Añadir columnas a trips si ya existe la tabla (instancias ya desplegadas)
    const tripColumns = [
      "ALTER TABLE trips ADD COLUMN IF NOT EXISTS origen_lat DECIMAL(10,7)",
      "ALTER TABLE trips ADD COLUMN IF NOT EXISTS origen_lng DECIMAL(10,7)",
      "ALTER TABLE trips ADD COLUMN IF NOT EXISTS destino_lat DECIMAL(10,7)",
      "ALTER TABLE trips ADD COLUMN IF NOT EXISTS destino_lng DECIMAL(10,7)",
      "ALTER TABLE trips ADD COLUMN IF NOT EXISTS precio_maximo DECIMAL(6,2)",
      "ALTER TABLE trips ADD COLUMN IF NOT EXISTS distancia_km INT",
      "ALTER TABLE trips ADD COLUMN IF NOT EXISTS duracion_min INT",
      "ALTER TABLE trips ADD COLUMN IF NOT EXISTS ruta_polyline TEXT",
    ]
    for (const sql of tripColumns) {
      await conn.query(sql).catch(() => {}) // ignora si ya existe
    }

    await conn.query(`
      CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        trip_id INT NOT NULL,
        pasajero_id INT NOT NULL,
        estado ENUM('pendiente','confirmada','cancelada') DEFAULT 'pendiente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
        FOREIGN KEY (pasajero_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_booking (trip_id, pasajero_id)
      )
    `)

    await conn.query(`
      CREATE TABLE IF NOT EXISTS paradas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        trip_id INT NOT NULL,
        ciudad VARCHAR(255) NOT NULL,
        lat DECIMAL(10,7),
        lng DECIMAL(10,7),
        orden TINYINT NOT NULL,
        distancia_desde_origen_km INT DEFAULT 0,
        precio_desde_origen DECIMAL(6,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
      )
    `)

    await conn.query(`
      CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        marca VARCHAR(50) NOT NULL,
        modelo VARCHAR(50) NOT NULL,
        matricula VARCHAR(20) NOT NULL UNIQUE,
        color VARCHAR(30) NOT NULL,
        plazas INT NOT NULL,
        anio INT,
        aire_acondicionado BOOLEAN DEFAULT FALSE,
        musica BOOLEAN DEFAULT FALSE,
        maletero_grande BOOLEAN DEFAULT FALSE,
        descripcion VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
      )
    `)

    // Añadir pickup_address a paradas
    await conn.query(`
      ALTER TABLE paradas ADD COLUMN IF NOT EXISTS pickup_address VARCHAR(255) DEFAULT NULL
    `).catch(() => {})

    console.log('Tablas creadas/actualizadas correctamente')
  } catch (err) {
    console.error('Error en migración:', err.message)
  } finally {
    conn.release()
    process.exit()
  }
}

createTables()
