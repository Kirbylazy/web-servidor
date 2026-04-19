import { useState, useEffect, useRef } from 'react'
import { useParams, Link } from 'react-router-dom'
import { api } from '../services/api'
import { useAuth } from '../context/AuthContext'
import './Chat.css'

const Chat = () => {
  const { tripId } = useParams()
  const { user } = useAuth()
  const [messages, setMessages] = useState([])
  const [text, setText] = useState('')
  const [trip, setTrip] = useState(null)
  const [sending, setSending] = useState(false)
  const bottomRef = useRef(null)
  const lastId = useRef(0)

  useEffect(() => {
    api.get(`/trips/${tripId}`).then(data => setTrip(data.trip)).catch(() => {})
    api.get(`/messages/${tripId}`).then(data => {
      setMessages(data.messages)
      if (data.messages.length) {
        lastId.current = data.messages[data.messages.length - 1].id
      }
    }).catch(() => {})
  }, [tripId])

  useEffect(() => {
    const interval = setInterval(async () => {
      try {
        const data = await api.get(`/messages/${tripId}?after_id=${lastId.current}`)
        if (data.messages.length) {
          setMessages(prev => [...prev, ...data.messages])
          lastId.current = data.messages[data.messages.length - 1].id
        }
      } catch { /* silenciar errores de polling */ }
    }, 4000)
    return () => clearInterval(interval)
  }, [tripId])

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  const handleSend = async () => {
    if (!text.trim() || sending) return
    setSending(true)
    try {
      const data = await api.post('/messages', { trip_id: parseInt(tripId), contenido: text.trim() })
      setMessages(prev => [...prev, data.message])
      lastId.current = data.message.id
      setText('')
    } catch { /* error silenciado */ }
    finally { setSending(false) }
  }

  const handleKeyDown = e => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  const formatTime = ts => new Date(ts).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
  const formatDate = ts => new Date(ts).toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })

  // Agrupar mensajes por fecha
  let lastDate = null

  return (
    <div className="chat-page">
      <div className="chat-topbar">
        <Link to={`/trips/${tripId}`} className="back-link">←</Link>
        <div className="chat-topbar-info">
          {trip && <span className="chat-trip-route">{trip.origen} → {trip.destino}</span>}
        </div>
      </div>

      <div className="chat-messages">
        {messages.map(msg => {
          const isOwn = msg.sender_id === user.id
          const msgDate = formatDate(msg.created_at)
          let showDate = false
          if (msgDate !== lastDate) { showDate = true; lastDate = msgDate }

          return (
            <div key={msg.id}>
              {showDate && <div className="chat-date-divider"><span>{msgDate}</span></div>}
              <div className={`chat-bubble ${isOwn ? 'chat-own' : 'chat-other'}`}>
                {!isOwn && <span className="chat-sender">{msg.sender_nombre}</span>}
                <p className="chat-text">{msg.contenido}</p>
                <span className="chat-time">{formatTime(msg.created_at)}</span>
              </div>
            </div>
          )
        })}
        <div ref={bottomRef} />
      </div>

      <div className="chat-input-bar">
        <input
          value={text}
          onChange={e => setText(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Escribe un mensaje..."
          maxLength={1000}
        />
        <button onClick={handleSend} disabled={!text.trim() || sending}>Enviar</button>
      </div>
    </div>
  )
}

export default Chat
