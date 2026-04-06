export const getUserById = (req, res) => {
  res.json({ message: `Usuario ${req.params.id}` })
}

export const updateUser = (req, res) => {
  res.json({ message: `Usuario ${req.params.id} actualizado` })
}
