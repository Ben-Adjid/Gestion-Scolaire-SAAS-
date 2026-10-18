export function formatDate(date) {
  if (!date) return '—'
  const [y, m, d] = String(date).slice(0, 10).split('-')
  if (!y || !m || !d) return '—'
  return `${d}/${m}/${y}`
}

export function mentionColor(key) {
  switch (key) {
    case 'felicitations':
      return 'gold'
    case 'honneur':
      return 'silver'
    case 'encouragements':
      return 'bronze'
    case 'passable':
      return 'neutral'
    default:
      return 'danger'
  }
}

export function noteClass(valeur) {
  if (valeur >= 16) return 'note-good'
  if (valeur >= 12) return 'note-fair'
  if (valeur >= 10) return 'note-ok'
  return 'note-bad'
}
