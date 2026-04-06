import pool from '../db.js'

export const getVehicle = async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM vehicles WHERE user_id = ?', [req.params.id])
    res.json({ vehicle: rows[0] || null })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}

export const saveVehicle = async (req, res) => {
  if (parseInt(req.params.id) !== req.user.id) {
    return res.status(403).json({ error: 'No autorizado' })
  }

  const { marca, modelo, matricula, color, plazas, anio, aire_acondicionado, musica, maletero_grande, descripcion } = req.body

  if (!marca || !modelo || !matricula || !color || !plazas) {
    return res.status(400).json({ error: 'Marca, modelo, matrícula, color y plazas son obligatorios' })
  }

  try {
    const [existing] = await pool.query('SELECT id FROM vehicles WHERE user_id = ?', [req.user.id])

    if (existing.length > 0) {
      await pool.query(
        `UPDATE vehicles SET marca=?, modelo=?, matricula=?, color=?, plazas=?, anio=?,
         aire_acondicionado=?, musica=?, maletero_grande=?, descripcion=? WHERE user_id=?`,
        [marca, modelo, matricula, color, plazas, anio || null,
         aire_acondicionado || false, musica || false, maletero_grande || false,
         descripcion || null, req.user.id]
      )
    } else {
      await pool.query(
        `INSERT INTO vehicles (user_id, marca, modelo, matricula, color, plazas, anio,
         aire_acondicionado, musica, maletero_grande, descripcion)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [req.user.id, marca, modelo, matricula, color, plazas, anio || null,
         aire_acondicionado || false, musica || false, maletero_grande || false,
         descripcion || null]
      )
    }

    const [rows] = await pool.query('SELECT * FROM vehicles WHERE user_id = ?', [req.user.id])
    res.json({ vehicle: rows[0] })
  } catch (err) {
    console.error(err)
    res.status(500).json({ error: 'Error interno del servidor' })
  }
}
