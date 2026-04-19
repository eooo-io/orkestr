import { useEffect, useState } from 'react'
import {
  fetchProjectRoles,
  createProjectRole,
  endProjectRole,
  type ProjectRoleAssignment,
} from '@/api/client'

interface Props {
  projectId: number
}

const ROLE_LABEL: Record<ProjectRoleAssignment['role'], string> = {
  ic: 'IC',
  dri: 'DRI',
  coach: 'Coach',
}

const ROLE_DESC: Record<ProjectRoleAssignment['role'], string> = {
  ic: 'Owns a capability',
  dri: 'Owns a cross-cutting outcome',
  coach: 'Player-coach (craft + people)',
}

export function RoleMap({ projectId }: Props) {
  const [assignments, setAssignments] = useState<ProjectRoleAssignment[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [userId, setUserId] = useState('')
  const [role, setRole] = useState<ProjectRoleAssignment['role']>('ic')
  const [scope, setScope] = useState('')

  const load = () => {
    setLoading(true)
    fetchProjectRoles(projectId)
      .then(setAssignments)
      .finally(() => setLoading(false))
  }

  useEffect(load, [projectId])

  const handleAdd = async () => {
    if (!userId.trim()) return
    await createProjectRole(projectId, {
      user_id: parseInt(userId),
      role,
      scope: scope || undefined,
    })
    setUserId('')
    setScope('')
    setShowForm(false)
    load()
  }

  const handleEnd = async (id: number) => {
    await endProjectRole(projectId, id)
    load()
  }

  const grouped: Record<string, ProjectRoleAssignment[]> = { ic: [], dri: [], coach: [] }
  for (const a of assignments) grouped[a.role]?.push(a)

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
          Role map
        </h3>
        <button
          onClick={() => setShowForm(!showForm)}
          className="text-xs px-2.5 py-1 border border-border rounded hover:bg-muted/40"
        >
          {showForm ? 'Cancel' : '+ Add'}
        </button>
      </div>

      {showForm && (
        <div className="border border-border rounded p-3 space-y-2">
          <div className="grid grid-cols-2 gap-2">
            <input
              type="number"
              value={userId}
              onChange={(e) => setUserId(e.target.value)}
              placeholder="User ID"
              className="px-2 py-1 text-xs border border-input bg-background rounded"
            />
            <select
              value={role}
              onChange={(e) => setRole(e.target.value as ProjectRoleAssignment['role'])}
              className="px-2 py-1 text-xs border border-input bg-background rounded"
            >
              <option value="ic">IC — {ROLE_DESC.ic}</option>
              <option value="dri">DRI — {ROLE_DESC.dri}</option>
              <option value="coach">Coach — {ROLE_DESC.coach}</option>
            </select>
          </div>
          <input
            type="text"
            value={scope}
            onChange={(e) => setScope(e.target.value)}
            placeholder="Scope (optional, e.g. merchant-churn)"
            className="w-full px-2 py-1 text-xs border border-input bg-background rounded"
          />
          <button
            onClick={handleAdd}
            disabled={!userId.trim()}
            className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded disabled:opacity-50"
          >
            Assign
          </button>
        </div>
      )}

      {loading ? (
        <p className="text-xs text-muted-foreground animate-pulse">Loading…</p>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          {(['ic', 'dri', 'coach'] as const).map((r) => (
            <div key={r} className="border border-border rounded p-3">
              <div className="text-xs font-semibold">{ROLE_LABEL[r]}</div>
              <div className="text-[10px] text-muted-foreground mb-2">{ROLE_DESC[r]}</div>
              {grouped[r].length === 0 ? (
                <p className="text-xs text-muted-foreground/60">—</p>
              ) : (
                <ul className="space-y-1">
                  {grouped[r].map((a) => (
                    <li key={a.id} className="flex items-center gap-1 text-xs">
                      <span className="truncate flex-1">
                        {a.user?.name ?? `user #${a.user_id}`}
                        {a.scope && <span className="text-muted-foreground"> · {a.scope}</span>}
                      </span>
                      <button
                        onClick={() => handleEnd(a.id)}
                        className="text-[10px] text-muted-foreground hover:text-destructive"
                        title="End assignment"
                      >
                        ×
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
