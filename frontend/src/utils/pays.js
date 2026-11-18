// Pays de l'établissement : monnaie, en-tête officiel des bulletins et indicatif téléphonique.
// Les valeurs sont fournies par l'API (/etablissement → pays_info) et mises à jour par EtablissementContext.
const PAR_DEFAUT = {
  code: 'TD',
  nom: 'Tchad',
  republique: 'RÉPUBLIQUE DU TCHAD',
  devise: 'Unité — Travail — Progrès',
  monnaie: 'FCFA',
  indicatif: '+235',
}

let courant = PAR_DEFAUT

export function definirPays(info) {
  courant = info && info.code ? { ...PAR_DEFAUT, ...info } : PAR_DEFAUT
}

export const paysCourant = () => courant

/** Symbole de la monnaie de l'école (FCFA, GNF, DH…). */
export const monnaie = () => courant.monnaie

/** Montant formaté avec la monnaie de l'école : « 25 000 FCFA ». */
export const formatMontant = (v) => `${Math.round(Number(v) || 0).toLocaleString('fr-FR')} ${courant.monnaie}`

/** Exemple de numéro avec l'indicatif du pays, pour les champs téléphone. */
export const exempleTelephone = () => `Ex : ${courant.indicatif} 00 00 00 00`
