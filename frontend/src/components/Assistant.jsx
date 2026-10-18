import { useEffect, useRef, useState } from 'react'
import { assistantApi } from '../api/endpoints'

const SUGGESTIONS = [
  'Statistiques',
  'Meilleurs élèves',
  'Appréciation 15',
  'Moyenne annuelle',
  'Emploi du temps',
]

export default function Assistant() {
  const [open, setOpen] = useState(false)
  const [messages, setMessages] = useState([
    { role: 'assistant', text: "Bonjour ! Je suis l'assistant Lycée Ibnou Cina. Posez-moi une question ou tapez « aide »." },
  ])
  const [input, setInput] = useState('')
  const [sending, setSending] = useState(false)
  const scrollRef = useRef(null)

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight
    }
  }, [messages, open])

  const send = async (text) => {
    const message = (text ?? input).trim()
    if (!message || sending) return

    setMessages((prev) => [...prev, { role: 'user', text: message }])
    setInput('')
    setSending(true)

    try {
      const res = await assistantApi.chat(message)
      setMessages((prev) => [...prev, { role: 'assistant', text: res.data.reply }])
    } catch {
      setMessages((prev) => [
        ...prev,
        { role: 'assistant', text: "Une erreur est survenue. Réessayez." },
      ])
    } finally {
      setSending(false)
    }
  }

  return (
    <>
      <button
        type="button"
        className="assistant-fab"
        onClick={() => setOpen((o) => !o)}
        aria-label="Assistant IA"
      >
        {open ? '✕' : '🤖'}
      </button>

      {open && (
        <div className="assistant-panel">
          <div className="assistant-header">
            <span>🤖 Assistant IA</span>
            <span className="assistant-badge">local</span>
          </div>
          <div className="assistant-messages" ref={scrollRef}>
            {messages.map((m, i) => (
              <div key={i} className={`assistant-msg assistant-msg--${m.role}`}>
                {m.text.split('\n').map((line, j) => (
                  <span key={j}>
                    {line}
                    <br />
                  </span>
                ))}
              </div>
            ))}
          </div>
          <div className="assistant-suggestions">
            {SUGGESTIONS.map((s) => (
              <button key={s} type="button" className="chip" onClick={() => send(s)}>
                {s}
              </button>
            ))}
          </div>
          <form
            className="assistant-input"
            onSubmit={(e) => {
              e.preventDefault()
              send()
            }}
          >
            <input
              type="text"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Posez votre question…"
            />
            <button type="submit" disabled={sending}>
              {sending ? '…' : '➤'}
            </button>
          </form>
        </div>
      )}
    </>
  )
}
