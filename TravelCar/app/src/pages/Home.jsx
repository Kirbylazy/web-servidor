import { useAuth } from '../context/AuthContext'
import './Home.css'

const Home = () => {
  const { user, logout } = useAuth()

  return (
    <div className="home-container">
      <header className="home-header">
        <h1>TravelCar</h1>
        <div className="header-user">
          <span>Hola, {user.nombre}</span>
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
