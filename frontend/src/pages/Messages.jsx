import { useCallback, useEffect, useState } from 'react'
import { messagesApi } from '../api/endpoints'
import PageHeader from '../components/PageHeader'
import Spinner from '../components/Spinner'
import EmptyState from '../components/EmptyState'
import Pagination from '../components/Pagination'
import Alert from '../components/Alert'
import { useToast } from '../components/Toast'
import { useAuth } from '../context/AuthContext'
import { Field, Input, Select, Textarea } from '../components/Field'

const TYPE_LABELS = {
  annonce: 'Annonce',
  devoir: 'Devoir programmé',
  examen: "Planification d'examens",
  sujet: "Sujet d'examen",
}

function typeBadge(type) {
  switch (type) {
    case 'annonce':
      return 'badge--blue'
    case 'examen':
      return 'badge--amber'
    case 'devoir':
      return 'badge--neutral'
    case 'sujet':
      return 'badge--success'
    default:
      return 'badge--neutral'
  }
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleString('fr-FR')
}

export default function Messages() {
  const toast = useToast()
  const { user } = useAuth()
  const isTeacher = user?.role === 'enseignant'

  const [tab, setTab] = useState('boite')
  const [items, setItems] = useState([])
  const [meta, setMeta] = useState(null)
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [page, setPage] = useState(1)
  const [openId, setOpenId] = useState(null)

  const [form, setForm] = useState({ type: isTeacher ? 'devoir' : 'annonce', titre: '', contenu: '' })
  const [fichier, setFichier] = useState(null)
  const [sending, setSending] = useState(false)

  const typeOptions = isTeacher
    ? [
        { value: 'devoir', label: 'Devoir programmé' },
        { value: 'sujet', label: "Sujet d'examen" },
      ]
    : [
        { value: 'annonce', label: 'Annonce' },
        { value: 'examen', label: "Planification d'examens" },
      ]

  const fetchData = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [res, statsRes] = await Promise.all([
        messagesApi.list({ page }),
        messagesApi.stats(),
      ])
      setItems(res.data.data)
      setMeta(res.data)
      setStats(statsRes.data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [page])

  useEffect(() => {
    fetchData()
  }, [fetchData])

  const openMessage = async (message) => {
    setOpenId(message.id)
    if (!message.lu) {
      try {
        await messagesApi.markRead(message.id)
        message.lu = true
        setItems((prev) => prev.map((m) => (m.id === message.id ? message : m)))
        setStats((prev) => (prev ? { non_lus: Math.max(0, prev.non_lus - 1) } : prev))
      } catch {
        // ignore
      }
    }
  }

  const downloadFichier = async (message) => {
    try {
      const res = await messagesApi.fichier(message.id)
      const url = URL.createObjectURL(res.data)
      const link = document.createElement('a')
      link.href = url
      link.download = message.fichier_nom
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    } catch (err) {
      toast.error(err.message || 'Téléchargement impossible.')
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setSending(true)
    try {
      const fd = new FormData()
      fd.append('type', form.type)
      fd.append('cible', isTeacher ? 'administration' : 'enseignants')
      fd.append('titre', form.titre)
      fd.append('contenu', form.contenu)
      if (fichier) fd.append('fichier', fichier)

      const res = await messagesApi.create(fd)
      toast.success(res.data.message)
      setForm({ type: isTeacher ? 'devoir' : 'annonce', titre: '', contenu: '' })
      setFichier(null)
      setTab('boite')
      fetchData()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSending(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Messagerie"
        subtitle="Annonces, devoirs programmés, planification d'examens et sujets."
      />

      {error && <Alert>{error}</Alert>}

      <div className="tabs">
        <button type="button" className={`tab ${tab === 'boite' ? 'tab--active' : ''}`} onClick={() => setTab('boite')}>
          Boîte de réception {stats && stats.non_lus > 0 && <span className="badge badge--danger">{stats.non_lus}</span>}
        </button>
        <button type="button" className={`tab ${tab === 'nouveau' ? 'tab--active' : ''}`} onClick={() => setTab('nouveau')}>
          Nouveau message
        </button>
      </div>

      {tab === 'boite' ? (
        <div className="card">
          <div className="card-body">
            {loading ? (
              <div className="page-loader"><Spinner /></div>
            ) : items.length === 0 ? (
              <EmptyState title="Aucun message" description="Vous n'avez aucun message pour le moment." />
            ) : (
              <div className="message-list">
                {items.map((m) => (
                  <div key={m.id} className={`message-item ${!m.lu ? 'message-item--unread' : ''}`} onClick={() => openMessage(m)}>
                    <div className="message-item-head">
                      <span className={`badge ${typeBadge(m.type)}`}>{TYPE_LABELS[m.type] || m.type}</span>
                      <strong>{m.titre}</strong>
                      <span className="muted" style={{ marginLeft: 'auto' }}>
                        {m.expediteur?.name || '—'} • {formatDate(m.created_at)}
                      </span>
                    </div>
                    {openId === m.id && (
                      <div className="message-item-body">
                        <p style={{ whiteSpace: 'pre-wrap' }}>{m.contenu}</p>
                        {m.fichier_nom && (
                          <button
                            type="button"
                            className="dossier-file-link"
                            onClick={(e) => {
                              e.stopPropagation()
                              downloadFichier(m)
                            }}
                          >
                            📎 Télécharger {m.fichier_nom}
                          </button>
                        )}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
          {meta && <Pagination meta={meta} onPageChange={setPage} />}
        </div>
      ) : (
        <div className="card">
          <div className="card-body">
            <form onSubmit={handleSubmit} noValidate>
              <Field label="Type" htmlFor="message-type" required>
                <Select id="message-type" value={form.type} onChange={(e) => setForm((prev) => ({ ...prev, type: e.target.value }))}>
                  {typeOptions.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                  ))}
                </Select>
              </Field>
              <Field label="Titre" htmlFor="message-titre" required>
                <Input id="message-titre" value={form.titre} onChange={(e) => setForm((prev) => ({ ...prev, titre: e.target.value }))} placeholder="Ex : Devoir de maths le 12 mars" />
              </Field>
              <Field label="Message" htmlFor="message-contenu" required>
                <Textarea id="message-contenu" rows={5} value={form.contenu} onChange={(e) => setForm((prev) => ({ ...prev, contenu: e.target.value }))} placeholder="Détaillez votre message…" />
              </Field>
              <Field label="Pièce jointe (facultatif)" htmlFor="message-fichier">
                <Input id="message-fichier" type="file" onChange={(e) => setFichier(e.target.files?.[0] || null)} />
              </Field>
              <button type="submit" className="btn btn--primary" disabled={sending}>
                {sending ? 'Envoi…' : 'Envoyer'}
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
