import { Server } from 'socket.io'
import pool from './db.js'

let io

// Active simulations: tripId -> { timer, currentIndex, path, speed, paused }
const simulations = new Map()

// Decode encoded polyline (precision 5, same as Google/OSRM)
function decodePolyline(encoded) {
  const points = []
  let index = 0, lat = 0, lng = 0
  while (index < encoded.length) {
    let b, shift = 0, result = 0
    do { b = encoded.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5 } while (b >= 0x20)
    lat += (result & 1) ? ~(result >> 1) : (result >> 1)
    shift = 0; result = 0
    do { b = encoded.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5 } while (b >= 0x20)
    lng += (result & 1) ? ~(result >> 1) : (result >> 1)
    points.push({ lat: lat / 1e5, lng: lng / 1e5 })
  }
  return points
}

// Haversine distance in km
function haversine(a, b) {
  const R = 6371
  const dLat = (b.lat - a.lat) * Math.PI / 180
  const dLng = (b.lng - a.lng) * Math.PI / 180
  const x = Math.sin(dLat / 2) ** 2 +
    Math.cos(a.lat * Math.PI / 180) * Math.cos(b.lat * Math.PI / 180) * Math.sin(dLng / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x))
}

// Calculate ETA from current position to each parada
function calcETAs(currentPos, paradas, path, currentIndex, speedKmh) {
  if (!paradas?.length || !path?.length) return []

  // Remaining distance along route from current index
  let distFromCurrent = 0
  const distAtIndex = []
  distAtIndex[currentIndex] = 0

  for (let i = currentIndex; i < path.length - 1; i++) {
    distFromCurrent += haversine(path[i], path[i + 1])
    distAtIndex[i + 1] = distFromCurrent
  }

  return paradas.map(p => {
    // Find closest point on remaining path to this parada
    let minDist = Infinity
    let closestIdx = currentIndex

    for (let i = currentIndex; i < path.length; i++) {
      const d = haversine(path[i], { lat: parseFloat(p.lat), lng: parseFloat(p.lng) })
      if (d < minDist) { minDist = d; closestIdx = i }
    }

    const distKm = distAtIndex[closestIdx] ?? 0
    const etaMin = speedKmh > 0 ? Math.round(distKm / speedKmh * 60) : 0

    return {
      ciudad: p.ciudad,
      orden: p.orden,
      etaMin,
      distKm: Math.round(distKm * 10) / 10,
      reached: closestIdx <= currentIndex && minDist < 2, // within 2km = reached
    }
  })
}

function startSimulation(tripId, path, paradas, speed) {
  if (simulations.has(tripId)) {
    clearInterval(simulations.get(tripId).timer)
  }

  const sim = {
    currentIndex: 0,
    path,
    paradas,
    speed, // multiplier: 1 = real speed, 10 = 10x faster
    paused: false,
    timer: null,
    baseSpeedKmh: 90, // simulated driving speed
  }

  // Emit position every 500ms, advancing along path
  // At speed=1, we move ~90km/h worth of distance per real second
  // At speed=10, 10x that
  sim.timer = setInterval(() => {
    if (sim.paused || sim.currentIndex >= path.length - 1) {
      if (sim.currentIndex >= path.length - 1) {
        // Trip completed
        io.to(`trip:${tripId}`).emit('simulation:complete', { tripId })
        clearInterval(sim.timer)
        simulations.delete(tripId)
      }
      return
    }

    // Move forward: calculate how many path points to skip based on speed
    const kmPerTick = sim.baseSpeedKmh * sim.speed / 3600 * 0.5 // km per 500ms tick
    let moved = 0

    while (moved < kmPerTick && sim.currentIndex < path.length - 1) {
      const d = haversine(path[sim.currentIndex], path[sim.currentIndex + 1])
      moved += d
      sim.currentIndex++
    }

    const pos = path[sim.currentIndex]
    const etas = calcETAs(pos, paradas, path, sim.currentIndex, sim.baseSpeedKmh * sim.speed)

    const progress = Math.round(sim.currentIndex / (path.length - 1) * 100)

    io.to(`trip:${tripId}`).emit('simulation:position', {
      tripId,
      lat: pos.lat,
      lng: pos.lng,
      index: sim.currentIndex,
      total: path.length,
      progress,
      etas,
    })
  }, 500)

  simulations.set(tripId, sim)

  // Emit initial position
  const pos = path[0]
  const etas = calcETAs(pos, paradas, path, 0, sim.baseSpeedKmh * sim.speed)
  io.to(`trip:${tripId}`).emit('simulation:position', {
    tripId,
    lat: pos.lat,
    lng: pos.lng,
    index: 0,
    total: path.length,
    progress: 0,
    etas,
  })
}

