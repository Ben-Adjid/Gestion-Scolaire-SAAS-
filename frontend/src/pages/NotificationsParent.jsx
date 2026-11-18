import { useEffect, useState } from 'react'
import { espaceParentApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'

const EVENEMENTS = {
  absence: { libelle: 'Absences', aide: 'Quand votre enfant est noté absent à l’appel.' },
  retard: { libelle: 'Retards', aide: 'Quand votre enfant arrive en retard.' },
  convocation: { libelle: 'Convocations', aide: 'Quand l’établissement vous convoque.' },
  sortie: { libelle: 'Sorties', aide: 'Quand votre enfant quitte l’établissement avec votre autorisation.' },
}
const CANAUX = { email: 'E-mail', whatsapp: 'WhatsApp' }

// Notifications à la carte : le parent choisit quoi recevoir et sur quel canal.
export default function NotificationsParent() {
  const toast = useToast()
  const [prefs, setPrefs] = useState(null)
  const [erreur, setErreur] = useState(null)
  const [envoi, setEnvoi] = useState(false)

  useEffect(() => {
    espaceParentApi.preferences().then((res) => setPrefs(res.data.preferences)).catch((e) => setErreur(e.message))
  }, [])

  const basculer = (evenement, canal) =>
    setPrefs((p) => ({ ...p, [evenement]: { ...p[evenement], [canal]: !p[evenement][canal] } }))

  const enregistrer = async () => {
    setEnvoi(true)
    try {
      const res = await espaceParentApi.enregistrerPreferences(prefs)
      setPrefs(res.data.preferences)
      toast.success('Vos préférences sont enregistrées.')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  if (erreur) return <Alert>{erreur}</Alert>
  if (!prefs) return <Spinner />

  return (
    <div>
      <PageHeader title="Mes notifications" subtitle="Choisissez les informations que vous recevez, et par quel moyen." />

      <div className="card">
        <div className="card-body">
          <div className="table-wrapper">
            <table className="table">
              <thead>
                <tr>
                  <th>Événement</th>
                  {Object.entries(CANAUX).map(([c, l]) => <th key={c} style={{ textAlign: 'center' }}>{l}</th>)}
                </tr>
              </thead>
              <tbody>
                {Object.entries(EVENEMENTS).map(([e, { libelle, aide }]) => (
                  <tr key={e}>
                    <td>
                      <div className="cell-strong">{libelle}</div>
                      <div className="cell-muted">{aide}</div>
                    </td>
                    {Object.keys(CANAUX).map((c) => (
                      <td key={c} style={{ textAlign: 'center' }}>
                        <label className="interrupteur" aria-label={`${libelle} par ${CANAUX[c]}`}>
                          <input type="checkbox" checked={Boolean(prefs[e]?.[c])} onChange={() => basculer(e, c)} />
                          <span />
                        </label>
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="cell-muted" style={{ marginTop: 12 }}>
            WhatsApp utilise le numéro enregistré par l’établissement sur votre compte. Les messages de l’administration restent visibles dans votre espace.
          </p>
        </div>
        <div className="card-footer" style={{ display: 'flex', justifyContent: 'flex-end', padding: '12px 20px' }}>
          <button type="button" className="btn btn--primary" onClick={enregistrer} disabled={envoi}>
            {envoi ? 'Enregistrement…' : 'Enregistrer'}
          </button>
        </div>
      </div>
    </div>
  )
}
