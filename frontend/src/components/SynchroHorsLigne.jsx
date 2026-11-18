import { useCallback, useEffect, useState } from 'react'
import { useToast } from './Toast'
import { EVENEMENT_FILE, appelsEnAttente, synchroniser } from '../utils/horsLigne'

// Envoie les appels faits hors ligne dès que le réseau revient, et affiche ce qui reste à envoyer.
export default function SynchroHorsLigne() {
  const toast = useToast()
  const [enAttente, setEnAttente] = useState(() => appelsEnAttente().length)
  const [enLigne, setEnLigne] = useState(() => navigator.onLine)
  const [envoi, setEnvoi] = useState(false)

  const lancer = useCallback(async () => {
    if (!navigator.onLine || appelsEnAttente().length === 0) return
    setEnvoi(true)
    try {
      const r = await synchroniser()
      if (r.envoyes > 0) toast.success(`${r.envoyes} appel(s) fait(s) hors ligne envoyé(s).`)
      r.refuses.forEach((x) => toast.error(`Appel du ${x.appel.date} non envoyé : ${x.message}`))
    } finally {
      setEnvoi(false)
      setEnAttente(appelsEnAttente().length)
    }
  }, [toast])

  useEffect(() => {
    const surFile = () => setEnAttente(appelsEnAttente().length)
    const surEnLigne = () => { setEnLigne(true); lancer() }
    const surHorsLigne = () => setEnLigne(false)
    window.addEventListener(EVENEMENT_FILE, surFile)
    window.addEventListener('online', surEnLigne)
    window.addEventListener('offline', surHorsLigne)
    lancer()
    return () => {
      window.removeEventListener(EVENEMENT_FILE, surFile)
      window.removeEventListener('online', surEnLigne)
      window.removeEventListener('offline', surHorsLigne)
    }
  }, [lancer])

  if (enLigne && enAttente === 0) return null

  return (
    <div className={`synchro-hors-ligne ${enLigne ? '' : 'synchro-hors-ligne--coupe'}`} role="status">
      {!enLigne && <span>Pas de réseau : vous travaillez hors ligne.</span>}
      {enAttente > 0 && (
        <span>
          {enAttente} appel(s) en attente d’envoi.
          {enLigne && (
            <button type="button" className="btn btn--ghost btn--sm" onClick={lancer} disabled={envoi} style={{ marginLeft: 8 }}>
              {envoi ? 'Envoi…' : 'Envoyer maintenant'}
            </button>
          )}
        </span>
      )}
    </div>
  )
}
