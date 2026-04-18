import { useState, useEffect, useRef } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { api } from '../services/api'
import CityAutocomplete from '../components/CityAutocomplete'
import { loadGoogleMapsScript } from '../utils/googleMaps'
import './TripForm.css'

// --- Utilities ---

function decodePolyline(encoded) {
  const points = []
  let index = 0, lat = 0, lng = 0
  while (index < encoded.length) {
    let b, shift = 0, result = 0
    do { b = encoded.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5 } while (b >= 0x20)
    lat += (result & 1) ? ~(result >> 1) : (result >> 1)
    shift = 0; result = 0
    do { b = encoded.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5 } while (b >= 0x20)
    lng += (result & 1) ? ~(result >> 1) : (result >> 1)
    points.push({ lat: lat / 1e5, lng: lng / 1e5 })
  }
  return points
}

function haversine(a, b) {
  const toRad = x => x * Math.PI / 180
  const dLat = toRad(b.lat - a.lat)
  const dLng = toRad(b.lng - a.lng)
  const h = Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2
  return 2 * 6371 * Math.asin(Math.sqrt(h))
}

function distributePrices(legs, generalPrice) {
  const totalKm = legs.reduce((s, l) => s + l.distanceKm, 0)
  if (!totalKm) return legs.map(() => '0')
  const prices = legs.map(leg => Math.round(generalPrice * (leg.distanceKm / totalKm) * 2) / 2)
  const sumRest = prices.slice(0, -1).reduce((s, p) => s + p, 0)
  prices[prices.length - 1] = Math.round((generalPrice - sumRest) * 2) / 2
  return prices.map(String)
}

const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''

// --- Component ---

