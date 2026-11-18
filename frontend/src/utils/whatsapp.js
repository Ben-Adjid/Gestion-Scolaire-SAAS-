// Lien « Discuter sur WhatsApp » : ouvre la conversation avec un message pré-rempli.
// Rien n'est envoyé à la place de l'utilisateur : il relit et envoie lui-même.
export function lienWhatsApp(numero, message = '') {
  const chiffres = String(numero || '').replace(/\D+/g, '')
  if (chiffres.length < 8) return null
  const texte = message ? `?text=${encodeURIComponent(message)}` : ''
  return `https://wa.me/${chiffres}${texte}`
}
