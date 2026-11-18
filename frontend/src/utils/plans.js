const ORDRE_FORMULES = ['standard', 'vip', 'entreprise']

// Une fonctionnalité est accessible si l'utilisateur est super admin ou si sa formule l'inclut.
// Le catalogue vient du backend (config/plans.php, exposé par /api/etablissement).
export function aLaFonctionnalite(user, etablissement, feature) {
  if (!feature || user?.role === 'super_admin') return true
  return (etablissement?.fonctionnalites || []).includes(feature)
}

// Libellé de la formule la moins chère qui inclut la fonctionnalité (ex. « VIP »), ou null.
export function formuleMinimale(feature, etablissement) {
  const formules = etablissement?.formules || {}
  const code = ORDRE_FORMULES.find((c) => (formules[c]?.fonctionnalites || []).includes(feature))
  return code ? formules[code].libelle : null
}
