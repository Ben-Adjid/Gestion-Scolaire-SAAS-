import { useEffect, useState } from 'react'
import { supportApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Field, Input, Textarea } from '../components/Field'
import { useToast } from '../components/Toast'

const STATUT_LIBELLES = {
  ouverte: { label: 'Ouverte', cls: 'badge--amber' },
  en_cours: { label: 'En cours', cls: 'badge--amber' },
  resolue: { label: 'Résolue', cls: 'badge--success' },
}

// Formule Entreprise : demandes envoyées au support prioritaire de la plateforme.
export default function Support() {
  const toast = useToast()
  const [demandes, setDemandes] = useState(null)
  const [sujet, setSujet] = useState('')
  const [message, setMessage] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)

  const charger = () => supportApi.liste().then((res) => setDemandes(res.data)).catch((e) => setErreur(e.message))

  useEffect(() => {
    charger()
  }, [])

  const envoyer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await supportApi.envoyer({ sujet, message })
      toast.success('Votre demande a été transmise au support prioritaire.')
      setSujet('')
      setMessage('')
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  if (demandes === null && !erreur) return <Spinner />

  return (
    <div>
      <PageHeader title="Support prioritaire" subtitle="Une question urgente ? Nous répondons en priorité aux formules Entreprise." />

      {erreur && <Alert>{erreur}</Alert>}

      <form className="card form-carte" onSubmit={envoyer}>
        <div className="card-body">
          <section className="form-section">
            <h3>Nouvelle demande</h3>
            <div className="form-grille">
              <Field label="Sujet" htmlFor="support-sujet" required>
                <Input id="support-sujet" value={sujet} maxLength={150} onChange={(e) => setSujet(e.target.value)} />
              </Field>
              <Field label="Décrivez le problème" htmlFor="support-message" required hint="Indiquez les écrans concernés et ce que vous attendiez.">
                <Textarea id="support-message" rows={4} value={message} maxLength={3000} onChange={(e) => setMessage(e.target.value)} />
              </Field>
            </div>
          </section>
          <div className="form-actions">
            <button type="submit" className="btn btn--primary" disabled={envoi || !sujet.trim() || !message.trim()}>
              {envoi ? 'Envoi…' : 'Envoyer la demande'}
            </button>
          </div>
        </div>
      </form>

      <section className="card" style={{ marginTop: 16 }}>
        <div className="card-header"><h2>Mes demandes</h2></div>
        <div className="card-body">
          {demandes?.length === 0 && <EmptyState title="Aucune demande" description="Vos demandes et leurs réponses apparaîtront ici." />}
          {demandes?.map((d) => (
            <article key={d.id} className="support-demande">
              <header>
                <strong>{d.sujet}</strong>
                <span className={`badge ${STATUT_LIBELLES[d.statut]?.cls || ''}`}>{STATUT_LIBELLES[d.statut]?.label || d.statut}</span>
              </header>
              <p>{d.message}</p>
              {d.reponse && <p className="support-reponse"><strong>Réponse :</strong> {d.reponse}</p>}
              <small className="muted">{new Date(d.created_at).toLocaleDateString('fr-FR')}</small>
            </article>
          ))}
        </div>
      </section>
    </div>
  )
}
