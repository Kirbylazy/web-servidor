import { useState, useEffect } from 'react'
import { useSearchParams, Link } from 'react-router-dom'
import { api } from '../services/api'
import CityAutocomplete from '../components/CityAutocomplete'
import './SearchTrips.css'

// Normaliza texto: minúsculas y sin acentos para comparar
const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''

// Calcula el tramo relevante de un viaje según la búsqueda
function getPickupTime(trip, parada) {
  if (!trip.hora || !trip.duracion_min || !trip.distancia_km) return trip.hora
  const distKm = parseFloat(parada.distancia_desde_origen_km) || 0
  const fraction = trip.distancia_km > 0 ? distKm / trip.distancia_km : 0
  const etaMin = Math.round(trip.duracion_min * fraction)
  const [h, m] = trip.hora.split(':').map(Number)
  const totalMin = h * 60 + m + etaMin
  const hh = String(Math.floor(totalMin / 60) % 24).padStart(2, '0')
  const mm = String(totalMin % 60).padStart(2, '0')
  return `${hh}:${mm}`
}

function getTramo(trip, searchOrigen, searchDestino) {
  const paradas = trip.paradas || []
  if (!paradas.length || !searchOrigen || !searchDestino) {
    return { origen: trip.origen, destino: trip.destino, precio: trip.precio_asiento, plazas: trip.asientos_disponibles, hora: trip.hora }
  }

  const nOrigen = norm(searchOrigen)
  const nDestino = norm(searchDestino)

  const po = paradas.find(p => norm(p.ciudad).includes(nOrigen))
  const pd = paradas.find(p => norm(p.ciudad).includes(nDestino))

  if (po && pd && po.orden < pd.orden) {
    let minPlazas = trip.asientos_totales
    for (let i = po.orden; i < pd.orden; i++) {
      const seg = paradas.find(p => p.orden === i)
      if (seg?.asientos_disponibles_segmento !== undefined && seg.asientos_disponibles_segmento < minPlazas) {
        minPlazas = seg.asientos_disponibles_segmento
      }
    }
    return {
      origen: po.ciudad,
      destino: pd.ciudad,
      precio: parseFloat(pd.precio_desde_origen) - parseFloat(po.precio_desde_origen),
      plazas: minPlazas,
      hora: getPickupTime(trip, po)
    }
  }

  return { origen: trip.origen, destino: trip.destino, precio: trip.precio_asiento, plazas: trip.asientos_disponibles, hora: trip.hora }
}

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

  // Refresh results when coming back to this page (e.g. after booking)
  useEffect(() => {
    const handleFocus = () => {
      if (searched) fetchTrips()
    }
    window.addEventListener('focus', handleFocus)
    return () => window.removeEventListener('focus', handleFocus)
  }, [searched, form])

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
        <CityAutocomplete name="origen" value={form.origen} placeholder="Origen"
          onChange={e => { const v = e.target.value; setForm(prev => ({ ...prev, origen: v })) }} />
        <CityAutocomplete name="destino" value={form.destino} placeholder="Destino"
          onChange={e => { const v = e.target.value; setForm(prev => ({ ...prev, destino: v })) }} />
        <input type="date" value={form.fecha}
          onChange={e => setForm(prev => ({ ...prev, fecha: e.target.value })) } />
        <button type="submit" className="btn-search">Buscar</button>
      </form>

      <div className="search-results">
        {loading && <p className="search-status">Buscando...</p>}

        {!loading && searched && trips.length === 0 && (
          <p className="search-status">No hay viajes disponibles con esos criterios.</p>
        )}

        {trips.map(trip => {
          const tramo = getTramo(trip, form.origen, form.destino)
          return (
            <Link to={`/trips/${trip.id}?tramo_origen=${encodeURIComponent(tramo.origen)}&tramo_destino=${encodeURIComponent(tramo.destino)}`} key={trip.id} className="trip-card">
              <div className="trip-card-header">
                <div className="trip-route">
                  <span className="trip-city">{tramo.origen}</span>
                  <span className="trip-arrow">→</span>
                  <span className="trip-city">{tramo.destino}</span>
                </div>
                <span className="trip-price">{Number(tramo.precio).toFixed(2)} €</span>
              </div>
              <div className="trip-card-meta">
                <span>{formatDate(trip.fecha)} · {formatTime(tramo.hora)}</span>
                <span>{tramo.plazas} plaza{tramo.plazas !== 1 ? 's' : ''}</span>
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
          )
        })}
      </div>
    </div>
  )
}

export default SearchTrips
