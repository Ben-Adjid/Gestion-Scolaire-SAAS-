import { useEffect, useState } from 'react'
import client from '../api/client'
import { telecharger } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { Field, Input, Select } from '../components/Field'
import { useToast } from '../components/Toast'

const CATEGORIES = {
  salaires: { libelle: 'Salaires du personnel', couleur: '#4F46E5' },
  fournitures: { libelle: 'Fournitures', couleur: '#0F6B57' },
  maintenance: { libelle: 'Maintenance', couleur: '#C2703D' },
  transport: { libelle: 'Transport', couleur: '#0891b2' },
  autre: { libelle: 'Autres', couleur: '#6B6A63' },
}

const VIDE = { categorie: 'salaires', libelle: '', montant: '', date: new Date().toISOString().slice(0, 10) }
const montant = (v) => `${Math.round(Number(v) || 0).toLocaleString('fr-FR')} FCFA`

// Camembert SVG des dépenses par catégorie.
function Camembert({ parts, total }) {
  if (total <= 0) return <p className="muted">Aucune dépense enregistrée.</p>
  let angle = -90
  const rayon = 70
  const centre = 90
  const point = (a) => [centre + rayon * Math.cos((a * Math.PI) / 180), centre + rayon * Math.sin((a * Math.PI) / 180)]

  return (
    <div className="camembert">
      <svg viewBox="0 0 180 180" role="img" aria-label="Répartition des dépenses">
        {parts.map((p) => {
          const part = p.total / total
          const debut = angle
          angle += part * 360
          const [x1, y1] = point(debut)
          const [x2, y2] = point(angle)
          const grand = part > 0.5 ? 1 : 0
          if (part >= 0.999) return <circle key={p.categorie} cx={centre} cy={centre} r={rayon} fill={CATEGORIES[p.categorie]?.couleur} />
          return <path key={p.categorie} d={`M${centre},${centre} L${x1},${y1} A${rayon},${rayon} 0 ${grand} 1 ${x2},${y2} Z`} fill={CATEGORIES[p.categorie]?.couleur} />
        })}
      </svg>
      <ul className="camembert-legende">
        {parts.map((p) => (
          <li key={p.categorie}>
            <span className="camembert-pastille" style={{ background: CATEGORIES[p.categorie]?.couleur }} />
            {CATEGORIES[p.categorie]?.libelle || p.categorie} — {montant(p.total)} ({Math.round((p.total / total) * 100)} %)
          </li>
        ))}
      </ul>
    </div>
  )
}

// Dépenses de l'établissement (Entreprise) : saisie, liste, répartition et résultat net.
export default function Depenses() {
  const toast = useToast()

  const exporterExcel = () => {
    client.get('/depenses/export', { responseType: 'blob' })
      .then((res) => telecharger(res.data, 'depenses.xlsx'))
      .catch((err) => toast.error(err.message))
  }
  const [depenses, setDepenses] = useState(null)
  const [synthese, setSynthese] = useState(null)
  const [form, setForm] = useState(VIDE)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState(null)

  const charger = () =>
    Promise.all([client.get('/depenses'), client.get('/comptabilite/synthese')])
      .then(([d, s]) => {
        setDepenses(d.data)
        setSynthese(s.data)
      })
      .catch((e) => setErreur(e.message))

  useEffect(() => {
    charger()
  }, [])

  const enregistrer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await client.post('/depenses', { ...form, montant: Number(form.montant) })
      toast.success('Dépense enregistrée.')
      setForm(VIDE)
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const supprimer = async (d) => {
    if (!window.confirm(`Supprimer la dépense « ${d.libelle} » ?`)) return
    try {
      await client.delete(`/depenses/${d.id}`)
      toast.success('Dépense supprimée.')
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  if (depenses === null && !erreur) return <Spinner />

  const resultat = synthese?.depenses?.resultat_net ?? 0

  return (
    <div>
      <PageHeader title="Dépenses & résultat" subtitle="Salaires, fournitures, maintenance : à mettre en face des frais perçus." />
      <div className="toolbar" style={{ justifyContent: 'flex-end', marginBottom: 12 }}>
        <button type="button" className="btn btn--ghost" onClick={exporterExcel}>Exporter les dépenses (Excel)</button>
      </div>
      {erreur && <Alert>{erreur}</Alert>}

      {synthese && (
        <div className="analyses-indicateurs">
          <div className="card analyse-kpi"><span className="analyse-kpi-valeur">{montant(synthese.kpis.encaisse)}</span><span className="analyse-kpi-label">Perçus</span></div>
          <div className="card analyse-kpi"><span className="analyse-kpi-valeur">{montant(synthese.depenses.total)}</span><span className="analyse-kpi-label">Dépenses</span></div>
          <div className="card analyse-kpi"><span className="analyse-kpi-valeur" style={{ color: resultat < 0 ? 'var(--danger)' : 'var(--success)' }}>{montant(resultat)}</span><span className="analyse-kpi-label">Résultat net</span></div>
        </div>
      )}

      <section className="card" style={{ marginBottom: 16 }}>
        <div className="card-header"><h2>Répartition</h2></div>
        <div className="card-body">
          <Camembert parts={synthese?.depenses?.par_categorie || []} total={synthese?.depenses?.total || 0} />
        </div>
      </section>

      <form className="card form-carte" onSubmit={enregistrer}>
        <div className="card-body">
          <section className="form-section">
            <h3>Nouvelle dépense</h3>
            <div className="form-grille">
              <Field label="Catégorie" htmlFor="dep-cat" required>
                <Select id="dep-cat" value={form.categorie} onChange={(e) => setForm({ ...form, categorie: e.target.value })}>
                  {Object.entries(CATEGORIES).map(([v, c]) => <option key={v} value={v}>{c.libelle}</option>)}
                </Select>
              </Field>
              <Field label="Libellé" htmlFor="dep-lib" required>
                <Input id="dep-lib" value={form.libelle} maxLength={150} onChange={(e) => setForm({ ...form, libelle: e.target.value })} />
              </Field>
              <Field label="Montant (FCFA)" htmlFor="dep-mont" required>
                <Input id="dep-mont" type="number" min={1} value={form.montant} onChange={(e) => setForm({ ...form, montant: e.target.value })} />
              </Field>
              <Field label="Date" htmlFor="dep-date" required>
                <Input id="dep-date" type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} />
              </Field>
            </div>
          </section>
          <div className="form-actions">
            <button type="submit" className="btn btn--primary" disabled={envoi || !form.libelle.trim() || !form.montant || !form.date}>
              {envoi ? 'Enregistrement…' : 'Enregistrer la dépense'}
            </button>
          </div>
        </div>
      </form>

      {depenses?.length === 0 && <EmptyState title="Aucune dépense" description="Les dépenses saisies apparaîtront ici." />}

      {depenses?.length > 0 && (
        <section className="card">
          <div className="card-body table-wrapper">
            <table className="table">
              <thead><tr><th>Date</th><th>Catégorie</th><th>Libellé</th><th>Montant</th><th /></tr></thead>
              <tbody>
                {depenses.map((d) => (
                  <tr key={d.id}>
                    <td>{d.date}</td>
                    <td>{CATEGORIES[d.categorie]?.libelle || d.categorie}</td>
                    <td>{d.libelle}</td>
                    <td>{montant(d.montant)}</td>
                    <td><button type="button" className="btn btn--ghost btn--sm" onClick={() => supprimer(d)}>Supprimer</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </div>
  )
}
