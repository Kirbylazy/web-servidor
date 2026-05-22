import { useState, useEffect, useRef } from 'react'
import { useParams, useSearchParams, Link, useNavigate } from 'react-router-dom'
import { io } from 'socket.io-client'
import { api } from '../services/api'
import { useAuth } from '../context/AuthContext'
import { createMap, addPolyline, decodePolyline, fitBoundsToPoints, L } from '../utils/mapService'
import './TripDetail.css'

const SOCKET_URL = import.meta.env.VITE_SOCKET_URL || import.meta.env.VITE_API_URL?.replace('/Zirpo/api', '') || 'http://localhost:3000'
const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''

function closestIndex(path, lat, lng) {
  let best = 0, bestDist = Infinity
  for (let i = 0; i < path.length; i++) {
    const d = (path[i][0] - lat) ** 2 + (path[i][1] - lng) ** 2
    if (d < bestDist) { bestDist = d; best = i }
  }
  return best
}

function TripMap({ polyline, paradas, origenLat, origenLng, destinoLat, destinoLng, tramoOrigen, tramoDestino }) {
  const mapRef = useRef(null)
  const mapInstanceRef = useRef(null)

  useEffect(() => {
    if (!mapRef.current || !origenLat) return

    if (mapInstanceRef.current) {
      mapInstanceRef.current.remove()
      mapInstanceRef.current = null
    }

    const map = createMap(mapRef.current)
    mapInstanceRef.current = map

    let visibleParadas = paradas?.length > 0 ? paradas : null
    let slicedPath = null

    if (tramoOrigen && tramoDestino && paradas?.length > 0) {
      const nO = norm(tramoOrigen)
      const nD = norm(tramoDestino)
      const idxO = paradas.findIndex(p => norm(p.ciudad).includes(nO) || nO.includes(norm(p.ciudad)))
      const idxD = paradas.findIndex(p => norm(p.ciudad).includes(nD) || nD.includes(norm(p.ciudad)))
      if (idxO !== -1 && idxD !== -1 && idxO < idxD) {
        visibleParadas = paradas.slice(idxO, idxD + 1)
        if (polyline) {
          const fullPath = decodePolyline(polyline)
          const pO = visibleParadas[0]
          const pD = visibleParadas[visibleParadas.length - 1]
          const startIdx = closestIndex(fullPath, parseFloat(pO.lat), parseFloat(pO.lng))
          const endIdx = closestIndex(fullPath, parseFloat(pD.lat), parseFloat(pD.lng))
          slicedPath = fullPath.slice(Math.min(startIdx, endIdx), Math.max(startIdx, endIdx) + 1)
        }
      }
    }

    const points = visibleParadas
      ? visibleParadas.map(p => ({ lat: parseFloat(p.lat), lng: parseFloat(p.lng), label: p.ciudad }))
      : [
          { lat: parseFloat(origenLat), lng: parseFloat(origenLng), label: 'Origen' },
          { lat: parseFloat(destinoLat), lng: parseFloat(destinoLng), label: 'Destino' },
        ]

    points.forEach(p => L.marker([p.lat, p.lng]).addTo(map).bindTooltip(p.label))

    if (slicedPath && slicedPath.length > 1) {
      const pl = L.polyline(slicedPath, { color: '#2563eb', weight: 5, opacity: 0.8 }).addTo(map)
      map.fitBounds(pl.getBounds(), { padding: [40, 40] })
    } else if (polyline) {
      const pl = addPolyline(map, polyline)
      map.fitBounds(pl.getBounds(), { padding: [40, 40] })
    } else {
      fitBoundsToPoints(map, points)
    }

    return () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.remove()
        mapInstanceRef.current = null
      }
    }
  }, [polyline, origenLat, paradas, tramoOrigen, tramoDestino])

  if (!origenLat) return null
  return <div ref={mapRef} className="trip-map" />
}

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
  const [etaMin, setEtaMin] = useState(null)
  const [etaConnected, setEtaConnected] = useState(false)
  const socketRef = useRef(null)

  const paramOrigen = searchParams.get('tramo_origen')
  const paramDestino = searchParams.get('tramo_destino')

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

  const myBooking = bookings.find(b => b.pasajero_id === user?.id)
  const isConductor = String(trip?.conductor_id) === String(user?.id)

  // Determine the tramo to display:
  // Priority: 1) search params, 2) existing booking's tramo, 3) full trip
  const getTramo = () => {
    if (!trip) return {}

    // Try search params first
    let tOrigen = paramOrigen
    let tDestino = paramDestino

    // If no search params but user has a booking with tramo, use that
    if ((!tOrigen || !tDestino) && myBooking) {
      tOrigen = myBooking.tramo_origen || trip.origen
      tDestino = myBooking.tramo_destino || trip.destino
    }

    if (tOrigen && tDestino && paradas.length > 0) {
      const nOrigen = norm(tOrigen)
      const nDestino = norm(tDestino)
      const po = paradas.find(p => norm(p.ciudad).includes(nOrigen) || nOrigen.includes(norm(p.ciudad)))
      const pd = paradas.find(p => norm(p.ciudad).includes(nDestino) || nDestino.includes(norm(p.ciudad)))
      if (po && pd && po.orden < pd.orden) {
        return {
          origen: po.ciudad,
          destino: pd.ciudad,
          precio: parseFloat(pd.precio_desde_origen) - parseFloat(po.precio_desde_origen),
          isPartial: po.orden > 0 || pd.orden < paradas.length - 1
        }
      }
    }

    return { origen: trip.origen, destino: trip.destino, precio: trip.precio_asiento, isPartial: false }
  }

  const tramo = getTramo()

  // Calculate available seats for the specific tramo
  const tramoDisponibles = (() => {
    if (!trip || !paradas.length) return trip?.asientos_disponibles ?? 0
    const nO = norm(tramo.origen)
    const nD = norm(tramo.destino)
    const idxO = paradas.findIndex(p => norm(p.ciudad).includes(nO) || nO.includes(norm(p.ciudad)))
    const idxD = paradas.findIndex(p => norm(p.ciudad).includes(nD) || nD.includes(norm(p.ciudad)))
    if (idxO === -1 || idxD === -1) return trip?.asientos_disponibles ?? 0
    let min = trip.asientos_totales
    for (let i = idxO; i < idxD; i++) {
      const seg = paradas[i]?.asientos_disponibles_segmento
      if (seg !== undefined && seg < min) min = seg
    }
    return min
  })()

  // Filter paradas to show only the tramo
  const tramoParadas = (() => {
    if (!tramo.isPartial || !paradas.length) return paradas
    const nO = norm(tramo.origen)
    const nD = norm(tramo.destino)
    const idxO = paradas.findIndex(p => norm(p.ciudad).includes(nO) || nO.includes(norm(p.ciudad)))
    const idxD = paradas.findIndex(p => norm(p.ciudad).includes(nD) || nD.includes(norm(p.ciudad)))
    if (idxO !== -1 && idxD !== -1 && idxO < idxD) {
      return paradas.slice(idxO, idxD + 1)
    }
    return paradas
  })()

  // Socket.io ETA for passengers when trip is en_ruta
  useEffect(() => {
    if (!trip || trip.estado !== 'en_ruta') return
    if (isConductor) return
    if (!myBooking || myBooking.estado !== 'confirmada') return

    const socket = io(SOCKET_URL)
    socketRef.current = socket

    socket.on('connect', () => {
      setEtaConnected(true)
      socket.emit('trip:join', parseInt(id))
    })

    socket.on('disconnect', () => setEtaConnected(false))

    socket.on('simulation:position', (data) => {
      if (!data.etas?.length) return
      const pickupCity = tramo.origen
      const nPickup = norm(pickupCity)
      const match = data.etas.find(e =>
        norm(e.ciudad).includes(nPickup) || nPickup.includes(norm(e.ciudad))
      )
      if (match) {
        setEtaMin(match.reached ? 0 : match.etaMin)
      }
    })

    socket.on('simulation:complete', () => setEtaMin(0))

    return () => {
      socket.emit('trip:leave', parseInt(id))
      socket.disconnect()
      socketRef.current = null
      setEtaConnected(false)
    }
  }, [trip?.estado, trip?.id, isConductor, myBooking?.estado])

  const handleBook = async () => {
    setBooking(true)
    setMsg('')
    try {
      await api.post('/bookings', {
        trip_id: trip.id,
        tramo_origen: tramo.origen,
        tramo_destino: tramo.destino,
      })
      await fetchTrip()
      setMsg('Reserva solicitada! El conductor te confirmara pronto.')
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
  const formatDuration = min => {
    const h = Math.floor(min / 60)
    const m = min % 60
    return h > 0 ? `${h}h ${m}min` : `${m}min`
  }

  // Calculate estimated arrival time for a parada based on distance proportion
  const getEstimatedTime = (parada) => {
    if (!trip?.hora || !trip?.duracion_min || !trip?.distancia_km) return formatTime(trip?.hora || '00:00')
    const distKm = parseFloat(parada.distancia_desde_origen_km) || 0
    const fraction = trip.distancia_km > 0 ? distKm / trip.distancia_km : 0
    const etaMin = Math.round(trip.duracion_min * fraction)
    const [h, m] = trip.hora.split(':').map(Number)
    const totalMin = h * 60 + m + etaMin
    const hh = String(Math.floor(totalMin / 60) % 24).padStart(2, '0')
    const mm = String(totalMin % 60).padStart(2, '0')
    return `${hh}:${mm}`
  }

  if (loading) return <div className="trip-detail-page"><p className="loading">Cargando...</p></div>

  return (
    <div className="trip-detail-page">
      <div className="trip-detail-topbar">
        <Link to="/" className="back-link">← Volver</Link>
      </div>

      {etaMin !== null && !isConductor && (
        <div className="eta-banner">
          <div className="eta-banner-icon">🚗</div>
          <div className="eta-banner-text">
            {etaMin === 0
              ? <span className="eta-arrived">El conductor ha llegado</span>
              : <><span className="eta-minutes">{etaMin}</span> min para que llegue el conductor</>
            }
          </div>
        </div>
      )}

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

        {!isConductor && trip.estado === 'activo' && (
          <div className="detail-actions">
            {myBooking ? (
              <>
                <span className={`status-badge status-${myBooking.estado}`}>
                  {myBooking.estado === 'pendiente' ? '⏳ Pendiente de confirmación'
                    : '✅ Reserva confirmada'}
                </span>
                <div className="detail-actions-buttons">
                  <button className="btn-chat" onClick={() => navigate(`/chat/${trip.id}/${user.id}`)}>
                    Chatear
                  </button>
                  <button className="btn-cancel-action" onClick={() => handleBookingStatus(myBooking.id, 'cancelada')}>
                    Cancelar reserva
                  </button>
                </div>
              </>
            ) : tramoDisponibles > 0 ? (
              <div className="detail-actions-buttons">
                <button className="btn-book-action" onClick={handleBook} disabled={booking}>
                  {booking ? 'Solicitando...' : 'Reservar'}
                </button>
              </div>
            ) : (
              <p className="no-seats">No quedan plazas disponibles</p>
            )}
            {msg && <p className={msg.includes('!') ? 'msg-ok' : 'msg-error'}>{msg}</p>}
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
            <span>{tramoDisponibles} disponibles</span>
          </div>
          <div className="detail-meta-item">
            <span className="meta-label">Precio</span>
            <span className="detail-price">{Number(tramo.precio).toFixed(2)} €/plaza</span>
          </div>
        </div>

        {(trip.distancia_km || trip.duracion_min) && (
          <div className="detail-route-info">
            {trip.distancia_km && <span>{trip.distancia_km} km</span>}
            {trip.duracion_min && <span>{formatDuration(trip.duracion_min)}</span>}
          </div>
        )}

        <TripMap
          polyline={trip.ruta_polyline}
          paradas={paradas}
          origenLat={trip.origen_lat} origenLng={trip.origen_lng}
          destinoLat={trip.destino_lat} destinoLng={trip.destino_lng}
          tramoOrigen={tramo.isPartial ? tramo.origen : null}
          tramoDestino={tramo.isPartial ? tramo.destino : null}
        />

        {tramoParadas.length > 1 && (
          <div className="paradas-list">
            {tramoParadas.map((p, i) => {
              const siguiente = tramoParadas[i + 1]
              const esFinal = i === tramoParadas.length - 1
              const esFirst = i === 0
              return (
                <div key={p.id} className={`parada-item ${esFinal ? 'parada-final' : ''}`}>
                  <div className={`parada-dot ${esFirst ? 'dot-origin' : esFinal ? 'dot-dest' : 'dot-stop'}`} />
                  <div className="parada-info">
                    <span className="parada-ciudad">{p.ciudad}</span>
                    {!esFinal && siguiente && (
                      <span className="parada-precio">
                        hasta {siguiente.ciudad}: {(parseFloat(siguiente.precio_desde_origen) - parseFloat(p.precio_desde_origen)).toFixed(2)} €
                      </span>
                    )}
                  </div>
                  <div className="parada-time-tag">
                    {esFirst && <span className="parada-tag">{tramo.isPartial ? 'Subida' : 'Salida'}</span>}
                    {esFinal && <span className="parada-tag">{tramo.isPartial ? 'Bajada' : 'Llegada'}</span>}
                    <span className="parada-hora">{getEstimatedTime(p)}</span>
                  </div>
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

        {isConductor && trip.estado !== 'cancelado' && (
          <button className="btn-book-action" onClick={() => navigate(`/live/${trip.id}`)}
            style={{ width: '100%', marginBottom: '1rem' }}>
            {trip.estado === 'en_ruta' ? 'Ver viaje en curso' : 'Iniciar viaje'}
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
                  <div>
                    <span>{b.pasajero_nombre}</span>
                    {b.tramo_origen && (
                      <span className="booking-tramo">{b.tramo_origen} → {b.tramo_destino}</span>
                    )}
                  </div>
                </div>
                <span className={`status-badge status-${b.estado}`}>{b.estado}</span>
                <div className="booking-actions">
                  {b.estado === 'pendiente' && (
                    <>
                      <button className="btn-confirm" onClick={() => handleBookingStatus(b.id, 'confirmada')}>✓ Aceptar</button>
                      <button className="btn-reject" onClick={() => handleBookingStatus(b.id, 'cancelada')}>✗ Rechazar</button>
                    </>
                  )}
                  <button className="btn-chat" onClick={() => navigate(`/chat/${trip.id}/${b.pasajero_id}`)}>
                    💬 Chat
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

export default TripDetail
