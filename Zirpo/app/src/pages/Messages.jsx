import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../services/api'
import { useAuth } from '../context/AuthContext'
import './Messages.css'

const Messages = () => {
  const { user } = useAuth()
  const [conversations, setConversations] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get('/messages/conversations')
      .then(data => setConversations(data.conversations))
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  const formatDate = ts => {
    const d = new Date(ts)
    const now = new Date()
    const diff = now - d
    if (diff < 86400000 && d.getDate() === now.getDate()) {
      return d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
    }
    if (diff < 172800000) return 'Ayer'
    return d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })
  }

  if (loading) return <div className="messages-page"><p className="messages-loading">Cargando conversaciones...</p></div>

  return (
    <div className="messages-page">
      <h2 className="messages-title">Mensajes</h2>
      {conversations.length === 0 ? (
        <div className="messages-empty">
          <span className="messages-empty-icon">💬</span>
          <p>No tienes conversaciones activas</p>
          <p className="messages-empty-hint">Cuando reserves o publiques un viaje, aquí aparecerán los chats</p>
        </div>
      ) : (
        <div className="messages-list">
          {conversations.map(conv => (
            <Link to={`/chat/${conv.trip_id}/${conv.passenger_id}`} key={`${conv.trip_id}-${conv.passenger_id}`} className="messages-item">
              <div className="messages-item-avatar">
                {conv.other_foto
                  ? <img src={conv.other_foto} alt="" className="messages-avatar-img" />
                  : <div className="messages-avatar-placeholder">{conv.other_nombre?.[0]?.toUpperCase()}</div>
                }
              </div>
              <div className="messages-item-body">
                <div className="messages-item-top">
                  <span className="messages-item-name">{conv.other_nombre}</span>
                  <span className="messages-item-time">{formatDate(conv.last_message_at)}</span>
                </div>
                <div className="messages-item-route">{conv.origen} → {conv.destino}</div>
                <p className="messages-item-preview">{conv.last_message}</p>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}

export default Messages
