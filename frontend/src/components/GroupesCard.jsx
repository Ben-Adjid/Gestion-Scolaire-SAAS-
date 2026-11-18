import { useEffect, useState } from 'react'
import client from '../api/client'
import Spinner from './Spinner'
import Alert from './Alert'
import { useToast } from './Toast'
import { Field, Input, Select } from './Field'

const PLAN_LABEL = { standard: 'Standard', vip: 'VIP', entreprise: 'Entreprise' }

// Plateforme : création des groupes scolaires et rattachement des sites.
export default function GroupesCard() {
  const toast = useToast()
  const [groupes, setGroupes] = useState([])
  const [sansGroupe, setSansGroupe] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [nouveauNom, setNouveauNom] = useState('')
  const [enCours, setEnCours] = useState(false)
  const [choix, setChoix] = useState({})

  const charger = () => {
    setError(null)
    return client
      .get('/super-admin/groupes')
      .then((res) => {
        setGroupes(res.data.groupes)
        setSansGroupe(res.data.sans_groupe)
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    charger()
  }, [])

  const creerGroupe = async (e) => {
    e.preventDefault()
    setEnCours(true)
    try {
      const res = await client.post('/super-admin/groupes', { nom: nouveauNom })
      toast.success(res.data.message)
      setNouveauNom('')
      charger()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnCours(false)
    }
  }

  const rattacher = async (groupeId) => {
    const etablissementId = choix[groupeId]
    if (!etablissementId) return
    try {
      const res = await client.post(`/super-admin/etablissements/${etablissementId}/groupe`, { groupe_scolaire_id: groupeId })
      toast.success(res.data.message)
      setChoix((c) => ({ ...c, [groupeId]: '' }))
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const renommer = async (groupe) => {
    const nom = window.prompt('Nouveau nom du groupe :', groupe.nom)
    if (!nom || !nom.trim() || nom.trim() === groupe.nom) return
    try {
      const res = await client.put(`/super-admin/groupes/${groupe.id}`, { nom: nom.trim() })
      toast.success(res.data.message)
      charger()
    } catch (err) {
      toast.error(err.message || 'Renommage impossible.')
    }
  }

  const supprimer = async (groupe) => {
    if (!window.confirm(`Supprimer le groupe « ${groupe.nom} » ?`)) return
    try {
      const res = await client.delete(`/super-admin/groupes/${groupe.id}`)
      toast.success(res.data.message)
      charger()
    } catch (err) {
      // Un groupe qui contient encore des sites ne peut pas être supprimé : le message du serveur l'explique.
      toast.error(err.message || 'Suppression impossible.')
    }
  }

  const retirer = async (etablissementId, nomSite) => {
    try {
      await client.post(`/super-admin/etablissements/${etablissementId}/groupe`, { groupe_scolaire_id: null })
      toast.success(`« ${nomSite} » retiré du groupe.`)
      charger()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <section className="card" style={{ marginBottom: 16 }}>
      <div className="card-header">
        <h2>Groupes scolaires</h2>
        <span className="muted">Plusieurs sites sous un même propriétaire</span>
      </div>
      <div className="card-body">
        {error && <Alert>{error}</Alert>}
        {loading ? (
          <Spinner />
        ) : (
          <>
            {groupes.length === 0 && <p className="muted">Aucun groupe pour le moment.</p>}

            {groupes.map((groupe) => (
              <div key={groupe.id} className="groupe-bloc">
                <div className="groupe-entete">
                  <strong>{groupe.nom}</strong>
                  <span className="muted">{groupe.sites.length} site(s)</span>
                  <span style={{ display: 'flex', gap: 8 }}>
                    <button type="button" className="btn btn--ghost btn--sm" onClick={() => renommer(groupe)}>Renommer</button>
                    <button type="button" className="btn btn--ghost btn--sm" onClick={() => supprimer(groupe)}>Supprimer</button>
                  </span>
                </div>

                <ul className="dash-ranking">
                  {groupe.sites.map((site) => (
                    <li key={site.id} className="dash-ranking-row">
                      <div className="dash-ranking-info">
                        <span className="top-name">{site.nom}</span>
                        <span className="top-classe">Formule {PLAN_LABEL[site.plan] || site.plan}</span>
                      </div>
                      {site.plan !== 'entreprise' && (
                        <span className="badge badge--amber" title="La synthèse multi-sites est réservée à la formule Entreprise">
                          Synthèse non incluse
                        </span>
                      )}
                      <button type="button" className="btn btn--ghost btn--sm" onClick={() => retirer(site.id, site.nom)}>
                        Retirer
                      </button>
                    </li>
                  ))}
                </ul>

                {sansGroupe.length > 0 && (
                  <div className="groupe-ajout">
                    <Field label="Ajouter un site" htmlFor={`site-${groupe.id}`}>
                      <Select
                        id={`site-${groupe.id}`}
                        value={choix[groupe.id] || ''}
                        onChange={(e) => setChoix((c) => ({ ...c, [groupe.id]: e.target.value }))}
                      >
                        <option value="">— Choisir un établissement —</option>
                        {sansGroupe.map((e) => (
                          <option key={e.id} value={e.id}>{e.nom}</option>
                        ))}
                      </Select>
                    </Field>
                    <button
                      type="button"
                      className="btn btn--primary"
                      disabled={!choix[groupe.id]}
                      onClick={() => rattacher(groupe.id)}
                    >
                      Rattacher
                    </button>
                  </div>
                )}
              </div>
            ))}

            <form onSubmit={creerGroupe} className="groupe-creation" noValidate>
              <Field label="Nouveau groupe" htmlFor="groupe-nom" required>
                <Input
                  id="groupe-nom"
                  value={nouveauNom}
                  onChange={(e) => setNouveauNom(e.target.value)}
                  placeholder="Ex : Groupe scolaire Al-Nour"
                />
              </Field>
              <button type="submit" className="btn btn--ghost" disabled={enCours || !nouveauNom.trim()}>
                {enCours ? 'Création…' : 'Créer le groupe'}
              </button>
            </form>
          </>
        )}
      </div>
    </section>
  )
}
