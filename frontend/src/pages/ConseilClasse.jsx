import { useEffect, useState } from 'react'
import { classesApi, conseilApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { Field, Input, Select, Textarea } from '../components/Field'
import { TRIMESTRES } from '../utils/constants'

export default function ConseilClasse() {
  const toast = useToast()
  const [classes, setClasses] = useState([])
  const [classeId, setClasseId] = useState('')
  const [trimestre, setTrimestre] = useState(1)
  const [synthese, setSynthese] = useState(null)
  const [historique, setHistorique] = useState([])
  const [loading, setLoading] = useState(false)
  const [erreur, setErreur] = useState(null)
  const [form, setForm] = useState({ date_conseil: new Date().toISOString().slice(0, 10), observations: '' })
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch((e) => setErreur(e.message))
  }, [])

  useEffect(() => {
    if (!classeId) {
      setSynthese(null)
      setHistorique([])
      return
    }
    setLoading(true)
    setErreur(null)
    Promise.all([conseilApi.prepare(classeId, trimestre), conseilApi.list(classeId)])
      .then(([prep, hist]) => {
        setSynthese(prep.data)
        setHistorique(hist.data)
      })
      .catch((e) => setErreur(e.message))
      .finally(() => setLoading(false))
  }, [classeId, trimestre])

  const [pvAffiche, setPvAffiche] = useState('')

  const voirPv = async (id) => {
    try {
      const res = await conseilApi.show(classeId, id)
      setPvAffiche(res.data.pv)
    } catch (err) {
      toast.error(err.message)
    }
  }

  const enregistrer = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      await conseilApi.create(classeId, { ...form, trimestre })
      toast.success('Procès-verbal enregistré.')
      const hist = await conseilApi.list(classeId)
      setHistorique(hist.data)
      setForm((f) => ({ ...f, observations: '' }))
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Conseil de classe"
        subtitle="Synthèse des résultats par élève et procès-verbal du conseil."
      />

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="card-body" style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
          <Field label="Classe" htmlFor="conseil-classe">
            <Select id="conseil-classe" value={classeId} onChange={(e) => setClasseId(e.target.value)}>
              <option value="">— Choisir une classe —</option>
              {classes.map((c) => (
                <option key={c.id} value={c.id}>{c.nom} ({c.niveau})</option>
              ))}
            </Select>
          </Field>
          <Field label="Trimestre" htmlFor="conseil-trimestre">
            <Select id="conseil-trimestre" value={trimestre} onChange={(e) => setTrimestre(Number(e.target.value))}>
              {TRIMESTRES.map((t) => (
                <option key={t.value} value={t.value}>{t.label}</option>
              ))}
            </Select>
          </Field>
        </div>
      </div>

      {erreur && <Alert>{erreur}</Alert>}
      {loading && <Spinner />}

      {!classeId && !loading && (
        <EmptyState title="Choisissez une classe" description="Sélectionnez une classe et un trimestre pour préparer le conseil." />
      )}

      {synthese && !loading && (
        <>
          <div className="card" style={{ marginBottom: 16 }}>
            <div className="card-header"><h2>Synthèse — {synthese.classe.nom}</h2></div>
            <div className="card-body">
              {synthese.eleves.length === 0 ? (
                <p className="muted">Aucun élève dans cette classe.</p>
              ) : (
                <div className="table-wrapper">
                  <table className="table">
                    <thead>
                      <tr>
                        <th>Rang</th><th>Élève</th><th>Moyenne</th><th>Mention</th><th>Décision</th>
                      </tr>
                    </thead>
                    <tbody>
                      {synthese.eleves.map((eleve) => (
                        <tr key={eleve.id}>
                          <td>{eleve.rang ?? '—'}</td>
                          <td className="cell-strong">{eleve.prenom} {eleve.nom}</td>
                          <td>{eleve.moyenne != null ? Number(eleve.moyenne).toFixed(2) : '—'}</td>
                          <td>{eleve.mention || '—'}</td>
                          <td>{eleve.decision}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>

          <form className="card" style={{ marginBottom: 16 }} onSubmit={enregistrer}>
            <div className="card-header"><h2>Procès-verbal</h2></div>
            <div className="card-body">
              <Field label="Date du conseil" htmlFor="conseil-date" required>
                <Input id="conseil-date" type="date" value={form.date_conseil} onChange={(e) => setForm({ ...form, date_conseil: e.target.value })} />
              </Field>
              <Field label="Observations" htmlFor="conseil-obs">
                <Textarea id="conseil-obs" rows={4} value={form.observations} onChange={(e) => setForm({ ...form, observations: e.target.value })} />
              </Field>
              <button type="submit" className="btn btn--primary" disabled={saving}>
                {saving ? 'Enregistrement…' : 'Enregistrer le procès-verbal'}
              </button>
            </div>
          </form>
        </>
      )}

      {classeId && historique.length > 0 && (
        <div className="card">
          <div className="card-header"><h2>Procès-verbaux enregistrés</h2></div>
          <div className="card-body">
            <ul className="top-list">
              {historique.map((pv) => (
                <li key={pv.id} className="top-item">
                  <span className="top-name">Trimestre {pv.trimestre} — {pv.date_conseil}</span>
                  <button type="button" className="card-link" onClick={() => voirPv(pv.id)}>Voir</button>
                </li>
              ))}
            </ul>
            {pvAffiche && (
              <pre className="pv-texte" style={{ whiteSpace: 'pre-wrap', marginTop: 16 }}>{pvAffiche}</pre>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
