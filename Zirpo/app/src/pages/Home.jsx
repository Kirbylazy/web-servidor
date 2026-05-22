import { Link, useNavigate } from 'react-router-dom'
import { useState, useEffect, useRef } from 'react'
import { io } from 'socket.io-client'
import { api } from '../services/api'
import { useAuth } from '../context/AuthContext'
import CityAutocomplete from '../components/CityAutocomplete'
import './Home.css'

const SOCKET_URL = import.meta.env.VITE_SOCKET_URL || import.meta.env.VITE_API_URL?.replace('/Zirpo/api', '') || 'http://localhost:3000'
const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''

function ActiveTripBanner({ booking }) {
  const [eta, setEta] = useState(null)
  const socketRef = useRef(null)

  const fmtEta = (m) => {
    const h = Math.floor(m / 60)
    const mins = m % 60
    return h > 0 ? `${h}h ${mins}min` : `${mins}min`
  }

  useEffect(() => {
    if (booking.trip_estado !== 'en_ruta') return

    const socket = io(SOCKET_URL)
    socketRef.current = socket

    socket.on('connect', () => {
      socket.emit('trip:join', booking.trip_id)
    })

    socket.on('simulation:position', (data) => {
      if (!data.etas?.length) return
      const nOrigen = norm(booking.origen)
      const match = data.etas.find(e =>
        norm(e.ciudad).includes(nOrigen) || nOrigen.includes(norm(e.ciudad))
      )
      if (match) {
        setEta(match.reached ? 0 : match.etaMin)
      }
    })

    socket.on('simulation:complete', () => setEta(0))

    return () => {
      socket.emit('trip:leave', booking.trip_id)
      socket.disconnect()
    }
  }, [booking.trip_id, booking.origen, booking.trip_estado])

  const isEnRuta = booking.trip_estado === 'en_ruta'

  return (
    <Link to={`/trips/${booking.trip_id}?tramo_origen=${encodeURIComponent(booking.origen)}&tramo_destino=${encodeURIComponent(booking.destino)}`}
      className="active-trip-banner">
      <div className="active-trip-route">
        <span className="active-trip-cities">{booking.origen} → {booking.destino}</span>
        <span className="active-trip-conductor">con {booking.conductor_nombre}</span>
      </div>
      <div className="active-trip-eta">
        {isEnRuta ? (
          eta === null ? <span className="eta-badge-pulse">En ruta...</span>
          : eta === 0 ? <span className="eta-badge-arrived">Ha llegado</span>
          : <span className="eta-badge-pulse">{fmtEta(eta)}</span>
        ) : (
          <span className="eta-badge-pending">
            {booking.estado === 'pendiente' ? 'Pendiente' : 'Confirmada'}
          </span>
        )}
      </div>
    </Link>
  )
}

const Home = () => {
  const navigate = useNavigate()
  const { user } = useAuth()
  const [search, setSearch] = useState({ origen: '', destino: '', fecha: '' })
  const [activeBookings, setActiveBookings] = useState([])

  useEffect(() => {
    if (!user) return
    api.get('/bookings').then(data => {
      const active = (data.bookings || []).filter(b =>
        b.estado !== 'cancelada' && (b.trip_estado === 'activo' || b.trip_estado === 'en_ruta')
      )
      setActiveBookings(active)
    }).catch(() => {})
  }, [user])

  const handleSearch = (e) => {
    e.preventDefault()
    const params = new URLSearchParams(search).toString()
    navigate(`/search?${params}`)
  }

  return (
    <div className="home-container">
      <main className="home-main">
        {activeBookings.length > 0 && (
          <section className="active-trips-section">
            <h3 className="active-trips-title">Tus viajes activos</h3>
            {activeBookings.map(b => (
              <ActiveTripBanner key={b.id} booking={b} />
            ))}
          </section>
        )}

        <section className="hero">
          <h2>¿A dónde vas?</h2>
          <form className="search-form" onSubmit={handleSearch}>
            <CityAutocomplete
              name="origen"
              value={search.origen}
              placeholder="Origen"
              onChange={e => { const v = e.target.value; setSearch(prev => ({ ...prev, origen: v })) }}
            />
            <CityAutocomplete
              name="destino"
              value={search.destino}
              placeholder="Destino"
              onChange={e => { const v = e.target.value; setSearch(prev => ({ ...prev, destino: v })) }}
            />
            <input
              type="date"
              value={search.fecha}
              onChange={e => setSearch(prev => ({ ...prev, fecha: e.target.value }))}
            />
            <button type="submit" className="btn-primary">Buscar</button>
          </form>
        </section>

        <section className="actions">
          <Link to="/publish" className="action-card action-publish">
            <span className="action-icon">🚗</span>
            <strong>Publicar viaje</strong>
            <span>Ofrece plazas en tu coche</span>
          </Link>
          <Link to="/my-trips" className="action-card action-mytrips">
            <span className="action-icon">📋</span>
            <strong>Mis viajes</strong>
            <span>Gestiona tus viajes y reservas</span>
          </Link>
        </section>
      </main>
    </div>
  )
}

export default Home
