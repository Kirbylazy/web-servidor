import pool from '../db.js'

const GEOCODER_URL = process.env.GEOCODER_URL || 'http://localhost:2322'
const ROUTER_URL = process.env.ROUTER_URL || 'http://localhost:8080'

async function geocodeCity(city) {
  const params = new URLSearchParams({ q: city, limit: '1' })
  const res = await fetch(`${GEOCODER_URL}/search?${params}`)
  const data = await res.json()
  if (!data.length) return null
  return { lat: data[0].lat, lng: data[0].lng }
}

async function calcularRuta(origen, destino) {
  try {
    const [origenGeo, destinoGeo] = await Promise.all([
      geocodeCity(origen),
      geocodeCity(destino),
    ])
    if (!origenGeo || !destinoGeo) return null

    const res = await fetch(
      `${ROUTER_URL}/route?point=${origenGeo.lat},${origenGeo.lng}&point=${destinoGeo.lat},${destinoGeo.lng}&profile=car&type=json&points_encoded=true`
    )
    const data = await res.json()
    if (!data.paths?.length) return null

    const path = data.paths[0]
    return {
      origen_lat: origenGeo.lat,
      origen_lng: origenGeo.lng,
      destino_lat: destinoGeo.lat,
      destino_lng: destinoGeo.lng,
      distancia_km: Math.round(path.distance / 1000),
      duracion_min: Math.round(path.time / 60000),
      ruta_polyline: path.points,
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

    // Adjuntar paradas con disponibilidad por segmento
    if (rows.length > 0) {
      const tripIds = rows.map(t => t.id)
      const [allParadas] = await pool.query(
        `SELECT * FROM paradas WHERE trip_id IN (${tripIds.map(() => '?').join(',')}) ORDER BY orden`,
        tripIds
      )
      const [allBookings] = await pool.query(
        `SELECT * FROM bookings WHERE trip_id IN (${tripIds.map(() => '?').join(',')}) AND estado IN ('pendiente', 'confirmada')`,
        tripIds
      )
      const paradasByTrip = {}
      allParadas.forEach(p => {
        if (!paradasByTrip[p.trip_id]) paradasByTrip[p.trip_id] = []
        paradasByTrip[p.trip_id].push(p)
      })
      const bookingsByTrip = {}
      allBookings.forEach(b => {
        if (!bookingsByTrip[b.trip_id]) bookingsByTrip[b.trip_id] = []
        bookingsByTrip[b.trip_id].push(b)
      })

      const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''
      const getOrden = (paradas, ciudad) => {
        if (!ciudad) return null
        const n = norm(ciudad)
        const p = paradas.find(p => norm(p.ciudad).includes(n) || n.includes(norm(p.ciudad)))
        return p ? p.orden : null
      }

      rows.forEach(t => {
        const paradas = paradasByTrip[t.id] || []
        const tripBookings = bookingsByTrip[t.id] || []
        t.paradas = paradas

        if (paradas.length >= 2) {
          const numSeg = paradas.length - 1
          const occ = new Array(numSeg).fill(0)
          for (const b of tripBookings) {
            const sO = b.tramo_origen ? getOrden(paradas, b.tramo_origen) : 0
            const sD = b.tramo_destino ? getOrden(paradas, b.tramo_destino) : paradas.length - 1
            if (sO === null || sD === null) continue
            for (let s = sO; s < sD && s < numSeg; s++) occ[s]++
          }
          paradas.forEach((p, i) => {
            if (i < numSeg) p.asientos_disponibles_segmento = t.asientos_totales - occ[i]
          })
        }
      })
    }

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

    // Calculate per-segment availability
    const trip = rows[0]
    if (paradas.length >= 2) {
      const numSegments = paradas.length - 1
      const occupancy = new Array(numSegments).fill(0)
      const activeBookings = bookings.filter(b => b.estado === 'confirmada' || b.estado === 'pendiente')
      const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''
      const getOrden = (ciudad) => {
        if (!ciudad) return null
        const n = norm(ciudad)
        const p = paradas.find(p => norm(p.ciudad).includes(n) || n.includes(norm(p.ciudad)))
        return p ? p.orden : null
      }
      for (const b of activeBookings) {
        const sO = b.tramo_origen ? getOrden(b.tramo_origen) : 0
        const sD = b.tramo_destino ? getOrden(b.tramo_destino) : paradas.length - 1
        if (sO === null || sD === null) continue
        for (let s = sO; s < sD && s < numSegments; s++) occupancy[s]++
      }
      // Attach per-segment availability to paradas
      paradas.forEach((p, i) => {
        if (i < numSegments) {
          p.asientos_disponibles_segmento = trip.asientos_totales - occupancy[i]
        }
      })
    }

    res.json({ trip, bookings, paradas })
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
          INSERT INTO paradas (trip_id, ciudad, lat, lng, orden, distancia_desde_origen_km, precio_desde_origen, pickup_address)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        `, [tripId, p.ciudad, p.lat ?? null, p.lng ?? null, p.orden, p.distancia_desde_origen_km ?? 0, p.precio_desde_origen ?? 0, p.pickup_address ?? null])
      }
    }

    const [rows] = await pool.query('SELECT * FROM trips WHERE id = ?', [tripId])
    res.status(201).json({ trip: rows[0] })
  } catch (err) {
    console.error('createTrip error:', err)
    res.status(500).json({ error: err.message || 'Error interno del servidor' })
  }
}

export const updateTrip = async (req, res) => {
  const { descripcion, estado } = req.body
  try {
    const [rows] = await pool.query('SELECT * FROM trips WHERE id = ?', [req.params.id])
    if (!rows[0]) return res.status(404).json({ error: 'Viaje no encontrado' })
    if (rows[0].conductor_id !== req.user.id) return res.status(403).json({ error: 'No autorizado' })

    if (estado && !['activo', 'completado', 'cancelado', 'en_ruta'].includes(estado)) {
      return res.status(400).json({ error: 'Estado no valido' })
    }

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

    const activos = trips.filter(t => t.estado === 'activo' || t.estado === 'en_ruta')
    const historial = trips.filter(t => t.estado !== 'activo' && t.estado !== 'en_ruta')

    res.json({ trips: activos, historial })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}
