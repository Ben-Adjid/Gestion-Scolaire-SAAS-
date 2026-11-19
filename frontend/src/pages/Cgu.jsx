import { Link } from 'react-router-dom'

export const CGU_VERSION = '2026-11-19'

// Conditions générales d'utilisation de la plateforme. Accessibles sans connexion.
export default function Cgu() {
  return (
    <div className="cgu-page">
      <article className="cgu-document">
        <p className="cgu-retour"><Link to="/login">← Retour à la connexion</Link></p>
        <h1>Conditions générales d’utilisation</h1>
        <p className="cgu-version">Version {CGU_VERSION}</p>

        <h2>1. Objet et acceptation</h2>
        <p>
          Les présentes conditions régissent l’accès et l’usage de la plateforme de gestion scolaire
          (ci-après « la plateforme ») par les établissements scolaires abonnés, leurs personnels et les parents
          d’élèves. L’administrateur de l’établissement accepte ces conditions lors de l’activation de son compte ;
          l’usage de la plateforme par chaque utilisateur vaut acceptation de leur version en vigueur.
        </p>

        <h2>2. Propriété des données scolaires</h2>
        <p>
          L’établissement est seul propriétaire des données scolaires qu’il saisit ou importe : identité des élèves,
          notes, absences, bulletins, paiements, dossiers et correspondances. L’éditeur de la plateforme ne les utilise
          que pour fournir le service. Il ne les vend, ne les cède et ne les exploite à aucune fin commerciale.
          L’établissement peut en obtenir une copie complète par export à tout moment, et leur suppression sur demande
          écrite, sous réserve des obligations légales de conservation.
        </p>

        <h2>3. Obligation de moyens et limites de responsabilité</h2>
        <p>
          L’éditeur met en œuvre des moyens techniques raisonnables pour assurer la disponibilité, la sécurité et
          l’intégrité de la plateforme. Il s’agit d’une obligation de moyens, non de résultat.
        </p>
        <p>
          L’éditeur décline toute responsabilité pour les interruptions de service dues à une coupure du réseau Internet
          de l’établissement ou de ses utilisateurs, à une panne d’électricité, à un équipement local défaillant, ou à
          l’indisponibilité d’un service tiers (opérateur de téléphonie, messagerie, service de paiement, WhatsApp).
          Pour limiter l’impact de ces coupures, certaines fonctions, comme l’appel des élèves, fonctionnent hors
          connexion et se synchronisent au retour du réseau. L’établissement doit conserver ses propres sauvegardes
          de ses documents essentiels.
        </p>

        <h2>4. Protection des données des mineurs</h2>
        <p>
          La plateforme traite des données d’élèves, dont beaucoup sont mineurs. Ces données sont confidentielles.
          L’accès n’est ouvert qu’aux personnes habilitées de l’établissement et, pour leur seul enfant, aux parents
          ou responsables légaux. Chaque accès est journalisé. Les données ne sont ni publiées, ni utilisées à des fins
          publicitaires. Les demandes d’accès ou de rectification d’un parent sont adressées à l’établissement, qui
          demeure responsable du traitement.
        </p>

        <h2>5. Sécurité des comptes</h2>
        <p>
          Chaque utilisateur dispose d’un compte personnel. Il doit garder ses identifiants secrets, ne pas les
          communiquer et signaler immédiatement toute utilisation suspecte. L’établissement répond des actions faites
          depuis les comptes de son personnel.
        </p>

        <h2>6. Abonnement</h2>
        <p>
          L’accès est ouvert pour la durée de l’abonnement choisi (formule Standard, VIP ou Entreprise). À l’échéance
          sans renouvellement, l’accès est suspendu, sans suppression des données. Les tarifs et les fonctionnalités
          de chaque formule sont ceux en vigueur au moment de la souscription.
        </p>

        <h2>7. Droit applicable et litiges</h2>
        <p>
          Les présentes conditions sont régies par le droit applicable au siège de l’éditeur. En cas de litige, les
          parties recherchent d’abord une solution amiable. À défaut, le litige est porté devant les tribunaux
          compétents du siège de l’éditeur.
        </p>

        <h2>8. Contact</h2>
        <p>
          Pour toute question sur ces conditions ou sur vos données, utilisez les coordonnées de contact indiquées
          dans l’application (bouton « Support »).
        </p>
      </article>
    </div>
  )
}
