import { useState, useEffect, useRef } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { api } from '../services/api'
import { useAuth } from '../context/AuthContext'
import './TripDetail.css'

function TripMap({ polyline, origenLat, origenLng, destinoLat, destinoLng }) {
  const mapRef = useRef(null)
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY

  useEffect(() => {
    if (!apiKey || !mapRef.current) return
    if (!origenLat || !destinoLat) return

    const init = () => {
      const map = new window.google.maps.Map(mapRef.current, {
        zoom: 7,
        center: {
          lat: (parseFloat(origenLat) + parseFloat(destinoLat)) / 2,
          lng: (parseFloat(origenLng) + parseFloat(destinoLng)) / 2,
        },
        disableDefaultUI: true,
        zoomControl: true,
      })

      new window.google.maps.Marker({ position: { lat: parseFloat(origenLat), lng: parseFloat(origenLng) }, map, title: 'Origen' })
      new window.google.maps.Marker({ position: { lat: parseFloat(destinoLat), lng: parseFloat(destinoLng) }, map, title: 'Destino' })

      if (polyline) {
        const decoded = window.google.maps.geometry.encoding.decodePath(polyline)
        new window.google.maps.Polyline({
          path: decoded,
          map,
          strokeColor: '#4F46E5',
          strokeWeight: 4,
        })
        const bounds = new window.google.maps.LatLngBounds()
        decoded.forEach(p => bounds.extend(p))
        map.fitBounds(bounds, 40)
      }
    }

    if (window.google?.maps) {
      init()
    } else {
      const script = document.createElement('script')
      script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=geometry,places&language=es`
      script.async = true
      script.onload = init
      document.head.appendChild(script)
    }
  }, [polyline, origenLat, destinoLat])

  if (!apiKey || !origenLat) return null

  return <div ref={mapRef} className="trip-map" />
}

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

        {(trip.distancia_km || trip.duracion_min) && (
          <div className="detail-route-info">
            {trip.distancia_km && <span>{trip.distancia_km} km</span>}
            {trip.duracion_min && <span>{Math.floor(trip.duracion_min / 60)}h {trip.duracion_min % 60}min</span>}
          </div>
        )}

        <TripMap
          polyline={trip.ruta_polyline}
          origenLat={trip.origen_lat} origenLng={trip.origen_lng}
          destinoLat={trip.destino_lat} destinoLng={trip.destino_lng}
        />

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
