import { useEffect, useState } from 'react'
import { paiementEnLigneApi } from '../api/endpoints'
import { Field, Input, Select } from './Field'
import { useToast } from './Toast'

// Paramètres : compte CinetPay de l'école. L'argent des parents arrive directement sur ce compte.
export default function PaiementEnLigneCarte() {
  const toast = useToast()
  const [config, setConfig] = useState(null)
  const [form, setForm] = useState({ prestataire: '', site_id: '', api_key: '' })
  const [erreurs, setErreurs] = useState({})
  const [envoi, setEnvoi] = useState(false)

  const appliquer = (c) => {
    setConfig(c)
    setForm({ prestataire: c.prestataire || '', site_id: c.site_id || '', api_key: '' })
  }

  useEffect(() => {
    paiementEnLigneApi.configuration().then((r) => appliquer(r.data)).catch(() => {})
  }, [])

  if (!config) return null

  const enregistrer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    setErreurs({})
    try {
      const r = await paiementEnLigneApi.configurer({ prestataire: form.prestataire || null, site_id: form.site_id || null, api_key: form.api_key || null })
      appliquer(r.data)
      toast.success('Paiement en ligne enregistré.')
    } catch (err) {
      setErreurs(err.errors || {})
      if (!err.errors) toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const etat = config.actif === 'cinetpay'
    ? { texte: 'Actif : les parents paient par mobile money ou carte via CinetPay.', classe: 'badge--success' }
    : config.actif === 'simulation'
      ? { texte: 'Mode d’essai : les parents voient le bouton, mais aucun argent ne circule.', classe: 'badge--amber' }
      : { texte: 'Inactif : paiement au guichet uniquement.', classe: '' }

  return (
    <div className="card" style={{ marginTop: 24 }}>
      <div className="card-header"><h2>Paiement en ligne des frais</h2></div>
      <div className="card-body">
        <p style={{ marginTop: 0 }}><span className={`badge ${etat.classe}`}>{etat.texte}</span></p>
        <p className="field-hint">
          Créez un compte marchand sur cinetpay.com, puis copiez ici le Site ID et la clé API. Dans votre espace CinetPay,
          l’adresse de notification est : <code>{config.url_notification}</code>
        </p>
        <form onSubmit={enregistrer} noValidate>
          <div className="form-grid">
            <Field label="Prestataire" htmlFor="pel-prestataire">
              <Select id="pel-prestataire" value={form.prestataire} onChange={(e) => setForm((p) => ({ ...p, prestataire: e.target.value }))}>
                <option value="">Aucun (désactivé)</option>
                <option value="cinetpay">CinetPay (mobile money, cartes)</option>
              </Select>
            </Field>
            <Field label="Site ID" htmlFor="pel-site" error={erreurs.site_id?.[0]}>
              <Input id="pel-site" value={form.site_id} onChange={(e) => setForm((p) => ({ ...p, site_id: e.target.value }))} disabled={!form.prestataire} />
            </Field>
            <Field
              label="Clé API"
              htmlFor="pel-cle"
              error={erreurs.api_key?.[0]}
              hint={config.api_key_masquee ? `Enregistrée (${config.api_key_masquee}). Laissez vide pour la garder.` : 'Conservée chiffrée, jamais réaffichée.'}
            >
              <Input id="pel-cle" type="password" autoComplete="off" value={form.api_key} onChange={(e) => setForm((p) => ({ ...p, api_key: e.target.value }))} disabled={!form.prestataire} />
            </Field>
          </div>
          <div className="form-actions">
            <button type="submit" className="btn btn--primary" disabled={envoi}>{envoi ? 'Enregistrement…' : 'Enregistrer'}</button>
          </div>
        </form>
      </div>
    </div>
  )
}
