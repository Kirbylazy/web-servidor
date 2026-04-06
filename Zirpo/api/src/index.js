import express from 'express'
import cors from 'cors'
import 'dotenv/config'

import authRouter from './routes/auth.js'
import tripsRouter from './routes/trips.js'
import bookingsRouter from './routes/bookings.js'
import usersRouter from './routes/users.js'
import messagesRouter from './routes/messages.js'

const app = express()
const PORT = process.env.PORT || 3000

app.use(cors())
app.use(express.json())

app.get('/health', (req, res) => res.json({ status: 'ok' }))

app.use('/api/auth', authRouter)
app.use('/api/trips', tripsRouter)
app.use('/api/bookings', bookingsRouter)
app.use('/api/users', usersRouter)
app.use('/api/messages', messagesRouter)

app.listen(PORT, () => {
  console.log(`Zirpo API corriendo en http://localhost:${PORT}`)
})
