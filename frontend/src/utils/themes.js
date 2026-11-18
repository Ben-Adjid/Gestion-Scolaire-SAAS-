// Ambiance visuelle par type de compte. Chaque thème redéfinit les variables CSS sur <html data-theme="…">.
//   concentration : enseignant — vert émeraude profond et gris doux, pour la saisie.
//   familial      : parent — bleu nuit et ambre, rassurant et chaleureux.
//   premium       : direction (admin, proviseur) — mode sombre, indigo électrique.
//   defaut        : autres comptes.
const THEME_PAR_ROLE = {
  enseignant: 'concentration',
  parent: 'familial',
  admin: 'premium',
  proviseur: 'premium',
}

export function themePourRole(role) {
  return THEME_PAR_ROLE[role] || 'defaut'
}

// Applique le thème du compte connecté ; le retour rétablit le thème par défaut.
export function appliquerTheme(role) {
  const racine = document.documentElement
  const theme = themePourRole(role)
  if (theme === 'defaut') {
    delete racine.dataset.theme
  } else {
    racine.dataset.theme = theme
  }
  return () => delete racine.dataset.theme
}
