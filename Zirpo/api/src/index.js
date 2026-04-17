import express from 'express'
import cors from 'cors'
import helmet from 'helmet'
import 'dotenv/config'
import cron from 'node-cron'
import pool from './db.js'

import authRouter from './routes/auth.js'
import tripsRouter from './routes/trips.js'
import bookingsRouter from './routes/bookings.js'
import usersRouter from './routes/users.js'
import messagesRouter from './routes/messages.js'

const app = express()
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

app.listen(PORT, () => {
  console.log(`Zirpo API corriendo en http://localhost:${PORT}`)
})
