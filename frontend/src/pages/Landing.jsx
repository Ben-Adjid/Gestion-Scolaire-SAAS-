import { Link } from 'react-router-dom'
import Logo, { LogoMarque } from '../components/Logo'

// Coordonnées affichées dans le pied de page. Renseignez-les avant la mise en ligne : rien n’est affiché si elles sont vides.
const CONTACT = {
  email: 'contact@edugestion-afrique.com',
  telephone: '+221 77 000 00 00',
  whatsapp: '221770000000',
  horaires: 'Support du lundi au samedi, de 8h à 18h GMT',
}

// Tarifs et périmètre alignés sur config/plans.php (prix mensuels en FCFA).
const FORMULES = [
  {
    cle: 'standard',
    nom: 'Standard',
    prix: '25 000',
    pour: 'Pour démarrer sereinement.',
    utilisateurs: 'Jusqu’à 10 utilisateurs',
    inclus: [
      'Élèves, classes et inscriptions',
      'Notes, bulletins et tableau d’honneur',
      'Frais scolaires, paiements et reçus',
      'Emploi du temps, présences et messagerie',
      'Relances et notifications aux parents',
    ],
  },
  {
    cle: 'vip',
    nom: 'VIP',
    prix: '50 000',
    pour: 'Le plus choisi par les établissements.',
    utilisateurs: 'Jusqu’à 25 utilisateurs',
    recommandee: true,
    inclus: [
      'Tout le plan Standard',
      'Pointage et présence des enseignants',
      'Export comptable et analyses avancées',
      'Conseil de classe et alertes',
      'Espace parents et cahier de texte',
      'Bulletins personnalisés',
    ],
  },
  {
    cle: 'entreprise',
    nom: 'Entreprise',
    prix: '100 000',
    pour: 'Pour les groupes et les réseaux d’écoles.',
    utilisateurs: 'Utilisateurs illimités',
    inclus: [
      'Tout le plan VIP',
      'Plusieurs sites sous un même groupe',
      'Personnalisation de l’espace de l’école',
      'Comptabilité avancée et archivage',
      'Accès API et support prioritaire',
    ],
  },
]

const FONCTIONNALITES = [
  {
    titre: 'Gestion académique',
    texte: 'Notes saisies par matière, moyennes calculées automatiquement, rangs par classe et bulletins prêts à imprimer.',
    tag: 'Bulletins',
  },
  {
    titre: 'Caisse et reçus officiels',
    texte: 'Une quittance numérotée et une référence unique pour chaque encaissement, le reliquat visible en un coup d’œil, et le reçu envoyé par e-mail aux familles.',
    tag: 'Reçus',
  },
  {
    titre: 'Pointage des enseignants',
    texte: 'Heures de cours réellement assurées, présences des enseignants et suivi depuis un tableau dédié.',
    tag: 'VIP',
  },
  {
    titre: 'Espace parents dédié',
    texte: 'Les familles consultent les notes, les présences et téléchargent les reçus depuis leur téléphone.',
    tag: 'Familles',
  },
]

const MODES = ['Espèces', 'Chèque', 'Virement', 'Mobile Money (Wave, Orange Money, MTN…)', 'Carte bancaire']

