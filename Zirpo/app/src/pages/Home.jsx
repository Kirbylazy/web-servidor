import { Link, useNavigate } from 'react-router-dom'
import { useState } from 'react'
import CityAutocomplete from '../components/CityAutocomplete'
import './Home.css'

const Home = () => {
  const navigate = useNavigate()
  const [search, setSearch] = useState({ origen: '', destino: '', fecha: '' })

  const handleSearch = (e) => {
    e.preventDefault()
    const params = new URLSearchParams(search).toString()
    navigate(`/search?${params}`)
  }

  return (
    <div className="home-container">
      <main className="home-main">
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
