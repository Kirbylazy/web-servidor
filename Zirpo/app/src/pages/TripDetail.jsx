import { useState, useEffect, useRef } from 'react'
import { useParams, useSearchParams, Link, useNavigate } from 'react-router-dom'
import { api } from '../services/api'
import { useAuth } from '../context/AuthContext'
import { loadGoogleMapsScript } from '../utils/googleMaps'
import './TripDetail.css'

function TripMap({ polyline, paradas, origenLat, origenLng, destinoLat, destinoLng }) {
  const mapRef = useRef(null)
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY

  useEffect(() => {
    if (!apiKey || !mapRef.current || !origenLat) return

    loadGoogleMapsScript(apiKey).then(() => {
      const map = new window.google.maps.Map(mapRef.current, {
        disableDefaultUI: true,
        zoomControl: true,
      })

      // Marcadores para cada parada si existen, si no solo origen y destino
      const points = paradas?.length > 0
        ? paradas.map(p => ({ lat: parseFloat(p.lat), lng: parseFloat(p.lng), label: p.ciudad }))
        : [
            { lat: parseFloat(origenLat), lng: parseFloat(origenLng), label: 'Origen' },
            { lat: parseFloat(destinoLat), lng: parseFloat(destinoLng), label: 'Destino' },
          ]

      points.forEach(p => new window.google.maps.Marker({ position: p, map, title: p.label }))

      if (polyline) {
        const decoded = window.google.maps.geometry.encoding.decodePath(polyline)
        new window.google.maps.Polyline({ path: decoded, map, strokeColor: '#4F46E5', strokeWeight: 4 })
        const bounds = new window.google.maps.LatLngBounds()
        decoded.forEach(p => bounds.extend(p))
        map.fitBounds(bounds, 40)
      } else {
        const bounds = new window.google.maps.LatLngBounds()
        points.forEach(p => bounds.extend(p))
        map.fitBounds(bounds, 40)
      }
    })
  }, [polyline, origenLat, paradas])

  if (!apiKey || !origenLat) return null
  return <div ref={mapRef} className="trip-map" />
}

const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''

const TripDetail = () => {
  const { id } = useParams()
  const [searchParams] = useSearchParams()
  const { user } = useAuth()
  const navigate = useNavigate()
  const [trip, setTrip] = useState(null)
  const [bookings, setBookings] = useState([])
  const [paradas, setParadas] = useState([])
  const [loading, setLoading] = useState(true)
  const [booking, setBooking] = useState(false)
  const [msg, setMsg] = useState('')

  const tramoOrigen = searchParams.get('tramo_origen')
  const tramoDestino = searchParams.get('tramo_destino')

  useEffect(() => { fetchTrip() }, [id])

  const fetchTrip = async () => {
    try {
      const data = await api.get(`/trips/${id}`)
      setTrip(data.trip)
      setBookings(data.bookings)
      setParadas(data.paradas || [])
    } catch {
      navigate('/')
    } finally {
      setLoading(false)
    }
  }

  // Calcular el tramo que buscó el pasajero
  const getTramo = () => {
    if (!tramoOrigen || !tramoDestino || !paradas.length) {
      return { origen: trip?.origen, destino: trip?.destino, precio: trip?.precio_asiento }
    }
    const nOrigen = norm(tramoOrigen)
    const nDestino = norm(tramoDestino)
    const po = paradas.find(p => norm(p.ciudad).includes(nOrigen))
    const pd = paradas.find(p => norm(p.ciudad).includes(nDestino))
    if (po && pd && po.orden < pd.orden) {
      return {
        origen: po.ciudad,
        destino: pd.ciudad,
        precio: parseFloat(pd.precio_desde_origen) - parseFloat(po.precio_desde_origen)
      }
    }
    return { origen: trip?.origen, destino: trip?.destino, precio: trip?.precio_asiento }
  }

  const tramo = trip ? getTramo() : {}
  const myBooking = bookings.find(b => b.pasajero_id === user?.id)
  const isConductor = trip?.conductor_id === user?.id

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
            <span className="city-name">{tramo.origen}</span>
          </div>
          <span className="detail-arrow">→</span>
          <div className="detail-city detail-city-right">
            <span className="city-label">Destino</span>
            <span className="city-name">{tramo.destino}</span>
          </div>
        </div>

        {/* Sección de acciones del pasajero */}
        {!isConductor && trip.estado === 'activo' && (
          <div className="detail-actions">
            {myBooking ? (
              <>
                <span className={`status-badge status-${myBooking.estado}`}>
                  {myBooking.estado === 'pendiente' ? '⏳ Pendiente de confirmación'
                    : '✅ Reserva confirmada'}
                </span>
                <div className="detail-actions-buttons">
                  <button className="btn-chat" onClick={() => navigate(`/chat/${trip.id}`)}>
                    Chatear
                  </button>
                  <button className="btn-cancel-action" onClick={() => handleBookingStatus(myBooking.id, 'cancelada')}>
                    Cancelar reserva
                  </button>
                </div>
              </>
            ) : trip.asientos_disponibles > 0 ? (
              <div className="detail-actions-buttons">
                <button className="btn-book-action" onClick={handleBook} disabled={booking}>
                  {booking ? 'Solicitando...' : 'Reservar'}
                </button>
                <button className="btn-chat" onClick={() => navigate(`/chat/${trip.id}`)}>
                  Chatear
                </button>
              </div>
            ) : (
              <p className="no-seats">No quedan plazas disponibles</p>
            )}
            {msg && <p className={msg.includes('¡') ? 'msg-ok' : 'msg-error'}>{msg}</p>}
          </div>
        )}

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
            <span className="detail-price">{Number(tramo.precio).toFixed(2)} €/plaza</span>
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
          paradas={paradas}
          origenLat={trip.origen_lat} origenLng={trip.origen_lng}
          destinoLat={trip.destino_lat} destinoLng={trip.destino_lng}
        />

        {paradas.length > 1 && (
          <div className="paradas-list">
            {paradas.map((p, i) => {
              const siguiente = paradas[i + 1]
              const esFinal = i === paradas.length - 1
              return (
                <div key={p.id} className={`parada-item ${esFinal ? 'parada-final' : ''}`}>
                  <div className={`parada-dot ${i === 0 ? 'dot-origin' : esFinal ? 'dot-dest' : 'dot-stop'}`} />
                  <div className="parada-info">
                    <span className="parada-ciudad">{p.ciudad}</span>
                    {!esFinal && siguiente && (
                      <span className="parada-precio">
                        hasta {siguiente.ciudad}: {(parseFloat(siguiente.precio_desde_origen) - parseFloat(p.precio_desde_origen)).toFixed(2)} €
                      </span>
                    )}
                  </div>
                  {i === 0 && <span className="parada-tag">Salida</span>}
                  {esFinal && <span className="parada-tag">Llegada</span>}
                </div>
              )
            })}
          </div>
        )}

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

        {/* Panel del conductor */}
        {isConductor && (
          <button className="btn-chat" style={{ width: '100%', marginBottom: '1rem' }}
            onClick={() => navigate(`/chat/${trip.id}`)}>
            Chat del viaje
          </button>
        )}
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
