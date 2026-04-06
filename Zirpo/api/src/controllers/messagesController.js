export const getMessages = (req, res) => {
  res.json({ message: `Mensajes del viaje ${req.params.tripId}` })
}

export const sendMessage = (req, res) => {
  res.status(201).json({ message: 'Mensaje enviado' })
}