export function initSocket(server) {
  io = new Server(server, {
    cors: { origin: '*' },
  })

  io.on('connection', (socket) => {
    console.log(`Socket conectado: ${socket.id}`)

    // Join a trip room (both conductor and passengers)
    socket.on('trip:join', (tripId) => {
      socket.join(`trip:${tripId}`)
      console.log(`${socket.id} se unió a trip:${tripId}`)
    })

    socket.on('trip:leave', (tripId) => {
      socket.leave(`trip:${tripId}`)
    })

    // Start simulation (conductor only)
    socket.on('simulation:start', async ({ tripId, speed = 1 }) => {
      try {
        const [rows] = await pool.query(
          'SELECT ruta_polyline FROM trips WHERE id = ?', [tripId]
        )
        if (!rows[0]?.ruta_polyline) {
          socket.emit('simulation:error', { message: 'El viaje no tiene ruta calculada' })
          return
        }

        const [paradas] = await pool.query(
          'SELECT * FROM paradas WHERE trip_id = ? ORDER BY orden', [tripId]
        )

        const path = decodePolyline(rows[0].ruta_polyline)
        if (path.length < 2) {
          socket.emit('simulation:error', { message: 'Ruta demasiado corta' })
          return
        }

        // Update trip status
        await pool.query("UPDATE trips SET estado = 'en_ruta' WHERE id = ?", [tripId])

        startSimulation(tripId, path, paradas, speed)
        io.to(`trip:${tripId}`).emit('simulation:started', { tripId, speed })
        console.log(`Simulación iniciada: trip ${tripId}, speed ${speed}x`)
      } catch (err) {
        console.error('simulation:start error:', err)
        socket.emit('simulation:error', { message: err.message })
      }
    })

    // Pause/resume
    socket.on('simulation:pause', ({ tripId }) => {
      const sim = simulations.get(tripId)
      if (sim) {
        sim.paused = true
        io.to(`trip:${tripId}`).emit('simulation:paused', { tripId })
      }
    })

    socket.on('simulation:resume', ({ tripId }) => {
      const sim = simulations.get(tripId)
      if (sim) {
        sim.paused = false
        io.to(`trip:${tripId}`).emit('simulation:resumed', { tripId })
      }
    })

    // Change speed
    socket.on('simulation:speed', ({ tripId, speed }) => {
      const sim = simulations.get(tripId)
      if (sim) {
        sim.speed = speed
        io.to(`trip:${tripId}`).emit('simulation:speedChanged', { tripId, speed })
      }
    })

    // Stop simulation
    socket.on('simulation:stop', async ({ tripId }) => {
      const sim = simulations.get(tripId)
      if (sim) {
        clearInterval(sim.timer)
        simulations.delete(tripId)
        await pool.query("UPDATE trips SET estado = 'activo' WHERE id = ?", [tripId])
        io.to(`trip:${tripId}`).emit('simulation:stopped', { tripId })
        console.log(`Simulación detenida: trip ${tripId}`)
      }
    })

    socket.on('disconnect', () => {
      console.log(`Socket desconectado: ${socket.id}`)
    })
  })

  return io
}

export function getIO() {
  return io
}
