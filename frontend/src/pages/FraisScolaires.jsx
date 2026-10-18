import { useCallback, useEffect, useState } from 'react'
import { classesApi, etudiantsApi, fraisScolairesApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { Field, Input, Select } from '../components/Field'

const MODES = [
  { value: 'especes', label: 'Espèces' },
  { value: 'virement', label: 'Virement' },
  { value: 'cheque', label: 'Chèque' },
  { value: 'mobile_money', label: 'Mobile Money' },
]

function money(v) {
  return new Intl.NumberFormat('fr-FR').format(Number(v || 0))
}

function statutBadge(frais) {
  if (frais.montant_paye >= frais.montant_total && frais.montant_total > 0) {
    return { label: 'Payé', cls: 'badge--success' }
  }
  if (frais.montant_paye > 0) {
    return { label: 'Partiel', cls: 'badge--amber' }
  }
  return { label: 'Impayé', cls: 'badge--danger' }
}

export default function FraisScolaires() {
  const toast = useToast()
  const { user } = useAuth()
  const { etablissement } = useEtablissement()
  const canEdit = ['admin', 'gestionnaire', 'agent_comptable'].includes(user?.role)
  const annee = etablissement.annee_scolaire || '2025-2026'

  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [stats, setStats] = useState(null)

  const [search, setSearch] = useState('')
  const [classeId, setClasseId] = useState('')
  const [statut, setStatut] = useState('')
  const [page, setPage] = useState(1)

  const [classes, setClasses] = useState([])
  const [etudiants, setEtudiants] = useState([])

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState({ etudiant_id: '', annee_scolaire: annee, montant_total: '' })
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [paiementFor, setPaiementFor] = useState(null)
  const [paiementForm, setPaiementForm] = useState({
    montant: '',
    date_paiement: new Date().toISOString().slice(0, 10),
    mode: 'especes',
  })
  const [savingPaiement, setSavingPaiement] = useState(false)

  const [deleting, setDeleting] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  const [bulkOpen, setBulkOpen] = useState(false)
  const [bulkForm, setBulkForm] = useState({ classe_id: '', montant_total: '' })
  const [savingBulk, setSavingBulk] = useState(false)

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
    etudiantsApi.list({ per_page: 1000 }).then((res) => setEtudiants(res.data.data)).catch(() => {})
  }, [])

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await fraisScolairesApi.list({
        search: search || undefined,
        classe_id: classeId || undefined,
        statut: statut || undefined,
        annee_scolaire: annee,
        page,
      })
      setItems(res.data.data)
      setMeta(res.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [search, classeId, statut, annee, page])

  useEffect(() => {
    const timer = setTimeout(fetchData, 300)
    return () => clearTimeout(timer)
  }, [fetchData])

  useEffect(() => {
    fraisScolairesApi.stats().then((res) => setStats(res.data)).catch(() => {})
  }, [items])

  const openCreate = () => {
    setEditing(null)
    setForm({ etudiant_id: '', annee_scolaire: annee, montant_total: '' })
    setFormErrors({})
    setModalOpen(true)
  }

  const openEdit = (item) => {
    setEditing(item)
    setForm({
      etudiant_id: item.etudiant_id,
      annee_scolaire: item.annee_scolaire,
      montant_total: item.montant_total,
    })
    setFormErrors({})
    setModalOpen(true)
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
      if (editing) {
        await fraisScolairesApi.update(editing.id, { ...form, etudiant_id: editing.etudiant_id })
        toast.success('Frais mis à jour avec succès.')
      } else {
        await fraisScolairesApi.create(form)
        toast.success('Frais scolaires enregistrés.')
      }
      setModalOpen(false)
      fetchData()
    } catch (err) {
      setFormErrors(err.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const openPaiement = (item) => {
    setPaiementFor(item)
    setPaiementForm({
      montant: item.reste > 0 ? item.reste : '',
      date_paiement: new Date().toISOString().slice(0, 10),
      mode: 'especes',
    })
  }

  const handlePaiementSubmit = async (e) => {
    e.preventDefault()
    setSavingPaiement(true)
    try {
      await fraisScolairesApi.addPaiement(paiementFor.id, paiementForm)
      toast.success('Paiement enregistré avec succès.')
      setPaiementFor(null)
      fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSavingPaiement(false)
    }
  }

  const confirmDelete = async () => {
    setDeleteLoading(true)
    try {
      await fraisScolairesApi.remove(deleting.id)
      toast.success('Frais supprimés.')
      setDeleting(null)
      fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDeleteLoading(false)
    }
  }

  const handleBulkSubmit = async (e) => {
    e.preventDefault()
    setSavingBulk(true)
    try {
      const res = await fraisScolairesApi.bulkInit({ ...bulkForm, annee_scolaire: annee })
      toast.success(res.data.message)
      setBulkOpen(false)
      fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSavingBulk(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Frais scolaires"
        subtitle="Suivi des frais de scolarité, des paiements et des restes à payer."
        search={search}
        onSearch={setSearch}
        searchPlaceholder="Rechercher un élève…"
        actionLabel={canEdit ? 'Ajouter des frais' : null}
        onAction={canEdit ? openCreate : undefined}
        extra={
          <>
            {canEdit && (
              <button type="button" className="btn btn--ghost" onClick={() => setBulkOpen(true)}>
                ⚙️ Initialiser une classe
              </button>
            )}
            <Select
              value={classeId}
              onChange={(e) => { setClasseId(e.target.value); setPage(1) }}
              className="filter-select"
              aria-label="Filtrer par classe"
            >
              <option value="">Toutes les classes</option>
              {classes.map((c) => (
                <option key={c.id} value={c.id}>{c.nom}</option>
              ))}
            </Select>
            <Select
              value={statut}
              onChange={(e) => { setStatut(e.target.value); setPage(1) }}
              className="filter-select"
              aria-label="Filtrer par statut"
            >
              <option value="">Tous les statuts</option>
              <option value="paye">Payé</option>
              <option value="partiel">Partiel</option>
              <option value="impaye">Impayé</option>
            </Select>
          </>
        }
      />

      {stats && (
        <div className="stat-grid stat-grid--4">
          <div className="stat-card stat-card--indigo">
            <span className="stat-icon">💰</span>
            <div className="stat-body">
              <span className="stat-value">{money(stats.montant_total)}</span>
              <span className="stat-label">Montant attendu</span>
            </div>
          </div>
          <div className="stat-card stat-card--green">
            <span className="stat-icon">✅</span>
            <div className="stat-body">
              <span className="stat-value">{money(stats.montant_paye)}</span>
              <span className="stat-label">Encaissé</span>
            </div>
          </div>
          <div className="stat-card stat-card--rose">
            <span className="stat-icon">⚠️</span>
            <div className="stat-body">
              <span className="stat-value">{money(stats.reste)}</span>
              <span className="stat-label">Reste à payer</span>
            </div>
          </div>
          <div className="stat-card stat-card--amber">
            <span className="stat-icon">🎓</span>
            <div className="stat-body">
              <span className="stat-value">{stats.effectif}</span>
              <span className="stat-label">Élèves suivis</span>
            </div>
          </div>
        </div>
      )}

      {error && <Alert>{error}</Alert>}

      <div className="card">
        <div className="card-body">
          {loading ? (
            <div className="page-loader"><Spinner /></div>
          ) : items.length === 0 ? (
            <EmptyState
              title="Aucun frais enregistré"
              description="Ajoutez les frais de scolarité de vos élèves pour suivre les paiements."
            />
          ) : (
            <div className="table-wrapper">
              <table className="table">
                <thead>
                  <tr>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th>Montant total</th>
                    <th>Payé</th>
                    <th>Reste</th>
                    <th>Statut</th>
                    <th className="th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((item) => {
                    const s = statutBadge(item)
                    return (
                      <tr key={item.id}>
                        <td className="cell-strong">
                          {item.etudiant?.prenom} {item.etudiant?.nom}
                          <div className="muted" style={{ fontSize: 12 }}>{item.etudiant?.matricule}</div>
                        </td>
                        <td><span className="badge">{item.etudiant?.classe?.nom || '—'}</span></td>
                        <td>{money(item.montant_total)}</td>
                        <td className="cell-strong">{money(item.montant_paye)}</td>
                        <td className={item.reste > 0 ? 'cell-danger' : ''}>{money(item.reste)}</td>
                        <td><span className={`badge ${s.cls}`}>{s.label}</span></td>
                        <td className="cell-actions">
                          {canEdit && (
                            <>
                              <button type="button" className="btn btn--ghost btn--sm" onClick={() => openPaiement(item)}>
                                Paiement
                              </button>
                              <button type="button" className="icon-btn" title="Modifier" onClick={() => openEdit(item)}>✏️</button>
                              <button type="button" className="icon-btn" title="Supprimer" onClick={() => setDeleting(item)}>🗑️</button>
                            </>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
        {meta && <Pagination meta={meta} onPageChange={setPage} />}
      </div>

      <Modal
        open={modalOpen}
        title={editing ? 'Modifier les frais' : 'Ajouter des frais'}
        onClose={() => setModalOpen(false)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setModalOpen(false)} disabled={saving}>Annuler</button>
            <button type="submit" form="frais-form" className="btn btn--primary" disabled={saving}>
              {saving ? 'Enregistrement…' : 'Enregistrer'}
            </button>
          </>
        }
      >
        <form id="frais-form" onSubmit={handleSubmit} noValidate>
          {!editing && (
            <Field label="Élève" htmlFor="etudiant_id" required error={formErrors.etudiant_id?.[0]}>
              <Select id="etudiant_id" name="etudiant_id" value={form.etudiant_id} onChange={handleChange}>
                <option value="">— Sélectionner —</option>
                {etudiants.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.prenom} {e.nom} {e.matricule ? `(${e.matricule})` : ''}
                  </option>
                ))}
              </Select>
            </Field>
          )}
          <div className="form-grid">
            <Field label="Année scolaire" htmlFor="annee_scolaire" required error={formErrors.annee_scolaire?.[0]}>
              <Input id="annee_scolaire" name="annee_scolaire" value={form.annee_scolaire} onChange={handleChange} />
            </Field>
            <Field label="Montant total (FCFA)" htmlFor="montant_total" required error={formErrors.montant_total?.[0]}>
              <Input id="montant_total" type="number" min="0" step="0.01" name="montant_total" value={form.montant_total} onChange={handleChange} />
            </Field>
          </div>
        </form>
      </Modal>

      <Modal
        open={Boolean(paiementFor)}
        title={`Enregistrer un paiement — ${paiementFor?.etudiant?.prenom} ${paiementFor?.etudiant?.nom}`}
        onClose={() => setPaiementFor(null)}
        size="sm"
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setPaiementFor(null)} disabled={savingPaiement}>Annuler</button>
            <button type="submit" form="paiement-form" className="btn btn--primary" disabled={savingPaiement}>
              {savingPaiement ? 'Enregistrement…' : 'Encaisser'}
            </button>
          </>
        }
      >
        <form id="paiement-form" onSubmit={handlePaiementSubmit} noValidate>
          <Field label="Montant (FCFA)" htmlFor="paiement-montant" required>
            <Input
              id="paiement-montant"
              type="number"
              min="0"
              step="0.01"
              name="montant"
              value={paiementForm.montant}
              onChange={(e) => setPaiementForm((prev) => ({ ...prev, montant: e.target.value }))}
            />
          </Field>
          <div className="form-grid">
            <Field label="Date" htmlFor="paiement-date" required>
              <Input
                id="paiement-date"
                type="date"
                name="date_paiement"
                value={paiementForm.date_paiement}
                onChange={(e) => setPaiementForm((prev) => ({ ...prev, date_paiement: e.target.value }))}
              />
            </Field>
            <Field label="Mode" htmlFor="paiement-mode">
              <Select
                id="paiement-mode"
                name="mode"
                value={paiementForm.mode}
                onChange={(e) => setPaiementForm((prev) => ({ ...prev, mode: e.target.value }))}
              >
                {MODES.map((m) => (
                  <option key={m.value} value={m.value}>{m.label}</option>
                ))}
              </Select>
            </Field>
          </div>
        </form>
      </Modal>

      <Modal
        open={bulkOpen}
        title="Initialiser les frais d'une classe"
        onClose={() => setBulkOpen(false)}
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setBulkOpen(false)} disabled={savingBulk}>Annuler</button>
            <button type="submit" form="bulk-frais-form" className="btn btn--primary" disabled={savingBulk}>
              {savingBulk ? 'Initialisation…' : 'Initialiser'}
            </button>
          </>
        }
      >
        <form id="bulk-frais-form" onSubmit={handleBulkSubmit} noValidate>
          <p className="field-hint" style={{ marginBottom: 14 }}>
            Crée automatiquement les frais de {annee} pour tous les élèves de la classe qui n'en ont pas encore.
          </p>
          <Field label="Classe" htmlFor="bulk-classe" required>
            <Select
              id="bulk-classe"
              name="classe_id"
              value={bulkForm.classe_id}
              onChange={(e) => setBulkForm((prev) => ({ ...prev, classe_id: e.target.value }))}
            >
              <option value="">— Sélectionner —</option>
              {classes.map((c) => (
                <option key={c.id} value={c.id}>{c.nom} ({c.niveau})</option>
              ))}
            </Select>
          </Field>
          <Field label="Montant total (FCFA)" htmlFor="bulk-montant" required>
            <Input
              id="bulk-montant"
              type="number"
              min="0"
              step="0.01"
              name="montant_total"
              value={bulkForm.montant_total}
              onChange={(e) => setBulkForm((prev) => ({ ...prev, montant_total: e.target.value }))}
            />
          </Field>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer les frais"
        message="Voulez-vous vraiment supprimer ces frais scolaires ?"
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
