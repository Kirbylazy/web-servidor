export const getBookings = (req, res) => {
  res.json({ message: 'Lista de reservas' })
}

export const createBooking = (req, res) => {
  res.status(201).json({ message: 'Reserva creada' })
}

export const updateBookingStatus = (req, res) => {
  res.json({ message: `Reserva ${req.params.id} — estado actualizado` })
}
