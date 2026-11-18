import { useCallback, useEffect, useRef, useState } from 'react'
import { assistantApi } from '../api/endpoints'
import { useEtablissement } from '../context/EtablissementContext'

const SUGGESTIONS = [
  'Statistiques',
  'Meilleurs élèves',
  'Appréciation 15',
  'Moyenne annuelle',
  'Emploi du temps',
]

const DUREE_FERMETURE_MS = 180

// Assistant flottant (coin inférieur droit). Fermable de quatre façons :
// bouton ✕ de l'en-tête, bouton flottant, touche Échap, clic en dehors du panneau.
export default function Assistant() {
  const { etablissement } = useEtablissement()
  const [open, setOpen] = useState(false)
  const [fermeture, setFermeture] = useState(false)
  const [messages, setMessages] = useState([])
  const [input, setInput] = useState('')
  const [sending, setSending] = useState(false)
  const scrollRef = useRef(null)
  const panneauRef = useRef(null)
  const fabRef = useRef(null)
  const fermetureTimer = useRef(null)

  const fermer = useCallback(() => {
    if (!open || fermeture) return
    setFermeture(true)
    fermetureTimer.current = setTimeout(() => {
      setOpen(false)
      setFermeture(false)
    }, DUREE_FERMETURE_MS)
  }, [open, fermeture])

  const ouvrir = useCallback(() => {
    clearTimeout(fermetureTimer.current)
    setFermeture(false)
    setOpen(true)
  }, [])

  // Ouvert depuis la barre supérieure (« Demander l’assistant »).
  useEffect(() => {
    window.addEventListener('ouvrir-assistant', ouvrir)
    return () => window.removeEventListener('ouvrir-assistant', ouvrir)
  }, [ouvrir])

  // Premier message d'accueil, avec le nom de l'établissement.
  useEffect(() => {
    if (open && messages.length === 0) {
      setMessages([{ role: 'assistant', text: `Bonjour ! Je suis l'assistant ${etablissement.nom}. Posez-moi une question ou tapez « aide ».` }])
    }
  }, [open, messages.length, etablissement.nom])

  // Touche Échap et clic en dehors du panneau.
  useEffect(() => {
    if (!open) return undefined

    const surEchap = (e) => {
      if (e.key === 'Escape') fermer()
    }
    const surClicExterieur = (e) => {
      if (panneauRef.current?.contains(e.target) || fabRef.current?.contains(e.target)) return
      fermer()
    }

    document.addEventListener('keydown', surEchap)
    document.addEventListener('mousedown', surClicExterieur)
    return () => {
      document.removeEventListener('keydown', surEchap)
      document.removeEventListener('mousedown', surClicExterieur)
    }
  }, [open, fermer])

  useEffect(() => () => clearTimeout(fermetureTimer.current), [])

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
      setMessages((prev) => [...prev, { role: 'assistant', text: 'Une erreur est survenue. Réessayez.' }])
    } finally {
      setSending(false)
    }
  }

  const basculer = () => (open && !fermeture ? fermer() : ouvrir())

  return (
    <>
      <button
        ref={fabRef}
        type="button"
        className="assistant-fab"
        onClick={basculer}
        aria-label={open && !fermeture ? "Fermer l'assistant" : "Ouvrir l'assistant"}
        aria-expanded={open && !fermeture}
      >
        {open && !fermeture ? '✕' : ''}
      </button>

      {open && (
        <div
          ref={panneauRef}
          role="dialog"
          aria-label="Assistant"
          className={`assistant-panel ${fermeture ? 'assistant-panel--ferme' : 'assistant-panel--ouvert'}`}
        >
          <div className="assistant-header">
            <span>Assistant</span>
            <button type="button" className="assistant-fermer" onClick={fermer} aria-label="Fermer l'assistant">
              ✕
            </button>
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
              aria-label="Votre question"
            />
            <button type="submit" disabled={sending} aria-label="Envoyer">
              {sending ? '…' : '➤'}
            </button>
          </form>
        </div>
      )}
    </>
  )
}
