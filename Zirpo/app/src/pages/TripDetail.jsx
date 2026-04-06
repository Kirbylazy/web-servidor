import { useState, useEffect } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { api } from '../services/api'
import { useAuth } from '../context/AuthContext'
import './TripDetail.css'

const TripDetail = () => {
  const { id } = useParams()
  const { user } = useAuth()
  const navigate = useNavigate()
  const [trip, setTrip] = useState(null)
  const [bookings, setBookings] = useState([])
  const [loading, setLoading] = useState(true)
  const [booking, setBooking] = useState(false)
  const [msg, setMsg] = useState('')

  useEffect(() => { fetchTrip() }, [id])

  const fetchTrip = async () => {
    try {
      const data = await api.get(`/trips/${id}`)
      setTrip(data.trip)
      setBookings(data.bookings)
    } catch {
      navigate('/')
    } finally {
      setLoading(false)
    }
  }

  const myBooking = bookings.find(b => b.pasajero_id === user.id)
  const isConductor = trip?.conductor_id === user.id

  const handleBook = async () => {
    setBooking(true)
    setMsg('')
    try {
      await api.post('/bookings', { trip_id: trip.id })
      await fetchTrip()
      setMsg('¡Reserva solicitada! El conductor te confirmará pronto.')
    } catch (err) {
      setMsg(err.message)
    } finally {
      setBooking(false)
    }
  }

  const handleBookingStatus = async (bookingId, estado) => {
    try {
      await api.patch(`/bookings/${bookingId}/status`, { estado })
      await fetchTrip()
    } catch (err) {
      setMsg(err.message)
    }
  }

  const formatDate = d => new Date(d).toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' })
  const formatTime = t => t.slice(0, 5)

  if (loading) return <div className="trip-detail-page"><p className="loading">Cargando...</p></div>

  return (
    <div className="trip-detail-page">
      <div className="trip-detail-topbar">
        <Link to="/" className="back-link">← Volver</Link>
      </div>

      <div className="trip-detail-card">
        <div className="detail-route">
          <div className="detail-city">
            <span className="city-label">Origen</span>
            <span className="city-name">{trip.origen}</span>
          </div>
          <span className="detail-arrow">→</span>
          <div className="detail-city detail-city-right">
            <span className="city-label">Destino</span>
            <span className="city-name">{trip.destino}</span>
          </div>
        </div>

        <div className="detail-meta">
          <div className="detail-meta-item">
            <span className="meta-label">Fecha</span>
            <span>{formatDate(trip.fecha)}</span>
          </div>
          <div className="detail-meta-item">
            <span className="meta-label">Hora</span>
            <span>{formatTime(trip.hora)}</span>
          </div>
          <div className="detail-meta-item">
            <span className="meta-label">Plazas</span>
            <span>{trip.asientos_disponibles} disponibles</span>
          </div>
          <div className="detail-meta-item">
            <span className="meta-label">Precio</span>
            <span className="detail-price">{Number(trip.precio_asiento).toFixed(2)} €/plaza</span>
          </div>
        </div>

        {trip.descripcion && (
          <p className="detail-desc">{trip.descripcion}</p>
        )}

        <div className="detail-driver">
          {trip.conductor_foto
            ? <img src={trip.conductor_foto} alt="" className="driver-avatar-lg" />
            : <div className="driver-avatar-placeholder-lg">{trip.conductor_nombre?.[0]}</div>
          }
          <div>
            <p className="driver-name">{trip.conductor_nombre} {trip.conductor_apellidos}</p>
            {trip.marca && <p className="driver-car">{trip.marca} {trip.modelo} · {trip.color}</p>}
          </div>
        </div>

        {trip.aire_acondicionado || trip.musica || trip.maletero_grande ? (
          <div className="detail-amenities">
            {trip.aire_acondicionado && <span>❄️ Aire acondicionado</span>}
            {trip.musica && <span>🎵 Música</span>}
            {trip.maletero_grande && <span>🧳 Maletero grande</span>}
          </div>
        ) : null}

        {msg && <p className={msg.includes('¡') ? 'msg-ok' : 'msg-error'}>{msg}</p>}

        {/* Acción del pasajero */}
        {!isConductor && trip.estado === 'activo' && (
          myBooking ? (
            <div className="booking-status">
              <span className={`status-badge status-${myBooking.estado}`}>
                {myBooking.estado === 'pendiente' ? '⏳ Reserva pendiente de confirmación'
                  : myBooking.estado === 'confirmada' ? '✅ Reserva confirmada'
                  : '❌ Reserva cancelada'}
              </span>
              {myBooking.estado !== 'cancelada' && (
                <button className="btn-cancel" onClick={() => handleBookingStatus(myBooking.id, 'cancelada')}>
                  Cancelar reserva
                </button>
              )}
            </div>
          ) : trip.asientos_disponibles > 0 ? (
            <button className="btn-book" onClick={handleBook} disabled={booking}>
              {booking ? 'Solicitando...' : `Reservar plaza · ${Number(trip.precio_asiento).toFixed(2)} €`}
            </button>
          ) : (
            <p className="no-seats">No quedan plazas disponibles</p>
          )
        )}

        {/* Panel del conductor */}
        {isConductor && bookings.length > 0 && (
          <div className="conductor-bookings">
            <h3>Solicitudes de reserva</h3>
            {bookings.map(b => (
              <div key={b.id} className="booking-row">
                <div className="booking-passenger">
                  {b.pasajero_foto
                    ? <img src={b.pasajero_foto} alt="" className="driver-avatar" />
                    : <div className="driver-avatar-placeholder">{b.pasajero_nombre?.[0]}</div>
                  }
                  <span>{b.pasajero_nombre}</span>
                </div>
                <span className={`status-badge status-${b.estado}`}>{b.estado}</span>
                {b.estado === 'pendiente' && (
                  <div className="booking-actions">
                    <button className="btn-confirm" onClick={() => handleBookingStatus(b.id, 'confirmada')}>✓ Aceptar</button>
                    <button className="btn-reject" onClick={() => handleBookingStatus(b.id, 'cancelada')}>✗ Rechazar</button>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default TripDetail
