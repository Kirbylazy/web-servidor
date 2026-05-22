import pool from '../db.js'

const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''

// Get the orden of a city in the paradas list
function getOrden(paradas, ciudad) {
  if (!ciudad) return null
  const n = norm(ciudad)
  const p = paradas.find(p => norm(p.ciudad).includes(n) || n.includes(norm(p.ciudad)))
  return p ? p.orden : null
}

// Calculate how many seats are occupied on each segment of a trip
// A segment is between parada[i] and parada[i+1]
// Returns array of occupied counts, one per segment
function calcOccupancy(paradas, bookings, tripOrigen, tripDestino) {
  const numSegments = paradas.length - 1
  if (numSegments <= 0) return []

  const occupancy = new Array(numSegments).fill(0)

  for (const b of bookings) {
    if (b.estado !== 'confirmada' && b.estado !== 'pendiente') continue
    const startOrden = b.tramo_origen ? getOrden(paradas, b.tramo_origen) : 0
    const endOrden = b.tramo_destino ? getOrden(paradas, b.tramo_destino) : paradas.length - 1
    if (startOrden === null || endOrden === null) continue
    // This booking occupies segments from startOrden to endOrden-1
    for (let s = startOrden; s < endOrden && s < numSegments; s++) {
      occupancy[s]++
    }
  }

  return occupancy
}

// Available seats for a specific tramo = asientos_totales - max occupancy on that tramo's segments
function availableForTramo(paradas, bookings, tripOrigen, tripDestino, asientosTotales, tramoOrigen, tramoDestino) {
  const occupancy = calcOccupancy(paradas, bookings, tripOrigen, tripDestino)
  if (occupancy.length === 0) return asientosTotales

  const startOrden = tramoOrigen ? getOrden(paradas, tramoOrigen) : 0
  const endOrden = tramoDestino ? getOrden(paradas, tramoDestino) : paradas.length - 1
  if (startOrden === null || endOrden === null) return asientosTotales

  let maxOccupied = 0
  for (let s = startOrden; s < endOrden && s < occupancy.length; s++) {
    if (occupancy[s] > maxOccupied) maxOccupied = occupancy[s]
  }

  return asientosTotales - maxOccupied
}

export const getBookings = async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT b.*,
             COALESCE(b.tramo_origen, t.origen) AS origen,
             COALESCE(b.tramo_destino, t.destino) AS destino,
             t.fecha, t.hora, t.precio_asiento,
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
  const { trip_id, tramo_origen, tramo_destino } = req.body
  if (!trip_id) return res.status(400).json({ error: 'trip_id es obligatorio' })

  try {
    const [trips] = await pool.query('SELECT * FROM trips WHERE id = ?', [trip_id])
    const trip = trips[0]
    if (!trip) return res.status(404).json({ error: 'Viaje no encontrado' })
    if (trip.estado !== 'activo') return res.status(400).json({ error: 'El viaje no está disponible' })
    if (trip.conductor_id === req.user.id) return res.status(400).json({ error: 'No puedes reservar tu propio viaje' })

    // Get paradas and existing bookings to check per-segment availability
    const [paradas] = await pool.query(
      'SELECT * FROM paradas WHERE trip_id = ? ORDER BY orden', [trip_id]
    )
    const [existingBookings] = await pool.query(
      'SELECT * FROM bookings WHERE trip_id = ? AND estado IN ("pendiente", "confirmada")', [trip_id]
    )

    if (paradas.length >= 2) {
      const available = availableForTramo(
        paradas, existingBookings, trip.origen, trip.destino,
        trip.asientos_totales, tramo_origen, tramo_destino
      )
      if (available <= 0) {
        return res.status(400).json({ error: 'No quedan plazas disponibles en este tramo' })
      }
    } else {
      // No paradas, fallback to simple check
      if (trip.asientos_disponibles <= 0) {
        return res.status(400).json({ error: 'No quedan plazas disponibles' })
      }
    }

    const [result] = await pool.query(
      'INSERT INTO bookings (trip_id, pasajero_id, tramo_origen, tramo_destino) VALUES (?, ?, ?, ?)',
      [trip_id, req.user.id, tramo_origen || null, tramo_destino || null]
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
      SELECT b.*, t.conductor_id, t.asientos_totales
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
      // Check per-segment availability before confirming
      const [paradas] = await pool.query(
        'SELECT * FROM paradas WHERE trip_id = ? ORDER BY orden', [booking.trip_id]
      )
      const [existingBookings] = await pool.query(
        'SELECT * FROM bookings WHERE trip_id = ? AND id != ? AND estado IN ("pendiente", "confirmada")',
        [booking.trip_id, booking.id]
      )

      if (paradas.length >= 2) {
        const available = availableForTramo(
          paradas, existingBookings, null, null,
          booking.asientos_totales, booking.tramo_origen, booking.tramo_destino
        )
        if (available <= 0) {
          return res.status(400).json({ error: 'No quedan plazas en este tramo' })
        }
      }

      await pool.query('UPDATE bookings SET estado = ? WHERE id = ?', [estado, req.params.id])
      // Update asientos_disponibles as the minimum available across all segments
      await updateTripAvailability(booking.trip_id)
      const [updated] = await pool.query('SELECT * FROM bookings WHERE id = ?', [req.params.id])
      return res.json({ booking: updated[0] })
    }

    if (estado === 'cancelada') {
      await pool.query('DELETE FROM bookings WHERE id = ?', [req.params.id])
      await updateTripAvailability(booking.trip_id)
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

// Recalculate trips.asientos_disponibles as the minimum available seats across all segments
async function updateTripAvailability(tripId) {
  const [trips] = await pool.query('SELECT * FROM trips WHERE id = ?', [tripId])
  const trip = trips[0]
  if (!trip) return

  const [paradas] = await pool.query(
    'SELECT * FROM paradas WHERE trip_id = ? ORDER BY orden', [tripId]
  )
  const [bookings] = await pool.query(
    'SELECT * FROM bookings WHERE trip_id = ? AND estado IN ("pendiente", "confirmada")', [tripId]
  )

  if (paradas.length < 2) {
    // Simple: count confirmed bookings
    const confirmed = bookings.filter(b => b.estado === 'confirmada' || b.estado === 'pendiente').length
    await pool.query('UPDATE trips SET asientos_disponibles = ? WHERE id = ?',
      [Math.max(0, trip.asientos_totales - confirmed), tripId])
    return
  }

  const occupancy = calcOccupancy(paradas, bookings, trip.origen, trip.destino)
  const maxOccupied = occupancy.length > 0 ? Math.max(...occupancy) : 0
  const available = Math.max(0, trip.asientos_totales - maxOccupied)

  await pool.query('UPDATE trips SET asientos_disponibles = ? WHERE id = ?', [available, tripId])
}
