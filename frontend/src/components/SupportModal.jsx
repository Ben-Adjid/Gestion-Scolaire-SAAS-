import { useState } from 'react'
import { supportApi } from '../api/endpoints'
import { useAuth } from '../context/AuthContext'
import { useEtablissement } from '../context/EtablissementContext'
import { aLaFonctionnalite } from '../utils/plans'
import Modal from './Modal'
import { Field, Input, Textarea } from './Field'
import { useToast } from './Toast'

// Bouton « Contacter le support » : ticket prioritaire (formule Entreprise), ou accès à l'assistant.
export default function SupportModal({ open, onClose }) {
  const toast = useToast()
  const { user } = useAuth()
  const { etablissement } = useEtablissement()
  const [sujet, setSujet] = useState('')
  const [message, setMessage] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const prioritaire = aLaFonctionnalite(user, etablissement, 'support_prioritaire')

  const envoyer = async (e) => {
    e.preventDefault()
    setEnvoi(true)
    try {
      await supportApi.envoyer({ sujet, message })
      toast.success('Votre demande a été transmise au support prioritaire.')
      setSujet('')
      setMessage('')
      onClose()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setEnvoi(false)
    }
  }

  const assistantDisponible = user?.role !== 'parent'

  // Contact direct de la plateforme (e-mail, WhatsApp), réglé dans frontend/.env. Les parents s'adressent à l'école.
  const emailSupport = (import.meta.env.VITE_SUPPORT_EMAIL || '').trim()
  const whatsappSupport = (import.meta.env.VITE_SUPPORT_WHATSAPP || '').replace(/\D/g, '')
  const contactDirect = user?.role !== 'parent' && Boolean(emailSupport || whatsappSupport)

  const ouvrirAssistant = () => {
    onClose()
    window.dispatchEvent(new Event('ouvrir-assistant'))
  }

  return (
    <Modal
      open={open}
      title={prioritaire ? 'Contacter le support prioritaire' : 'Demander de l’aide'}
      onClose={onClose}
      footer={
        prioritaire ? (
          <>
            <button type="button" className="btn btn--ghost" onClick={onClose} disabled={envoi}>Annuler</button>
            <button type="submit" form="support-modal" className="btn btn--primary" disabled={envoi || !sujet.trim() || !message.trim()}>
              {envoi ? 'Envoi…' : 'Envoyer'}
            </button>
          </>
        ) : (
          <>
            <button type="button" className="btn btn--ghost" onClick={onClose}>Fermer</button>
            {assistantDisponible && (
              <button type="button" className="btn btn--primary" onClick={ouvrirAssistant}>Ouvrir l’assistant</button>
            )}
          </>
        )
      }
    >
      {contactDirect && (
        <div className="support-direct">
          <p className="form-section-aide">Contactez directement l’équipe de la plateforme :</p>
          <div className="support-direct-boutons">
            {emailSupport && (
              <a
                className="btn btn--ghost"
                href={`mailto:${emailSupport}?subject=${encodeURIComponent(`Support — ${etablissement?.nom || ''}`)}`}
              >
                Écrire un e-mail
              </a>
            )}
            {whatsappSupport && (
              <a
                className="btn btn--ghost"
                href={`https://wa.me/${whatsappSupport}?text=${encodeURIComponent(`Bonjour, je vous contacte depuis ${etablissement?.nom || 'notre établissement'}.`)}`}
                target="_blank"
                rel="noopener noreferrer"
              >
                Écrire sur WhatsApp
              </a>
            )}
          </div>
        </div>
      )}

      {prioritaire ? (
        <form id="support-modal" onSubmit={envoyer}>
          <p className="form-section-aide">Décrivez votre besoin : notre équipe vous répond en priorité.</p>
          <Field label="Sujet" htmlFor="support-modal-sujet" required>
            <Input id="support-modal-sujet" value={sujet} maxLength={150} onChange={(e) => setSujet(e.target.value)} />
          </Field>
          <Field label="Message" htmlFor="support-modal-message" required>
            <Textarea id="support-modal-message" rows={4} value={message} maxLength={3000} onChange={(e) => setMessage(e.target.value)} />
          </Field>
        </form>
      ) : (
        <p>
          {assistantDisponible
            ? 'Le support prioritaire est inclus dans la formule Entreprise. En attendant, l’assistant peut répondre à la plupart de vos questions sur la scolarité, les notes et les démarches.'
            : 'Le support prioritaire est inclus dans la formule Entreprise. Pour toute question, écrivez à l’administration depuis la page Échanges.'}
        </p>
      )}
    </Modal>
  )
}
