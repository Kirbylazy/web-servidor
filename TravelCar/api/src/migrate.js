import 'dotenv/config'
import pool from './db.js'

const createTables = async () => {
  const conn = await pool.getConnection()

  try {
    await conn.query(`
      CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        telefono VARCHAR(20),
        foto VARCHAR(255),
        valoracion_media DECIMAL(3,2) DEFAULT 0.00,
        verificado BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `)

    console.log('Tabla users creada correctamente')
  } catch (err) {
    console.error('Error creando tablas:', err.message)
  } finally {
    conn.release()
    process.exit()
  }
}

createTables()
