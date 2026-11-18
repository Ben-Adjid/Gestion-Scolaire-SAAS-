import { useEffect, useState } from 'react'
import client from '../api/client'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Field, Input, Textarea } from '../components/Field'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'

const PEUT_PUBLIER = ['admin', 'proviseur']

// Fil d'actualité : annonces, sorties et événements de l'établissement.
export default function Actualites() {
  const toast = useToast()
  const { user } = useAuth()
  const [items, setItems] = useState(null)
  const [erreur, setErreur] = useState(null)
  const [titre, setTitre] = useState('')
  const [contenu, setContenu] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const publie = PEUT_PUBLIER.includes(user?.role)

  const charger = () => client.get('/actualites').then((res) => setItems(res.data)).catch((e) => setErreur(e.message))

  useEffect(() => {
    charger()
  }, [])

  const publier = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await client.post('/actualites', { titre, contenu })
      toast.success('Publication diffusée.')
      setTitre('')
      setContenu('')
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const supprimer = async (item) => {
    if (!window.confirm(`Supprimer la publication « ${item.titre} » ?`)) return
    try {
      await client.delete(`/actualites/${item.id}`)
      toast.success('Publication supprimée.')
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  if (items === null && !erreur) return <Spinner />

  return (
    <div>
      <PageHeader title="Fil d’actualité" subtitle="Annonces, sorties et événements de l’établissement." />
      {erreur && <Alert>{erreur}</Alert>}

      {publie && (
        <form className="card form-carte" onSubmit={publier}>
          <div className="card-body">
            <section className="form-section">
              <h3>Nouvelle publication</h3>
              <Field label="Titre" htmlFor="act-titre" required>
                <Input id="act-titre" value={titre} maxLength={150} onChange={(e) => setTitre(e.target.value)} />
              </Field>
              <Field label="Message" htmlFor="act-contenu" required hint="Visible par toute la communauté (enseignants, parents).">
                <Textarea id="act-contenu" rows={4} value={contenu} maxLength={3000} onChange={(e) => setContenu(e.target.value)} />
              </Field>
            </section>
            <div className="form-actions">
              <button type="submit" className="btn btn--primary" disabled={envoi || !titre.trim() || !contenu.trim()}>
                {envoi ? 'Publication…' : 'Publier'}
              </button>
            </div>
          </div>
        </form>
      )}

      {items?.length === 0 && <EmptyState title="Rien à signaler" description="Les publications de l’établissement apparaîtront ici." />}

      {items?.map((a) => (
        <article key={a.id} className="card actualite-carte">
          <div className="card-body">
            <header className="actualite-entete">
              <strong>{a.titre}</strong>
              <span className="muted">{new Date(a.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })} · {a.auteur?.name}</span>
            </header>
            <p className="actualite-texte">{a.contenu}</p>
            {publie && (
              <div className="form-actions">
                <button type="button" className="btn btn--ghost btn--sm" onClick={() => supprimer(a)}>Supprimer</button>
              </div>
            )}
          </div>
        </article>
      ))}
    </div>
  )
}
