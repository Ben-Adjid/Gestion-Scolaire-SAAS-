import { useEffect, useState } from 'react'
import client from '../api/client'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Field, Input, Select } from '../components/Field'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'

const VIDE = { classe_id: '', matiere_id: '', date: '', heure_debut: '08:00', duree_minutes: 120, salle: '' }
const liste = (reponse) => reponse.data.data ?? reponse.data
const PEUT_MODIFIER = ['admin', 'proviseur', 'censeur']

// Planning des examens : consultation pour l'équipe, saisie pour la direction.
export default function PlanningExamens() {
  const toast = useToast()
  const { user } = useAuth()
  const [epreuves, setEpreuves] = useState(null)
  const [classes, setClasses] = useState([])
  const [matieres, setMatieres] = useState([])
  const [form, setForm] = useState(VIDE)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)
  const peutModifier = PEUT_MODIFIER.includes(user?.role)

  const charger = () => client.get('/planning-examens').then((res) => setEpreuves(res.data)).catch((e) => setErreur(e.message))

  useEffect(() => {
    charger()
    if (peutModifier) {
      client.get('/classes', { params: { per_page: 200 } }).then((res) => setClasses(liste(res))).catch(() => {})
      client.get('/matieres', { params: { per_page: 200 } }).then((res) => setMatieres(liste(res))).catch(() => {})
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const planifier = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await client.post('/planning-examens', { ...form, duree_minutes: Number(form.duree_minutes), salle: form.salle || null })
      toast.success('Épreuve planifiée.')
      setForm(VIDE)
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const supprimer = async (epreuve) => {
    if (!window.confirm('Supprimer cette épreuve du planning ?')) return
    try {
      await client.delete(`/planning-examens/${epreuve.id}`)
      toast.success('Épreuve supprimée.')
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  if (epreuves === null && !erreur) return <Spinner />

  const parDate = (epreuves || []).reduce((acc, e) => {
    (acc[e.date] ||= []).push(e)
    return acc
  }, {})

  return (
    <div>
      <PageHeader title="Planning des examens" subtitle="Dates, heures et salles, visibles par les parents de chaque classe." />
      {erreur && <Alert>{erreur}</Alert>}

      {peutModifier && (
        <form className="card form-carte" onSubmit={planifier}>
          <div className="card-body">
            <section className="form-section">
              <h3>Nouvelle épreuve</h3>
              <div className="form-grille">
                <Field label="Classe" htmlFor="ex-classe" required>
                  <Select id="ex-classe" value={form.classe_id} onChange={(e) => setForm({ ...form, classe_id: e.target.value })}>
                    <option value="">Choisir…</option>
                    {classes.map((c) => <option key={c.id} value={c.id}>{c.nom}</option>)}
                  </Select>
                </Field>
                <Field label="Matière" htmlFor="ex-matiere" required>
                  <Select id="ex-matiere" value={form.matiere_id} onChange={(e) => setForm({ ...form, matiere_id: e.target.value })}>
                    <option value="">Choisir…</option>
                    {matieres.map((m) => <option key={m.id} value={m.id}>{m.nom}</option>)}
                  </Select>
                </Field>
                <Field label="Date" htmlFor="ex-date" required>
                  <Input id="ex-date" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} />
                </Field>
                <Field label="Heure de début" htmlFor="ex-heure" required>
                  <Input id="ex-heure" type="time" value={form.heure_debut} onChange={(e) => setForm({ ...form, heure_debut: e.target.value })} />
                </Field>
                <Field label="Durée (minutes)" htmlFor="ex-duree" required>
                  <Input id="ex-duree" type="number" min={30} max={480} value={form.duree_minutes} onChange={(e) => setForm({ ...form, duree_minutes: e.target.value })} />
                </Field>
                <Field label="Salle" htmlFor="ex-salle">
                  <Input id="ex-salle" value={form.salle} maxLength={60} onChange={(e) => setForm({ ...form, salle: e.target.value })} />
                </Field>
              </div>
            </section>
            <div className="form-actions">
              <button type="submit" className="btn btn--primary" disabled={envoi || !form.classe_id || !form.matiere_id || !form.date}>
                {envoi ? 'Enregistrement…' : 'Planifier'}
              </button>
            </div>
          </div>
        </form>
      )}

      {epreuves?.length === 0 && (
        <EmptyState title="Aucune épreuve planifiée" description="Ajoutez les épreuves : elles apparaîtront aussi dans l’espace parent." />
      )}

      {Object.entries(parDate).map(([date, items]) => (
        <section key={date} className="cahier-jour">
          <h2 className="cahier-date">{new Date(date).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}</h2>
          {items.map((e) => (
            <article key={e.id} className="cahier-entree" style={{ '--matiere': e.matiere?.couleur || 'var(--primary)' }}>
              <header>
                <span className="cahier-matiere">{e.matiere?.nom}</span>
                <span className="cahier-classe">{e.classe?.nom}</span>
                <span className="muted">
                  {e.heure_debut?.slice(0, 5)} · {e.duree_minutes} min{e.salle ? ` · salle ${e.salle}` : ''}
                </span>
              </header>
              {peutModifier && (
                <div className="cahier-actions">
                  <button type="button" className="btn btn--ghost btn--sm" onClick={() => supprimer(e)}>Supprimer</button>
                </div>
              )}
            </article>
          ))}
        </section>
      ))}
    </div>
  )
}
