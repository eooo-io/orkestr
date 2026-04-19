import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { fetchWorkFeed, forkRun, type WorkFeedEntry } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'

export function WorkFeed() {
  const [entries, setEntries] = useState<WorkFeedEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [forking, setForking] = useState<number | null>(null)
  const showToast = useAppStore((s) => s.showToast)

  useEffect(() => {
    fetchWorkFeed()
      .then((res) => setEntries(res.data))
      .finally(() => setLoading(false))
  }, [])

  const handleFork = async (runId: number) => {
    setForking(runId)
    try {
      const result = await forkRun(runId)
      showToast(`Forked as run #${result.id}`)
    } catch {
      showToast('Failed to fork', 'error')
    } finally {
      setForking(null)
    }
  }

  if (loading) {
    return <div className="p-6 text-sm text-muted-foreground animate-pulse">Loading feed…</div>
  }

  return (
    <div className="max-w-3xl mx-auto p-6 space-y-4">
      <header>
        <h1 className="text-2xl font-semibold">Work feed</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Recent public runs across your organization — fork any of them to start from the same context.
        </p>
      </header>

      {entries.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          Nothing here yet. Runs become visible once someone marks them <strong>team</strong> or{' '}
          <strong>org</strong>.
        </p>
      ) : (
        <ul className="space-y-3">
          {entries.map((entry) => (
            <li key={entry.id} className="border border-border rounded p-3 space-y-2">
              <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <span className="font-mono">#{entry.id}</span>
                <span className="px-1.5 py-0.5 rounded bg-muted/60 font-mono">
                  {entry.status}
                </span>
                <span>· {new Date(entry.created_at).toLocaleString()}</span>
                {entry.halt_reason && (
                  <span className="text-destructive">· halted: {entry.halt_reason}</span>
                )}
              </div>

              <div className="flex items-start gap-3">
                <div className="flex-1 min-w-0">
                  {entry.agent && (
                    <Link
                      to={`/agents/${entry.agent.id}/profile`}
                      className="text-sm font-medium text-primary hover:underline"
                    >
                      {entry.agent.icon && <span className="mr-1">{entry.agent.icon}</span>}
                      {entry.agent.name}
                    </Link>
                  )}
                  {entry.agent?.owner && (
                    <span className="text-xs text-muted-foreground ml-2">
                      · {entry.agent.owner.name}
                    </span>
                  )}
                  <p className="text-sm text-foreground/90 mt-1">{entry.input_summary || '—'}</p>
                  <p className="text-[11px] text-muted-foreground mt-1">
                    {entry.total_tokens} tokens
                    {entry.creator && ` · triggered by ${entry.creator.name}`}
                  </p>
                </div>
                <button
                  onClick={() => handleFork(entry.id)}
                  disabled={forking === entry.id}
                  className="shrink-0 text-xs px-2.5 py-1 border border-border rounded hover:bg-muted/40 disabled:opacity-50"
                >
                  {forking === entry.id ? 'Forking…' : 'Fork'}
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
