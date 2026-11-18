import { useEffect, useState } from 'react'
import client from '../api/client'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'

const montant = (v) => `${Math.round(Number(v) || 0).toLocaleString('fr-FR')} FCFA`

// Multi-sites : synthèse consolidée du groupe scolaire (totaux agrégés par site).
export default function Groupe() {
  const [donnees, setDonnees] = useState(null)
  const [erreur, setErreur] = useState(null)

  useEffect(() => {
    client.get('/groupe/synthese').then((res) => setDonnees(res.data)).catch((e) => setErreur(e.message))
  }, [])

  if (donnees === null && !erreur) return <Spinner />

  return (
    <div>
      <PageHeader
        title={donnees ? `Groupe ${donnees.groupe}` : 'Groupe scolaire'}
        subtitle="Vue consolidée de tous les sites. Les données détaillées restent propres à chaque établissement."
      />
      {erreur && <Alert>{erreur}</Alert>}

      {donnees && !donnees.groupe && (
        <Alert>{donnees.message}</Alert>
      )}

      {donnees && donnees.groupe && (
        <>
          <div className="analyses-indicateurs">
            <div className="card analyse-kpi"><span className="analyse-kpi-valeur">{donnees.totaux.sites}</span><span className="analyse-kpi-label">Sites</span></div>
            <div className="card analyse-kpi"><span className="analyse-kpi-valeur">{donnees.totaux.eleves}</span><span className="analyse-kpi-label">Élèves</span></div>
            <div className="card analyse-kpi"><span className="analyse-kpi-valeur">{montant(donnees.totaux.encaisse)}</span><span className="analyse-kpi-label">Perçus</span></div>
            <div className="card analyse-kpi"><span className="analyse-kpi-valeur">{donnees.totaux.taux_recouvrement !== null ? `${donnees.totaux.taux_recouvrement} %` : '—'}</span><span className="analyse-kpi-label">Recouvrement</span></div>
          </div>

          <section className="card">
            <div className="card-body table-wrapper">
              <table className="table">
                <thead>
                  <tr><th>Site</th><th>Formule</th><th>Élèves</th><th>Facturé</th><th>Perçu</th><th>Recouvrement</th></tr>
                </thead>
                <tbody>
                  {donnees.sites.map((s) => (
                    <tr key={s.id}>
                      <td>{s.nom}</td>
                      <td>{s.plan}</td>
                      <td>{s.eleves}</td>
                      <td>{montant(s.facture)}</td>
                      <td>{montant(s.encaisse)}</td>
                      <td>{s.taux_recouvrement !== null ? `${s.taux_recouvrement} %` : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}
    </div>
  )
}