export default function Landing() {
  return (
    <div className="vitrine">
      <header className="vitrine-nav">
        <div className="vitrine-container vitrine-nav-ligne">
          <Link to="/accueil" className="vitrine-marque" aria-label="EduGestion, accueil">
            <Logo taille={36} />
          </Link>

          <nav className="vitrine-liens" aria-label="Navigation principale">
            <a href="#fonctionnalites">Fonctionnalités</a>
            <a href="#tarifs">Tarifs</a>
            <a href="#application">Application mobile</a>
            <Link to="/cgu">CGU</Link>
          </nav>

          <Link to="/login" className="vitrine-btn vitrine-btn--primaire vitrine-btn--compact">Espace connexion</Link>

          <details className="vitrine-menu">
            <summary>Menu</summary>
            <nav aria-label="Navigation mobile">
              <a href="#fonctionnalites">Fonctionnalités</a>
              <a href="#tarifs">Tarifs</a>
              <a href="#application">Application mobile</a>
              <Link to="/cgu">CGU</Link>
            </nav>
          </details>
        </div>
      </header>

      <main>
        <section className="vitrine-hero">
          <div className="vitrine-container vitrine-hero-grille">
            <div>
              <p className="vitrine-badge-or">Conçu pour les écoles d’Afrique francophone</p>
              <h1>Le système tout-en-un pour diriger votre établissement scolaire avec excellence</h1>
              <p className="vitrine-lead">
                Inscriptions, notes, bulletins instantanés, reçus de caisse et pointage des enseignants,
                dans un seul espace sécurisé. Adapté aux réalités et aux programmes éducatifs de la région.
              </p>
              <div className="vitrine-actions">
                <a href="#fonctionnalites" className="vitrine-btn vitrine-btn--primaire">Découvrir la plateforme</a>
                <Link to="/login" className="vitrine-btn vitrine-btn--secondaire">Connexion établissement</Link>
              </div>
              <ul className="vitrine-reassurance" aria-label="Points forts">
                <li>PWA installable sur téléphone</li>
                <li>Interface accessible hors ligne</li>
                <li>Sécurité multi-établissements</li>
              </ul>
            </div>

            <figure className="vitrine-maquette" aria-label="Aperçu illustratif du tableau de bord">
              <div className="vitrine-maquette-barre" aria-hidden="true"><span /><span /><span /></div>
              <div className="vitrine-maquette-corps">
                <div className="vitrine-maquette-stats">
                  <div><span>Élèves inscrits</span><strong>412</strong></div>
                  <div><span>Encaissé</span><strong>3,8 M</strong></div>
                  <div><span>Reste dû</span><strong>640 k</strong></div>
                </div>
                <div className="vitrine-recu">
                  <div className="vitrine-recu-tete">
                    <span>Reçu de paiement</span>
                    <span className="vitrine-recu-num">REC-2026-0042</span>
                  </div>
                  <div className="vitrine-recu-ligne"><span>Élève</span><span>Awa Diop · 6ème A</span></div>
                  <div className="vitrine-recu-ligne"><span>Encaissé</span><strong>30 000 FCFA</strong></div>
                  <div className="vitrine-recu-ligne"><span>Mode</span><span>Espèces</span></div>
                  <span className="vitrine-recu-reste">Reste à payer : 20 000 FCFA</span>
                </div>
              </div>
              <figcaption>Aperçu illustratif, données fictives.</figcaption>
            </figure>
          </div>
        </section>

        <section id="fonctionnalites" className="vitrine-section">
          <div className="vitrine-container">
            <p className="vitrine-surtitre">Fonctionnalités</p>
            <h2>Tout le quotidien de l’école, au même endroit</h2>
            <div className="vitrine-bento">
              {FONCTIONNALITES.map((f) => (
                <article key={f.titre} className="vitrine-carte">
                  <span className="vitrine-tag">{f.tag}</span>
                  <h3>{f.titre}</h3>
                  <p>{f.texte}</p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section id="tarifs" className="vitrine-section vitrine-section--ardoise">
          <div className="vitrine-container">
            <p className="vitrine-surtitre">Tarifs</p>
            <h2>Une formule claire, sans frais cachés</h2>
            <p className="vitrine-lead">Tarifs mensuels en FCFA. Une formule s’active après un échange avec la plateforme.</p>
            <div className="vitrine-tarifs">
              {FORMULES.map((f) => (
                <article key={f.cle} className={`vitrine-prix ${f.recommandee ? 'vitrine-prix--vedette' : ''}`}>
                  {f.recommandee && <span className="vitrine-prix-badge">Recommandé</span>}
                  <h3>{f.nom}</h3>
                  <p className="vitrine-prix-montant">{f.prix} <span>FCFA / mois</span></p>
                  <p className="vitrine-prix-pour">{f.pour}</p>
                  <ul>
                    {f.inclus.map((i) => <li key={i}>{i}</li>)}
                  </ul>
                  <p className="vitrine-prix-utilisateurs">{f.utilisateurs}</p>
                  <Link to="/login" className={`vitrine-btn ${f.recommandee ? 'vitrine-btn--primaire' : 'vitrine-btn--secondaire'} vitrine-btn--bloc`}>
                    Choisir {f.nom}
                  </Link>
                </article>
              ))}
            </div>
            <p className="vitrine-modes">
              <strong>Modes de règlement acceptés :</strong> {MODES.join(' · ')}
            </p>
          </div>
        </section>

        <section id="application" className="vitrine-section">
          <div className="vitrine-container vitrine-pwa">
            <div>
              <p className="vitrine-surtitre">Application mobile</p>
              <h2>Installée sur votre téléphone, sans passer par les stores</h2>
              <p className="vitrine-lead">
                Ouvrez la plateforme dans votre navigateur, puis ajoutez-la à votre écran d’accueil.
                Elle se lance comme une application, en plein écran.
              </p>
              <ol className="vitrine-etapes">
                <li><strong>Android</strong> : menu du navigateur, puis « Installer l’application ».</li>
                <li><strong>iPhone</strong> : bouton Partager, puis « Sur l’écran d’accueil ».</li>
              </ol>
              <p className="vitrine-note">
                L’interface est accessible hors ligne. Les données, elles, demandent une connexion.
              </p>
            </div>
            <div className="vitrine-telephones" aria-hidden="true">
              <div className="vitrine-telephone">
                <span className="vitrine-ecran-install">
                  <LogoMarque taille={48} />
                  <strong>EduGestion</strong>
                  <em>Ajouter à l’écran d’accueil</em>
                </span>
              </div>
              <div className="vitrine-telephone vitrine-telephone--ecran">
                <span className="vitrine-ecran-liste">
                  <strong>Mes reçus</strong>
                  <div><span>REC-2026-0042</span><b>Payé</b></div>
                  <div><span>REC-2026-0038</span><b>Payé</b></div>
                  <div><span>Bulletin T1</span><b>Disponible</b></div>
                </span>
              </div>
            </div>
          </div>
        </section>
      </main>

      <footer className="vitrine-footer">
        <div className="vitrine-container vitrine-footer-grille">
          <div>
            <Logo taille={32} />
            <p>Conçu avec fierté pour l’Afrique.</p>
          </div>
          <div>
            <h3>Contact</h3>
            {CONTACT.email && <p><a href={`mailto:${CONTACT.email}`}>{CONTACT.email}</a></p>}
            {CONTACT.telephone && <p><a href={`tel:${CONTACT.telephone.replace(/\s/g, '')}`}>{CONTACT.telephone}</a></p>}
            {CONTACT.whatsapp && <p><a href={`https://wa.me/${CONTACT.whatsapp}`} target="_blank" rel="noopener noreferrer">WhatsApp</a></p>}
            {CONTACT.horaires && <p>{CONTACT.horaires}</p>}
            {!CONTACT.email && !CONTACT.telephone && <p>Contactez-nous depuis l’espace connexion.</p>}
          </div>
          <div>
            <h3>Légal</h3>
            <p><Link to="/cgu">Conditions générales d’utilisation</Link></p>
            <p><Link to="/login">Espace connexion</Link></p>
          </div>
        </div>
        <p className="vitrine-copy">© {new Date().getFullYear()} EduGestion · Gestion scolaire</p>
      </footer>
    </div>
  )
}
