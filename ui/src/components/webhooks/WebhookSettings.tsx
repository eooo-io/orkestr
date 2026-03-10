import { useEffect, useState } from 'react'
import {
  Plus,
  Trash2,
  Pencil,
  Send,
  ChevronDown,
  ChevronRight,
  Check,
  X,
  Loader2,
  Globe,
  Clock,
} from 'lucide-react'
import {
  fetchWebhooks,
  createWebhook,
  updateWebhook,
  deleteWebhook,
  fetchWebhookDeliveries,
  testWebhook,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import type { Webhook, WebhookDelivery } from '@/types'

const WEBHOOK_EVENTS = [
  { value: 'skill.created', label: 'Skill Created' },
  { value: 'skill.updated', label: 'Skill Updated' },
  { value: 'skill.deleted', label: 'Skill Deleted' },
  { value: 'project.synced', label: 'Project Synced' },
]

interface Props {
  projectId: number
}

export function WebhookSettings({ projectId }: Props) {
  const { showToast } = useAppStore()
  const [webhooks, setWebhooks] = useState<Webhook[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [deliveries, setDeliveries] = useState<Record<number, WebhookDelivery[]>>({})
  const [loadingDeliveries, setLoadingDeliveries] = useState<number | null>(null)
  const [testingId, setTestingId] = useState<number | null>(null)

  // Form state
  const [formEvent, setFormEvent] = useState('skill.updated')
  const [formUrl, setFormUrl] = useState('')
  const [formSecret, setFormSecret] = useState('')
  const [formActive, setFormActive] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  const loadWebhooks = () => {
    fetchWebhooks(projectId)
      .then(setWebhooks)
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadWebhooks()
  }, [projectId])

  const resetForm = () => {
    setFormEvent('skill.updated')
    setFormUrl('')
    setFormSecret('')
    setFormActive(true)
    setEditingId(null)
    setShowForm(false)
  }

  const startEdit = (wh: Webhook) => {
    setFormEvent(wh.event)
    setFormUrl(wh.url)
    setFormSecret(wh.secret || '')
    setFormActive(wh.is_active)
    setEditingId(wh.id)
    setShowForm(true)
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!formUrl.trim()) return
    setSubmitting(true)

    try {
      if (editingId) {
        await updateWebhook(editingId, {
          event: formEvent,
          url: formUrl.trim(),
          secret: formSecret.trim() || null,
          is_active: formActive,
        })
        showToast('Webhook updated')
      } else {
        await createWebhook(projectId, {
          event: formEvent,
          url: formUrl.trim(),
          secret: formSecret.trim() || undefined,
          is_active: formActive,
        })
        showToast('Webhook created')
      }
      resetForm()
      loadWebhooks()
    } catch {
      showToast('Failed to save webhook', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this webhook?')) return
    try {
      await deleteWebhook(id)
      showToast('Webhook deleted')
      loadWebhooks()
    } catch {
      showToast('Failed to delete webhook', 'error')
    }
  }

  const handleTest = async (wh: Webhook) => {
    setTestingId(wh.id)
    try {
      await testWebhook(wh.id)
      showToast('Test webhook sent')
      // Reload deliveries if expanded
      if (expandedId === wh.id) {
        loadDeliveries(wh.id)
      }
      loadWebhooks()
    } catch {
      showToast('Test webhook failed', 'error')
    } finally {
      setTestingId(null)
    }
  }

  const toggleExpanded = (id: number) => {
    if (expandedId === id) {
      setExpandedId(null)
    } else {
      setExpandedId(id)
      loadDeliveries(id)
    }
  }

  const loadDeliveries = async (webhookId: number) => {
    setLoadingDeliveries(webhookId)
    try {
      const data = await fetchWebhookDeliveries(webhookId)
      setDeliveries((prev) => ({ ...prev, [webhookId]: data }))
    } catch {
      // silently fail
    } finally {
      setLoadingDeliveries(null)
    }
  }

  const statusColor = (status: number | null) => {
    if (!status) return 'text-muted-foreground'
    if (status >= 200 && status < 300) return 'text-green-500'
    if (status >= 400) return 'text-red-500'
    return 'text-yellow-500'
  }

  const eventLabel = (event: string) =>
    WEBHOOK_EVENTS.find((e) => e.value === event)?.label || event

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-medium">Webhooks</h3>
          <p className="text-xs text-muted-foreground mt-0.5">
            Get notified when skills change or projects sync
          </p>
        </div>
        {!showForm && (
          <Button size="sm" onClick={() => setShowForm(true)}>
            <Plus className="h-4 w-4 mr-1" />
            Add Webhook
          </Button>
        )}
      </div>

      {showForm && (
        <form
          onSubmit={handleSubmit}
          className="border border-border rounded-lg p-4 space-y-3 bg-muted/30"
        >
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                Event
              </label>
              <select
                value={formEvent}
                onChange={(e) => setFormEvent(e.target.value)}
                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              >
                {WEBHOOK_EVENTS.map((ev) => (
                  <option key={ev.value} value={ev.value}>
                    {ev.label}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                URL
              </label>
              <input
                type="url"
                value={formUrl}
                onChange={(e) => setFormUrl(e.target.value)}
                placeholder="https://example.com/webhook"
                required
                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                Secret (optional, for HMAC signing)
              </label>
              <input
                type="text"
                value={formSecret}
                onChange={(e) => setFormSecret(e.target.value)}
                placeholder="webhook-secret"
                className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm font-mono"
              />
            </div>
            <div className="flex items-end pb-1">
              <label className="flex items-center gap-2 text-sm cursor-pointer">
                <input
                  type="checkbox"
                  checked={formActive}
                  onChange={(e) => setFormActive(e.target.checked)}
                  className="rounded border-border"
                />
                Active
              </label>
            </div>
          </div>
          <div className="flex items-center gap-2 pt-1">
            <Button type="submit" size="sm" disabled={submitting}>
              {submitting ? (
                <Loader2 className="h-4 w-4 animate-spin mr-1" />
              ) : editingId ? (
                <Check className="h-4 w-4 mr-1" />
              ) : (
                <Plus className="h-4 w-4 mr-1" />
              )}
              {editingId ? 'Update' : 'Create'}
            </Button>
            <Button type="button" variant="ghost" size="sm" onClick={resetForm}>
              <X className="h-4 w-4 mr-1" />
              Cancel
            </Button>
          </div>
        </form>
      )}

      {webhooks.length === 0 && !showForm ? (
        <div className="text-center py-12 text-muted-foreground">
          <Globe className="h-8 w-8 mx-auto mb-2 opacity-40" />
          <p className="text-sm">No webhooks configured.</p>
          <p className="text-xs mt-1">
            Add a webhook to get notified of changes.
          </p>
        </div>
      ) : (
        <div className="space-y-2">
          {webhooks.map((wh) => (
            <div
              key={wh.id}
              className="border border-border rounded-lg overflow-hidden"
            >
              <div className="flex items-center justify-between px-4 py-3">
                <div className="flex items-center gap-3 min-w-0">
                  <button
                    onClick={() => toggleExpanded(wh.id)}
                    className="text-muted-foreground hover:text-foreground"
                  >
                    {expandedId === wh.id ? (
                      <ChevronDown className="h-4 w-4" />
                    ) : (
                      <ChevronRight className="h-4 w-4" />
                    )}
                  </button>
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                      wh.is_active
                        ? 'bg-green-500/10 text-green-500'
                        : 'bg-muted text-muted-foreground'
                    }`}
                  >
                    {wh.is_active ? 'Active' : 'Inactive'}
                  </span>
                  <span className="text-xs font-medium px-2 py-0.5 rounded bg-accent">
                    {eventLabel(wh.event)}
                  </span>
                  <span className="text-sm font-mono text-muted-foreground truncate">
                    {wh.url}
                  </span>
                </div>
                <div className="flex items-center gap-1.5 ml-3 shrink-0">
                  {wh.last_triggered_at && (
                    <span className="flex items-center gap-1 text-xs text-muted-foreground mr-2">
                      <Clock className="h-3 w-3" />
                      {new Date(wh.last_triggered_at).toLocaleString()}
                      {wh.last_status !== null && (
                        <span className={statusColor(wh.last_status)}>
                          {wh.last_status}
                        </span>
                      )}
                    </span>
                  )}
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleTest(wh)}
                    disabled={testingId === wh.id}
                  >
                    {testingId === wh.id ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <Send className="h-3.5 w-3.5" />
                    )}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => startEdit(wh)}
                  >
                    <Pencil className="h-3.5 w-3.5" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleDelete(wh.id)}
                  >
                    <Trash2 className="h-3.5 w-3.5 text-red-500" />
                  </Button>
                </div>
              </div>

              {expandedId === wh.id && (
                <div className="border-t border-border bg-muted/20 px-4 py-3">
                  <h4 className="text-xs font-medium text-muted-foreground mb-2">
                    Recent Deliveries
                  </h4>
                  {loadingDeliveries === wh.id ? (
                    <div className="flex items-center justify-center py-4">
                      <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                    </div>
                  ) : !deliveries[wh.id]?.length ? (
                    <p className="text-xs text-muted-foreground py-2">
                      No deliveries yet.
                    </p>
                  ) : (
                    <div className="space-y-1.5">
                      {deliveries[wh.id].slice(0, 10).map((d) => (
                        <div
                          key={d.id}
                          className="flex items-center justify-between text-xs px-3 py-2 rounded bg-background border border-border"
                        >
                          <div className="flex items-center gap-3">
                            <span className={statusColor(d.response_status)}>
                              {d.response_status ?? '---'}
                            </span>
                            <span className="text-muted-foreground">
                              {d.event}
                            </span>
                          </div>
                          <div className="flex items-center gap-3 text-muted-foreground">
                            {d.duration_ms !== null && (
                              <span>{d.duration_ms}ms</span>
                            )}
                            <span>
                              {new Date(d.created_at).toLocaleString()}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
