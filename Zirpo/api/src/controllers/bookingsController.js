import pool from '../db.js'

export const getBookings = async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT b.*, t.origen, t.destino, t.fecha, t.hora, t.precio_asiento,
             t.estado AS trip_estado,
             u.nombre AS conductor_nombre, u.foto AS conductor_foto
      FROM bookings b
      JOIN trips t ON b.trip_id = t.id
      JOIN users u ON t.conductor_id = u.id
      WHERE b.pasajero_id = ?
      ORDER BY t.fecha DESC
    `, [req.user.id])
    res.json({ bookings: rows })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const createBooking = async (req, res) => {
  const { trip_id } = req.body
  if (!trip_id) return res.status(400).json({ error: 'trip_id es obligatorio' })

  try {
    const [trips] = await pool.query('SELECT * FROM trips WHERE id = ?', [trip_id])
    const trip = trips[0]
    if (!trip) return res.status(404).json({ error: 'Viaje no encontrado' })
    if (trip.estado !== 'activo') return res.status(400).json({ error: 'El viaje no está disponible' })
    if (trip.asientos_disponibles <= 0) return res.status(400).json({ error: 'No quedan plazas disponibles' })
    if (trip.conductor_id === req.user.id) return res.status(400).json({ error: 'No puedes reservar tu propio viaje' })

    const [result] = await pool.query(
      'INSERT INTO bookings (trip_id, pasajero_id) VALUES (?, ?)',
      [trip_id, req.user.id]
    )

    const [rows] = await pool.query('SELECT * FROM bookings WHERE id = ?', [result.insertId])
    res.status(201).json({ booking: rows[0] })
  } catch (err) {
    if (err.code === 'ER_DUP_ENTRY') return res.status(400).json({ error: 'Ya tienes una reserva en este viaje' })
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const updateBookingStatus = async (req, res) => {
  const { estado } = req.body
  if (!['confirmada', 'cancelada'].includes(estado)) {
    return res.status(400).json({ error: 'Estado inválido' })
  }

  try {
    const [bookings] = await pool.query(`
      SELECT b.*, t.conductor_id, t.asientos_disponibles
      FROM bookings b JOIN trips t ON b.trip_id = t.id
      WHERE b.id = ?
    `, [req.params.id])
    const booking = bookings[0]
    if (!booking) return res.status(404).json({ error: 'Reserva no encontrada' })

    const esConductor = booking.conductor_id === req.user.id
    const esPasajero = booking.pasajero_id === req.user.id

    if (!esConductor && !esPasajero) return res.status(403).json({ error: 'No autorizado' })
    if (estado === 'confirmada' && !esConductor) return res.status(403).json({ error: 'Solo el conductor puede confirmar' })

    if (estado === 'confirmada' && booking.estado === 'pendiente') {
      await pool.query('UPDATE bookings SET estado = ? WHERE id = ?', [estado, req.params.id])
      await pool.query('UPDATE trips SET asientos_disponibles = asientos_disponibles - 1 WHERE id = ?', [booking.trip_id])
      const [updated] = await pool.query('SELECT * FROM bookings WHERE id = ?', [req.params.id])
      return res.json({ booking: updated[0] })
    }

    if (estado === 'cancelada') {
      if (booking.estado === 'confirmada') {
        await pool.query('UPDATE trips SET asientos_disponibles = asientos_disponibles + 1 WHERE id = ?', [booking.trip_id])
      }
      await pool.query('DELETE FROM bookings WHERE id = ?', [req.params.id])
      return res.json({ message: 'Reserva cancelada' })
    }

    await pool.query('UPDATE bookings SET estado = ? WHERE id = ?', [estado, req.params.id])
    const [updated] = await pool.query('SELECT * FROM bookings WHERE id = ?', [req.params.id])
    res.json({ booking: updated[0] })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}
