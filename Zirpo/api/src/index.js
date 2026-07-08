import express from 'express'
import { createServer } from 'http'
import cors from 'cors'
import helmet from 'helmet'
import 'dotenv/config'
import cron from 'node-cron'
import pool from './db.js'
import { initSocket } from './socket.js'

import authRouter from './routes/auth.js'
import tripsRouter from './routes/trips.js'
import bookingsRouter from './routes/bookings.js'
import usersRouter from './routes/users.js'
import messagesRouter from './routes/messages.js'

const app = express()
const server = createServer(app)
initSocket(server)
const PORT = process.env.PORT || 3000

app.use(helmet({
  hsts: {
    maxAge: 31536000,        // 1 año en segundos
    includeSubDomains: true,
    preload: true
  },
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'none'"],
      frameAncestors: ["'none'"]
    }
  }
}))
app.use(cors())
app.use(express.json())

// Servir fotos de perfil
app.use('/api/uploads', express.static('uploads'))

// Proxy para geocoder local (puerto 2322)
app.use('/api/geocoder', async (req, res) => {
  try {
    const response = await fetch(`http://localhost:2322${req.url}`)
    const data = await response.json()
    res.json(data)
  } catch (err) {
    res.status(502).json({ error: 'Geocoder no disponible' })
  }
})

// Proxy para GraphHopper local (puerto 8080)
app.use('/api/router', async (req, res) => {
  try {
    const response = await fetch(`http://localhost:8080${req.url}`)
    const data = await response.json()
    res.json(data)
  } catch (err) {
    res.status(502).json({ error: 'Router no disponible' })
  }
})

app.get('/health', (req, res) => res.json({ status: 'ok' }))

app.use('/api/auth', authRouter)
app.use('/api/trips', tripsRouter)
app.use('/api/bookings', bookingsRouter)
app.use('/api/users', usersRouter)
app.use('/api/messages', messagesRouter)

// Marcar como completados los viajes cuya fecha+hora ya pasó
cron.schedule('0 * * * *', async () => {
  try {
    const [result] = await pool.query(
      "UPDATE trips SET estado='completado' WHERE estado='activo' AND TIMESTAMP(fecha, hora) < NOW()"
    )
    if (result.affectedRows > 0) console.log(`Cron: ${result.affectedRows} viaje(s) marcados como completados`)
  } catch (err) {
    console.error('Cron error:', err.message)
  }
})

// Borrar mensajes con más de 1 mes de antigüedad (cada día a las 3:00)
cron.schedule('0 3 * * *', async () => {
  try {
    const [result] = await pool.query(
      "DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)"
    )
    if (result.affectedRows > 0) console.log(`Cron: ${result.affectedRows} mensaje(s) antiguos eliminados`)
  } catch (err) {
    console.error('Cron cleanup messages error:', err.message)
  }
})

server.listen(PORT, () => {
  console.log(`Zirpo API corriendo en http://localhost:${PORT}`)
})
