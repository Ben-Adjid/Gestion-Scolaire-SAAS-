import { useEffect, useState } from 'react'

// Proposition d'installation : bouton natif sur Android et ordinateur, marche à suivre sur iPhone et iPad.
// Rien n'est affiché si l'application est déjà installée.
export default function InstallerApp() {
  const [invite, setInvite] = useState(null)
  const [installee, setInstallee] = useState(false)

  const estInstallee = () =>
    window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true

  const iosSafari = () => {
    const ua = window.navigator.userAgent
    const ios = /iPad|iPhone|iPod/.test(ua) || (ua.includes('Macintosh') && navigator.maxTouchPoints > 1)
    return ios && !/CriOS|FxiOS|EdgiOS/.test(ua)
  }

  useEffect(() => {
    if (estInstallee()) {
      setInstallee(true)
      return undefined
    }
    const surInvite = (e) => {
      e.preventDefault()
      setInvite(e)
    }
    const surInstallee = () => {
      setInstallee(true)
      setInvite(null)
    }
    window.addEventListener('beforeinstallprompt', surInvite)
    window.addEventListener('appinstalled', surInstallee)
    return () => {
      window.removeEventListener('beforeinstallprompt', surInvite)
      window.removeEventListener('appinstalled', surInstallee)
    }
  }, [])

  if (installee) return null

  if (invite) {
    return (
      <p className="installer-app">
        <button
          type="button"
          className="btn btn--ghost btn--sm"
          onClick={async () => {
            invite.prompt()
            await invite.userChoice.catch(() => null)
            setInvite(null)
          }}
        >
          Installer l’application sur mon téléphone
        </button>
      </p>
    )
  }

  if (iosSafari()) {
    return (
      <p className="installer-app installer-app--aide">
        Pour installer l’application : touchez le bouton Partager de Safari, puis « Sur l’écran d’accueil ».
      </p>
    )
  }

  return null
}
