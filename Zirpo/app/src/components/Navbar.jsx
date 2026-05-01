import { useState, useRef, useEffect } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import './Navbar.css'

const Navbar = () => {
  const { user, logout } = useAuth()
  const [open, setOpen] = useState(false)
  const menuRef = useRef(null)
  const location = useLocation()

  // Close menu on route change
  useEffect(() => { setOpen(false) }, [location.pathname])

  // Close menu on click outside
  useEffect(() => {
    if (!open) return
    const handler = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) setOpen(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [open])

  return (
    <nav className="navbar">
      <Link to="/" className="navbar-logo">Zirpo</Link>
      <div className="navbar-profile" ref={menuRef}>
        <button className="navbar-avatar-btn" onClick={() => setOpen(prev => !prev)}>
          {user.foto
            ? <img src={user.foto} alt="perfil" className="navbar-avatar" />
            : <div className="navbar-avatar-placeholder">{user.nombre?.[0]?.toUpperCase()}</div>
          }
          <span className="navbar-username">{user.nombre}</span>
          <svg className={`navbar-chevron ${open ? 'open' : ''}`} width="12" height="12" viewBox="0 0 12 12">
            <path d="M3 4.5L6 7.5L9 4.5" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
          </svg>
        </button>

        {open && (
          <div className="navbar-dropdown">
            <Link to="/" className="navbar-dropdown-item">
              <span className="navbar-dropdown-icon">🏠</span> Inicio
            </Link>
            <Link to="/profile" className="navbar-dropdown-item">
              <span className="navbar-dropdown-icon">👤</span> Perfil
            </Link>
            <Link to="/my-trips" className="navbar-dropdown-item">
              <span className="navbar-dropdown-icon">🚗</span> Mis viajes
            </Link>
            <Link to="/messages" className="navbar-dropdown-item">
              <span className="navbar-dropdown-icon">💬</span> Mensajes
            </Link>
            <div className="navbar-dropdown-divider" />
            <button className="navbar-dropdown-item navbar-logout" onClick={logout}>
              <span className="navbar-dropdown-icon">🚪</span> Cerrar sesión
            </button>
          </div>
        )}
      </div>
    </nav>
  )
}

export default Navbar
