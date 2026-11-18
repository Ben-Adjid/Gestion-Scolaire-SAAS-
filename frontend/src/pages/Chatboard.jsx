import { useCallback, useEffect, useRef, useState } from 'react'
import client from '../api/client'
import { messagesApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import { useToast } from '../components/Toast'
import { useEtablissement } from '../context/EtablissementContext'
import { useNavigate } from 'react-router-dom'

const INTERVALLE_MS = 20000

const listeDe = (reponse) => (Array.isArray(reponse.data) ? reponse.data : reponse.data.data || [])

const heure = (date) => new Date(date).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })

// Chatboard parent : conversation directe avec l'administration de l'établissement.
// Les messages sont rafraîchis automatiquement toutes les 20 secondes.
export default function Chatboard() {
  const toast = useToast()
  const { etablissement } = useEtablissement()
  const navigate = useNavigate()
  const [fil, setFil] = useState(null)
  const [texte, setTexte] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const basRef = useRef(null)

  const charger = useCallback(async () => {
    const [recus, envoyes] = await Promise.all([messagesApi.list(), client.get('/messages/sent')])
    const tous = [
      ...listeDe(recus).map((m) => ({ ...m, sens: 'recu' })),
      ...listeDe(envoyes).map((m) => ({ ...m, sens: 'envoye' })),
    ]
    const vus = new Set()
    const uniques = tous.filter((m) => {
      const cle = `${m.sens}|${m.created_at}|${m.body}`
      if (vus.has(cle)) return false
      vus.add(cle)
      return true
    })
    uniques.sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
    setFil(uniques)
  }, [])

  useEffect(() => {
    charger().catch((e) => toast.error(e.message))
    const timer = setInterval(() => charger().catch(() => {}), INTERVALLE_MS)
    return () => clearInterval(timer)
  }, [charger, toast])

  useEffect(() => {
    basRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' })
  }, [fil])

  const envoyer = async (e) => {
    e.preventDefault()
    if (!texte.trim()) return
    setEnvoi(true)
    try {
      await client.post('/messages', {
        titre: 'Message d’un parent',
        contenu: texte.trim(),
        cible: 'administration',
      })
      setTexte('')
      await charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  if (fil === null) return <Spinner />

  return (
    <div className="chatboard">
      <div className="chat-barre">
        <button type="button" className="btn btn--ghost btn--sm" onClick={() => navigate('/espace-parent')}>
          ← Retour à mes enfants
        </button>
        <button type="button" className="chat-fermer" aria-label="Fermer les échanges" onClick={() => navigate('/espace-parent')}>
          ✕
        </button>
      </div>
      <PageHeader
        title="Échanges avec l’administration"
        subtitle={`${etablissement.nom} répond dans cet espace. Les messages se mettent à jour automatiquement.`}
      />

      <section className="chat-fil" aria-live="polite">
        {fil.length === 0 && <p className="chat-vide">Aucun échange pour le moment. Écrivez à l’administration ci-dessous.</p>}
        {fil.map((m) => (
          <article key={`${m.sens}-${m.id}`} className={`chat-bulle ${m.sens === 'envoye' ? 'chat-bulle--moi' : ''}`}>
            <header>
              <strong>{m.sens === 'envoye' ? 'Vous' : m.sender?.name || 'Administration'}</strong>
              <time>{heure(m.created_at)}</time>
            </header>
            <p>{m.body}</p>
          </article>
        ))}
        <div ref={basRef} />
      </section>

      <form className="chat-composer" onSubmit={envoyer}>
        <textarea
          aria-label="Votre message"
          rows={2}
          value={texte}
          placeholder="Écrivez à l’administration…"
          onChange={(e) => setTexte(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && !e.shiftKey) envoyer(e)
          }}
        />
        <button type="submit" className="btn btn--primary" disabled={envoi || !texte.trim()}>
          {envoi ? 'Envoi…' : 'Envoyer'}
        </button>
      </form>
    </div>
  )
}