const PublishTrip = () => {
  const navigate = useNavigate()
  const mapRef = useRef(null)
  const rendererRef = useRef(null)
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY

  const [step, setStep] = useState(1)
  const [origen, setOrigen] = useState('')
  const [destino, setDestino] = useState('')
  const [form, setForm] = useState({
    fecha: '', hora: '', asientos_totales: 1, descripcion: '', precio_general: ''
  })

  const [routeData, setRouteData] = useState(null)
  const [stops, setStops] = useState([])
  const [segmentPrices, setSegmentPrices] = useState([])
  const [suggestedCities, setSuggestedCities] = useState([])
  const [calculando, setCalculando] = useState(false)
  const [loadingSuggestions, setLoadingSuggestions] = useState(false)
  const [needsRecalc, setNeedsRecalc] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handle = e => setForm(prev => ({ ...prev, [e.target.name]: e.target.value }))
  const generalPrice = parseFloat(form.precio_general) || 0

  // --- Route calculation ---

  const calcRoute = async (waypoints) => {
    setCalculando(true)
    setError('')
    await loadGoogleMapsScript(apiKey)
    const service = new window.google.maps.DirectionsService()
    const wp = waypoints.filter(s => s.trim()).map(s => ({ location: `${s}, España`, stopover: true }))

    return new Promise((resolve, reject) => {
      service.route({
        origin: `${origen}, España`,
        destination: `${destino}, España`,
        waypoints: wp,
        travelMode: window.google.maps.TravelMode.DRIVING,
      }, (result, status) => {
        setCalculando(false)
        if (status !== 'OK') { setError('No se pudo calcular la ruta.'); reject(status); return }

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

        resolve({
          legs,
          polyline: result.routes[0].overview_polyline.points,
          totalDistanceKm: legs.reduce((s, l) => s + l.distanceKm, 0),
          totalDurationMin: legs.reduce((s, l) => s + l.durationMin, 0),
          directionsResult: result,
        })
      })
    })
  }

  const updateRoute = async (waypoints) => {
    try {
      const data = await calcRoute(waypoints)
      setRouteData(data)
      setSegmentPrices(distributePrices(data.legs, generalPrice))
      setNeedsRecalc(false)
    } catch { /* error already set */ }
  }

  // --- Suggested cities ---

  const findSuggestions = async (polyline, totalKm) => {
    setLoadingSuggestions(true)
    try {
      const points = decodePolyline(polyline)
      const samples = []
      let accum = 0, next = 100

      for (let i = 1; i < points.length; i++) {
        accum += haversine(points[i - 1], points[i])
        if (accum >= next && accum < totalKm - 50) {
          samples.push(points[i])
          next += 100
        }
      }

      const geocoder = new window.google.maps.Geocoder()
      const skip = [norm(origen), norm(destino)]
      const cities = []

      for (const pt of samples) {
        const results = await new Promise(r =>
          geocoder.geocode({ location: pt }, (res, st) => r(st === 'OK' ? res : []))
        )
        const loc = results.find(r => r.types.includes('locality'))
        const name = loc?.address_components.find(c => c.types.includes('locality'))?.long_name
        if (name && !skip.includes(norm(name)) && !cities.some(c => norm(c) === norm(name))) {
          cities.push(name)
        }
      }
      setSuggestedCities(cities)
    } catch {
      setSuggestedCities([])
    } finally {
      setLoadingSuggestions(false)
    }
  }

  // --- Step navigation ---

  const goToStep2 = async () => {
    if (!origen || !destino || !form.fecha || !form.hora || !form.precio_general) return
    setError('')
    setStops([])
    setSuggestedCities([])
    setNeedsRecalc(false)

    try {
      const data = await calcRoute([])
      setRouteData(data)
      setSegmentPrices(distributePrices(data.legs, generalPrice))
      setStep(2)
      findSuggestions(data.polyline, data.totalDistanceKm)
    } catch { /* error already set */ }
  }

  // --- Stop management ---

  const addSuggested = async (city) => {
    const next = [...stops, city]
    setStops(next)
    await updateRoute(next)
  }

  const removeStop = async (i) => {
    const next = stops.filter((_, idx) => idx !== i)
    setStops(next)
    await updateRoute(next.filter(s => s.trim()))
  }

  const addManual = () => { setStops(prev => [...prev, '']); setNeedsRecalc(true) }
  const updateStopVal = (i, val) => { setStops(prev => prev.map((s, idx) => idx === i ? val : s)); setNeedsRecalc(true) }

  // --- Map rendering ---

  useEffect(() => {
    if (!routeData || !mapRef.current || !window.google?.maps) return
    const map = new window.google.maps.Map(mapRef.current, { disableDefaultUI: true, zoomControl: true })
    if (rendererRef.current) rendererRef.current.setMap(null)
    rendererRef.current = new window.google.maps.DirectionsRenderer({ map })
    rendererRef.current.setDirections(routeData.directionsResult)
  }, [routeData])

  // --- Submit ---

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!routeData) return
    const prices = segmentPrices.map(p => parseFloat(p) || 0)
    if (prices.some(p => p <= 0)) { setError('Introduce un precio válido en cada tramo'); return }

    setError('')
    setLoading(true)
    try {
      let distAcum = 0, precioAcum = 0
      const paradas = routeData.legs.map((leg, i) => {
        const p = { ciudad: leg.start, lat: leg.startLat, lng: leg.startLng, orden: i, distancia_desde_origen_km: distAcum, precio_desde_origen: precioAcum }
        distAcum += leg.distanceKm
        precioAcum = Math.round((precioAcum + prices[i]) * 100) / 100
        return p
      })
      const last = routeData.legs.at(-1)
      paradas.push({ ciudad: last.end, lat: last.endLat, lng: last.endLng, orden: routeData.legs.length, distancia_desde_origen_km: distAcum, precio_desde_origen: precioAcum })

      const { precio_general, ...formData } = form
      const { trip } = await api.post('/trips', {
        ...formData,
        origen, destino,
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
  const availableSuggestions = suggestedCities.filter(c => !stops.some(s => norm(s) === norm(c)))

  return (
    <div className="tripform-page">
      <div className="tripform-card">
        <div className="tripform-topbar">
          {step === 1
            ? <Link to="/" className="back-link">← Volver al inicio</Link>
            : <button type="button" className="back-link-btn" onClick={() => setStep(1)}>← Volver al paso 1</button>
          }
        </div>

        {/* Step indicator */}
        <div className="step-indicator">
          <div className={`step-num ${step >= 1 ? 'active' : ''}`}>1</div>
          <div className={`step-line ${step >= 2 ? 'active' : ''}`} />
          <div className={`step-num ${step >= 2 ? 'active' : ''}`}>2</div>
        </div>

        {/* ========== STEP 1 ========== */}
        {step === 1 && (
          <>
            <h2>Publicar viaje</h2>

            <div className="route-section">
              <div className="route-stop">
                <div className="route-dot route-dot-origin" />
                <div className="form-group" style={{ flex: 1 }}>
                  <label>Origen</label>
                  <CityAutocomplete name="origen" value={origen}
                    onChange={e => setOrigen(e.target.value)} placeholder="Ciudad de salida" required />
                </div>
              </div>
              <div className="route-stop">
                <div className="route-dot route-dot-dest" />
                <div className="form-group" style={{ flex: 1 }}>
                  <label>Destino</label>
                  <CityAutocomplete name="destino" value={destino}
                    onChange={e => setDestino(e.target.value)} placeholder="Ciudad de llegada" required />
                </div>
              </div>
            </div>

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

            <div className="form-row">
              <div className="form-group">
                <label>Plazas disponibles</label>
                <input type="number" name="asientos_totales" min="1" max="8"
                  value={form.asientos_totales} onChange={handle} required />
              </div>
              <div className="form-group">
                <label>Precio por plaza (€)</label>
                <input type="number" name="precio_general" min="0" step="0.50"
                  value={form.precio_general} onChange={handle} placeholder="Ej: 25" required />
              </div>
            </div>

            <div className="form-group">
              <label>Descripción (opcional)</label>
              <input name="descripcion" value={form.descripcion} onChange={handle}
                placeholder="Preferencias, equipaje..." />
            </div>

            {error && <p className="msg-error">{error}</p>}

            <button type="button" className="btn-primary" onClick={goToStep2}
              disabled={!origen || !destino || !form.fecha || !form.hora || !form.precio_general || calculando}>
              {calculando ? 'Calculando ruta...' : 'Siguiente →'}
            </button>
          </>
        )}

        {/* ========== STEP 2 ========== */}
        {step === 2 && (
          <form onSubmit={handleSubmit}>
            <h2>Personaliza tu ruta</h2>

            {/* Map + summary */}
            {routeData && (
              <div className="route-preview">
                <div ref={mapRef} className="trip-map" />
                <div className="route-summary">
                  <span>{routeData.totalDistanceKm} km</span>
                  <span>{Math.floor(routeData.totalDurationMin / 60)}h {routeData.totalDurationMin % 60}min</span>
                  <span className="total-price">{totalPrice.toFixed(2)} € / plaza</span>
                </div>
              </div>
            )}

            {/* Suggested cities */}
            {(loadingSuggestions || availableSuggestions.length > 0) && (
              <div className="suggestions-section">
                <p className="suggestions-title">Ciudades de paso sugeridas</p>
                {loadingSuggestions ? (
                  <p className="suggestions-loading">Buscando ciudades en la ruta...</p>
                ) : (
                  <div className="suggestions-chips">
                    {availableSuggestions.map(city => (
                      <button key={city} type="button" className="suggestion-chip"
                        onClick={() => addSuggested(city)} disabled={calculando}>
                        + {city}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* Route stops */}
            <div className="route-section step2-stops">
              <div className="route-stop">
                <div className="route-dot route-dot-origin" />
                <span className="stop-city-label">{origen}</span>
              </div>

              {stops.map((stop, i) => (
                <div className="route-stop" key={i}>
                  <div className="route-dot route-dot-stop" />
                  {stop && suggestedCities.some(sc => norm(sc) === norm(stop)) ? (
                    <span className="stop-city-label">{stop}</span>
                  ) : (
                    <div className="form-group" style={{ flex: 1, marginBottom: 0 }}>
                      <CityAutocomplete name={`stop_${i}`} value={stop}
                        onChange={e => updateStopVal(i, e.target.value)} placeholder="Ciudad intermedia" />
                    </div>
                  )}
                  <button type="button" className="btn-remove-stop" onClick={() => removeStop(i)}>✕</button>
                </div>
              ))}

              <div className="route-stop">
                <div className="route-dot route-dot-dest" />
                <span className="stop-city-label">{destino}</span>
              </div>
            </div>

            <button type="button" className="btn-add-stop" onClick={addManual}>
              + Añadir parada manualmente
            </button>

            {needsRecalc && (
              <button type="button" className="btn-calculate" onClick={() => updateRoute(stops.filter(s => s.trim()))} disabled={calculando}>
                {calculando ? 'Recalculando...' : 'Actualizar ruta'}
              </button>
            )}

            {/* Segment prices */}
            {routeData && (
              <div className="segments-list step2-segments">
                <p className="segments-title">Precio por plaza en cada tramo</p>
                {routeData.legs.map((leg, i) => (
                  <div key={i} className="segment-row">
                    <div className="segment-info">
                      <span className="segment-cities">{leg.start} → {leg.end}</span>
                      <span className="segment-meta">{leg.distanceKm} km · {leg.durationMin} min</span>
                    </div>
                    <div className="segment-price-input">
                      <input type="number" min="0" step="0.5"
                        value={segmentPrices[i] ?? ''}
                        onChange={e => setSegmentPrices(prev => prev.map((p, idx) => idx === i ? e.target.value : p))}
                        placeholder="€" required />
                      <span>€</span>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {error && <p className="msg-error">{error}</p>}

            <button type="submit" className="btn-primary" disabled={loading || !routeData || calculando}
              style={{ marginTop: '1.25rem' }}>
              {loading ? 'Publicando...' : 'Publicar viaje'}
            </button>
          </form>
        )}
      </div>
    </div>
  )
}

export default PublishTrip
