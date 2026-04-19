import { useEffect, useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { fetchAgentProfile, type AgentProfile } from '@/api/client'

export function AgentProfile() {
  const { id } = useParams<{ id: string }>()
  const [profile, setProfile] = useState<AgentProfile | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!id) return
    fetchAgentProfile(parseInt(id))
      .then(setProfile)
      .catch(() => setError('Failed to load profile'))
  }, [id])

  if (error) return <div className="p-6 text-sm text-destructive">{error}</div>
  if (!profile) return <div className="p-6 text-sm text-muted-foreground animate-pulse">Loading…</div>

  const reputation = profile.reputation_score !== null
    ? `${Number(profile.reputation_score).toFixed(1)}`
    : '—'

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      <header className="flex items-start gap-4">
        <div className="flex-1">
          <h1 className="text-2xl font-semibold">
            {profile.icon && <span className="mr-2">{profile.icon}</span>}
            {profile.name}
          </h1>
          <p className="text-sm text-muted-foreground mt-1">{profile.description ?? '—'}</p>
          <p className="text-xs text-muted-foreground mt-2">{profile.domain_summary}</p>
        </div>
        <div className="shrink-0 text-right">
          <div className="text-xs text-muted-foreground">Reputation</div>
          <div className="text-2xl font-mono font-semibold">{reputation}</div>
          <div className="text-[10px] text-muted-foreground">
            {profile.reputation_last_computed_at
              ? new Date(profile.reputation_last_computed_at).toLocaleDateString()
              : 'not yet computed'}
          </div>
        </div>
      </header>

      <section className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="border border-border rounded p-3">
          <div className="text-xs font-semibold uppercase text-muted-foreground mb-1">Owner</div>
          {profile.owner ? (
            <div>
              <div className="text-sm">{profile.owner.name}</div>
              <div className="text-xs text-muted-foreground">{profile.owner.email}</div>
            </div>
          ) : (
            <div className="text-sm text-muted-foreground">No owner set</div>
          )}
        </div>
        <div className="border border-border rounded p-3">
          <div className="text-xs font-semibold uppercase text-muted-foreground mb-1">Invocations</div>
          <div className="text-2xl font-mono">{profile.total_invocations}</div>
        </div>
      </section>

      <section>
        <h2 className="text-sm font-semibold uppercase text-muted-foreground mb-2">
          Recent public runs
        </h2>
        {profile.recent_runs.length === 0 ? (
          <p className="text-sm text-muted-foreground">No public runs yet.</p>
        ) : (
          <ul className="divide-y divide-border border border-border rounded">
            {profile.recent_runs.map((r) => (
              <li key={r.id} className="p-3 flex items-center gap-3 text-sm">
                <Link to={`/runs/${r.id}`} className="text-primary hover:underline font-mono text-xs">
                  #{r.id}
                </Link>
                <span className="px-1.5 py-0.5 rounded bg-muted/60 text-[10px] font-mono">
                  {r.status}
                </span>
                <span className="text-muted-foreground text-xs flex-1">
                  {new Date(r.created_at).toLocaleString()}
                </span>
                <span className="text-xs text-muted-foreground">{r.total_tokens} tok</span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  )
}
