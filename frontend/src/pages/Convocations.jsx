import { useCallback, useEffect, useMemo, useState } from 'react'
import { classesApi, convocationsApi, etudiantsApi } from '../api/endpoints'
import Modal from '../components/Modal'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { useEtablissement } from '../context/EtablissementContext'
import { Field, Input, Select } from '../components/Field'
import { formatDate } from '../utils/format'

export default function Convocations() {
  const toast = useToast()
  const { etablissement } = useEtablissement()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [page, setPage] = useState(1)

  const [classes, setClasses] = useState([])
  const [etudiants, setEtudiants] = useState([])
  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState({ etudiant_id: '', date: new Date().toISOString().slice(0, 10), motif: '' })
  const [classeId, setClasseId] = useState('')
  const [search, setSearch] = useState('')
  const [saving, setSaving] = useState(false)

  const [doc, setDoc] = useState(null)

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
    etudiantsApi.list({ per_page: 1000 }).then((res) => setEtudiants(res.data.data)).catch(() => {})
  }, [])

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await convocationsApi.list({ page })
      setItems(res.data.data)
      setMeta(res.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [page])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const filteredEtudiants = useMemo(() => {
    let list = etudiants
    if (classeId) list = list.filter((e) => String(e.classe_id) === String(classeId))
    if (search.trim()) {
      const s = search.trim().toLowerCase()
      list = list.filter(
        (e) =>
          e.nom.toLowerCase().includes(s) ||
          e.prenom.toLowerCase().includes(s) ||
          (e.matricule || '').toLowerCase().includes(s)
      )
    }
    return list
  }, [etudiants, classeId, search])

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const res = await convocationsApi.create(form)
      toast.success(res.data.message)
      setModalOpen(false)
      setDoc(res.data.convocation)
      setForm({ etudiant_id: '', date: new Date().toISOString().slice(0, 10), motif: '' })
      setClasseId('')
      setSearch('')
      fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  const remove = async (item) => {
    try {
      await convocationsApi.remove(item.id)
      toast.success('Convocation supprimée.')
      fetchData()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div>
      <div className="toolbar">
        <button type="button" className="btn btn--primary" onClick={() => setModalOpen(true)}>
          + Nouvelle convocation
        </button>
      </div>

      {error && <Alert>{error}</Alert>}

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader"><Spinner /></div>
          ) : items.length === 0 ? (
            <EmptyState title="Aucune convocation" description="Aucune convocation enregistrée." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Élève</th>
                    <th>Matricule</th>
                    <th>Classe</th>
                    <th>Date</th>
                    <th>Motif</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td className="cell-strong">{item.etudiant?.prenom} {item.etudiant?.nom}</td>
                      <td className="cell-muted">{item.etudiant?.matricule || '—'}</td>
                      <td><span className="badge">{item.etudiant?.classe?.nom || '—'}</span></td>
                      <td>{formatDate(item.date)}</td>
                      <td className="cell-muted">{item.motif || '—'}</td>
                      <td className="cell-actions">
                        <button type="button" className="btn btn--ghost btn--sm" onClick={() => setDoc(item)}>
                          🖨️ Imprimer
                        </button>
                        <button type="button" className="icon-btn" title="Supprimer" onClick={() => remove(item)}>🗑️</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
        {meta && <Pagination meta={meta} onPageChange={setPage} />}
      </div>

      <Modal
        open={modalOpen}
        title="Nouvelle convocation"
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setModalOpen(false)} disabled={saving}>Annuler</button>
            <button type="submit" form="convocation-form" className="btn btn--primary" disabled={saving}>
              {saving ? 'Enregistrement…' : 'Enregistrer & imprimer'}
            </button>
          </>
        }
      >
        <form id="convocation-form" onSubmit={handleSubmit} noValidate>
          <Field label="Classe" htmlFor="convocation-classe">
            <Select id="convocation-classe" value={classeId} onChange={(e) => { setClasseId(e.target.value); setForm((p) => ({ ...p, etudiant_id: '' })) }}>
              <option value="">Toutes les classes</option>
              {classes.map((c) => (
                <option key={c.id} value={c.id}>{c.nom} ({c.niveau})</option>
              ))}
            </Select>
          </Field>
          <Field label="Rechercher l'élève (nom ou matricule)" htmlFor="convocation-search">
            <Input id="convocation-search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Ex : Ndiaye ou GS-00012" />
          </Field>
          <Field label="Élève" htmlFor="convocation-etudiant" required>
            <Select id="convocation-etudiant" name="etudiant_id" value={form.etudiant_id} onChange={handleChange}>
              <option value="">— Sélectionner —</option>
              {filteredEtudiants.map((e) => (
                <option key={e.id} value={e.id}>{e.prenom} {e.nom} {e.matricule ? `(${e.matricule})` : ''}</option>
              ))}
            </Select>
          </Field>
          <div className="form-grid">
            <Field label="Date" htmlFor="convocation-date" required>
              <Input id="convocation-date" type="date" name="date" value={form.date} onChange={handleChange} />
            </Field>
            <Field label="Motif" htmlFor="convocation-motif">
              <Input id="convocation-motif" name="motif" value={form.motif} onChange={handleChange} placeholder="Ex : discipline" />
            </Field>
          </div>
        </form>
      </Modal>

      <Modal
        open={Boolean(doc)}
        title=""
        onClose={() => setDoc(null)}
        size="lg"
        center
      >
        {doc && (
          <div className="convocation-doc" id="convocation-print">
            <div className="convocation-doc-head">
              <div className="attestation-country">RÉPUBLIQUE DU TCHAD</div>
              <div className="attestation-motto">{etablissement.devise || 'Unité — Travail — Progrès'}</div>
              <div className="attestation-ministry">{etablissement.ministere}</div>
              <div className="attestation-school">{etablissement.nom}</div>
            </div>

            <h2 className="convocation-doc-title">CONVOCATION</h2>

            <p className="attestation-text">Le Chef d'établissement convoque l'élève</p>

            <div className="attestation-student">
              {doc.etudiant?.prenom} {doc.etudiant?.nom}
            </div>
            <div className="attestation-meta">
              Matricule : {doc.etudiant?.matricule || '—'} — Classe : {doc.etudiant?.classe?.nom || '—'}
            </div>

            <p className="attestation-text attestation-text--body">
              à se présenter au bureau le <strong>{formatDate(doc.date)}</strong>
              {doc.motif ? <> pour le motif suivant : <strong>« {doc.motif} »</strong></> : ''}.
            </p>

            <div className="attestation-sign">
              <div className="attestation-date">
                Fait à {etablissement.nom}, le {new Date().toLocaleDateString('fr-FR')}
              </div>
              <div>
                <div className="attestation-sign-block">
                  <span className="attestation-sign-line" />
                  <span>
                    {etablissement.responsable
                      ? `Le Chef d'établissement, ${etablissement.responsable}`
                      : "Le Chef d'établissement"}
                  </span>
                </div>
              </div>
            </div>

            <div className="attestation-print-action no-print">
              <button type="button" className="btn btn--primary btn--sm" onClick={() => window.print()}>
                🖨️ Imprimer / PDF
              </button>
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
