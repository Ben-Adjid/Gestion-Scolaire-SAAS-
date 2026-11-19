// Identité visuelle de la plateforme : une marque géométrique (livre ouvert, toque de réussite, cordon en or)
// et un nom en typographie sobre. Les couleurs sont fixes pour rester lisibles sur tous les thèmes d'école.

export const COULEUR_MARQUE = '#1e3a8a'
export const COULEUR_OR = '#f59e0b'

// Marque seule, sur fond bleu nuit arrondi : favicon, icône PWA, avatar.
export function LogoMarque({ taille = 40, title = 'EduGestion', className }) {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 64 64"
      width={taille}
      height={taille}
      role="img"
      aria-label={title}
      className={className}
    >
      <rect width="64" height="64" rx="15" fill={COULEUR_MARQUE} />
      <path d="M10 40c8-5 16-5 22-1v14c-6-3-14-3-22 1z" fill="#ffffff" opacity="0.95" />
      <path d="M54 40c-8-5-16-5-22-1v14c6-3 14-3 22 1z" fill="#ffffff" opacity="0.95" />
      <path d="M32 13 49 21 32 29 15 21z" fill={COULEUR_OR} />
      <path d="M23 27v7c0 2 4 3.5 9 3.5s9-1.5 9-3.5v-7L32 31z" fill="#fbbf24" />
      <path d="M47 22.5V31" stroke={COULEUR_OR} strokeWidth="2" strokeLinecap="round" />
      <circle cx="47" cy="32.5" r="2.2" fill={COULEUR_OR} />
    </svg>
  )
}

// Logo complet : marque et nom. Utilisé sur la page de connexion et dans la barre latérale.
export default function Logo({ taille = 44, className }) {
  return (
    <div className={`logo-complet ${className || ''}`}>
      <LogoMarque taille={taille} />
      <div className="logo-complet-texte">
        <span className="logo-complet-nom">EduGestion</span>
        <span className="logo-complet-sous">Gestion scolaire</span>
      </div>
    </div>
  )
}
