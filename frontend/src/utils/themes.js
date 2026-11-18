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

// Le thème visuel est celui de l'établissement (couleur appliquée par EtablissementContext) : tous les comptes
// d'une même école voient la même interface, et deux écoles diffèrent. Les ambiances par rôle ne sont plus utilisées.
export function themePourRole() {
  return 'defaut'
}

// Retire toute ambiance par rôle ; l'établissement fixe seulement sa couleur.
export function appliquerTheme() {
  const racine = document.documentElement
  delete racine.dataset.theme
  return () => delete racine.dataset.theme
}
