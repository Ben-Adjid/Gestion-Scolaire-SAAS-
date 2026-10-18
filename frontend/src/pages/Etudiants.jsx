import { useCallback, useEffect, useState } from 'react'
import { classesApi, etudiantsApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Modal from '../components/Modal'
import ConfirmDialog from '../components/ConfirmDialog'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { Field, Input, Select } from '../components/Field'
import { downloadCsv } from '../utils/csv'
import { downloadTablePdf } from '../utils/pdf'
import { formatDate } from '../utils/format'

const EMPTY_FORM = {
  matricule: '',
  nom: '',
  prenom: '',
  date_naissance: '',
  genre: '',
  email: '',
  telephone: '',
  adresse: '',
  classe_id: '',
  annee_scolaire: '2025-2026',
}

export default function Etudiants() {
  const toast = useToast()
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  const [search, setSearch] = useState('')
  const [filters, setFilters] = useState({ classe_id: '', genre: '' })
  const [page, setPage] = useState(1)
  const [view, setView] = useState('table')

  const [classes, setClasses] = useState([])
  const [grouped, setGrouped] = useState({})

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY_FORM)
  const [formErrors, setFormErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [deleting, setDeleting] = useState(null)
  const [deleteLoading, setDeleteLoading] = useState(false)

  useEffect(() => {
    classesApi.list({ per_page: 1000 }).then((res) => setClasses(res.data.data)).catch(() => {})
  }, [])

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await etudiantsApi.list({
        search: search || undefined,
        classe_id: filters.classe_id || undefined,
        genre: filters.genre || undefined,
        page,
      })
      setItems(res.data.data)
      setMeta(res.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [search, filters, page])

  const fetchGrouped = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const res = await etudiantsApi.list({
        search: search || undefined,
        classe_id: filters.classe_id || undefined,
        genre: filters.genre || undefined,
        per_page: 1000,
      })
      const byClasse = {}
      res.data.data.forEach((e) => {
        const key = e.classe ? `${e.classe.nom} (${e.classe.niveau})` : 'Sans classe'
        if (!byClasse[key]) byClasse[key] = []
        byClasse[key].push(e)
      })
      Object.keys(byClasse).forEach((k) => {
        byClasse[k].sort((a, b) => a.nom.localeCompare(b.nom) || a.prenom.localeCompare(b.prenom))
      })
      setGrouped(byClasse)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [search, filters])

  useEffect(() => {
    const timer = setTimeout(() => (view === 'grouped' ? fetchGrouped() : fetchData()), 300)
    return () => clearTimeout(timer)
  }, [view, fetchGrouped, fetchData])

  const openCreate = () => {
    setEditing(null)
    setForm(EMPTY_FORM)
    setFormErrors({})
    setModalOpen(true)
  }

  const openEdit = (item) => {
    setEditing(item)
    setForm({
      matricule: item.matricule || '',
      nom: item.nom,
      prenom: item.prenom,
      date_naissance: item.date_naissance || '',
      genre: item.genre || '',
      email: item.email || '',
      telephone: item.telephone || '',
      adresse: item.adresse || '',
      classe_id: item.classe_id || '',
      annee_scolaire: item.annee_scolaire || '2025-2026',
    })
    setFormErrors({})
    setModalOpen(true)
  }

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
    setFormErrors((prev) => ({ ...prev, [name]: undefined }))
  }

  const handleFilterChange = (e) => {
    const { name, value } = e.target
    setFilters((prev) => ({ ...prev, [name]: value }))
    setPage(1)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setFormErrors({})
    const payload = { ...form }
    if (!payload.date_naissance) payload.date_naissance = null
    if (!payload.classe_id) payload.classe_id = null

    try {
      if (editing) {
        await etudiantsApi.update(editing.id, payload)
        toast.success('Élève mis à jour avec succès.')
      } else {
        await etudiantsApi.create(payload)
        toast.success('Élève créé avec succès.')
      }
      setModalOpen(false)
      view === 'grouped' ? fetchGrouped() : fetchData()
    } catch (err) {
      setFormErrors(err.errors || {})
    } finally {
      setSaving(false)
    }
  }

  const confirmDelete = async () => {
    setDeleteLoading(true)
    try {
      await etudiantsApi.remove(deleting.id)
      toast.success('Élève supprimé avec succès.')
      setDeleting(null)
      view === 'grouped' ? fetchGrouped() : fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDeleteLoading(false)
    }
  }

  const genreLabel = (genre) => (genre === 'M' ? 'Masculin' : genre === 'F' ? 'Féminin' : '—')

  const handleDownload = () => {
    const header = ['Nom', 'Prénom', 'Matricule', 'Classe', 'Genre', 'Date de naissance', 'Téléphone', 'Email']
    const rows = []
    const push = (e) =>
      rows.push([
        e.nom,
        e.prenom,
        e.matricule || '',
        e.classe?.nom || 'Sans classe',
        genreLabel(e.genre),
        formatDate(e.date_naissance),
        e.telephone || '',
        e.email || '',
      ])

    if (view === 'grouped') {
      Object.values(grouped).forEach((list) => list.forEach(push))
    } else {
      items.forEach(push)
    }

    downloadCsv(`eleves-${new Date().toISOString().slice(0, 10)}.csv`, [header, ...rows])
  }

  const handleDownloadPdf = () => {
    const columns = ['Nom', 'Prénom', 'Matricule', 'Classe', 'Genre']
    const rows = []
    const push = (e) =>
      rows.push([
        e.nom,
        e.prenom,
        e.matricule || '',
        e.classe?.nom || 'Sans classe',
        genreLabel(e.genre),
      ])

    if (view === 'grouped') {
      Object.values(grouped).forEach((list) => list.forEach(push))
    } else {
      items.forEach(push)
    }

    downloadTablePdf({
      title: 'Liste des élèves',
      subtitle: `Généré le ${new Date().toLocaleDateString('fr-FR')}`,
      columns,
      rows,
      filename: `eleves-${new Date().toISOString().slice(0, 10)}.pdf`,
    })
  }

  const total = Object.values(grouped).reduce((acc, list) => acc + list.length, 0)

  return (
    <div>
      <PageHeader
        title="Élèves"
        subtitle="Gérez et consultez les élèves par classe."
        search={search}
        onSearch={setSearch}
        searchPlaceholder="Rechercher un élève…"
        actionLabel="Nouvel élève"
        onAction={openCreate}
        extra={
          <>
            <Select
              name="classe_id"
              value={filters.classe_id}
              onChange={handleFilterChange}
              className="filter-select"
              aria-label="Filtrer par classe"
            >
              <option value="">Toutes les classes</option>
              {classes.map((classe) => (
                <option key={classe.id} value={classe.id}>
                  {classe.nom}
                </option>
              ))}
            </Select>
            <Select
              name="genre"
              value={filters.genre}
              onChange={handleFilterChange}
              className="filter-select"
              aria-label="Filtrer par genre"
            >
              <option value="">Tous les genres</option>
              <option value="M">Masculin</option>
              <option value="F">Féminin</option>
            </Select>
            <div className="view-toggle no-print">
              <button
                type="button"
                className={`view-btn ${view === 'table' ? 'view-btn--active' : ''}`}
                onClick={() => setView('table')}
              >
                Tableau
              </button>
              <button
                type="button"
                className={`view-btn ${view === 'grouped' ? 'view-btn--active' : ''}`}
                onClick={() => setView('grouped')}
              >
                Par classe
              </button>
            </div>
            <button type="button" className="btn btn--ghost no-print" onClick={() => window.print()}>
              🖨️ Imprimer
            </button>
            <button type="button" className="btn btn--ghost no-print" onClick={handleDownload}>
              ⬇ Télécharger CSV
            </button>
            <button type="button" className="btn btn--ghost no-print" onClick={handleDownloadPdf}>
              📄 Télécharger PDF
            </button>
          </>
        }
      />

      {error && <Alert>{error}</Alert>}

      <div className="print-area">
        <div className="print-header">
          <h2>Liste des élèves</h2>
          <p>
            {filters.classe_id
              ? classes.find((c) => c.id == filters.classe_id)?.nom
              : 'Toutes les classes'}{' '}
            • {new Date().toLocaleDateString('fr-FR')}
          </p>
        </div>

        {loading ? (
          <div className="page-loader">
            <Spinner />
          </div>
        ) : view === 'grouped' ? (
          Object.keys(grouped).length === 0 ? (
            <EmptyState title="Aucun élève" description="Aucun élève ne correspond à vos critères." />
          ) : (
            <div className="class-sections">
              {Object.entries(grouped).map(([classeNom, eleves]) => (
                <section key={classeNom} className="class-section">
                  <h3 className="class-section-title">
                    {classeNom} <span className="class-section-count">({eleves.length})</span>
                  </h3>
                  <div className="table-wrapper">
                    <table className="table">
                      <thead>
                        <tr>
                          <th>N°</th>
                          <th>Nom</th>
                          <th>Prénom</th>
                          <th>Matricule</th>
                          <th>Genre</th>
                          <th>Date de naissance</th>
                          <th>Contact</th>
                        </tr>
                      </thead>
                      <tbody>
                        {eleves.map((item, idx) => (
                          <tr key={item.id}>
                            <td className="cell-muted">{idx + 1}</td>
                            <td className="cell-strong">{item.nom}</td>
                            <td>{item.prenom}</td>
                            <td className="cell-muted">{item.matricule || '—'}</td>
                            <td>{genreLabel(item.genre)}</td>
                            <td>{formatDate(item.date_naissance)}</td>
                            <td className="cell-muted">{item.telephone || item.email || '—'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </section>
              ))}
              <p className="class-sections-total">
                Total : <strong>{total}</strong> élève{total > 1 ? 's' : ''}
              </p>
            </div>
          )
        ) : items.length === 0 ? (
          <EmptyState
            title="Aucun élève"
            description={
              search || filters.classe_id || filters.genre
                ? 'Aucun élève ne correspond à vos critères.'
                : 'Commencez par ajouter votre premier élève.'
            }
            action={
              !search && !filters.classe_id && !filters.genre && (
                <button type="button" className="btn btn--primary" onClick={openCreate}>
                  + Nouvel élève
                </button>
              )
            }
          />
        ) : (
          <div className="table-wrapper">
            <table className="table">
              <thead>
                <tr>
                  <th>Nom complet</th>
                  <th>Matricule</th>
                  <th>Classe</th>
                  <th>Genre</th>
                  <th>Date de naissance</th>
                  <th>Email</th>
                  <th className="th-actions no-print">Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id}>
                    <td className="cell-strong">
                      {item.prenom} {item.nom}
                    </td>
                    <td className="cell-muted">{item.matricule || '—'}</td>
                    <td>
                      {item.classe ? <span className="badge">{item.classe.nom}</span> : '—'}
                    </td>
                    <td>
                      {item.genre ? (
                        <span className={`badge ${item.genre === 'M' ? 'badge--blue' : 'badge--pink'}`}>
                          {genreLabel(item.genre)}
                        </span>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td>{formatDate(item.date_naissance)}</td>
                    <td className="cell-muted">{item.email || '—'}</td>
                    <td className="cell-actions no-print">
                      <button type="button" className="icon-btn" title="Modifier" onClick={() => openEdit(item)}>
                        ✏️
                      </button>
                      <button type="button" className="icon-btn" title="Supprimer" onClick={() => setDeleting(item)}>
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

      {view === 'table' && meta && <Pagination meta={meta} onPageChange={setPage} />}

      <Modal
        open={modalOpen}
        title={editing ? "Modifier l'élève" : 'Nouvel élève'}
        onClose={() => setModalOpen(false)}
        size="lg"
        footer={
          <>
            <button type="button" className="btn btn--ghost" onClick={() => setModalOpen(false)} disabled={saving}>
              Annuler
            </button>
            <button type="submit" form="etudiant-form" className="btn btn--primary" disabled={saving}>
              {saving ? 'Enregistrement…' : editing ? 'Enregistrer' : 'Créer'}
            </button>
          </>
        }
      >
        <form id="etudiant-form" onSubmit={handleSubmit} noValidate>
          <div className="form-grid">
            <Field label="Prénom" htmlFor="prenom" required error={formErrors.prenom?.[0]}>
              <Input id="prenom" name="prenom" value={form.prenom} onChange={handleChange} placeholder="Ex : Moussa" error={formErrors.prenom?.[0]} />
            </Field>
            <Field label="Nom" htmlFor="nom" required error={formErrors.nom?.[0]}>
              <Input id="nom" name="nom" value={form.nom} onChange={handleChange} placeholder="Ex : Ndiaye" error={formErrors.nom?.[0]} />
            </Field>
          </div>
          <div className="form-grid">
            <Field label="Date de naissance" htmlFor="date_naissance" error={formErrors.date_naissance?.[0]}>
              <Input id="date_naissance" type="date" name="date_naissance" value={form.date_naissance} onChange={handleChange} error={formErrors.date_naissance?.[0]} />
            </Field>
            <Field label="Genre" htmlFor="genre" error={formErrors.genre?.[0]}>
              <Select id="genre" name="genre" value={form.genre} onChange={handleChange}>
                <option value="">— Sélectionner —</option>
                <option value="M">Masculin</option>
                <option value="F">Féminin</option>
              </Select>
            </Field>
          </div>
          <Field label="Classe" htmlFor="classe_id" error={formErrors.classe_id?.[0]}>
            <Select id="classe_id" name="classe_id" value={form.classe_id} onChange={handleChange}>
              <option value="">— Sans classe —</option>
              {classes.map((classe) => (
                <option key={classe.id} value={classe.id}>
                  {classe.nom} ({classe.niveau})
                </option>
              ))}
            </Select>
          </Field>
          <div className="form-grid">
            <Field label="Adresse e-mail" htmlFor="email" error={formErrors.email?.[0]} hint="Optionnel">
              <Input id="email" type="email" name="email" value={form.email} onChange={handleChange} placeholder="etudiant@ecole.com" error={formErrors.email?.[0]} />
            </Field>
            <Field label="Téléphone" htmlFor="telephone" error={formErrors.telephone?.[0]} hint="Optionnel">
              <Input id="telephone" name="telephone" value={form.telephone} onChange={handleChange} placeholder="Ex : +221 77 000 00 00" error={formErrors.telephone?.[0]} />
            </Field>
          </div>
          <Field label="Adresse" htmlFor="adresse" error={formErrors.adresse?.[0]} hint="Optionnel">
            <Input id="adresse" name="adresse" value={form.adresse} onChange={handleChange} placeholder="Ex : Dakar, Médina" error={formErrors.adresse?.[0]} />
          </Field>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Supprimer l'élève"
        message={deleting ? `Voulez-vous vraiment supprimer l'élève « ${deleting.prenom} ${deleting.nom} » ?` : ''}
        loading={deleteLoading}
        onConfirm={confirmDelete}
        onCancel={() => setDeleting(null)}
      />
    </div>
  )
}
