import pool from '../db.js'

const GOOGLE_MAPS_KEY = process.env.GOOGLE_MAPS_API_KEY

async function calcularRuta(origen, destino) {
  if (!GOOGLE_MAPS_KEY) return null
  try {
    const url = `https://maps.googleapis.com/maps/api/directions/json?` +
      `origin=${encodeURIComponent(origen)}&destination=${encodeURIComponent(destino)}` +
      `&mode=driving&language=es&key=${GOOGLE_MAPS_KEY}`
    const res = await fetch(url)
    const data = await res.json()
    if (data.status !== 'OK') return null
    const leg = data.routes[0].legs[0]
    return {
      origen_lat: leg.start_location.lat,
      origen_lng: leg.start_location.lng,
      destino_lat: leg.end_location.lat,
      destino_lng: leg.end_location.lng,
      distancia_km: Math.round(leg.distance.value / 1000),
      duracion_min: Math.round(leg.duration.value / 60),
      ruta_polyline: data.routes[0].overview_polyline.points,
    }
  } catch {
    return null
  }
}

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

    if (origen && destino) {
      // Busca en origen/destino del viaje O en paradas intermedias (respetando el orden)
      sql += ` AND (
        (t.origen LIKE ? AND t.destino LIKE ?)
        OR EXISTS (
          SELECT 1 FROM paradas po
          JOIN paradas pd ON pd.trip_id = po.trip_id
          WHERE po.trip_id = t.id
            AND po.ciudad LIKE ?
            AND pd.ciudad LIKE ?
            AND po.orden < pd.orden
        )
      )`
      params.push(`%${origen}%`, `%${destino}%`, `%${origen}%`, `%${destino}%`)
    } else if (origen) {
      sql += ` AND (t.origen LIKE ? OR EXISTS (SELECT 1 FROM paradas WHERE trip_id = t.id AND ciudad LIKE ?))`
      params.push(`%${origen}%`, `%${origen}%`)
    } else if (destino) {
      sql += ` AND (t.destino LIKE ? OR EXISTS (SELECT 1 FROM paradas WHERE trip_id = t.id AND ciudad LIKE ?))`
      params.push(`%${destino}%`, `%${destino}%`)
    }

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

    const [paradas] = await pool.query(
      'SELECT * FROM paradas WHERE trip_id = ? ORDER BY orden',
      [req.params.id]
    )

    res.json({ trip: rows[0], bookings, paradas })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const createTrip = async (req, res) => {
  const {
    origen, destino, fecha, hora, asientos_totales, precio_asiento, descripcion,
    paradas, ruta_polyline, distancia_km, duracion_min,
    origen_lat, origen_lng, destino_lat, destino_lng
  } = req.body

  if (!origen || !destino || !fecha || !hora || !asientos_totales || !precio_asiento) {
    return res.status(400).json({ error: 'Faltan campos obligatorios' })
  }

  try {
    // Si el cliente ya calculó la ruta, usamos esos datos; si no, la calculamos en el servidor
    let routeInfo = { origen_lat, origen_lng, destino_lat, destino_lng, distancia_km, duracion_min, ruta_polyline }
    if (!ruta_polyline) {
      const ruta = await calcularRuta(origen, destino)
      if (ruta) routeInfo = ruta
    }

    const precio_maximo = routeInfo.distancia_km
      ? Math.round((routeInfo.distancia_km * 0.19 / asientos_totales) * 100) / 100
      : null

    const [result] = await pool.query(`
      INSERT INTO trips (
        conductor_id, origen, destino,
        origen_lat, origen_lng, destino_lat, destino_lng,
        fecha, hora, asientos_totales, asientos_disponibles,
        precio_asiento, precio_maximo, distancia_km, duracion_min, ruta_polyline,
        descripcion
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, [
      req.user.id, origen, destino,
      routeInfo.origen_lat ?? null, routeInfo.origen_lng ?? null,
      routeInfo.destino_lat ?? null, routeInfo.destino_lng ?? null,
      fecha, hora, asientos_totales, asientos_totales,
      precio_asiento, precio_maximo,
      routeInfo.distancia_km ?? null, routeInfo.duracion_min ?? null, routeInfo.ruta_polyline ?? null,
      descripcion || null
    ])

    const tripId = result.insertId

    // Guardar paradas si se proporcionaron
    if (paradas && paradas.length > 0) {
      for (const p of paradas) {
        await pool.query(`
          INSERT INTO paradas (trip_id, ciudad, lat, lng, orden, distancia_desde_origen_km, precio_desde_origen)
          VALUES (?, ?, ?, ?, ?, ?, ?)
        `, [tripId, p.ciudad, p.lat ?? null, p.lng ?? null, p.orden, p.distancia_desde_origen_km ?? 0, p.precio_desde_origen ?? 0])
      }
    }

    const [rows] = await pool.query('SELECT * FROM trips WHERE id = ?', [tripId])
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

    const activos = trips.filter(t => t.estado === 'activo')
    const historial = trips.filter(t => t.estado !== 'activo')

    res.json({ trips: activos, historial })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}
