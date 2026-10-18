import { useCallback, useEffect, useState } from 'react'
import { classesApi, dossiersApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'
import { Field, Input, Select } from '../components/Field'

const DOCUMENTS = [
  { key: 'acte_naissance', label: "Acte de naissance" },
  { key: 'photos_identite', label: "Photos d'identité" },
  { key: 'fiche_renseignement', label: 'Fiche de renseignement' },
  { key: 'paiement', label: "Paiement des frais d'examen" },
]

export default function DossiersExamen() {
  const toast = useToast()
  const { user } = useAuth()
  const canEdit = ['admin', 'censeur', 'cpe'].includes(user?.role)

  const [eleves, setEleves] = useState([])
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [classes, setClasses] = useState([])
  const [classeId, setClasseId] = useState('')

  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(null)
  const [files, setFiles] = useState({})
  const [saving, setSaving] = useState(false)

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await dossiersApi.index({ classe_id: classeId || undefined })
      setEleves(res.data.eleves)
      setStats(res.data.statistiques)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [classeId])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
  }, [])

  const openEdit = (eleve) => {
    const d = eleve.dossier
    setEditing(eleve)
    setForm({
      examen: eleve.examen,
      acte_naissance: d?.acte_naissance || false,
      acte_naissance_path: d?.acte_naissance_path || '',
      photos_identite: d?.photos_identite || false,
      photos_identite_path: d?.photos_identite_path || '',
      fiche_renseignement: d?.fiche_renseignement || false,
      fiche_renseignement_path: d?.fiche_renseignement_path || '',
      paiement: d?.paiement || false,
      observations: d?.observations || '',
    })
    setFiles({})
  }

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target
    setForm((prev) => ({ ...prev, [name]: type === 'checkbox' ? checked : value }))
  }

  const handleFile = (key, e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setFiles((prev) => ({ ...prev, [key]: file }))
    setForm((prev) => ({ ...prev, [key]: true }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const fd = new FormData()
      fd.append('examen', form.examen)
      fd.append('acte_naissance', form.acte_naissance ? '1' : '0')
      fd.append('photos_identite', form.photos_identite ? '1' : '0')
      fd.append('fiche_renseignement', form.fiche_renseignement ? '1' : '0')
      fd.append('paiement', form.paiement ? '1' : '0')
      if (form.observations) fd.append('observations', form.observations)
      if (files.acte_naissance) fd.append('acte_naissance_file', files.acte_naissance)
      if (files.photos_identite) fd.append('photos_identite_file', files.photos_identite)
      if (files.fiche_renseignement) fd.append('fiche_renseignement_file', files.fiche_renseignement)

      await dossiersApi.save(editing.id, fd)
      toast.success('Dossier mis à jour avec succès.')
      setEditing(null)
      fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Dossiers d'examen"
        subtitle="Suivi des dossiers de candidature des élèves de 3ème et de Terminale."
        extra={
          <Select value={classeId} onChange={(e) => setClasseId(e.target.value)} className="filter-select" aria-label="Filtrer par classe">
            <option value="">Toutes les classes d'examen</option>
            {classes.filter((c) => ['3ème', 'Terminale'].includes(c.niveau)).map((c) => (
              <option key={c.id} value={c.id}>{c.nom}</option>
            ))}
          </Select>
        }
      />

      {stats && (
        <div className="stat-grid stat-grid--3">
          <div className="stat-card stat-card--indigo">
            <span className="stat-icon">📁</span>
            <div className="stat-body">
              <span className="stat-value">{stats.total}</span>
              <span className="stat-label">Candidats</span>
            </div>
          </div>
          <div className="stat-card stat-card--green">
            <span className="stat-icon">✅</span>
            <div className="stat-body">
              <span className="stat-value">{stats.complets}</span>
              <span className="stat-label">Dossiers complets</span>
            </div>
          </div>
          <div className="stat-card stat-card--rose">
            <span className="stat-icon">⚠️</span>
            <div className="stat-body">
              <span className="stat-value">{stats.incomplets}</span>
              <span className="stat-label">Dossiers incomplets</span>
            </div>
          </div>
        </div>
      )}

      {error && <Alert>{error}</Alert>}

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader"><Spinner /></div>
          ) : eleves.length === 0 ? (
            <EmptyState title="Aucun candidat" description="Aucun élève en classe d'examen." />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Examen</th>
                    <th>Documents</th>
                    <th>Statut</th>
                    {canEdit && <th className="th-actions">Actions</th>}
                  </tr>
                </thead>
                <tbody>
                  {eleves.map((e) => {
                    const d = e.dossier
                    const docsCount = DOCUMENTS.filter((doc) => d?.[doc.key]).length
                    return (
                      <tr key={e.id}>
                        <td className="cell-strong">{e.prenom} {e.nom}</td>
                        <td><span className="badge">{e.classe?.nom}</span></td>
                        <td>
                          <span className={`badge ${e.examen === 'bac' ? 'badge--amber' : 'badge--blue'}`}>
                            {e.examen === 'bac' ? 'Bac' : 'Brevet'}
                          </span>
                        </td>
                        <td>{docsCount}/{DOCUMENTS.length} documents</td>
                        <td>
                          {d?.complet ? (
                            <span className="badge badge--success">Complet</span>
                          ) : (
                            <span className="badge badge--neutral">Incomplet</span>
                          )}
                        </td>
                        {canEdit && (
                          <td className="cell-actions">
                            <button type="button" className="btn btn--ghost btn--sm" onClick={() => openEdit(e)}>
                              Gérer le dossier
                            </button>
                          </td>
                        )}
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      <Modal
        open={Boolean(editing)}
        title={`Dossier de ${editing?.prenom} ${editing?.nom}`}
        onClose={() => setEditing(null)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setEditing(null)} disabled={saving}>Annuler</button>
            <button type="submit" form="dossier-form" className="btn btn--primary" disabled={saving}>
              {saving ? 'Enregistrement…' : 'Enregistrer'}
            </button>
          </>
        }
      >
        {form && (
          <form id="dossier-form" onSubmit={handleSubmit} noValidate>
            <Field label="Examen" htmlFor="examen">
              <Select id="examen" name="examen" value={form.examen} onChange={handleChange}>
                <option value="brevet">Brevet (3ème)</option>
                <option value="bac">Bac (Terminale)</option>
              </Select>
            </Field>
            {DOCUMENTS.map((doc) => (
              <div key={doc.key} className="dossier-doc">
                <label className="checkbox-label">
                  <input
                    type="checkbox"
                    name={doc.key}
                    checked={form[doc.key]}
                    onChange={handleChange}
                  />
                  {doc.label}
                </label>
                {doc.key !== 'paiement' && (
                  <>
                    <label className="photo-upload-label">
                      📎 {form[`${doc.key}_path`] ? 'Remplacer' : 'Téléverser'}
                      <input
                        type="file"
                        accept={doc.key === 'photos_identite' ? 'image/*' : 'image/*,.pdf'}
                        onChange={(e) => handleFile(doc.key, e)}
                        style={{ display: 'none' }}
                      />
                    </label>
                    {form[`${doc.key}_path`] && (
                      <a
                        className="dossier-file-link"
                        href={`/${form[`${doc.key}_path`]}`}
                        target="_blank"
                        rel="noreferrer"
                      >
                        👁️ Voir
                      </a>
                    )}
                  </>
                )}
              </div>
            ))}
            <Field label="Observations" htmlFor="observations">
              <Input id="observations" name="observations" value={form.observations} onChange={handleChange} placeholder="Facultatif" />
            </Field>
          </form>
        )}
      </Modal>
    </div>
  )
}
