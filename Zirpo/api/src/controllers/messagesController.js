import pool from '../db.js'

export const getMessages = async (req, res) => {
  try {
    const { tripId } = req.params
    const { since } = req.query

    const [trips] = await pool.query('SELECT conductor_id FROM trips WHERE id = ?', [tripId])
    if (!trips.length) return res.status(404).json({ error: 'Viaje no encontrado' })

    const isConductor = trips[0].conductor_id === req.user.id
    if (!isConductor) {
      const [bookings] = await pool.query(
        'SELECT id FROM bookings WHERE trip_id = ? AND pasajero_id = ?',
        [tripId, req.user.id]
      )
      if (!bookings.length) return res.status(403).json({ error: 'No autorizado' })
    }

    let sql = `
      SELECT m.id, m.trip_id, m.sender_id, m.contenido, m.created_at,
             u.nombre AS sender_nombre, u.foto AS sender_foto
      FROM messages m
      JOIN users u ON m.sender_id = u.id
      WHERE m.trip_id = ?
    `
    const params = [tripId]

    if (since) {
      sql += ' AND m.created_at > ?'
      params.push(since)
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
  const { trip_id, contenido } = req.body
  if (!trip_id || !contenido?.trim()) {
    return res.status(400).json({ error: 'trip_id y contenido son obligatorios' })
  }
  if (contenido.length > 1000) {
    return res.status(400).json({ error: 'Mensaje demasiado largo (máx. 1000 caracteres)' })
  }

  try {
    const [trips] = await pool.query('SELECT conductor_id FROM trips WHERE id = ?', [trip_id])
    if (!trips.length) return res.status(404).json({ error: 'Viaje no encontrado' })

    const isConductor = trips[0].conductor_id === req.user.id
    if (!isConductor) {
      const [bookings] = await pool.query(
        'SELECT id FROM bookings WHERE trip_id = ? AND pasajero_id = ?',
        [trip_id, req.user.id]
      )
      if (!bookings.length) return res.status(403).json({ error: 'No autorizado' })
    }

    const [result] = await pool.query(
      'INSERT INTO messages (trip_id, sender_id, contenido) VALUES (?, ?, ?)',
      [trip_id, req.user.id, contenido.trim()]
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
