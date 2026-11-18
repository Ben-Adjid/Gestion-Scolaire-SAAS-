import { useEffect, useState } from 'react'
import client from '../api/client'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Field, Input, Select, Textarea } from '../components/Field'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'

const TYPES = { rentree: 'Rentrée', conge: 'Congé', examen: 'Examen', reunion: 'Réunion', autre: 'Autre' }
const VIDE = { titre: '', type: 'autre', date_debut: '', date_fin: '', description: '' }
const PEUT_MODIFIER = ['admin', 'proviseur']

// Calendrier scolaire : rentrée, congés, examens et réunions de l'année.
export default function Calendrier() {
  const toast = useToast()
  const { user } = useAuth()
  const [evenements, setEvenements] = useState(null)
  const [form, setForm] = useState(VIDE)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)
  const peutModifier = PEUT_MODIFIER.includes(user?.role)

  const charger = () => client.get('/calendrier').then((res) => setEvenements(res.data)).catch((e) => setErreur(e.message))

  useEffect(() => {
    charger()
  }, [])

  const ajouter = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await client.post('/calendrier', { ...form, date_fin: form.date_fin || null, description: form.description || null })
      toast.success('Événement ajouté au calendrier.')
      setForm(VIDE)
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const retirer = async (evenement) => {
    if (!window.confirm(`Retirer « ${evenement.titre} » du calendrier ?`)) return
    try {
      await client.delete(`/calendrier/${evenement.id}`)
      toast.success('Événement retiré.')
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  if (evenements === null && !erreur) return <Spinner />

  const format = (d) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })

  return (
    <div>
      <PageHeader title="Calendrier scolaire" subtitle="Rentrée, congés, examens et réunions de l’année." />
      {erreur && <Alert>{erreur}</Alert>}

      {peutModifier && (
        <form className="card form-carte" onSubmit={ajouter}>
          <div className="card-body">
            <section className="form-section">
              <h3>Nouvel événement</h3>
              <div className="form-grille">
                <Field label="Titre" htmlFor="cal-titre" required>
                  <Input id="cal-titre" value={form.titre} maxLength={150} onChange={(e) => setForm({ ...form, titre: e.target.value })} />
                </Field>
                <Field label="Type" htmlFor="cal-type" required>
                  <Select id="cal-type" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
                    {Object.entries(TYPES).map(([valeur, libelle]) => <option key={valeur} value={valeur}>{libelle}</option>)}
                  </Select>
                </Field>
                <Field label="Date de début" htmlFor="cal-debut" required>
                  <Input id="cal-debut" type="date" value={form.date_debut} onChange={(e) => setForm({ ...form, date_debut: e.target.value })} />
                </Field>
                <Field label="Date de fin" htmlFor="cal-fin" hint="Facultatif, pour un événement sur plusieurs jours.">
                  <Input id="cal-fin" type="date" value={form.date_fin} min={form.date_debut || undefined} onChange={(e) => setForm({ ...form, date_fin: e.target.value })} />
                </Field>
              </div>
              <Field label="Description" htmlFor="cal-desc">
                <Textarea id="cal-desc" rows={2} value={form.description} maxLength={1000} onChange={(e) => setForm({ ...form, description: e.target.value })} />
              </Field>
            </section>
            <div className="form-actions">
              <button type="submit" className="btn btn--primary" disabled={envoi || !form.titre.trim() || !form.date_debut}>
                {envoi ? 'Ajout…' : 'Ajouter au calendrier'}
              </button>
            </div>
          </div>
        </form>
      )}

      {evenements?.length === 0 && <EmptyState title="Calendrier vide" description="Les dates importantes de l’année apparaîtront ici." />}

      {evenements?.map((e) => (
        <article key={e.id} className={`cahier-entree calendrier-${e.type}`}>
          <header>
            <span className="cahier-matiere">{e.titre}</span>
            <span className="cahier-classe">{TYPES[e.type] || e.type}</span>
            <span className="muted">
              {format(e.date_debut)}{e.date_fin && e.date_fin !== e.date_debut ? ` → ${format(e.date_fin)}` : ''}
            </span>
          </header>
          {e.description && <p>{e.description}</p>}
          {peutModifier && (
            <div className="cahier-actions">
              <button type="button" className="btn btn--ghost btn--sm" onClick={() => retirer(e)}>Retirer</button>
            </div>
          )}
        </article>
      ))}
    </div>
  )
}
