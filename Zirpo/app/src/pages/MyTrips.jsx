import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../services/api'
import './MyTrips.css'

const MyTrips = () => {
  const [trips, setTrips] = useState([])
  const [bookings, setBookings] = useState([])
  const [tab, setTab] = useState('conductor')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    Promise.all([
      api.get('/trips/my'),
      api.get('/bookings')
    ]).then(([tripsData, bookingsData]) => {
      setTrips(tripsData.trips)
      setBookings(bookingsData.bookings)
    }).finally(() => setLoading(false))
  }, [])

  const formatDate = d => new Date(d).toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })
  const formatTime = t => t.slice(0, 5)

  if (loading) return (
    <div className="mytrips-page">
      <div className="mytrips-topbar"><Link to="/" className="back-link">← Inicio</Link></div>
      <p className="loading">Cargando...</p>
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
              ? <p className="empty">No has publicado ningún viaje todavía.</p>
              : trips.map(trip => (
                <Link to={`/trips/${trip.id}`} key={trip.id} className="mytrip-card">
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
              ))
            }
          </>
        )}

        {tab === 'pasajero' && (
          bookings.length === 0
            ? <p className="empty">No tienes reservas todavía.</p>
            : bookings.map(b => (
              <Link to={`/trips/${b.trip_id}`} key={b.id} className="mytrip-card">
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
                  <span>💶 {Number(b.precio_asiento).toFixed(2)} €</span>
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
