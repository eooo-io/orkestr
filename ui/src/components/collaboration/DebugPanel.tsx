import { useEffect, useState, useCallback } from 'react'
import { Bug, Plus, LogIn, StopCircle, Users } from 'lucide-react'
import axios from 'axios'

interface DebugSessionData {
  id: number
  uuid: string
  project_id: number
  execution_run_id: number | null
  title: string
  status: string
  participants: number[]
  breakpoints: Record<string, unknown>[] | null
  creator: { id: number; name: string; email: string } | null
  created_at: string
  ended_at: string | null
}

interface DebugPanelProps {
  projectId: number
  currentUserId?: number
}

export function DebugPanel({ projectId, currentUserId }: DebugPanelProps) {
  const [sessions, setSessions] = useState<DebugSessionData[]>([])
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)
  const [newTitle, setNewTitle] = useState('')
  const [newExecutionRunId, setNewExecutionRunId] = useState('')
  const [showCreateForm, setShowCreateForm] = useState(false)

  const fetchSessions = useCallback(async () => {
    try {
      const { data } = await axios.get(
        `/api/projects/${projectId}/debug-sessions`
      )
      setSessions(data.data ?? [])
    } catch {
      // Handle silently
    } finally {
      setLoading(false)
    }
  }, [projectId])

  useEffect(() => {
    fetchSessions()
    // Poll every 10 seconds to keep the list fresh
    const interval = setInterval(fetchSessions, 10000)
    return () => clearInterval(interval)
  }, [fetchSessions])

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!newTitle.trim() || creating) return

    setCreating(true)
    try {
      await axios.post(`/api/projects/${projectId}/debug-sessions`, {
        title: newTitle.trim(),
        execution_run_id: newExecutionRunId ? Number(newExecutionRunId) : null,
      })
      setNewTitle('')
      setNewExecutionRunId('')
      setShowCreateForm(false)
      fetchSessions()
    } catch {
      // Handle error
    } finally {
      setCreating(false)
    }
  }

  const handleJoin = async (sessionId: number) => {
    try {
      await axios.post(`/api/debug-sessions/${sessionId}/join`)
      fetchSessions()
    } catch {
      // Handle error
    }
  }

  const handleEnd = async (sessionId: number) => {
    try {
      await axios.post(`/api/debug-sessions/${sessionId}/end`)
      fetchSessions()
    } catch {
      // Handle error
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8 text-sm text-muted-foreground">
        Loading debug sessions...
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2 text-sm font-medium">
          <Bug className="h-4 w-4" />
          Active Debug Sessions ({sessions.length})
        </div>
        <button
          onClick={() => setShowCreateForm(!showCreateForm)}
          className="flex items-center gap-1 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90"
        >
          <Plus className="h-3 w-3" />
          New Session
        </button>
      </div>

      {/* Create form */}
      {showCreateForm && (
        <form
          onSubmit={handleCreate}
          className="rounded-lg border bg-card p-4 space-y-3"
        >
          <div>
            <label className="block text-xs font-medium text-muted-foreground mb-1">
              Session Title
            </label>
            <input
              type="text"
              value={newTitle}
              onChange={(e) => setNewTitle(e.target.value)}
              placeholder="e.g. Debugging agent loop issue"
              className="w-full rounded-md border bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
              autoFocus
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-muted-foreground mb-1">
              Execution Run ID (optional)
            </label>
            <input
              type="text"
              value={newExecutionRunId}
              onChange={(e) => setNewExecutionRunId(e.target.value)}
              placeholder="Link to a specific execution run"
              className="w-full rounded-md border bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>
          <div className="flex gap-2 justify-end">
            <button
              type="button"
              onClick={() => setShowCreateForm(false)}
              className="rounded-md px-3 py-1.5 text-xs text-muted-foreground hover:text-foreground"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={!newTitle.trim() || creating}
              className="rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
            >
              {creating ? 'Creating...' : 'Create Session'}
            </button>
          </div>
        </form>
      )}

      {/* Sessions list */}
      {sessions.length === 0 ? (
        <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
          No active debug sessions. Create one to start collaborating.
        </div>
      ) : (
        <div className="space-y-3">
          {sessions.map((session) => {
            const isParticipant = currentUserId
              ? session.participants.includes(currentUserId)
              : false
            const isCreator = currentUserId === session.creator?.id

            return (
              <div
                key={session.id}
                className="rounded-lg border bg-card p-4"
              >
                <div className="flex items-start justify-between">
                  <div>
                    <h4 className="text-sm font-medium">{session.title}</h4>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      Started by {session.creator?.name ?? 'Unknown'}{' '}
                      {formatRelativeTime(session.created_at)}
                    </p>
                  </div>
                  <div className="flex items-center gap-2">
                    {!isParticipant && (
                      <button
                        onClick={() => handleJoin(session.id)}
                        className="flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs hover:bg-accent transition-colors"
                      >
                        <LogIn className="h-3 w-3" />
                        Join
                      </button>
                    )}
                    {(isCreator || isParticipant) && (
                      <button
                        onClick={() => handleEnd(session.id)}
                        className="flex items-center gap-1 rounded-md border border-red-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-950 transition-colors"
                      >
                        <StopCircle className="h-3 w-3" />
                        End
                      </button>
                    )}
                  </div>
                </div>

                {/* Participants */}
                <div className="mt-3 flex items-center gap-2">
                  <Users className="h-3 w-3 text-muted-foreground" />
                  <span className="text-xs text-muted-foreground">
                    {session.participants.length} participant
                    {session.participants.length !== 1 ? 's' : ''}
                  </span>
                  {isParticipant && (
                    <span className="rounded bg-green-500/10 px-1.5 py-0.5 text-[10px] font-medium text-green-600">
                      Joined
                    </span>
                  )}
                </div>

                {/* Breakpoints */}
                {session.breakpoints && session.breakpoints.length > 0 && (
                  <div className="mt-2">
                    <span className="text-xs text-muted-foreground">
                      {session.breakpoints.length} breakpoint
                      {session.breakpoints.length !== 1 ? 's' : ''}
                    </span>
                  </div>
                )}

                {/* Execution run link */}
                {session.execution_run_id && (
                  <div className="mt-2 text-xs text-muted-foreground">
                    Linked to execution run #{session.execution_run_id}
                  </div>
                )}
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

function formatRelativeTime(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffSecs = Math.floor(diffMs / 1000)
  const diffMins = Math.floor(diffSecs / 60)
  const diffHours = Math.floor(diffMins / 60)
  const diffDays = Math.floor(diffHours / 24)

  if (diffSecs < 60) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays < 7) return `${diffDays}d ago`
  return date.toLocaleDateString()
}
