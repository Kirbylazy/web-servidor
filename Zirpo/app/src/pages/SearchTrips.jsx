import { useState, useEffect } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { api } from '../services/api'
import './SearchTrips.css'

const SearchTrips = () => {
  const [searchParams, setSearchParams] = useSearchParams()
  const [form, setForm] = useState({
    origen: searchParams.get('origen') || '',
    destino: searchParams.get('destino') || '',
    fecha: searchParams.get('fecha') || ''
  })
  const [trips, setTrips] = useState([])
  const [loading, setLoading] = useState(false)
  const [searched, setSearched] = useState(false)

  useEffect(() => {
    if (searchParams.get('origen') || searchParams.get('destino') || searchParams.get('fecha')) {
      fetchTrips()
    }
  }, [])

  const fetchTrips = async (params = form) => {
    setLoading(true)
    setSearched(true)
    try {
      const query = new URLSearchParams(
        Object.fromEntries(Object.entries(params).filter(([, v]) => v))
      ).toString()
      const { trips } = await api.get(`/trips${query ? '?' + query : ''}`)
      setTrips(trips)
    } catch {
      setTrips([])
    } finally {
      setLoading(false)
    }
  }

  const handleSubmit = e => {
    e.preventDefault()
    setSearchParams(Object.fromEntries(Object.entries(form).filter(([, v]) => v)))
    fetchTrips()
  }

  const formatDate = d => new Date(d).toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' })
  const formatTime = t => t.slice(0, 5)

  return (
    <div className="search-page">
      <div className="search-topbar">
        <Link to="/" className="back-link">← Inicio</Link>
        <h2>Buscar viajes</h2>
      </div>

      <form className="search-filters" onSubmit={handleSubmit}>
        <input placeholder="Origen" value={form.origen}
          onChange={e => setForm({ ...form, origen: e.target.value })} />
        <input placeholder="Destino" value={form.destino}
          onChange={e => setForm({ ...form, destino: e.target.value })} />
        <input type="date" value={form.fecha}
          onChange={e => setForm({ ...form, fecha: e.target.value })} />
        <button type="submit" className="btn-search">Buscar</button>
      </form>

      <div className="search-results">
        {loading && <p className="search-status">Buscando...</p>}

        {!loading && searched && trips.length === 0 && (
          <p className="search-status">No hay viajes disponibles con esos criterios.</p>
        )}

        {trips.map(trip => (
          <Link to={`/trips/${trip.id}`} key={trip.id} className="trip-card">
            <div className="trip-card-header">
              <div className="trip-route">
                <span className="trip-city">{trip.origen}</span>
                <span className="trip-arrow">→</span>
                <span className="trip-city">{trip.destino}</span>
              </div>
              <span className="trip-price">{Number(trip.precio_asiento).toFixed(2)} €</span>
            </div>
            <div className="trip-card-meta">
              <span>{formatDate(trip.fecha)} · {formatTime(trip.hora)}</span>
              <span>{trip.asientos_disponibles} plaza{trip.asientos_disponibles !== 1 ? 's' : ''}</span>
            </div>
            <div className="trip-card-driver">
              {trip.conductor_foto
                ? <img src={trip.conductor_foto} alt="" className="driver-avatar" />
                : <div className="driver-avatar-placeholder">{trip.conductor_nombre?.[0]}</div>
              }
              <span>{trip.conductor_nombre} {trip.conductor_apellidos}</span>
              {trip.marca && <span className="trip-car">· {trip.marca} {trip.modelo}</span>}
            </div>
          </Link>
        ))}
      </div>
    </div>
  )
}

export default SearchTrips
