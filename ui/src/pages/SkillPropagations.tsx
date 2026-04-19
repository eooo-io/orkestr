import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  fetchSkillPropagations,
  acceptSkillPropagation,
  dismissSkillPropagation,
  type SkillPropagation,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'

export function SkillPropagations() {
  const activeOrgId = useAppStore((s) => {
    const first = s.projects[0]
    return (first as { organization_id?: number } | undefined)?.organization_id ?? null
  })
  const showToast = useAppStore((s) => s.showToast)
  const [propagations, setPropagations] = useState<SkillPropagation[]>([])
  const [loading, setLoading] = useState(true)
  const [resolving, setResolving] = useState<number | null>(null)

  const load = () => {
    if (!activeOrgId) {
      setLoading(false)
      return
    }
    setLoading(true)
    fetchSkillPropagations(activeOrgId)
      .then(setPropagations)
      .finally(() => setLoading(false))
  }

  useEffect(load, [activeOrgId])

  const handleAccept = async (p: SkillPropagation) => {
    setResolving(p.id)
    try {
      const result = await acceptSkillPropagation(p.id)
      showToast(`Accepted — new skill ${result.new_skill_slug}`)
      load()
    } catch {
      showToast('Failed to accept', 'error')
    } finally {
      setResolving(null)
    }
  }

  const handleDismiss = async (p: SkillPropagation) => {
    setResolving(p.id)
    try {
      await dismissSkillPropagation(p.id)
      showToast('Dismissed')
      load()
    } finally {
      setResolving(null)
    }
  }

  if (!activeOrgId) {
    return (
      <div className="p-6 text-sm text-muted-foreground">
        Join or select an organization to see propagation suggestions.
      </div>
    )
  }

  if (loading) {
    return <div className="p-6 text-sm text-muted-foreground animate-pulse">Loading propagations…</div>
  }

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-4">
      <header>
        <h1 className="text-2xl font-semibold">Skill propagations</h1>
        <p className="text-sm text-muted-foreground mt-1">
          High-performing skills from other projects in your org, suggested for compatible agents here.
        </p>
      </header>

      {propagations.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          No suggestions yet. They accumulate as skills rack up successful eval runs across projects.
        </p>
      ) : (
        <ul className="space-y-3">
          {propagations.map((p) => (
            <li key={p.id} className="border border-border rounded p-4 space-y-2">
              <div className="flex items-start gap-3">
                <div className="flex-1 min-w-0">
                  <h3 className="text-sm font-semibold">
                    {p.source_skill?.name}
                  </h3>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    From{' '}
                    <span className="font-mono">{p.source_skill?.project?.name}</span>
                    {' → '}
                    <span className="font-mono">{p.target_project?.name}</span>
                    {p.target_agent && (
                      <>
                        {' · target agent '}
                        <Link
                          to={`/agents/${p.target_agent.id}/profile`}
                          className="text-primary hover:underline"
                        >
                          {p.target_agent.name}
                        </Link>
                      </>
                    )}
                  </p>
                </div>
                <span className="shrink-0 text-xs font-mono text-muted-foreground">
                  score {Number(p.suggestion_score).toFixed(2)}
                </span>
              </div>

              <div className="flex items-center gap-2 justify-end">
                <button
                  onClick={() => handleDismiss(p)}
                  disabled={resolving === p.id}
                  className="text-xs px-3 py-1 border border-border rounded hover:bg-muted/40"
                >
                  Dismiss
                </button>
                <button
                  onClick={() => handleAccept(p)}
                  disabled={resolving === p.id}
                  className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded disabled:opacity-50"
                >
                  Accept into {p.target_project?.name}
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
