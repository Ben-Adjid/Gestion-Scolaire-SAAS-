import { useCallback, useEffect, useState } from 'react'
import { classesApi, dossierInscriptionApi, etudiantsApi, inscriptionsApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { Field, Input, Select } from '../components/Field'
import { formatDate } from '../utils/format'

const STATUTS = [
  { value: 'active', label: 'Active' },
  { value: 'terminee', label: 'Terminée' },
  { value: 'annulee', label: 'Annulée' },
]

const EMPTY_NEW = {
  nom: '',
  prenom: '',
  date_naissance: '',
  genre: '',
  classe_id: '',
  annee_scolaire: '2025-2026',
  date_inscription: new Date().toISOString().slice(0, 10),
}

const EMPTY_OLD = {
  etudiant_id: '',
  classe_id: '',
  annee_scolaire: '2025-2026',
  date_inscription: new Date().toISOString().slice(0, 10),
}

const EMPTY_DOSSIER = {
  acte_naissance: false,
  acte_naissance_path: '',
  bulletin: false,
  bulletin_path: '',
  photo: false,
  photo_path: '',
  observations: '',
}

const DOSSIER_DOCS = [
  { key: 'acte_naissance', label: "Acte de naissance" },
  { key: 'bulletin', label: 'Bulletin de notes (dernier trimestre)' },
  { key: 'photo', label: "Photo d'identité récente" },
]

function dossierFrom(d) {
  if (!d) return EMPTY_DOSSIER
  return {
    acte_naissance: !!d.acte_naissance,
    acte_naissance_path: d.acte_naissance_path || '',
    bulletin: !!d.bulletin,
    bulletin_path: d.bulletin_path || '',
    photo: !!d.photo,
    photo_path: d.photo_path || '',
    observations: d.observations || '',
  }
}

export default function Inscriptions() {
  const toast = useToast()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [search, setSearch] = useState('')
  const [annee, setAnnee] = useState('')
  const [page, setPage] = useState(1)

  const [etudiants, setEtudiants] = useState([])
  const [classes, setClasses] = useState([])

  const [modalOpen, setModalOpen] = useState(false)
  const [mode, setMode] = useState('nouveau')
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY_NEW)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [deleting, setDeleting] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  const [matriculeSearch, setMatriculeSearch] = useState('')
  const [lookupResult, setLookupResult] = useState(null)
  const [lookupLoading, setLookupLoading] = useState(false)
  const [lookupError, setLookupError] = useState(null)

  const [dossier, setDossier] = useState(EMPTY_DOSSIER)
  const [docFiles, setDocFiles] = useState({ acte_naissance: null, bulletin: null, photo: null })

  useEffect(() => {
    etudiantsApi.list({ per_page: 1000 }).then((res) => setEtudiants(res.data.data)).catch(() => {})
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
  }, [])

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await inscriptionsApi.list({
        search: search || undefined,
        annee_scolaire: annee || undefined,
        page,
      })
      setItems(res.data.data)
      setMeta(res.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [search, annee, page])

  useEffect(() => {
    const timer = setTimeout(fetchData, 300)
    return () => clearTimeout(timer)
  }, [fetchData])

  const openCreate = (m) => {
    setEditing(null)
    setMode(m)
    setForm(m === 'nouveau' ? EMPTY_NEW : EMPTY_OLD)
    setFormErrors({})
    setMatriculeSearch('')
    setLookupResult(null)
    setLookupError(null)
    setDossier(EMPTY_DOSSIER)
    setDocFiles({ acte_naissance: null, bulletin: null, photo: null })
    setModalOpen(true)
  }

  const searchMatricule = async () => {
    if (!matriculeSearch.trim()) return
    setLookupLoading(true)
    setLookupError(null)
    setLookupResult(null)
    try {
      const res = await etudiantsApi.lookup(matriculeSearch.trim())
      setLookupResult(res.data)
      setForm((prev) => ({ ...prev, etudiant_id: res.data.etudiant.id }))
      setDossier(dossierFrom(res.data.dossier))
      setDocFiles({ acte_naissance: null, bulletin: null, photo: null })
    } catch (err) {
      setLookupError(err.response?.data?.message || err.message)
    } finally {
      setLookupLoading(false)
    }
  }

  const openEdit = (item) => {
    setEditing(item)
    setMode('ancien')
    setForm({
      etudiant_id: item.etudiant_id,
      classe_id: item.classe_id,
      annee_scolaire: item.annee_scolaire,
      date_inscription: item.date_inscription || '',
    })
    setFormErrors({})
    setDossier(EMPTY_DOSSIER)
    setMatriculeSearch('')
    setLookupResult(null)
    setLookupError(null)
    dossierInscriptionApi
      .show(item.etudiant_id)
      .then((res) => {
        setDossier(dossierFrom(res.data))
      })
      .catch(() => {})
    setDocFiles({ acte_naissance: null, bulletin: null, photo: null })
    setModalOpen(true)
  }

  const handleDossierChange = (e) => {
    const { name, value, type, checked } = e.target
    setDossier((prev) => ({ ...prev, [name]: type === 'checkbox' ? checked : value }))
  }

  const handleDocFile = (key, e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setDocFiles((prev) => ({ ...prev, [key]: file }))
    setDossier((prev) => ({ ...prev, [key]: true }))
  }

  const saveDossier = async (etudiantId) => {
    const fd = new FormData()
    fd.append('acte_naissance', dossier.acte_naissance ? '1' : '0')
    fd.append('bulletin', dossier.bulletin ? '1' : '0')
    fd.append('photo', dossier.photo ? '1' : '0')
    if (dossier.observations) fd.append('observations', dossier.observations)
    if (docFiles.acte_naissance) fd.append('acte_naissance_file', docFiles.acte_naissance)
    if (docFiles.bulletin) fd.append('bulletin_file', docFiles.bulletin)
    if (docFiles.photo) fd.append('photo_file', docFiles.photo)
    await dossierInscriptionApi.save(etudiantId, fd)
  }

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
    setFormErrors((prev) => ({ ...prev, [name]: undefined }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setFormErrors({})
    try {
      let etudiantId = form.etudiant_id
      let message = 'Inscription enregistrée avec succès.'

      if (editing) {
        await inscriptionsApi.update(editing.id, { ...form, statut: editing.statut, type: editing.type })
        message = 'Inscription mise à jour avec succès.'
      } else if (mode === 'nouveau') {
        const res = await inscriptionsApi.create({
          nom: form.nom,
          prenom: form.prenom,
          date_naissance: form.date_naissance || null,
          genre: form.genre || null,
          classe_id: form.classe_id,
          annee_scolaire: form.annee_scolaire,
          date_inscription: form.date_inscription,
          statut: 'active',
          type: 'nouveau',
        })
        etudiantId = res.data.inscription.etudiant_id
        message = 'Élève inscrit avec succès.'
      } else {
        await inscriptionsApi.create({
          etudiant_id: form.etudiant_id,
          classe_id: form.classe_id,
          annee_scolaire: form.annee_scolaire,
          date_inscription: form.date_inscription,
          statut: 'active',
          type: 'ancien',
        })
        message = 'Réinscription enregistrée avec succès.'
      }

      if (etudiantId) {
        try {
          await saveDossier(etudiantId)
        } catch {
          // Le dossier peut être complété plus tard.
        }
      }

      toast.success(message)
      setModalOpen(false)
      fetchData()
    } catch (err) {
      setFormErrors(err.errors || {})
      if (!err.errors || !Object.keys(err.errors).length) {
        toast.error(err.message)
      }
    } finally {
      setSaving(false)
    }
  }

  const confirmDelete = async () => {
    setDeleteLoading(true)
    try {
      await inscriptionsApi.remove(deleting.id)
      toast.success('Inscription supprimée avec succès.')
      setDeleting(null)
      fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDeleteLoading(false)
    }
  }

  const statutBadge = (statut) =>
    statut === 'active'
      ? 'badge--success'
      : statut === 'annulee'
        ? 'badge--danger'
        : 'badge--neutral'

  return (
    <div>
      <PageHeader
        title="Inscriptions"
        subtitle="Inscrivez un nouvel élève ou réinscrivez un ancien élève."
        search={search}
        onSearch={setSearch}
        searchPlaceholder="Rechercher un élève…"
        extra={
          <Input
            type="text"
            name="annee"
            value={annee}
            onChange={(e) => {
              setAnnee(e.target.value)
              setPage(1)
            }}
            placeholder="Année (ex. 2025-2026)"
            className="filter-input"
          />
        }
      />

      {error && <Alert>{error}</Alert>}

      <div className="inscription-actions">
        <button type="button" className="btn btn--primary" onClick={() => openCreate('nouveau')}>
          + Nouvel élève
        </button>
        <button type="button" className="btn btn--ghost" onClick={() => openCreate('ancien')}>
          ↻ Réinscrire un ancien élève
        </button>
      </div>

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader">
              <Spinner />
            </div>
          ) : items.length === 0 ? (
            <EmptyState
              title="Aucune inscription"
              description="Commencez par inscrire vos premiers élèves."
            />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Élève</th>
                    <th>Matricule</th>
                    <th>Classe</th>
                    <th>Type</th>
                    <th>Année</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => (
                    <tr key={item.id}>
                      <td className="cell-strong">
                        {item.etudiant?.prenom} {item.etudiant?.nom}
                      </td>
                      <td className="cell-muted">{item.etudiant?.matricule || '—'}</td>
                      <td>
                        <span className="badge">{item.classe?.nom}</span>
                      </td>
                      <td>
                        <span className={`badge ${item.type === 'ancien' ? 'badge--amber' : 'badge--blue'}`}>
                          {item.type === 'ancien' ? 'Ancien élève' : 'Nouvel élève'}
                        </span>
                      </td>
                      <td>{item.annee_scolaire}</td>
                      <td>{formatDate(item.date_inscription)}</td>
                      <td>
                        <span className={`badge ${statutBadge(item.statut)}`}>
                          {STATUTS.find((s) => s.value === item.statut)?.label || item.statut}
                        </span>
                      </td>
                      <td className="cell-actions">
                        <button
                          type="button"
                          className="icon-btn"
                          title="Modifier"
                          onClick={() => openEdit(item)}
                        >
                          ✏️
                        </button>
                        <button
                          type="button"
                          className="icon-btn"
                          title="Supprimer"
                          onClick={() => setDeleting(item)}
                        >
                          🗑️
                        </button>
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
        title={
          editing
            ? "Modifier l'inscription"
            : mode === 'nouveau'
              ? 'Inscrire un nouvel élève'
              : 'Réinscrire un ancien élève'
        }
        onClose={() => setModalOpen(false)}
        size="lg"
        footer={
          <>
            <button
              type="button"
              className="btn btn--ghost"
              onClick={() => setModalOpen(false)}
              disabled={saving}
            >
              Annuler
            </button>
            <button
              type="submit"
              form="inscription-form"
              className="btn btn--primary"
              disabled={saving}
            >
              {saving ? 'Enregistrement…' : mode === 'nouveau' && !editing ? 'Inscrire' : 'Enregistrer'}
            </button>
          </>
        }
      >
        <form id="inscription-form" onSubmit={handleSubmit} noValidate>
          {!editing && mode === 'nouveau' && (
            <>
              <div className="form-grid">
                <Field label="Prénom" htmlFor="prenom" required error={formErrors.prenom?.[0]}>
                  <Input
                    id="prenom"
                    name="prenom"
                    value={form.prenom}
                    onChange={handleChange}
                    placeholder="Ex : Moussa"
                    error={formErrors.prenom?.[0]}
                  />
                </Field>
                <Field label="Nom" htmlFor="nom" required error={formErrors.nom?.[0]}>
                  <Input
                    id="nom"
                    name="nom"
                    value={form.nom}
                    onChange={handleChange}
                    placeholder="Ex : Ndiaye"
                    error={formErrors.nom?.[0]}
                  />
                </Field>
              </div>
              <div className="form-grid">
                <Field
                  label="Date de naissance"
                  htmlFor="date_naissance"
                  error={formErrors.date_naissance?.[0]}
                >
                  <Input
                    id="date_naissance"
                    type="date"
                    name="date_naissance"
                    value={form.date_naissance}
                    onChange={handleChange}
                    error={formErrors.date_naissance?.[0]}
                  />
                </Field>
                <Field label="Genre" htmlFor="genre" error={formErrors.genre?.[0]}>
                  <Select id="genre" name="genre" value={form.genre} onChange={handleChange}>
                    <option value="">— Sélectionner —</option>
                    <option value="M">Masculin</option>
                    <option value="F">Féminin</option>
                  </Select>
                </Field>
              </div>
            </>
          )}

          {editing ? (
            <Field label="Élève" htmlFor="etudiant_id" required error={formErrors.etudiant_id?.[0]}>
              <Select
                id="etudiant_id"
                name="etudiant_id"
                value={form.etudiant_id}
                onChange={handleChange}
                disabled
              >
                <option value="">— Sélectionner un élève —</option>
                {etudiants.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.prenom} {e.nom} {e.matricule ? `(${e.matricule})` : ''}
                  </option>
                ))}
              </Select>
            </Field>
          ) : mode === 'ancien' ? (
            <div className="lookup-box">
              <Field label="Rechercher par matricule" htmlFor="matricule-search">
                <div className="lookup-row">
                  <Input
                    id="matricule-search"
                    value={matriculeSearch}
                    onChange={(e) => setMatriculeSearch(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), searchMatricule())}
                    placeholder="Ex : GS-00012"
                  />
                  <button
                    type="button"
                    className="btn btn--primary"
                    onClick={searchMatricule}
                    disabled={lookupLoading}
                  >
                    {lookupLoading ? 'Recherche…' : 'Rechercher'}
                  </button>
                </div>
              </Field>

              {lookupError && <Alert type="error">{lookupError}</Alert>}

              {lookupResult && (
                <div className="lookup-result">
                  <div className="lookup-result-head">
                    <span className="lookup-avatar">
                      {(lookupResult.etudiant.prenom?.[0] || '') + (lookupResult.etudiant.nom?.[0] || '')}
                    </span>
                    <div>
                      <strong>
                        {lookupResult.etudiant.prenom} {lookupResult.etudiant.nom}
                      </strong>
                      <p className="muted">
                        Matricule {lookupResult.etudiant.matricule} •{' '}
                        {lookupResult.etudiant.classe?.nom || 'Sans classe'}
                      </p>
                    </div>
                  </div>
                  <div className="lookup-result-stats">
                    <div>
                      <span className="lookup-label">Moyenne annuelle</span>
                      <span className="lookup-value">
                        {lookupResult.moyenne_annuelle != null
                          ? `${lookupResult.moyenne_annuelle}/20`
                          : '—'}
                      </span>
                    </div>
                    <div>
                      <span className="lookup-label">Décision</span>
                      <span
                        className={`badge ${
                          lookupResult.decision.statut === 'admis'
                            ? 'badge--success'
                            : lookupResult.decision.statut === 'redouble'
                              ? 'badge--danger'
                              : 'badge--neutral'
                        }`}
                      >
                        {lookupResult.decision.label}
                      </span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          ) : null}

          <div className="form-grid">
            <Field label="Classe" htmlFor="classe_id" required error={formErrors.classe_id?.[0]}>
              <Select id="classe_id" name="classe_id" value={form.classe_id} onChange={handleChange}>
                <option value="">— Sélectionner —</option>
                {classes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.nom} ({c.niveau})
                  </option>
                ))}
              </Select>
            </Field>
            <Field
              label="Année scolaire"
              htmlFor="annee_scolaire"
              required
              error={formErrors.annee_scolaire?.[0]}
            >
              <Input
                id="annee_scolaire"
                name="annee_scolaire"
                value={form.annee_scolaire}
                onChange={handleChange}
                placeholder="2025-2026"
                error={formErrors.annee_scolaire?.[0]}
              />
            </Field>
          </div>
          <Field
            label="Date d'inscription"
            htmlFor="date_inscription"
            required
            error={formErrors.date_inscription?.[0]}
          >
            <Input
              id="date_inscription"
              type="date"
              name="date_inscription"
              value={form.date_inscription}
              onChange={handleChange}
              error={formErrors.date_inscription?.[0]}
            />
          </Field>

          <div className="dossier-section">
            <h4 className="dossier-title">📁 Dossier d'inscription</h4>
            <p className="field-hint">
              {mode === 'ancien' && !editing
                ? 'Le dossier existant est déjà rempli. Mettez à jour uniquement ce qui change (ex. photo).'
                : "Documents à téléverser lors de l'inscription."}
            </p>
            {DOSSIER_DOCS.map((doc) => (
              <div key={doc.key} className="dossier-doc">
                <label className="checkbox-label">
                  <input
                    type="checkbox"
                    name={doc.key}
                    checked={dossier[doc.key]}
                    onChange={handleDossierChange}
                  />
                  {doc.label}
                </label>
                <label className="photo-upload-label">
                  📎 {dossier[`${doc.key}_path`] ? 'Remplacer le fichier' : 'Téléverser'}
                  <input
                    type="file"
                    accept={doc.key === 'photo' ? 'image/*' : 'image/*,.pdf'}
                    onChange={(e) => handleDocFile(doc.key, e)}
                    style={{ display: 'none' }}
                  />
                </label>
                {dossier[`${doc.key}_path`] && (
                  <a
                    className="dossier-file-link"
                    href={`/${dossier[`${doc.key}_path`]}`}
                    target="_blank"
                    rel="noreferrer"
                  >
                    👁️ Voir le fichier
                  </a>
                )}
              </div>
            ))}

            <Field label="Observations" htmlFor="dossier-observations">
              <Input
                id="dossier-observations"
                name="observations"
                value={dossier.observations}
                onChange={handleDossierChange}
                placeholder="Ex : photo à renouveler, document manquant…"
              />
            </Field>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer l'inscription"
        message="Voulez-vous vraiment supprimer cette inscription ?"
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
