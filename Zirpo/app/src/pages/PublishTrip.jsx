import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { api } from '../services/api'
import CityAutocomplete from '../components/CityAutocomplete'
import './TripForm.css'

const PublishTrip = () => {
  const navigate = useNavigate()
  const [form, setForm] = useState({
    origen: '', destino: '', fecha: '', hora: '',
    asientos_totales: 1, precio_asiento: '', descripcion: ''
  })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handle = e => setForm({ ...form, [e.target.name]: e.target.value })

  const handleSubmit = async e => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const { trip } = await api.post('/trips', form)
      navigate(`/trips/${trip.id}`)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="tripform-page">
      <div className="tripform-card">
        <div className="tripform-topbar">
          <Link to="/" className="back-link">← Volver al inicio</Link>
        </div>
        <h2>Publicar viaje</h2>

        <form onSubmit={handleSubmit}>
          <div className="form-row">
            <div className="form-group">
              <label>Origen</label>
              <CityAutocomplete name="origen" value={form.origen} onChange={handle} placeholder="Ciudad de salida" required />
            </div>
            <div className="form-group">
              <label>Destino</label>
              <CityAutocomplete name="destino" value={form.destino} onChange={handle} placeholder="Ciudad de llegada" required />
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
              <input type="number" name="precio_asiento" min="0" step="0.5"
                value={form.precio_asiento} onChange={handle} placeholder="0.00" required />
            </div>
          </div>

          <div className="form-group">
            <label>Descripción (opcional)</label>
            <input name="descripcion" value={form.descripcion} onChange={handle}
              placeholder="Paradas, preferencias, equipaje..." />
          </div>

          {error && <p className="msg-error">{error}</p>}

          <button type="submit" className="btn-primary" disabled={loading}>
            {loading ? 'Publicando...' : 'Publicar viaje'}
          </button>
        </form>
      </div>
    </div>
  )
}

export default PublishTrip
