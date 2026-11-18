export const TRIMESTRES = [
  { value: 1, label: '1er Trimestre' },
  { value: 2, label: '2ème Trimestre' },
  { value: 3, label: '3ème Trimestre' },
]

export const TRIMESTRES_ANNUEL = [
  ...TRIMESTRES,
  { value: 0, label: 'Bilan annuel' },
]

// Rangs du palmarès, sans pictogramme.
export const MEDALS = ['1er', '2e', '3e']

export const TYPES_NOTE = ['devoir', 'composition', 'examen']

export const ROLE_LABELS = {
  super_admin: 'Super Administrateur SaaS',
  admin: 'Administrateur',
  proviseur: 'Proviseur',
  censeur: 'Censeur',
  cpe: 'CPE (Conseiller Pédagogique)',
  enseignant: 'Enseignant',
  secretaire: 'Secrétaire',
  gestionnaire: 'Gestionnaire',
  agent_comptable: 'Agent Comptable',
  responsable_it: 'Responsable IT',
}
