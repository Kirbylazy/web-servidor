import { useState, useEffect, useRef } from 'react'
import { Link } from 'react-router-dom'
import { io } from 'socket.io-client'
import { api } from '../services/api'
import './MyTrips.css'

const SOCKET_URL = import.meta.env.VITE_SOCKET_URL || import.meta.env.VITE_API_URL?.replace('/Zirpo/api', '') || 'http://localhost:3000'
const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''

function EtaBadge({ tripId, origen }) {
  const [eta, setEta] = useState(null)
  const socketRef = useRef(null)

  useEffect(() => {
    const socket = io(SOCKET_URL)
    socketRef.current = socket

    socket.on('connect', () => {
      socket.emit('trip:join', tripId)
    })

    socket.on('simulation:position', (data) => {
      if (!data.etas?.length) return
      const nOrigen = norm(origen)
      const match = data.etas.find(e =>
        norm(e.ciudad).includes(nOrigen) || nOrigen.includes(norm(e.ciudad))
      )
      if (match) {
        setEta(match.reached ? 0 : match.etaMin)
      }
    })

    socket.on('simulation:complete', () => setEta(0))

    return () => {
      socket.emit('trip:leave', tripId)
      socket.disconnect()
    }
  }, [tripId, origen])

  if (eta === null) return <span className="eta-live">En ruta...</span>
  if (eta === 0) return <span className="eta-live eta-arrived">Ha llegado</span>
  return <span className="eta-live">{eta} min</span>
}

const MyTrips = () => {
  const [trips, setTrips] = useState([])
  const [historial, setHistorial] = useState([])
  const [bookings, setBookings] = useState([])
  const [tab, setTab] = useState('conductor')
  const [loading, setLoading] = useState(true)
  const [cancelling, setCancelling] = useState(null)

  const fetchData = () =>
    Promise.all([
      api.get('/trips/my'),
      api.get('/bookings')
    ]).then(([tripsData, bookingsData]) => {
      setTrips(tripsData.trips)
      setHistorial(tripsData.historial || [])
      setBookings(bookingsData.bookings)
    }).finally(() => setLoading(false))

  useEffect(() => { fetchData() }, [])

  const handleCancel = async (e, tripId) => {
    e.preventDefault()
    if (!confirm('¿Cancelar este viaje?')) return
    setCancelling(tripId)
    try {
      await api.delete(`/trips/${tripId}`)
      await fetchData()
    } catch (err) {
      alert(err.message)
    } finally {
      setCancelling(null)
    }
  }

  const formatDate = d => new Date(d).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })
  const formatTime = t => t.slice(0, 5)

  if (loading) return (
    <div className="mytrips-page">
      <div className="mytrips-topbar"><Link to="/" className="back-link">← Inicio</Link></div>
      <p className="loading">Cargando...</p>
    </div>
  )

  const TripCard = ({ trip, showCancel = false }) => (
    <div className="mytrip-card-wrapper">
      <Link to={`/trips/${trip.id}`} className="mytrip-card">
        <div className="mytrip-route">
          <span>{trip.origen}</span>
          <span className="trip-arrow">→</span>
          <span>{trip.destino}</span>
        </div>
        <div className="mytrip-meta">
          <span>{formatDate(trip.fecha)} · {formatTime(trip.hora)}</span>
          <span className={`status-badge status-${trip.estado}`}>{trip.estado}</span>
        </div>
        <div className="mytrip-stats">
          <span>💺 {trip.asientos_disponibles}/{trip.asientos_totales} plazas</span>
          {trip.reservas_pendientes > 0 &&
            <span className="pending-badge">⏳ {trip.reservas_pendientes} pendiente{trip.reservas_pendientes > 1 ? 's' : ''}</span>
          }
          {trip.reservas_confirmadas > 0 &&
            <span className="confirmed-badge">✅ {trip.reservas_confirmadas} confirmada{trip.reservas_confirmadas > 1 ? 's' : ''}</span>
          }
        </div>
      </Link>
      {showCancel && (
        <button
          className="btn-cancel-trip"
          onClick={e => handleCancel(e, trip.id)}
          disabled={cancelling === trip.id}
        >
          {cancelling === trip.id ? '...' : 'Cancelar'}
        </button>
      )}
    </div>
  )

  return (
    <div className="mytrips-page">
      <div className="mytrips-topbar">
        <Link to="/" className="back-link">← Inicio</Link>
        <h2>Mis viajes</h2>
      </div>

      <div className="mytrips-tabs">
        <button className={tab === 'conductor' ? 'active' : ''} onClick={() => setTab('conductor')}>
          Como conductor ({trips.length})
        </button>
        <button className={tab === 'pasajero' ? 'active' : ''} onClick={() => setTab('pasajero')}>
          Como pasajero ({bookings.length})
        </button>
      </div>

      <div className="mytrips-content">
        {tab === 'conductor' && (
          <>
            <Link to="/publish" className="btn-publish">+ Publicar nuevo viaje</Link>

            {trips.length === 0
              ? <p className="empty">No tienes viajes activos.</p>
              : trips.map(trip => <TripCard key={trip.id} trip={trip} showCancel />)
            }

            {historial.length > 0 && (
              <>
                <h3 className="historial-title">Historial</h3>
                {historial.map(trip => <TripCard key={trip.id} trip={trip} />)}
              </>
            )}
          </>
        )}

        {tab === 'pasajero' && (
          bookings.length === 0
            ? <p className="empty">No tienes reservas todavía.</p>
            : bookings.map(b => (
              <Link to={`/trips/${b.trip_id}?tramo_origen=${encodeURIComponent(b.origen)}&tramo_destino=${encodeURIComponent(b.destino)}`} key={b.id} className="mytrip-card">
                {b.trip_estado === 'en_ruta' && b.estado === 'confirmada' && (
                  <div className="eta-banner-inline">
                    <span className="eta-icon">🚗</span>
                    <EtaBadge tripId={b.trip_id} origen={b.origen} />
                  </div>
                )}
                <div className="mytrip-route">
                  <span>{b.origen}</span>
                  <span className="trip-arrow">→</span>
                  <span>{b.destino}</span>
                </div>
                <div className="mytrip-meta">
                  <span>{formatDate(b.fecha)} · {formatTime(b.hora)}</span>
                  <span className={`status-badge status-${b.estado}`}>{b.estado}</span>
                </div>
                <div className="mytrip-stats">
                  <span>💶 {Number(b.precio_tramo ?? b.precio_asiento).toFixed(2)} €</span>
                  <span>🧑 {b.conductor_nombre}</span>
                </div>
              </Link>
            ))
        )}
      </div>
    </div>
  )
}

export default MyTrips
