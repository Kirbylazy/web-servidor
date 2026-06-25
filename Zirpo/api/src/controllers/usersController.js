import bcrypt from 'bcrypt'
import pool from '../db.js'

export const getUserById = async (req, res) => {
  try {
    const [rows] = await pool.query(
      'SELECT id, nombre, apellidos, email, telefono, foto, valoracion_media, verificado, created_at FROM users WHERE id = ?',
      [req.params.id]
    )
    if (!rows[0]) return res.status(404).json({ error: 'Usuario no encontrado' })
    res.json({ user: rows[0] })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const updateUser = async (req, res) => {
  const { nombre, apellidos, telefono } = req.body

  if (parseInt(req.params.id) !== req.user.id) {
    return res.status(403).json({ error: 'No autorizado' })
  }

  try {
    await pool.query(
      'UPDATE users SET nombre = ?, apellidos = ?, telefono = ? WHERE id = ?',
      [nombre, apellidos || null, telefono || null, req.user.id]
    )
    const [rows] = await pool.query(
      'SELECT id, nombre, apellidos, email, telefono, foto FROM users WHERE id = ?',
      [req.user.id]
    )
    res.json({ user: rows[0] })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const updatePassword = async (req, res) => {
  const { actual, nueva } = req.body

  if (parseInt(req.params.id) !== req.user.id) {
    return res.status(403).json({ error: 'No autorizado' })
  }

  if (!actual || !nueva) {
    return res.status(400).json({ error: 'Contraseña actual y nueva son obligatorias' })
  }

  if (nueva.length < 8) {
    return res.status(400).json({ error: 'La nueva contraseña debe tener al menos 8 caracteres' })
  }

  try {
    const [rows] = await pool.query('SELECT password FROM users WHERE id = ?', [req.user.id])
    const valid = await bcrypt.compare(actual, rows[0].password)
    if (!valid) return res.status(401).json({ error: 'La contraseña actual no es correcta' })

    const hash = await bcrypt.hash(nueva, 12)
    await pool.query('UPDATE users SET password = ? WHERE id = ?', [hash, req.user.id])
    res.json({ message: 'Contraseña actualizada correctamente' })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const updatePhoto = async (req, res) => {
  if (parseInt(req.params.id) !== req.user.id) {
    return res.status(403).json({ error: 'No autorizado' })
  }

  if (!req.file) return res.status(400).json({ error: 'No se subió ninguna imagen' })

  const fotoUrl = `/Zirpo/api/uploads/${req.file.filename}`

  try {
    await pool.query('UPDATE users SET foto = ? WHERE id = ?', [fotoUrl, req.user.id])
    res.json({ foto: fotoUrl })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}
