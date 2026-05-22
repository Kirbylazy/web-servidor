import { useState, useEffect, useRef } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import { io } from 'socket.io-client'
import { api } from '../services/api'
import { createMap, addPolyline, decodePolyline, L } from '../utils/mapService'
import './LiveTrip.css'

const SOCKET_URL = import.meta.env.VITE_SOCKET_URL || import.meta.env.VITE_API_URL?.replace('/Zirpo/api', '') || 'http://localhost:3000'

const LiveTrip = () => {
  const { id } = useParams()
  const navigate = useNavigate()
  const mapRef = useRef(null)
  const mapInstanceRef = useRef(null)
  const markerRef = useRef(null)
  const socketRef = useRef(null)

  const [trip, setTrip] = useState(null)
  const [paradas, setParadas] = useState([])
  const [loading, setLoading] = useState(true)
  const [connected, setConnected] = useState(false)
  const [simRunning, setSimRunning] = useState(false)
  const [simPaused, setSimPaused] = useState(false)
  const [speed, setSpeed] = useState(10)
  const [progress, setProgress] = useState(0)
  const [etas, setEtas] = useState([])
  const [currentPos, setCurrentPos] = useState(null)
  const [completed, setCompleted] = useState(false)

  // Load trip data
  useEffect(() => {
    const fetchTrip = async () => {
      try {
        const data = await api.get(`/trips/${id}`)
        setTrip(data.trip)
        setParadas(data.paradas || [])
      } catch {
        navigate('/')
      } finally {
        setLoading(false)
      }
    }
    fetchTrip()
  }, [id])

  // Socket connection
  useEffect(() => {
    const socket = io(SOCKET_URL)
    socketRef.current = socket

    socket.on('connect', () => {
      setConnected(true)
      socket.emit('trip:join', parseInt(id))
    })

    socket.on('disconnect', () => setConnected(false))

    socket.on('simulation:started', () => {
      setSimRunning(true)
      setSimPaused(false)
      setCompleted(false)
    })

    socket.on('simulation:position', (data) => {
      setCurrentPos({ lat: data.lat, lng: data.lng })
      setProgress(data.progress)
      setEtas(data.etas)
    })

    socket.on('simulation:paused', () => setSimPaused(true))
    socket.on('simulation:resumed', () => setSimPaused(false))
    socket.on('simulation:speedChanged', (data) => setSpeed(data.speed))
    socket.on('simulation:stopped', () => {
      setSimRunning(false)
      setSimPaused(false)
      setProgress(0)
      setEtas([])
      setCurrentPos(null)
    })

    socket.on('simulation:complete', () => {
      setSimRunning(false)
      setCompleted(true)
    })

    socket.on('simulation:error', (data) => {
      alert('Error: ' + data.message)
    })

    return () => {
      socket.emit('trip:leave', parseInt(id))
      socket.disconnect()
    }
  }, [id])

  // Initialize map
  useEffect(() => {
    if (!trip?.ruta_polyline || !mapRef.current) return

    if (mapInstanceRef.current) {
      mapInstanceRef.current.remove()
      mapInstanceRef.current = null
    }

    const map = createMap(mapRef.current)
    mapInstanceRef.current = map

    // Draw route
    const pl = addPolyline(map, trip.ruta_polyline, '#94a3b8', 4)
    map.fitBounds(pl.getBounds(), { padding: [50, 50] })

    // Add parada markers
    paradas.forEach((p, i) => {
      const isFirst = i === 0
      const isLast = i === paradas.length - 1
      const color = isFirst ? '#16a34a' : isLast ? '#dc2626' : '#f59e0b'

      L.circleMarker([parseFloat(p.lat), parseFloat(p.lng)], {
        radius: 8,
        fillColor: color,
        color: '#fff',
        weight: 2,
        fillOpacity: 1,
      }).addTo(map).bindTooltip(p.ciudad, { permanent: false })
    })

    // Create driver marker (blue pulsing dot)
    const driverIcon = L.divIcon({
      className: 'driver-marker',
      iconSize: [20, 20],
      iconAnchor: [10, 10],
    })

    const path = decodePolyline(trip.ruta_polyline)
    if (path.length > 0) {
      markerRef.current = L.marker([path[0][0], path[0][1]], { icon: driverIcon }).addTo(map)
    }

    return () => {
      if (mapInstanceRef.current) {
        mapInstanceRef.current.remove()
        mapInstanceRef.current = null
        markerRef.current = null
      }
    }
  }, [trip, paradas])

  // Update driver marker position
  useEffect(() => {
    if (!currentPos || !markerRef.current || !mapInstanceRef.current) return
    markerRef.current.setLatLng([currentPos.lat, currentPos.lng])
    mapInstanceRef.current.panTo([currentPos.lat, currentPos.lng], { animate: true })
  }, [currentPos])

  // Controls
  const startSim = () => {
    socketRef.current?.emit('simulation:start', { tripId: parseInt(id), speed })
  }

  const pauseSim = () => {
    socketRef.current?.emit('simulation:pause', { tripId: parseInt(id) })
  }

  const resumeSim = () => {
    socketRef.current?.emit('simulation:resume', { tripId: parseInt(id) })
  }

  const stopSim = () => {
    socketRef.current?.emit('simulation:stop', { tripId: parseInt(id) })
  }

  const changeSpeed = (newSpeed) => {
    setSpeed(newSpeed)
    if (simRunning) {
      socketRef.current?.emit('simulation:speed', { tripId: parseInt(id), speed: newSpeed })
    }
  }

  if (loading) return <div className="live-page"><p className="loading">Cargando...</p></div>

  return (
    <div className="live-page">
      <div className="live-topbar">
        <Link to={`/trips/${id}`} className="back-link">← Volver al viaje</Link>
        <span className={`connection-badge ${connected ? 'connected' : ''}`}>
          {connected ? 'Conectado' : 'Desconectado'}
        </span>
      </div>

      <div className="live-content">
        {/* Map */}
        <div ref={mapRef} className="live-map" />

        {/* Route info header */}
        <div className="live-route-header">
          <span className="live-route-cities">{trip.origen} → {trip.destino}</span>
          {simRunning && (
            <span className="live-progress">{progress}%</span>
          )}
        </div>

        {/* Progress bar */}
        {simRunning && (
          <div className="progress-bar">
            <div className="progress-fill" style={{ width: `${progress}%` }} />
          </div>
        )}

        {/* ETA list */}
        {etas.length > 0 && (
          <div className="eta-list">
            {etas.map((eta, i) => (
              <div key={i} className={`eta-item ${eta.reached ? 'reached' : ''}`}>
                <div className={`eta-dot ${i === 0 ? 'dot-origin' : i === etas.length - 1 ? 'dot-dest' : 'dot-stop'}`} />
                <span className="eta-city">{eta.ciudad}</span>
                <span className="eta-time">
                  {eta.reached ? 'Llegado' : `${eta.etaMin} min · ${eta.distKm} km`}
                </span>
              </div>
            ))}
          </div>
        )}

        {/* Simulation controls */}
        <div className="sim-controls">
          <p className="sim-label">Simulación de viaje</p>

          {/* Speed selector */}
          <div className="speed-selector">
            <span className="speed-label">Velocidad:</span>
            {[1, 5, 10, 25, 50].map(s => (
              <button key={s}
                className={`speed-btn ${speed === s ? 'active' : ''}`}
                onClick={() => changeSpeed(s)}>
                {s}x
              </button>
            ))}
          </div>

          {/* Action buttons */}
          <div className="sim-actions">
            {!simRunning && !completed && (
              <button className="btn-start" onClick={startSim} disabled={!connected}>
                Iniciar simulación
              </button>
            )}

            {simRunning && !simPaused && (
              <>
                <button className="btn-pause" onClick={pauseSim}>Pausar</button>
                <button className="btn-stop" onClick={stopSim}>Detener</button>
              </>
            )}

            {simRunning && simPaused && (
              <>
                <button className="btn-resume" onClick={resumeSim}>Reanudar</button>
                <button className="btn-stop" onClick={stopSim}>Detener</button>
              </>
            )}

            {completed && (
              <div className="sim-completed">
                <p>Viaje completado</p>
                <button className="btn-start" onClick={startSim} disabled={!connected}>
                  Repetir simulación
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}

export default LiveTrip
