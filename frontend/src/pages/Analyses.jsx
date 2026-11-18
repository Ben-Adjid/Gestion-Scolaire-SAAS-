import { useEffect, useState } from 'react'
import { analysesApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import Alert from '../components/Alert'
import EmptyState from '../components/EmptyState'
import ChartMoyennesClasses from '../components/ChartMoyennesClasses'

const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.']

const libelleMois = (cle) => {
  const [annee, mois] = cle.split('-')
  return `${MOIS[Number(mois) - 1]} ${annee}`
}

// Formules VIP et Entreprise : vue d'ensemble de la scolarité et du recouvrement.
export default function Analyses() {
  const [donnees, setDonnees] = useState(null)
  const [erreur, setErreur] = useState(null)

  useEffect(() => {
    analysesApi.index().then((res) => setDonnees(res.data)).catch((e) => setErreur(e.message))
  }, [])

  if (!donnees && !erreur) return <Spinner />

  if (erreur) return <Alert>{erreur}</Alert>

  const { indicateurs, moyennes_par_classe: moyennes, absences_par_classe: absences, absences_par_mois: mois } = donnees
  const maxMois = Math.max(1, ...mois.map((m) => m.total))

  return (
    <div>
      <PageHeader title="Analyses avancées" subtitle="Indicateurs de scolarité et de recouvrement de votre établissement." />

      <div className="analyses-indicateurs">
        <div className="card analyse-kpi">
          <span className="analyse-kpi-valeur">{indicateurs.eleves}</span>
          <span className="analyse-kpi-label">Élèves inscrits</span>
        </div>
        <div className="card analyse-kpi">
          <span className="analyse-kpi-valeur">{indicateurs.moyenne_generale ?? '—'}</span>
          <span className="analyse-kpi-label">Moyenne générale /20</span>
        </div>
        <div className="card analyse-kpi">
          <span className="analyse-kpi-valeur">{indicateurs.taux_recouvrement !== null ? `${indicateurs.taux_recouvrement} %` : '—'}</span>
          <span className="analyse-kpi-label">Taux de recouvrement</span>
        </div>
        <div className="card analyse-kpi">
          <span className="analyse-kpi-valeur">{indicateurs.absences_total}</span>
          <span className="analyse-kpi-label">Absences enregistrées</span>
        </div>
      </div>

      <div className="analyses-colonnes">
        <section className="card">
          <div className="card-header"><h2>Moyennes par classe</h2></div>
          <div className="card-body">
            {moyennes.length === 0
              ? <EmptyState title="Aucune note" description="Les moyennes apparaîtront dès les premières notes saisies." />
              : <ChartMoyennesClasses donnees={donnees.moyennes_classe_trimestre || []} />}
          </div>
        </section>

        <section className="card">
          <div className="card-header"><h2>Absences sur 6 mois</h2></div>
          <div className="card-body">
            {mois.length === 0 && <EmptyState title="Aucune absence" description="Aucune absence sur les six derniers mois." />}
            {mois.map((m) => (
              <div key={m.mois} className="analyse-barre">
                <span>{libelleMois(m.mois)}</span>
                <div className="analyse-barre-piste">
                  <div className="analyse-barre-remplissage analyse-barre-remplissage--alerte" style={{ width: `${(m.total / maxMois) * 100}%` }} />
                </div>
                <strong>{m.total}</strong>
              </div>
            ))}
          </div>
        </section>
      </div>

      <section className="card" style={{ marginTop: 16 }}>
        <div className="card-header"><h2>Absences par classe</h2></div>
        <div className="card-body table-wrapper">
          <table className="table">
            <thead>
              <tr><th>Classe</th><th>Total</th><th>Non justifiées</th></tr>
            </thead>
            <tbody>
              {absences.map((a) => (
                <tr key={a.nom}>
                  <td>{a.nom}</td>
                  <td>{a.total}</td>
                  <td className={a.non_justifiees > 0 ? 'cell-danger' : ''}>{a.non_justifiees}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  )
}
