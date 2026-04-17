import { useState, useEffect, useRef } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { api } from '../services/api'
import CityAutocomplete from '../components/CityAutocomplete'
import { loadGoogleMapsScript } from '../utils/googleMaps'
import './TripForm.css'

const PublishTrip = () => {
  const navigate = useNavigate()
  const mapRef = useRef(null)
  const rendererRef = useRef(null)
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY

  const [form, setForm] = useState({ fecha: '', hora: '', asientos_totales: 1, descripcion: '' })
  const [origen, setOrigen] = useState('')
  const [destino, setDestino] = useState('')
  const [stops, setStops] = useState([])       // ciudades intermedias
  const [routeData, setRouteData] = useState(null)
  const [segmentPrices, setSegmentPrices] = useState([])
  const [calculando, setCalculando] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handle = e => {
    const { name, value } = e.target
    setForm(prev => ({ ...prev, [name]: value }))
  }

  const addStop = () => setStops(prev => [...prev, ''])
  const removeStop = i => setStops(prev => prev.filter((_, idx) => idx !== i))
  const updateStop = (i, val) => setStops(prev => prev.map((s, idx) => idx === i ? val : s))

  // Reset ruta si cambian las ciudades
  useEffect(() => { setRouteData(null) }, [origen, destino, stops.join(',')])

  const calcularRuta = async () => {
    if (!origen || !destino) return
    setCalculando(true)
    setError('')

    try {
      await loadGoogleMapsScript(apiKey)
      const service = new window.google.maps.DirectionsService()

      const waypoints = stops
        .filter(s => s.trim())
        .map(s => ({ location: `${s}, España`, stopover: true }))

      service.route({
        origin: `${origen}, España`,
        destination: `${destino}, España`,
        waypoints,
        travelMode: window.google.maps.TravelMode.DRIVING,
      }, (result, status) => {
        setCalculando(false)
        if (status !== 'OK') {
          setError('No se pudo calcular la ruta. Verifica las ciudades.')
          return
        }

        const legs = result.routes[0].legs.map(leg => ({
          start: leg.start_address.split(',')[0].trim(),
          end: leg.end_address.split(',')[0].trim(),
          startLat: leg.start_location.lat(),
          startLng: leg.start_location.lng(),
          endLat: leg.end_location.lat(),
          endLng: leg.end_location.lng(),
          distanceKm: Math.round(leg.distance.value / 1000),
          durationMin: Math.round(leg.duration.value / 60),
        }))

        setRouteData({
          legs,
          polyline: result.routes[0].overview_polyline.points,
          totalDistanceKm: legs.reduce((s, l) => s + l.distanceKm, 0),
          totalDurationMin: legs.reduce((s, l) => s + l.durationMin, 0),
          directionsResult: result,
        })

        // Precio sugerido por DGT por tramo
        const seats = parseInt(form.asientos_totales) || 1
        setSegmentPrices(legs.map(leg =>
          String(Math.round(leg.distanceKm * 0.19 / seats * 2) / 2)
        ))
      })
    } catch {
      setCalculando(false)
      setError('Error al cargar Google Maps.')
    }
  }

  // Renderizar mapa cuando llega routeData
  useEffect(() => {
    if (!routeData || !mapRef.current) return
    if (!window.google?.maps) return

    const map = new window.google.maps.Map(mapRef.current, {
      disableDefaultUI: true,
      zoomControl: true,
    })

    if (rendererRef.current) rendererRef.current.setMap(null)
    rendererRef.current = new window.google.maps.DirectionsRenderer({ map })
    rendererRef.current.setDirections(routeData.directionsResult)
  }, [routeData])

  const handleSubmit = async e => {
    e.preventDefault()
    if (!routeData) { setError('Calcula la ruta antes de publicar'); return }

    const prices = segmentPrices.map(p => parseFloat(p) || 0)
    if (prices.some(p => p <= 0)) { setError('Introduce un precio válido en cada tramo'); return }

    setError('')
    setLoading(true)

    try {
      // Construir array de paradas con precio acumulado
      let distAcum = 0
      let precioAcum = 0
      const paradas = routeData.legs.map((leg, i) => {
        const p = { ciudad: leg.start, lat: leg.startLat, lng: leg.startLng, orden: i, distancia_desde_origen_km: distAcum, precio_desde_origen: precioAcum }
        distAcum += leg.distanceKm
        precioAcum = Math.round((precioAcum + prices[i]) * 100) / 100
        return p
      })
      const last = routeData.legs[routeData.legs.length - 1]
      paradas.push({ ciudad: last.end, lat: last.endLat, lng: last.endLng, orden: routeData.legs.length, distancia_desde_origen_km: distAcum, precio_desde_origen: precioAcum })

      const { trip } = await api.post('/trips', {
        ...form,
        origen,
        destino,
        precio_asiento: precioAcum,
        paradas,
        ruta_polyline: routeData.polyline,
        distancia_km: routeData.totalDistanceKm,
        duracion_min: routeData.totalDurationMin,
        origen_lat: routeData.legs[0].startLat,
        origen_lng: routeData.legs[0].startLng,
        destino_lat: last.endLat,
        destino_lng: last.endLng,
      })

      navigate(`/trips/${trip.id}`)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const totalPrice = segmentPrices.reduce((s, p) => s + (parseFloat(p) || 0), 0)
  const allCitiesFilled = origen && destino

  return (
    <div className="tripform-page">
      <div className="tripform-card">
        <div className="tripform-topbar">
          <Link to="/" className="back-link">← Volver al inicio</Link>
        </div>
        <h2>Publicar viaje</h2>

        <form onSubmit={handleSubmit}>

          {/* Ruta */}
          <div className="route-section">
            <div className="route-stop">
              <div className="route-dot route-dot-origin" />
              <div className="form-group" style={{ flex: 1 }}>
                <label>Origen</label>
                <CityAutocomplete name="origen" value={origen}
                  onChange={e => setOrigen(e.target.value)} placeholder="Ciudad de salida" required />
              </div>
            </div>

            {stops.map((stop, i) => (
              <div className="route-stop" key={i}>
                <div className="route-dot route-dot-stop" />
                <div className="form-group" style={{ flex: 1 }}>
                  <label>Parada {i + 1}</label>
                  <CityAutocomplete name={`stop_${i}`} value={stop}
                    onChange={e => updateStop(i, e.target.value)} placeholder="Ciudad intermedia" />
                </div>
                <button type="button" className="btn-remove-stop" onClick={() => removeStop(i)}>✕</button>
              </div>
            ))}

            <button type="button" className="btn-add-stop" onClick={addStop}>
              + Añadir parada intermedia
            </button>

            <div className="route-stop">
              <div className="route-dot route-dot-dest" />
              <div className="form-group" style={{ flex: 1 }}>
                <label>Destino</label>
                <CityAutocomplete name="destino" value={destino}
                  onChange={e => setDestino(e.target.value)} placeholder="Ciudad de llegada" required />
              </div>
            </div>
          </div>

          {/* Botón calcular ruta */}
          {allCitiesFilled && !routeData && (
            <button type="button" className="btn-calculate" onClick={calcularRuta} disabled={calculando}>
              {calculando ? 'Calculando ruta...' : 'Calcular ruta'}
            </button>
          )}

          {/* Preview de ruta */}
          {routeData && (
            <div className="route-preview">
              <div ref={mapRef} className="trip-map" />

              <div className="route-summary">
                <span>{routeData.totalDistanceKm} km</span>
                <span>{Math.floor(routeData.totalDurationMin / 60)}h {routeData.totalDurationMin % 60}min</span>
                {totalPrice > 0 && <span className="total-price">{totalPrice.toFixed(2)} € total</span>}
              </div>

              <div className="segments-list">
                <p className="segments-title">Precio por plaza en cada tramo</p>
                {routeData.legs.map((leg, i) => (
                  <div key={i} className="segment-row">
                    <div className="segment-info">
                      <span className="segment-cities">{leg.start} → {leg.end}</span>
                      <span className="segment-meta">{leg.distanceKm} km · {leg.durationMin} min</span>
                    </div>
                    <div className="segment-price-input">
                      <input
                        type="number" min="0" step="0.5"
                        value={segmentPrices[i] ?? ''}
                        onChange={e => setSegmentPrices(prev => prev.map((p, idx) => idx === i ? e.target.value : p))}
                        placeholder="€"
                        required
                      />
                      <span>€</span>
                    </div>
                  </div>
                ))}
              </div>

              <button type="button" className="btn-recalculate" onClick={calcularRuta}>
                Recalcular ruta
              </button>
            </div>
          )}

          {/* Fecha, hora, plazas */}
          <div className="form-row">
            <div className="form-group">
              <label>Fecha</label>
              <input type="date" name="fecha" value={form.fecha} onChange={handle} required />
            </div>
            <div className="form-group">
              <label>Hora de salida</label>
              <input type="time" name="hora" value={form.hora} onChange={handle} required />
            </div>
          </div>

          <div className="form-group">
            <label>Plazas disponibles</label>
            <input type="number" name="asientos_totales" min="1" max="8"
              value={form.asientos_totales} onChange={handle} required />
          </div>

          <div className="form-group">
            <label>Descripción (opcional)</label>
            <input name="descripcion" value={form.descripcion} onChange={handle}
              placeholder="Preferencias, equipaje..." />
          </div>

          {error && <p className="msg-error">{error}</p>}

          <button type="submit" className="btn-primary" disabled={loading || !routeData}>
            {loading ? 'Publicando...' : 'Publicar viaje'}
          </button>
        </form>
      </div>
    </div>
  )
}

export default PublishTrip
