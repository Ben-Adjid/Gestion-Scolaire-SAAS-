import { useEffect, useState } from 'react'
import { etablissementApi, reglesApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { useEtablissement } from '../context/EtablissementContext'
import { Field, Input } from '../components/Field'

const FIELDS = [
  { key: 'note_max', label: 'Note maximale (barème)', hint: 'Ex. 20', step: '0.5' },
  { key: 'seuil_admission', label: "Seuil d'admission (passe)", hint: 'Moyenne minimale pour être admis', step: '0.5' },
  { key: 'mention_felicitations', label: 'Seuil Félicitations', hint: 'Moyenne minimale', step: '0.5' },
  { key: 'mention_honneur', label: "Seuil Tableau d'honneur", hint: 'Moyenne minimale', step: '0.5' },
  { key: 'mention_encouragements', label: 'Seuil Encouragements', hint: 'Moyenne minimale', step: '0.5' },
  { key: 'nb_tableau_honneur', label: 'Élèves au tableau d\'honneur', hint: 'Nombre de meilleurs élèves retenus par classe', step: '1' },
]

const ETAB_FIELDS = [
  { key: 'nom', label: "Nom de l'établissement", required: true },
  { key: 'devise', label: 'Devise' },
  { key: 'ministere', label: 'Ministère' },
  { key: 'adresse', label: 'Adresse' },
  { key: 'telephone', label: 'Téléphone' },
  { key: 'responsable', label: "Nom du Chef d'établissement" },
  { key: 'annee_scolaire', label: 'Année scolaire', required: true },
  { key: 'prefixe_matricule', label: 'Préfixe des matricules', hint: 'Ex. LIC, LT…' },
]

export default function Parametres() {
  const toast = useToast()
  const { etablissement, setEtablissement } = useEtablissement()

  const [form, setForm] = useState(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState(null)

  const [etabForm, setEtabForm] = useState(null)
  const [savingEtab, setSavingEtab] = useState(false)
  const [logoFile, setLogoFile] = useState(null)
  const [logoPreview, setLogoPreview] = useState(null)

  useEffect(() => {
    reglesApi
      .show()
      .then((res) => setForm(res.data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))

    etablissementApi
      .show()
      .then((res) => {
        setEtabForm(res.data)
        if (res.data?.logo_path) setLogoPreview(`/${res.data.logo_path}`)
      })
      .catch(() => {})
  }, [])

  const handleChange = (e) => {
    const { name, value } = e.target
    setForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleEtabChange = (e) => {
    const { name, value } = e.target
    setEtabForm((prev) => ({ ...prev, [name]: value }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      await reglesApi.update(form)
      toast.success('Règles de gestion mises à jour avec succès.')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  const handleEtabSubmit = async (e) => {
    e.preventDefault()
    setSavingEtab(true)
    try {
      const fd = new FormData()
      Object.entries(etabForm || {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined) fd.append(key, value)
      })
      if (logoFile) fd.append('logo', logoFile)

      const res = await etablissementApi.update(fd)
      setEtablissement(res.data.etablissement)
      setLogoFile(null)
      setLogoPreview(res.data.etablissement?.logo_path ? `/${res.data.etablissement.logo_path}` : null)
      toast.success("Informations de l'établissement mises à jour.")
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSavingEtab(false)
    }
  }

  if (loading) {
    return (
      <div className="page-loader">
        <Spinner />
      </div>
    )
  }

  if (error) {
    return <Alert>{error}</Alert>
  }

  return (
    <div>
      <PageHeader
        title="Paramètres"
        subtitle="Configuration de l'établissement et des règles de gestion."
      />

      <div className="card">
        <div className="card-header">
          <h2>🏫 Établissement</h2>
        </div>
        <div className="card-body">
          <form id="etablissement-form" onSubmit={handleEtabSubmit} noValidate>
            <div className="form-grid">
              {ETAB_FIELDS.map((field) => (
                <Field
                  key={field.key}
                  label={field.label}
                  htmlFor={`etab-${field.key}`}
                  required={field.required}
                  hint={field.hint}
                >
                  <Input
                    id={`etab-${field.key}`}
                    name={field.key}
                    value={etabForm?.[field.key] ?? ''}
                    onChange={handleEtabChange}
                  />
                </Field>
              ))}
            </div>

            <Field label="Logo de l'établissement" htmlFor="etab-logo" hint="PNG, JPG ou SVG (2 Mo max)">
              <div className="logo-upload-row">
                <Input
                  id="etab-logo"
                  type="file"
                  accept="image/*"
                  onChange={(e) => {
                    const file = e.target.files?.[0]
                    if (!file) return
                    setLogoFile(file)
                    setLogoPreview(URL.createObjectURL(file))
                  }}
                />
                {logoPreview && (
                  <img src={logoPreview} alt="Logo" className="logo-preview" />
                )}
              </div>
            </Field>

            <button type="submit" className="btn btn--primary" disabled={savingEtab}>
              {savingEtab ? 'Enregistrement…' : "Enregistrer l'établissement"}
            </button>
          </form>
        </div>
      </div>

      <div className="dashboard-grid" style={{ marginTop: 20 }}>
        <div className="card">
          <div className="card-header">
            <h2>Seuils de calcul</h2>
          </div>
          <div className="card-body">
            <form id="regles-form" onSubmit={handleSubmit} noValidate>
              {FIELDS.map((field) => (
                <Field key={field.key} label={field.label} htmlFor={field.key} hint={field.hint}>
                  <Input
                    id={field.key}
                    type="number"
                    step={field.step}
                    min="0"
                    name={field.key}
                    value={form[field.key]}
                    onChange={handleChange}
                  />
                </Field>
              ))}
              <button type="submit" className="btn btn--primary" disabled={saving}>
                {saving ? 'Enregistrement…' : 'Enregistrer les règles'}
              </button>
            </form>
          </div>
        </div>

        <div className="card">
          <div className="card-header">
            <h2>Aperçu des règles</h2>
          </div>
          <div className="card-body">
            <ul className="rule-list">
              <li>
                📏 Barème des notes : <strong>0 à {form.note_max}</strong>
              </li>
              <li>
                ✅ Admission (passe) : moyenne ≥ <strong>{form.seuil_admission}</strong>
              </li>
              <li>
                🥇 Félicitations : moyenne ≥ <strong>{form.mention_felicitations}</strong>
              </li>
              <li>
                🥈 Tableau d'honneur : moyenne ≥ <strong>{form.mention_honneur}</strong>
              </li>
              <li>
                🥉 Encouragements : moyenne ≥ <strong>{form.mention_encouragements}</strong>
              </li>
              <li>
                🏅 Tableau d'honneur : les <strong>{form.nb_tableau_honneur}</strong> meilleurs élèves
                de chaque classe
              </li>
              <li>
                🧮 Moyenne générale = Σ (moyenne × coefficient) / Σ coefficients (calcul automatique)
              </li>
              <li>
                🔒 Une note validée est verrouillée : l'enseignant ne peut plus la modifier ni la
                supprimer.
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  )
}
