import { useCallback, useEffect, useState } from 'react'
import {
  fetchGotchas,
  createGotcha,
  deleteGotcha,
  resolveGotcha,
  reopenGotcha,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import type { SkillGotcha } from '@/types'

interface GotchaPanelProps {
  skillId: number
}

const SEVERITY_STYLES: Record<
  string,
  { bg: string; text: string; label: string }
> = {
  critical: { bg: 'bg-red-500/10', text: 'text-red-500', label: 'Critical' },
  warning: {
    bg: 'bg-yellow-500/10',
    text: 'text-yellow-500',
    label: 'Warning',
  },
  info: { bg: 'bg-blue-500/10', text: 'text-blue-500', label: 'Info' },
}

export function GotchaPanel({ skillId }: GotchaPanelProps) {
  const confirm = useConfirm()
  const [gotchas, setGotchas] = useState<SkillGotcha[]>([])
  const [loading, setLoading] = useState(true)
  const [showResolved, setShowResolved] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [severity, setSeverity] = useState<'critical' | 'warning' | 'info'>(
    'warning',
  )

  const load = useCallback(() => {
    fetchGotchas(skillId)
      .then(setGotchas)
      .finally(() => setLoading(false))
  }, [skillId])

  useEffect(() => {
    load()
  }, [load])

  const handleCreate = async () => {
    if (!title.trim() || !description.trim()) return
    await createGotcha(skillId, { title, description, severity })
    setTitle('')
    setDescription('')
    setSeverity('warning')
    setShowForm(false)
    load()
  }

  const handleDelete = async (id: number) => {
    if (!(await confirm({ message: 'Delete this gotcha?', title: 'Confirm Delete' }))) return
    await deleteGotcha(id)
    load()
  }

  const handleResolve = async (id: number) => {
    await resolveGotcha(id)
    load()
  }

  const handleReopen = async (id: number) => {
    await reopenGotcha(id)
    load()
  }

  if (loading) {
    return (
      <div className="p-4 text-sm text-muted-foreground animate-pulse">
        Loading gotchas...
      </div>
    )
  }

  const active = gotchas.filter((g) => !g.resolved_at)
  const resolved = gotchas.filter((g) => g.resolved_at)
  const displayed = showResolved ? gotchas : active

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="p-3 border-b border-border flex items-center justify-between">
        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowForm(!showForm)}
            className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded hover:opacity-90"
          >
            + Add Gotcha
          </button>
          {resolved.length > 0 && (
            <label className="flex items-center gap-1.5 text-xs text-muted-foreground cursor-pointer">
              <input
                type="checkbox"
                checked={showResolved}
                onChange={(e) => setShowResolved(e.target.checked)}
                className="rounded border-border"
              />
              Show resolved ({resolved.length})
            </label>
          )}
        </div>
        <span className="text-xs text-muted-foreground">
          {active.length} active
        </span>
      </div>

      {/* Add form */}
      {showForm && (
        <div className="p-3 border-b border-border bg-muted/30 space-y-2">
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="Gotcha title"
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded"
          />
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Describe the failure mode or edge case..."
            rows={3}
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded resize-none"
          />
          <div className="flex items-center gap-2">
            <select
              value={severity}
              onChange={(e) =>
                setSeverity(
                  e.target.value as 'critical' | 'warning' | 'info',
                )
              }
              className="text-xs border border-input bg-background rounded px-2 py-1"
            >
              <option value="critical">Critical</option>
              <option value="warning">Warning</option>
              <option value="info">Info</option>
            </select>
            <button
              onClick={handleCreate}
              disabled={!title.trim() || !description.trim()}
              className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded disabled:opacity-50"
            >
              Save
            </button>
            <button
              onClick={() => setShowForm(false)}
              className="text-xs px-3 py-1 text-muted-foreground hover:text-foreground"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {/* List */}
      <div className="flex-1 overflow-y-auto">
        {displayed.length === 0 ? (
          <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
            No gotchas yet. Add failure modes as you discover them.
          </div>
        ) : (
          <div className="divide-y divide-border">
            {displayed.map((gotcha) => {
              const s = SEVERITY_STYLES[gotcha.severity] || SEVERITY_STYLES.info
              const isResolved = !!gotcha.resolved_at

              return (
                <div
                  key={gotcha.id}
                  className={`p-3 group ${isResolved ? 'opacity-50' : ''}`}
                >
                  <div className="flex items-start gap-2">
                    <span
                      className={`shrink-0 mt-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded ${s.bg} ${s.text}`}
                    >
                      {s.label}
                    </span>
                    <div className="flex-1 min-w-0">
                      <div
                        className={`text-sm font-medium ${isResolved ? 'line-through' : ''}`}
                      >
                        {gotcha.title}
                      </div>
                      <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">
                        {gotcha.description}
                      </p>
                      <div className="text-[10px] text-muted-foreground/60 mt-1">
                        {gotcha.source !== 'manual' && (
                          <span className="mr-2">
                            Source: {gotcha.source.replace('_', ' ')}
                          </span>
                        )}
                        {new Date(gotcha.created_at).toLocaleDateString()}
                      </div>
                    </div>
                    <div className="shrink-0 opacity-0 group-hover:opacity-100 flex gap-1 transition-opacity">
                      {isResolved ? (
                        <button
                          onClick={() => handleReopen(gotcha.id)}
                          className="text-[10px] px-1.5 py-0.5 text-muted-foreground hover:text-foreground"
                          title="Reopen"
                        >
                          Reopen
                        </button>
                      ) : (
                        <button
                          onClick={() => handleResolve(gotcha.id)}
                          className="text-[10px] px-1.5 py-0.5 text-green-600 hover:text-green-500"
                          title="Resolve"
                        >
                          Resolve
                        </button>
                      )}
                      <button
                        onClick={() => handleDelete(gotcha.id)}
                        className="text-[10px] px-1.5 py-0.5 text-destructive hover:text-destructive/80"
                        title="Delete"
                      >
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}
