import { useState } from 'react'
import Modal from './Modal'
import { Field, Input, Select } from './Field'
import { useToast } from './Toast'
import { exempleTelephone } from '../utils/pays'

export const PARENT_VIDE = { nom: '', email: '', telephone: '', lien: 'pere' }

const LIENS = [
  { value: 'pere', label: 'Père' },
  { value: 'mere', label: 'Mère' },
  { value: 'tuteur', label: 'Tuteur / tutrice' },
  { value: 'parent', label: 'Autre responsable' },
]

/** Le parent n'est envoyé que si son nom est rempli (la section reste facultative). */
export const parentAEnvoyer = (parent) => (parent?.nom?.trim() ? parent : undefined)

/**
 * Champs « parent / responsable » d'une inscription (formules avec l'espace parent).
 * Un parent déjà connu (même e-mail ou même téléphone) est relié au lieu d'être recréé.
 */
export function ChampsParent({ valeur, onChange, erreurs = {}, parentsLies = [] }) {
  const changer = (e) => onChange({ ...valeur, [e.target.name]: e.target.value })
  const err = (champ) => erreurs[`parent.${champ}`]?.[0]

  return (
    <>
      <h3 className="form-titre-section">Parent / responsable (espace parent)</h3>
      {parentsLies.length > 0 && (
        <div className="parents-lies">
          {parentsLies.map((p) => (
            <span key={p.id}>
              Déjà relié : <strong>{p.nom}</strong> {p.email ? `· ${p.email}` : ''}
            </span>
          ))}
          <small>Le parent garde son compte et voit la nouvelle année. Remplissez les champs seulement pour ajouter un autre responsable.</small>
        </div>
      )}
      <p className="field-hint" style={{ marginBottom: 10 }}>
        Facultatif. Si ce parent a déjà un compte (frère ou sœur inscrit), il est simplement relié : pas de second compte.
      </p>
      <div className="form-grid">
        <Field label="Nom complet du parent" htmlFor="parent-nom" error={err('nom')}>
          <Input id="parent-nom" name="nom" value={valeur.nom} onChange={changer} placeholder="Ex : Hawa Mahamat" error={err('nom')} />
        </Field>
        <Field label="Lien avec l’élève" htmlFor="parent-lien" error={err('lien')}>
          <Select id="parent-lien" name="lien" value={valeur.lien} onChange={changer}>
            {LIENS.map((l) => <option key={l.value} value={l.value}>{l.label}</option>)}
          </Select>
        </Field>
        <Field label="E-mail (identifiant de connexion)" htmlFor="parent-email" error={err('email')}>
          <Input id="parent-email" type="email" name="email" value={valeur.email} onChange={changer} placeholder="parent@exemple.com" error={err('email')} />
        </Field>
        <Field label="Téléphone (WhatsApp)" htmlFor="parent-telephone" error={err('telephone')}>
          <Input id="parent-telephone" name="telephone" value={valeur.telephone} onChange={changer} placeholder={exempleTelephone()} error={err('telephone')} />
        </Field>
      </div>
    </>
  )
}

/** Résultat : identifiants à remettre au parent (le mot de passe provisoire n'est affiché qu'une fois). */
export function IdentifiantsParent({ resultat, eleve, onClose }) {
  const toast = useToast()
  const [copie, setCopie] = useState(false)
  if (!resultat) return null

  const nouveau = resultat.statut === 'cree'
  const texte = `Espace parent — ${document.title}\nÉlève : ${eleve || ''}\nIdentifiant : ${resultat.parent.email}\nMot de passe provisoire : ${resultat.mot_de_passe_provisoire}\nÀ changer à la première connexion.`

  // Fiche à remettre au parent : une page à part, sans le reste de l'application.
  const imprimer = () => {
    const echapper = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
    const couleur = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#1F5C4A'
    const fenetre = window.open('', '_blank', 'width=640,height=520')
    if (!fenetre) {
      toast.error('Autorisez les fenêtres pour imprimer, ou utilisez « Copier ».')
      return
    }
    fenetre.document.write(`<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Accès espace parent</title>
      <style>body{font-family:Segoe UI,Arial,sans-serif;margin:32px;color:#1f2a28}h1{font-size:20px;color:${couleur};margin:0 0 4px}
      .cadre{border:2px dashed ${couleur};border-radius:10px;padding:18px 22px;margin-top:18px}dt{font-size:12px;color:#6b7a76;text-transform:uppercase;margin-top:10px}
      dd{margin:2px 0 0;font-size:17px;font-weight:600}.mdp{font-family:Consolas,monospace;font-size:22px;letter-spacing:2px}p{font-size:13px;color:#555}</style></head>
      <body><h1>${echapper(document.title)}</h1><p>Accès à l’espace parent</p><div class="cadre"><dl>
      <dt>Parent</dt><dd>${echapper(resultat.parent.nom)}</dd><dt>Élève</dt><dd>${echapper(eleve)}</dd>
      <dt>Identifiant</dt><dd>${echapper(resultat.parent.email)}</dd><dt>Mot de passe provisoire</dt><dd class="mdp">${echapper(resultat.mot_de_passe_provisoire)}</dd>
      </dl></div><p>À la première connexion, vous choisirez votre propre mot de passe. Ne communiquez jamais vos identifiants.</p></body></html>`)
    fenetre.document.close()
    fenetre.focus()
    fenetre.print()
  }

  const copier = async () => {
    try {
      await navigator.clipboard.writeText(texte)
      setCopie(true)
      toast.success('Identifiants copiés.')
    } catch {
      toast.error('Copie impossible : recopiez les identifiants à la main.')
    }
  }

  return (
    <Modal
      open
      title={nouveau ? 'Compte parent créé' : 'Parent relié à l’élève'}
      onClose={onClose}
      footer={
        <>
          {nouveau && <button type="button" className="btn btn--ghost" onClick={copier}>{copie ? 'Copié' : 'Copier'}</button>}
          {nouveau && <button type="button" className="btn btn--ghost" onClick={imprimer}>Imprimer</button>}
          <button type="button" className="btn btn--primary" onClick={onClose}>Terminé</button>
        </>
      }
    >
      {nouveau ? (
        <div className="identifiants-parent">
          <p>Remettez ces identifiants à <strong>{resultat.parent.nom}</strong>. Le mot de passe ne sera plus affiché ensuite.</p>
          <dl>
            <dt>Élève</dt><dd>{eleve}</dd>
            <dt>Identifiant</dt><dd>{resultat.parent.email}</dd>
            <dt>Mot de passe provisoire</dt><dd className="identifiants-mdp">{resultat.mot_de_passe_provisoire}</dd>
          </dl>
          <p className="field-hint">Le parent devra choisir son propre mot de passe à la première connexion. Un e-mail de bienvenue (sans mot de passe) lui a été envoyé.</p>
        </div>
      ) : (
        <p>
          <strong>{resultat.parent.nom}</strong> ({resultat.parent.email || resultat.parent.telephone}) avait déjà un compte :
          {resultat.statut === 'deja_lie' ? ' il était déjà relié à cet élève.' : ' l’élève a été ajouté à son espace. Il utilise ses identifiants habituels.'}
        </p>
      )}
    </Modal>
  )
}
