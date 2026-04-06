export const getTrips = (req, res) => {
  res.json({ message: 'Lista de viajes' })
}

export const getTripById = (req, res) => {
  res.json({ message: `Viaje ${req.params.id}` })
}

export const createTrip = (req, res) => {
  res.status(201).json({ message: 'Viaje creado' })
}

export const updateTrip = (req, res) => {
  res.json({ message: `Viaje ${req.params.id} actualizado` })
}

export const deleteTrip = (req, res) => {
  res.json({ message: `Viaje ${req.params.id} eliminado` })
}
