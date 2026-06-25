import pool from '../db.js'

// --- Filtro de contenido ---

const PALABRAS_OFENSIVAS = [
  'puta', 'puto', 'mierda', 'coño', 'joder', 'hostia', 'ostia', 'cabron', 'cabrón',
  'gilipollas', 'subnormal', 'imbecil', 'imbécil', 'idiota', 'retrasado',
  'maricón', 'maricon', 'marica', 'bollera', 'negro de mierda', 'mora de mierda',
  'hijo de puta', 'hija de puta', 'hdp', 'hijoputa', 'hijaputa',
  'follar', 'fuck', 'shit', 'bitch', 'asshole', 'dick', 'cock', 'cunt',
  'capullo', 'zorra', 'cerdo', 'cerda', 'bastardo', 'bastarda',
  'pendejo', 'pendeja', 'chingar', 'verga', 'culero', 'culera',
]

const norm = s => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')

function censurarPalabras(texto) {
  let result = texto
  for (const palabra of PALABRAS_OFENSIVAS) {
    const pattern = new RegExp(norm(palabra).split('').join('[\\s._*-]*'), 'gi')
    result = result.replace(pattern, m => '*'.repeat(m.length))
  }
  return result
}

// Detecta teléfonos: 6-15 dígitos con separadores opcionales, con o sin prefijo +34
const PHONE_PATTERNS = [
  /(?:\+?\d{1,3}[\s.-]?)?\(?\d{2,4}\)?[\s.-]?\d{2,4}[\s.-]?\d{2,4}[\s.-]?\d{0,4}/g,
  /\b\d{3}[\s.-]?\d{3}[\s.-]?\d{3,4}\b/g,
  /\b\d{3}[\s.-]?\d{2}[\s.-]?\d{2}[\s.-]?\d{2}\b/g,
]

const EMAIL_PATTERN = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g

// Detecta números escritos en letras: "seis uno dos..."
const NUMEROS_TEXTO = /\b(cero|uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve)\b/gi

function contieneContacto(texto) {
  // Contar dígitos en el texto (ignorando espacios/separadores)
  const soloDigitos = texto.replace(/[^\d]/g, '')
  if (soloDigitos.length >= 9) return true

  // Patrones de teléfono
  for (const pattern of PHONE_PATTERNS) {
    const matches = texto.match(pattern)
    if (matches?.some(m => m.replace(/\D/g, '').length >= 9)) return true
  }

  // Email
  if (EMAIL_PATTERN.test(texto)) return true

  // Números escritos en letras (6+ palabras-número = probable teléfono)
  const numerosEnLetras = texto.match(NUMEROS_TEXTO)
  if (numerosEnLetras && numerosEnLetras.length >= 6) return true

  return false
}

function filtrarMensaje(texto) {
  if (contieneContacto(texto)) {
    return { blocked: true, reason: 'No se permiten números de teléfono ni emails. Usa el chat de la app para comunicarte.' }
  }
  return { blocked: false, text: censurarPalabras(texto) }
}

export const getConversations = async (req, res) => {
  try {
    const userId = req.user.id

    // Get all conversations grouped by (trip_id, passenger_id)
    const [rows] = await pool.query(`
      SELECT
        t.id AS trip_id,
        m.passenger_id,
        t.origen,
        t.destino,
        m_last.contenido AS last_message,
        m_last.created_at AS last_message_at,
        CASE
          WHEN t.conductor_id = ? THEN pasajero.nombre
          ELSE conductor.nombre
        END AS other_nombre,
        CASE
          WHEN t.conductor_id = ? THEN pasajero.foto
          ELSE conductor.foto
        END AS other_foto
      FROM (
        SELECT trip_id, passenger_id, MAX(id) AS max_id
        FROM messages
        WHERE passenger_id IS NOT NULL
        GROUP BY trip_id, passenger_id
      ) m
      JOIN messages m_last ON m_last.id = m.max_id
      JOIN trips t ON t.id = m.trip_id
      JOIN users conductor ON conductor.id = t.conductor_id
      JOIN users pasajero ON pasajero.id = m.passenger_id
      WHERE t.conductor_id = ? OR m.passenger_id = ?
      ORDER BY m_last.created_at DESC
    `, [userId, userId, userId, userId])

    res.json({ conversations: rows })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const getMessages = async (req, res) => {
  try {
    const { tripId, passengerId } = req.params
    const { after_id } = req.query

    const [trips] = await pool.query('SELECT conductor_id FROM trips WHERE id = ?', [tripId])
    if (!trips.length) return res.status(404).json({ error: 'Viaje no encontrado' })

    // Only the conductor or the specific passenger can read this conversation
    const isConductor = trips[0].conductor_id === req.user.id
    const isPassenger = parseInt(passengerId) === req.user.id
    if (!isConductor && !isPassenger) {
      return res.status(403).json({ error: 'No autorizado' })
    }

    let sql = `
      SELECT m.id, m.trip_id, m.passenger_id, m.sender_id, m.contenido, m.created_at,
             u.nombre AS sender_nombre, u.foto AS sender_foto
      FROM messages m
      JOIN users u ON m.sender_id = u.id
      WHERE m.trip_id = ? AND m.passenger_id = ?
    `
    const params = [tripId, passengerId]

    if (after_id) {
      sql += ' AND m.id > ?'
      params.push(parseInt(after_id))
    }

    sql += ' ORDER BY m.created_at ASC'

    const [rows] = await pool.query(sql, params)
    res.json({ messages: rows })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const sendMessage = async (req, res) => {
  const { trip_id, passenger_id, contenido } = req.body
  if (!trip_id || !passenger_id || !contenido?.trim()) {
    return res.status(400).json({ error: 'trip_id, passenger_id y contenido son obligatorios' })
  }
  if (contenido.length > 1000) {
    return res.status(400).json({ error: 'Mensaje demasiado largo (máx. 1000 caracteres)' })
  }

  try {
    const [trips] = await pool.query('SELECT conductor_id FROM trips WHERE id = ?', [trip_id])
    if (!trips.length) return res.status(404).json({ error: 'Viaje no encontrado' })

    // Only the conductor or the specific passenger can send in this conversation
    const isConductor = trips[0].conductor_id === req.user.id
    const isPassenger = parseInt(passenger_id) === req.user.id
    if (!isConductor && !isPassenger) {
      return res.status(403).json({ error: 'No autorizado' })
    }

    const filtro = filtrarMensaje(contenido.trim())
    if (filtro.blocked) {
      return res.status(400).json({ error: filtro.reason })
    }

    const [result] = await pool.query(
      'INSERT INTO messages (trip_id, sender_id, passenger_id, contenido) VALUES (?, ?, ?, ?)',
      [trip_id, req.user.id, passenger_id, filtro.text]
    )

    const [rows] = await pool.query(`
      SELECT m.*, u.nombre AS sender_nombre, u.foto AS sender_foto
      FROM messages m JOIN users u ON m.sender_id = u.id
      WHERE m.id = ?
    `, [result.insertId])

    res.status(201).json({ message: rows[0] })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}
