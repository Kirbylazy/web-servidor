import bcrypt from 'bcrypt'
import jwt from 'jsonwebtoken'
import pool from '../db.js'

export const register = async (req, res) => {
  const { nombre, email, password, telefono } = req.body

  if (!nombre || !email || !password) {
    return res.status(400).json({ error: 'Nombre, email y contraseña son obligatorios' })
  }

  try {
    const [existing] = await pool.query('SELECT id FROM users WHERE email = ?', [email])
    if (existing.length > 0) {
      return res.status(409).json({ error: 'El email ya está registrado' })
    }

    const hash = await bcrypt.hash(password, 12)

    const [result] = await pool.query(
      'INSERT INTO users (nombre, email, password, telefono) VALUES (?, ?, ?, ?)',
      [nombre, email, hash, telefono || null]
    )

    const token = jwt.sign(
      { id: result.insertId, email, nombre },
      process.env.JWT_SECRET,
      { expiresIn: process.env.JWT_EXPIRES_IN }
    )

    res.status(201).json({ token, user: { id: result.insertId, nombre, email } })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const login = async (req, res) => {
  const { email, password } = req.body

  if (!email || !password) {
    return res.status(400).json({ error: 'Email y contraseña son obligatorios' })
  }

  try {
    const [rows] = await pool.query('SELECT * FROM users WHERE email = ?', [email])
    const user = rows[0]

    if (!user) {
      return res.status(401).json({ error: 'Credenciales incorrectas' })
    }

    const valid = await bcrypt.compare(password, user.password)
    if (!valid) {
      return res.status(401).json({ error: 'Credenciales incorrectas' })
    }

    const token = jwt.sign(
      { id: user.id, email: user.email, nombre: user.nombre },
      process.env.JWT_SECRET,
      { expiresIn: process.env.JWT_EXPIRES_IN }
    )

    res.json({ token, user: { id: user.id, nombre: user.nombre, email: user.email } })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const me = async (req, res) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, nombre, email, telefono, foto, valoracion_media, verificado, created_at FROM users WHERE id = ?',
      [req.user.id]
    )
    const user = rows[0]

    if (!user) return res.status(404).json({ error: 'Usuario no encontrado' })

    res.json({ user })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}
