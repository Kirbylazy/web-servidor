import { useState, useEffect, useRef } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { api } from '../services/api'
import CityAutocomplete from '../components/CityAutocomplete'
import { loadGoogleMapsScript } from '../utils/googleMaps'
import './TripForm.css'

// --- Utilities ---

function calcDefaultPrices(legs) {
  return legs.map(leg => {
    const price = leg.distanceKm * 0.19 / 3 * 0.6
    return String(Math.round(price * 2) / 2)
  })
}

const norm = s => s?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '') ?? ''

// --- Component ---

const PublishTrip = () => {
  const navigate = useNavigate()
  const mapRef = useRef(null)
  const rendererRef = useRef(null)
  const modalMapRef = useRef(null)
  const modalSearchRef = useRef(null)
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY

  const [step, setStep] = useState(1)
  const [origen, setOrigen] = useState('')
  const [destino, setDestino] = useState('')
  const [form, setForm] = useState({
    fecha: '', hora: '', asientos_totales: 1, descripcion: ''
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

  // Step 3
  const [pickupPoints, setPickupPoints] = useState({})
  const [pickupModalCity, setPickupModalCity] = useState(null)
  const [tempPickup, setTempPickup] = useState(null)
  const [loadingPickups, setLoadingPickups] = useState(false)

  const handle = e => setForm(prev => ({ ...prev, [e.target.name]: e.target.value }))

  // --- Route calculation ---

  const calcRoute = async (waypoints) => {
    setCalculando(true)
    setError('')
    await loadGoogleMapsScript(apiKey)
    const service = new window.google.maps.DirectionsService()
    const filledWaypoints = waypoints.filter(s => s.trim())
    const wp = filledWaypoints.map(s => ({ location: `${s}, España`, stopover: true }))

    return new Promise((resolve, reject) => {
      service.route({
        origin: `${origen}, España`,
        destination: `${destino}, España`,
        waypoints: wp,
        optimizeWaypoints: true,
        travelMode: window.google.maps.TravelMode.DRIVING,
      }, (result, status) => {
        setCalculando(false)
        if (status !== 'OK') { setError('No se pudo calcular la ruta.'); reject(status); return }

        // Reordenar paradas según el orden óptimo de Google
        const waypointOrder = result.routes[0].waypoint_order || []
        const reorderedStops = waypointOrder.map(i => filledWaypoints[i])
        const orderedCityNames = [origen, ...reorderedStops, destino]

        const legs = result.routes[0].legs.map((leg, i) => ({
          start: orderedCityNames[i],
          end: orderedCityNames[i + 1],
          startLat: leg.start_location.lat(),
          startLng: leg.start_location.lng(),
          endLat: leg.end_location.lat(),
          endLng: leg.end_location.lng(),
          distanceKm: Math.round(leg.distance.value / 1000),
          durationMin: Math.round(leg.duration.value / 60),
        }))

        resolve({
          legs,
          polyline: result.routes[0].overview_polyline,
          totalDistanceKm: legs.reduce((s, l) => s + l.distanceKm, 0),
          totalDurationMin: legs.reduce((s, l) => s + l.durationMin, 0),
          directionsResult: result,
          reorderedStops,
        })
      })
    })
  }

  const updateRoute = async (waypoints) => {
    try {
      const data = await calcRoute(waypoints)
      setRouteData(data)
      setSegmentPrices(calcDefaultPrices(data.legs))
      setStops(data.reorderedStops)
      setNeedsRecalc(false)
    } catch { /* error already set */ }
  }

  // --- Suggested cities ---

  const findSuggestions = async (directionsResult, totalKm) => {
    setLoadingSuggestions(true)
    try {
      const path = directionsResult.routes[0].overview_path
      const spherical = window.google.maps.geometry.spherical
      const samples = []
      const interval = Math.max(50, Math.min(100, totalKm / 6))
      let accum = 0, nextSample = interval

      for (let i = 1; i < path.length; i++) {
        accum += spherical.computeDistanceBetween(path[i - 1], path[i]) / 1000
        if (accum >= nextSample && accum < totalKm - 30) {
          samples.push(path[i])
          nextSample += interval
        }
      }

      if (!samples.length) { setSuggestedCities([]); return }

      const geocoder = new window.google.maps.Geocoder()
      const skip = [norm(origen), norm(destino)]
      const cities = []

      for (const pt of samples) {
        try {
          const results = await new Promise((resolve, reject) =>
            geocoder.geocode({ location: pt }, (res, status) => {
              if (status === 'OK' && res?.length) resolve(res)
              else reject(status)
            })
          )
          let cityName = null
          for (const result of results) {
            const comp = result.address_components?.find(c =>
              c.types.includes('locality') || c.types.includes('administrative_area_level_4')
            )
            if (comp) { cityName = comp.long_name; break }
          }
          if (cityName && !skip.includes(norm(cityName)) && !cities.some(c => norm(c) === norm(cityName))) {
            cities.push(cityName)
          }
        } catch (status) {
          console.warn('Geocoder failed:', status)
        }
      }
      setSuggestedCities(cities)
    } catch (err) {
      console.error('findSuggestions error:', err)
      setSuggestedCities([])
    } finally {
      setLoadingSuggestions(false)
    }
  }

  // --- Default pickup points ---

  const fetchDefaultPickups = async () => {
    setLoadingPickups(true)
    const cities = [origen, ...stops.filter(s => s.trim()), destino]
    const service = new window.google.maps.places.PlacesService(document.createElement('div'))
    const points = {}

    for (let i = 0; i < cities.length; i++) {
      const city = cities[i]
      if (pickupPoints[city]) { points[city] = pickupPoints[city]; continue }

      const leg = routeData.legs[Math.min(i, routeData.legs.length - 1)]
      const cityLatLng = i < routeData.legs.length
        ? { lat: leg.startLat, lng: leg.startLng }
        : { lat: leg.endLat, lng: leg.endLng }

      try {
        const result = await new Promise((resolve, reject) => {
          service.findPlaceFromQuery({
            query: `estación de autobuses ${city}`,
            fields: ['formatted_address', 'geometry', 'name'],
            locationBias: new window.google.maps.LatLng(cityLatLng.lat, cityLatLng.lng),
          }, (results, status) => {
            if (status === 'OK' && results?.length) resolve(results[0])
            else reject(status)
          })
        })
        points[city] = {
          address: result.formatted_address || result.name,
          lat: result.geometry.location.lat(),
          lng: result.geometry.location.lng(),
          name: result.name,
        }
      } catch {
        points[city] = {
          address: `Centro de ${city}`,
          lat: cityLatLng.lat, lng: cityLatLng.lng,
          name: `Centro de ${city}`,
        }
      }
    }

    setPickupPoints(points)
    setLoadingPickups(false)
  }

  // --- Step navigation ---

  const goToStep2 = async () => {
    if (!origen || !destino || !form.fecha || !form.hora) return
    setError('')
    setStops([])
    setSuggestedCities([])
    setNeedsRecalc(false)

    try {
      const data = await calcRoute([])
      setRouteData(data)
      setSegmentPrices(calcDefaultPrices(data.legs))
      setStep(2)
      findSuggestions(data.directionsResult, data.totalDistanceKm)
    } catch { /* error already set */ }
  }

  const goToStep3 = async () => {
    if (!routeData) return
    const prices = segmentPrices.map(p => parseFloat(p) || 0)
    if (prices.some(p => p <= 0)) { setError('Introduce un precio válido en cada tramo'); return }
    setError('')
    await fetchDefaultPickups()
    setStep(3)
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

  // --- Map rendering (step 2) ---

  useEffect(() => {
    if (!routeData || !mapRef.current || !window.google?.maps) return
    const map = new window.google.maps.Map(mapRef.current, { disableDefaultUI: true, zoomControl: true })
    if (rendererRef.current) rendererRef.current.setMap(null)
    rendererRef.current = new window.google.maps.DirectionsRenderer({ map })
    rendererRef.current.setDirections(routeData.directionsResult)
  }, [routeData])

  // --- Pickup modal map ---

  useEffect(() => {
    if (!pickupModalCity || !modalMapRef.current || !window.google?.maps) return

    const point = pickupPoints[pickupModalCity]
    if (!point) return
    const center = { lat: point.lat, lng: point.lng }
    setTempPickup(point)

    const map = new window.google.maps.Map(modalMapRef.current, {
      center, zoom: 15, disableDefaultUI: true, zoomControl: true,
    })

    const marker = new window.google.maps.Marker({ map, position: center, draggable: true })
    const geocoder = new window.google.maps.Geocoder()

    const updateFromLatLng = (latLng) => {
      marker.setPosition(latLng)
      map.panTo(latLng)
      geocoder.geocode({ location: latLng }, (results, status) => {
        const addr = status === 'OK' && results?.[0] ? results[0].formatted_address : ''
        setTempPickup({ address: addr, lat: latLng.lat(), lng: latLng.lng(), name: addr })
      })
    }

    map.addListener('click', e => updateFromLatLng(e.latLng))
    marker.addListener('dragend', e => updateFromLatLng(e.latLng))

    if (modalSearchRef.current) {
      const autocomplete = new window.google.maps.places.Autocomplete(modalSearchRef.current, {
        componentRestrictions: { country: 'es' },
        fields: ['formatted_address', 'geometry', 'name'],
      })
      autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace()
        if (place.geometry) {
          const loc = place.geometry.location
          marker.setPosition(loc)
          map.setCenter(loc)
          map.setZoom(16)
          setTempPickup({
            address: place.formatted_address || place.name,
            lat: loc.lat(), lng: loc.lng(),
            name: place.name || place.formatted_address,
          })
        }
      })
    }
  }, [pickupModalCity])

  // --- Submit ---

  const handleSubmit = async () => {
    if (!routeData) return
    const prices = segmentPrices.map(p => parseFloat(p) || 0)
    const cities = [origen, ...stops.filter(s => s.trim()), destino]

    setError('')
    setLoading(true)
    try {
      let distAcum = 0, precioAcum = 0
      const paradas = routeData.legs.map((leg, i) => {
        const city = cities[i]
        const pp = pickupPoints[city]
        const p = {
          ciudad: leg.start,
          lat: pp?.lat ?? leg.startLat,
          lng: pp?.lng ?? leg.startLng,
          pickup_address: pp?.address ?? null,
          orden: i,
          distancia_desde_origen_km: distAcum,
          precio_desde_origen: precioAcum,
        }
        distAcum += leg.distanceKm
        precioAcum = Math.round((precioAcum + prices[i]) * 100) / 100
        return p
      })
      const last = routeData.legs.at(-1)
      const lastCity = cities[cities.length - 1]
      const lastPp = pickupPoints[lastCity]
      paradas.push({
        ciudad: last.end,
        lat: lastPp?.lat ?? last.endLat,
        lng: lastPp?.lng ?? last.endLng,
        pickup_address: lastPp?.address ?? null,
        orden: routeData.legs.length,
        distancia_desde_origen_km: distAcum,
        precio_desde_origen: precioAcum,
      })

      const { trip } = await api.post('/trips', {
        ...form,
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
  const allCities = [origen, ...stops.filter(s => s.trim()), destino]

  return (
    <div className="tripform-page">
      <div className="tripform-card">
        <div className="tripform-topbar">
          {step === 1
            ? <Link to="/" className="back-link">← Volver al inicio</Link>
            : <button type="button" className="back-link-btn" onClick={() => setStep(step - 1)}>← Volver al paso {step - 1}</button>
          }
        </div>

        {/* Step indicator */}
        <div className="step-indicator">
          <div className={`step-num ${step >= 1 ? 'active' : ''}`}>1</div>
          <div className={`step-line ${step >= 2 ? 'active' : ''}`} />
          <div className={`step-num ${step >= 2 ? 'active' : ''}`}>2</div>
          <div className={`step-line ${step >= 3 ? 'active' : ''}`} />
          <div className={`step-num ${step >= 3 ? 'active' : ''}`}>3</div>
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

            <button type="button" className="btn-primary" onClick={goToStep2}
              disabled={!origen || !destino || !form.fecha || !form.hora || calculando}>
              {calculando ? 'Calculando ruta...' : 'Siguiente →'}
            </button>
          </>
        )}

        {/* ========== STEP 2 ========== */}
        {step === 2 && (
          <>
            <h2>Personaliza tu ruta</h2>

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

            <button type="button" className="btn-primary" onClick={goToStep3}
              disabled={!routeData || calculando} style={{ marginTop: '1.25rem' }}>
              Siguiente →
            </button>
          </>
        )}

        {/* ========== STEP 3 ========== */}
        {step === 3 && (
          <>
            <h2>Puntos de recogida</h2>
            <p className="pickup-subtitle">Indica dónde recoges o dejas pasajeros en cada ciudad</p>

            {loadingPickups ? (
              <p className="suggestions-loading">Buscando puntos sugeridos...</p>
            ) : (
              <div className="pickup-list">
                {allCities.map((city, i) => (
                  <div key={city} className="pickup-row">
                    <div className="pickup-city-header">
                      <div className={`route-dot ${i === 0 ? 'route-dot-origin' : i === allCities.length - 1 ? 'route-dot-dest' : 'route-dot-stop'}`}
                        style={{ margin: 0 }} />
                      <span className="pickup-city-name">{city}</span>
                    </div>
                    <div className="pickup-point-info">
                      <span className="pickup-address">
                        {pickupPoints[city]?.name || pickupPoints[city]?.address || 'Sin definir'}
                      </span>
                      <button type="button" className="btn-edit-pickup"
                        onClick={() => setPickupModalCity(city)}>
                        Cambiar
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {error && <p className="msg-error">{error}</p>}

            <button type="button" className="btn-primary" onClick={handleSubmit}
              disabled={loading} style={{ marginTop: '0.5rem' }}>
              {loading ? 'Publicando...' : 'Publicar viaje'}
            </button>
          </>
        )}
      </div>

      {/* ========== PICKUP MAP MODAL ========== */}
      {pickupModalCity && (
        <div className="pickup-modal-overlay" onClick={() => setPickupModalCity(null)}>
          <div className="pickup-modal" onClick={e => e.stopPropagation()}>
            <div className="pickup-modal-header">
              <h3>Punto en {pickupModalCity}</h3>
              <button type="button" className="btn-close-modal" onClick={() => setPickupModalCity(null)}>✕</button>
            </div>

            <div className="pickup-modal-search">
              <input ref={modalSearchRef} placeholder="Buscar dirección..." />
            </div>

            <div ref={modalMapRef} className="pickup-modal-map" />

            <div className="pickup-modal-selected">
              {tempPickup?.address || 'Toca el mapa o busca una dirección'}
            </div>

            <button type="button" className="btn-primary" onClick={() => {
              if (tempPickup) {
                setPickupPoints(prev => ({ ...prev, [pickupModalCity]: tempPickup }))
              }
              setPickupModalCity(null)
            }}>
              Confirmar punto
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

export default PublishTrip
