import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import './Home.css'

const Home = () => {
  const { user, logout } = useAuth()

  return (
    <div className="home-container">
      <header className="home-header">
        <h1>TravelCar</h1>
        <div className="header-user">
          <Link to="/profile" className="header-profile">
            {user.foto
              ? <img src={user.foto} alt="perfil" className="header-avatar" />
              : <div className="header-avatar-placeholder">{user.nombre?.[0]?.toUpperCase()}</div>
            }
            <span>{user.nombre}</span>
          </Link>
          <button onClick={logout} className="btn-logout">Cerrar sesión</button>
        </div>
      </header>

      <main className="home-main">
        <h2>Bienvenido a TravelCar</h2>
        <p>Próximamente podrás publicar y buscar viajes.</p>
      </main>
    </div>
  )
}

export default Home
