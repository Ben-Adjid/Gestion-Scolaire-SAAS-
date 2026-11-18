import { useEffect, useState } from 'react'
import client from '../api/client'
import { classesApi, matieresApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Field, Input, Select, Textarea } from '../components/Field'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'

const aujourdhui = () => new Date().toISOString().slice(0, 10)
const VIDE = { classe_id: '', matiere_id: '', date: aujourdhui(), contenu: '', devoirs: '' }
const items = (reponse) => reponse.data.data ?? reponse.data

// Cahier de texte numérique : l'enseignant publie ce qui a été vu et les devoirs ;
// la direction et les parents consultent.
export default function CahierTexte() {
  const toast = useToast()
  const { user } = useAuth()
  const estEnseignant = user?.role === 'enseignant'

  const [entrees, setEntrees] = useState(null)
  const [classes, setClasses] = useState([])
  const [matieres, setMatieres] = useState([])
  const [form, setForm] = useState(VIDE)
  const [editionId, setEditionId] = useState(null)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)

  const charger = () =>
    client
      .get('/cahier-texte')
      .then((res) => setEntrees(res.data))
      .catch((e) => setErreur(e.message))

  useEffect(() => {
    charger()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (!estEnseignant) return
    classesApi.list({ per_page: 200 }).then((res) => setClasses(items(res))).catch(() => {})
    matieresApi.list({ per_page: 200 }).then((res) => setMatieres(items(res))).catch(() => {})
  }, [estEnseignant])

  const modifier = (entree) => {
    setEditionId(entree.id)
    setForm({
      classe_id: entree.classe?.id ?? '',
      matiere_id: entree.matiere?.id ?? '',
      date: entree.date,
      contenu: entree.contenu,
      devoirs: entree.devoirs || '',
    })
  }

  const reinitialiser = () => {
    setEditionId(null)
    setForm(VIDE)
  }

  const publier = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      if (editionId) {
        await client.put(`/cahier-texte/${editionId}`, form)
        toast.success('Entrée mise à jour.')
      } else {
        await client.post('/cahier-texte', form)
        toast.success('Entrée publiée au cahier de texte.')
      }
      reinitialiser()
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const supprimer = async (entree) => {
    if (!window.confirm('Supprimer cette entrée du cahier de texte ?')) return
    try {
      await client.delete(`/cahier-texte/${entree.id}`)
      toast.success('Entrée supprimée.')
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  if (entrees === null && !erreur) return <Spinner />

  // Regroupe les entrées par date, les plus récentes d'abord.
  const parDate = (entrees || []).reduce((acc, e) => {
    (acc[e.date] ||= []).push(e)
    return acc
  }, {})

  return (
    <div>
      <PageHeader
        title="Cahier de texte"
        subtitle={estEnseignant ? 'Ce que vous avez vu en cours, et les devoirs à faire.' : 'Le contenu des cours et les devoirs, par classe.'}
      />

      {erreur && <Alert>{erreur}</Alert>}

      {estEnseignant && (
        <form className="card form-carte" onSubmit={publier}>
          <div className="card-body">
            <section className="form-section">
              <h3>{editionId ? 'Modifier l’entrée' : 'Nouvelle entrée'}</h3>
              <div className="form-grille">
                <Field label="Classe" htmlFor="ct-classe" required>
                  <Select id="ct-classe" value={form.classe_id} onChange={(e) => setForm({ ...form, classe_id: e.target.value })}>
                    <option value="">Choisir…</option>
                    {classes.map((c) => <option key={c.id} value={c.id}>{c.nom}</option>)}
                  </Select>
                </Field>
                <Field label="Matière" htmlFor="ct-matiere" required>
                  <Select id="ct-matiere" value={form.matiere_id} onChange={(e) => setForm({ ...form, matiere_id: e.target.value })}>
                    <option value="">Choisir…</option>
                    {matieres.map((m) => <option key={m.id} value={m.id}>{m.nom}</option>)}
                  </Select>
                </Field>
                <Field label="Date de la séance" htmlFor="ct-date" required>
                  <Input id="ct-date" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} />
                </Field>
              </div>
              <Field label="Contenu du cours" htmlFor="ct-contenu" required>
                <Textarea id="ct-contenu" rows={3} value={form.contenu} maxLength={5000} onChange={(e) => setForm({ ...form, contenu: e.target.value })} />
              </Field>
              <Field label="Devoirs à faire" htmlFor="ct-devoirs">
                <Textarea id="ct-devoirs" rows={2} value={form.devoirs} maxLength={2000} onChange={(e) => setForm({ ...form, devoirs: e.target.value })} />
              </Field>
            </section>
            <div className="form-actions">
              {editionId && <button type="button" className="btn btn--ghost" onClick={reinitialiser}>Annuler</button>}
              <button type="submit" className="btn btn--primary" disabled={envoi || !form.classe_id || !form.matiere_id || !form.contenu.trim()}>
                {envoi ? 'Enregistrement…' : editionId ? 'Mettre à jour' : 'Publier'}
              </button>
            </div>
          </div>
        </form>
      )}

      {entrees?.length === 0 && <EmptyState title="Rien pour l’instant" description="Les entrées publiées par les enseignants apparaîtront ici." />}

      {Object.entries(parDate).map(([date, liste]) => (
        <section key={date} className="cahier-jour">
          <h2 className="cahier-date">{new Date(date).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}</h2>
          {liste.map((e) => (
            <article key={e.id} className="cahier-entree" style={{ '--matiere': e.matiere?.couleur || 'var(--primary)' }}>
              <header>
                <span className="cahier-matiere">{e.matiere?.nom}</span>
                <span className="cahier-classe">{e.classe?.nom}</span>
                <span className="muted">par {e.enseignant?.prenom} {e.enseignant?.nom}</span>
              </header>
              <p>{e.contenu}</p>
              {e.devoirs && <p className="cahier-devoirs"><strong>À faire :</strong> {e.devoirs}</p>}
              {estEnseignant && (
                <div className="cahier-actions">
                  <button type="button" className="btn btn--ghost btn--sm" onClick={() => modifier(e)}>Modifier</button>
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
