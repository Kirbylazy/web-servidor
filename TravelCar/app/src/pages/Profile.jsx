import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/api'
import './Profile.css'

const Profile = () => {
  const { user, setUser } = useAuth()
  const [tab, setTab] = useState('perfil')

  const [perfil, setPerfil] = useState({ nombre: '', apellidos: '', telefono: '' })
  const [password, setPassword] = useState({ actual: '', nueva: '', confirmar: '' })
  const [vehiculo, setVehiculo] = useState({
    marca: '', modelo: '', matricula: '', color: '', plazas: '',
    anio: '', aire_acondicionado: false, musica: false, maletero_grande: false, descripcion: ''
  })

  const [perfilMsg, setPerfilMsg] = useState('')
  const [passwordMsg, setPasswordMsg] = useState('')
  const [vehiculoMsg, setVehiculoMsg] = useState('')
  const [loadingPerfil, setLoadingPerfil] = useState(false)
  const [loadingPassword, setLoadingPassword] = useState(false)
  const [loadingVehiculo, setLoadingVehiculo] = useState(false)

  useEffect(() => {
    if (user) {
      setPerfil({ nombre: user.nombre || '', apellidos: user.apellidos || '', telefono: user.telefono || '' })
    }
    api.get(`/users/${user.id}/vehicle`).then(data => {
      if (data.vehicle) setVehiculo(v => ({ ...v, ...data.vehicle }))
    }).catch(() => {})
  }, [user])

  const handlePhotoChange = async (e) => {
    const file = e.target.files[0]
    if (!file) return
    const form = new FormData()
    form.append('foto', file)
    try {
      const res = await fetch(`${import.meta.env.VITE_API_URL || 'http://localhost:3000/api'}/users/${user.id}/photo`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        body: form,
      })
      const data = await res.json()
      if (data.foto) setUser(u => ({ ...u, foto: data.foto }))
    } catch {
      setPerfilMsg('Error al subir la foto')
    }
  }

  const handlePerfil = async (e) => {
    e.preventDefault()
    setPerfilMsg('')
    setLoadingPerfil(true)
    try {
      const data = await api.put(`/users/${user.id}`, perfil)
      setUser(u => ({ ...u, ...data.user }))
      setPerfilMsg('Perfil actualizado correctamente')
    } catch (err) {
      setPerfilMsg(err.message)
    } finally {
      setLoadingPerfil(false)
    }
  }

  const handlePassword = async (e) => {
    e.preventDefault()
    setPasswordMsg('')
    if (password.nueva !== password.confirmar) return setPasswordMsg('Las contraseñas no coinciden')
    setLoadingPassword(true)
    try {
      await api.put(`/users/${user.id}/password`, { actual: password.actual, nueva: password.nueva })
      setPasswordMsg('Contraseña actualizada correctamente')
      setPassword({ actual: '', nueva: '', confirmar: '' })
    } catch (err) {
      setPasswordMsg(err.message)
    } finally {
      setLoadingPassword(false)
    }
  }

  const handleVehiculo = async (e) => {
    e.preventDefault()
    setVehiculoMsg('')
    setLoadingVehiculo(true)
    try {
      await api.post(`/users/${user.id}/vehicle`, vehiculo)
      setVehiculoMsg('Vehículo guardado correctamente')
    } catch (err) {
      setVehiculoMsg(err.message)
    } finally {
      setLoadingVehiculo(false)
    }
  }

  const fotoUrl = user.foto
    ? `${user.foto}?t=${Date.now()}`
    : null

  return (
    <div className="profile-page">
      <div className="profile-card">
        <div className="profile-topbar">
          <Link to="/" className="back-link">← Volver al inicio</Link>
        </div>

        <div className="profile-header">
          <label className="photo-wrapper">
            {fotoUrl
              ? <img src={fotoUrl} alt="Foto de perfil" className="profile-photo" />
              : <div className="profile-photo-placeholder">{user.nombre?.[0]?.toUpperCase()}</div>
            }
            <div className="photo-overlay">Cambiar</div>
            <input type="file" accept="image/*" onChange={handlePhotoChange} hidden />
          </label>
          <div>
            <h2>{user.nombre} {user.apellidos}</h2>
            <p>{user.email}</p>
          </div>
        </div>

        <div className="tabs">
          <button className={tab === 'perfil' ? 'active' : ''} onClick={() => setTab('perfil')}>Perfil</button>
          <button className={tab === 'vehiculo' ? 'active' : ''} onClick={() => setTab('vehiculo')}>Vehículo</button>
        </div>

        {tab === 'perfil' && (
          <div className="tab-content">
            <form onSubmit={handlePerfil}>
              <h3>Datos personales</h3>
              <div className="form-row">
                <div className="form-group">
                  <label>Nombre</label>
                  <input value={perfil.nombre} onChange={e => setPerfil({ ...perfil, nombre: e.target.value })} required />
                </div>
                <div className="form-group">
                  <label>Apellidos</label>
                  <input value={perfil.apellidos} onChange={e => setPerfil({ ...perfil, apellidos: e.target.value })} />
                </div>
              </div>
              <div className="form-group">
                <label>Teléfono</label>
                <input value={perfil.telefono} onChange={e => setPerfil({ ...perfil, telefono: e.target.value })} placeholder="+34 600 000 000" />
              </div>
              {perfilMsg && <p className={perfilMsg.includes('correctamente') ? 'msg-ok' : 'msg-error'}>{perfilMsg}</p>}
              <button type="submit" className="btn-primary" disabled={loadingPerfil}>
                {loadingPerfil ? 'Guardando...' : 'Guardar cambios'}
              </button>
            </form>

            <hr />

            <form onSubmit={handlePassword}>
              <h3>Cambiar contraseña</h3>
              <div className="form-group">
                <label>Contraseña actual</label>
                <input type="password" value={password.actual} onChange={e => setPassword({ ...password, actual: e.target.value })} required />
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label>Nueva contraseña</label>
                  <input type="password" value={password.nueva} onChange={e => setPassword({ ...password, nueva: e.target.value })} required />
                </div>
                <div className="form-group">
                  <label>Confirmar nueva</label>
                  <input type="password" value={password.confirmar} onChange={e => setPassword({ ...password, confirmar: e.target.value })} required />
                </div>
              </div>
              {passwordMsg && <p className={passwordMsg.includes('correctamente') ? 'msg-ok' : 'msg-error'}>{passwordMsg}</p>}
              <button type="submit" className="btn-primary" disabled={loadingPassword}>
                {loadingPassword ? 'Actualizando...' : 'Cambiar contraseña'}
              </button>
            </form>
          </div>
        )}

        {tab === 'vehiculo' && (
          <div className="tab-content">
            <form onSubmit={handleVehiculo}>
              <h3>Datos del vehículo</h3>
              <div className="form-row">
                <div className="form-group">
                  <label>Marca</label>
                  <input value={vehiculo.marca} onChange={e => setVehiculo({ ...vehiculo, marca: e.target.value })} placeholder="Toyota" required />
                </div>
                <div className="form-group">
                  <label>Modelo</label>
                  <input value={vehiculo.modelo} onChange={e => setVehiculo({ ...vehiculo, modelo: e.target.value })} placeholder="Corolla" required />
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label>Matrícula</label>
                  <input value={vehiculo.matricula} onChange={e => setVehiculo({ ...vehiculo, matricula: e.target.value.toUpperCase() })} placeholder="1234 ABC" required />
                </div>
                <div className="form-group">
                  <label>Color</label>
                  <input value={vehiculo.color} onChange={e => setVehiculo({ ...vehiculo, color: e.target.value })} placeholder="Blanco" required />
                </div>
              </div>
              <div className="form-row">
                <div className="form-group">
                  <label>Plazas disponibles</label>
                  <input type="number" min="1" max="8" value={vehiculo.plazas} onChange={e => setVehiculo({ ...vehiculo, plazas: e.target.value })} required />
                </div>
                <div className="form-group">
                  <label>Año</label>
                  <input type="number" min="1990" max="2026" value={vehiculo.anio} onChange={e => setVehiculo({ ...vehiculo, anio: e.target.value })} placeholder="2020" />
                </div>
              </div>
              <div className="form-group">
                <label>Descripción (opcional)</label>
                <input value={vehiculo.descripcion} onChange={e => setVehiculo({ ...vehiculo, descripcion: e.target.value })} placeholder="Coche cómodo, música a gusto del pasajero..." />
              </div>
              <div className="checkboxes">
                <label><input type="checkbox" checked={vehiculo.aire_acondicionado} onChange={e => setVehiculo({ ...vehiculo, aire_acondicionado: e.target.checked })} /> Aire acondicionado</label>
                <label><input type="checkbox" checked={vehiculo.musica} onChange={e => setVehiculo({ ...vehiculo, musica: e.target.checked })} /> Música</label>
                <label><input type="checkbox" checked={vehiculo.maletero_grande} onChange={e => setVehiculo({ ...vehiculo, maletero_grande: e.target.checked })} /> Maletero grande</label>
              </div>
              {vehiculoMsg && <p className={vehiculoMsg.includes('correctamente') ? 'msg-ok' : 'msg-error'}>{vehiculoMsg}</p>}
              <button type="submit" className="btn-primary" disabled={loadingVehiculo}>
                {loadingVehiculo ? 'Guardando...' : 'Guardar vehículo'}
              </button>
            </form>
          </div>
        )}
      </div>
    </div>
  )
}

export default Profile
