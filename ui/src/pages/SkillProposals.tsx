import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  fetchSkillProposals,
  acceptSkillProposal,
  rejectSkillProposal,
  type SkillUpdateProposal,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'

export function SkillProposals() {
  const [proposals, setProposals] = useState<SkillUpdateProposal[]>([])
  const [loading, setLoading] = useState(true)
  const [resolving, setResolving] = useState<number | null>(null)
  const showToast = useAppStore((s) => s.showToast)

  const load = () => {
    setLoading(true)
    fetchSkillProposals()
      .then(setProposals)
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  const handleAccept = async (p: SkillUpdateProposal) => {
    if (!p.skill_id) {
      showToast('Attach this proposal to a skill first', 'error')
      return
    }
    setResolving(p.id)
    try {
      const result = await acceptSkillProposal(p.id)
      showToast(`Created version v${result.new_version_number}`)
      load()
    } catch {
      showToast('Failed to accept', 'error')
    } finally {
      setResolving(null)
    }
  }

  const handleReject = async (p: SkillUpdateProposal) => {
    setResolving(p.id)
    try {
      await rejectSkillProposal(p.id)
      showToast('Proposal rejected and suppressed for 30 days')
      load()
    } finally {
      setResolving(null)
    }
  }

  if (loading) {
    return <div className="p-6 text-sm text-muted-foreground animate-pulse">Loading proposals…</div>
  }

  return (
    <div className="max-w-3xl mx-auto p-6 space-y-4">
      <header>
        <h1 className="text-2xl font-semibold">Skill proposals</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Patterns detected in your agents' memories, ready to be encoded as durable skill updates.
        </p>
      </header>

      {proposals.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          No pending proposals. They'll appear here automatically as your agents accumulate feedback.
        </p>
      ) : (
        <ul className="space-y-3">
          {proposals.map((p) => (
            <li key={p.id} className="border border-border rounded p-4 space-y-2">
              <div className="flex items-start gap-3">
                <div className="flex-1 min-w-0">
                  <h3 className="text-sm font-semibold">{p.title}</h3>
                  {p.rationale && (
                    <p className="text-xs text-muted-foreground mt-1">{p.rationale}</p>
                  )}
                  {p.skill && (
                    <p className="text-xs mt-1">
                      Target:{' '}
                      <Link to={`/skills/${p.skill.id}`} className="text-primary hover:underline">
                        {p.skill.name}
                      </Link>
                    </p>
                  )}
                  {p.agent && (
                    <p className="text-xs text-muted-foreground">
                      From agent {p.agent.name}
                    </p>
                  )}
                </div>
              </div>

              {p.proposed_body && (
                <pre className="text-[11px] font-mono whitespace-pre-wrap bg-muted/30 border border-border rounded p-2 max-h-40 overflow-y-auto">
                  {p.proposed_body}
                </pre>
              )}

              <div className="flex items-center gap-2 justify-end">
                <button
                  onClick={() => handleReject(p)}
                  disabled={resolving === p.id}
                  className="text-xs px-3 py-1 border border-border rounded hover:bg-muted/40"
                >
                  Reject (suppress 30d)
                </button>
                <button
                  onClick={() => handleAccept(p)}
                  disabled={resolving === p.id || !p.skill_id}
                  title={!p.skill_id ? 'Attach a target skill first' : undefined}
                  className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded disabled:opacity-50"
                >
                  Accept → new version
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
