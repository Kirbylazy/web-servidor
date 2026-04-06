import pool from '../db.js'

export const getTrips = async (req, res) => {
  const { origen, destino, fecha } = req.query
  try {
    let sql = `
      SELECT t.*, u.nombre AS conductor_nombre, u.apellidos AS conductor_apellidos,
             u.foto AS conductor_foto, u.valoracion_media AS conductor_valoracion,
             v.marca, v.modelo, v.color, v.aire_acondicionado, v.musica, v.maletero_grande
      FROM trips t
      JOIN users u ON t.conductor_id = u.id
      LEFT JOIN vehicles v ON v.user_id = t.conductor_id
      WHERE t.estado = 'activo' AND t.asientos_disponibles > 0 AND t.fecha >= CURDATE()
    `
    const params = []
    if (origen) { sql += ' AND t.origen LIKE ?'; params.push(`%${origen}%`) }
    if (destino) { sql += ' AND t.destino LIKE ?'; params.push(`%${destino}%`) }
    if (fecha) { sql += ' AND t.fecha = ?'; params.push(fecha) }
    sql += ' ORDER BY t.fecha ASC, t.hora ASC'

    const [rows] = await pool.query(sql, params)
    res.json({ trips: rows })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const getTripById = async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT t.*, u.nombre AS conductor_nombre, u.apellidos AS conductor_apellidos,
             u.foto AS conductor_foto, u.valoracion_media AS conductor_valoracion,
             v.marca, v.modelo, v.color, v.aire_acondicionado, v.musica, v.maletero_grande
      FROM trips t
      JOIN users u ON t.conductor_id = u.id
      LEFT JOIN vehicles v ON v.user_id = t.conductor_id
      WHERE t.id = ?
    `, [req.params.id])

    if (!rows[0]) return res.status(404).json({ error: 'Viaje no encontrado' })

    const [bookings] = await pool.query(`
      SELECT b.*, u.nombre AS pasajero_nombre, u.foto AS pasajero_foto
      FROM bookings b
      JOIN users u ON b.pasajero_id = u.id
      WHERE b.trip_id = ?
    `, [req.params.id])

    res.json({ trip: rows[0], bookings })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const createTrip = async (req, res) => {
  const { origen, destino, fecha, hora, asientos_totales, precio_asiento, descripcion } = req.body

  if (!origen || !destino || !fecha || !hora || !asientos_totales || !precio_asiento) {
    return res.status(400).json({ error: 'Faltan campos obligatorios' })
  }

  try {
    const [result] = await pool.query(`
      INSERT INTO trips (conductor_id, origen, destino, fecha, hora, asientos_totales, asientos_disponibles, precio_asiento, descripcion)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, [req.user.id, origen, destino, fecha, hora, asientos_totales, asientos_totales, precio_asiento, descripcion || null])

    const [rows] = await pool.query('SELECT * FROM trips WHERE id = ?', [result.insertId])
    res.status(201).json({ trip: rows[0] })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const updateTrip = async (req, res) => {
  const { descripcion, estado } = req.body
  try {
    const [rows] = await pool.query('SELECT * FROM trips WHERE id = ?', [req.params.id])
    if (!rows[0]) return res.status(404).json({ error: 'Viaje no encontrado' })
    if (rows[0].conductor_id !== req.user.id) return res.status(403).json({ error: 'No autorizado' })

    await pool.query('UPDATE trips SET descripcion = ?, estado = ? WHERE id = ?',
      [descripcion ?? rows[0].descripcion, estado ?? rows[0].estado, req.params.id])

    const [updated] = await pool.query('SELECT * FROM trips WHERE id = ?', [req.params.id])
    res.json({ trip: updated[0] })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const deleteTrip = async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM trips WHERE id = ?', [req.params.id])
    if (!rows[0]) return res.status(404).json({ error: 'Viaje no encontrado' })
    if (rows[0].conductor_id !== req.user.id) return res.status(403).json({ error: 'No autorizado' })

    await pool.query("UPDATE trips SET estado = 'cancelado' WHERE id = ?", [req.params.id])
    res.json({ message: 'Viaje cancelado correctamente' })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const getMyTrips = async (req, res) => {
  try {
    const [trips] = await pool.query(`
      SELECT t.*,
        (SELECT COUNT(*) FROM bookings b WHERE b.trip_id = t.id AND b.estado = 'confirmada') AS reservas_confirmadas,
        (SELECT COUNT(*) FROM bookings b WHERE b.trip_id = t.id AND b.estado = 'pendiente') AS reservas_pendientes
      FROM trips t
      WHERE t.conductor_id = ?
      ORDER BY t.fecha DESC, t.hora DESC
    `, [req.user.id])
    res.json({ trips })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}
