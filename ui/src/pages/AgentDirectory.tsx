import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { routeAgents, type AgentRankMatch } from '@/api/client'
import { fetchAgents } from '@/api/client'
import type { Agent } from '@/types'

export function AgentDirectory() {
  const [agents, setAgents] = useState<Agent[]>([])
  const [question, setQuestion] = useState('')
  const [matches, setMatches] = useState<AgentRankMatch[] | null>(null)
  const [routing, setRouting] = useState(false)

  useEffect(() => {
    fetchAgents().then(setAgents).catch(() => {})
  }, [])

  const handleAsk = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!question.trim()) return
    setRouting(true)
    try {
      const results = await routeAgents(question)
      setMatches(results)
    } finally {
      setRouting(false)
    }
  }

  return (
    <div className="max-w-5xl mx-auto p-6 space-y-6">
      <header>
        <h1 className="text-2xl font-semibold">Agent directory</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Browse agents in your organization or ask who should help with something specific.
        </p>
      </header>

      <form
        onSubmit={handleAsk}
        className="flex gap-2 border border-border rounded p-3 bg-muted/20"
      >
        <input
          type="text"
          value={question}
          onChange={(e) => setQuestion(e.target.value)}
          placeholder="Who should I ask about…"
          className="flex-1 px-2.5 py-1.5 text-sm border border-input bg-background rounded"
        />
        <button
          type="submit"
          disabled={routing || !question.trim()}
          className="text-xs px-3 py-1.5 bg-primary text-primary-foreground rounded disabled:opacity-50"
        >
          {routing ? 'Routing…' : 'Ask'}
        </button>
      </form>

      {matches !== null && (
        <section>
          <h2 className="text-sm font-semibold uppercase text-muted-foreground mb-2">
            Best matches
          </h2>
          {matches.length === 0 ? (
            <p className="text-sm text-muted-foreground">No matches found.</p>
          ) : (
            <ul className="space-y-2">
              {matches.map((m) => (
                <li key={m.agent_id} className="border border-border rounded p-3">
                  <div className="flex items-start gap-3">
                    <Link
                      to={`/agents/${m.agent_id}/profile`}
                      className="text-sm font-medium text-primary hover:underline"
                    >
                      {m.icon && <span className="mr-1">{m.icon}</span>}
                      {m.name}
                    </Link>
                    <span className="text-xs text-muted-foreground">{m.role}</span>
                    <span className="ml-auto text-xs font-mono text-muted-foreground">
                      score {m.score.toFixed(2)}
                    </span>
                  </div>
                  <p className="text-xs text-muted-foreground mt-1">{m.reasoning}</p>
                  {m.owner && (
                    <p className="text-[11px] text-muted-foreground mt-0.5">
                      owner: {m.owner.name}
                      {m.reputation_score !== null && ` · rep ${m.reputation_score}`}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      <section>
        <h2 className="text-sm font-semibold uppercase text-muted-foreground mb-2">
          All agents
        </h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {agents.map((a) => (
            <Link
              key={a.id}
              to={`/agents/${a.id}/profile`}
              className="border border-border rounded p-3 hover:bg-muted/30 transition-colors"
            >
              <div className="text-sm font-medium">
                {a.icon && <span className="mr-1">{a.icon}</span>}
                {a.name}
              </div>
              <div className="text-xs text-muted-foreground mt-0.5">{a.role}</div>
              {a.description && (
                <p className="text-xs text-muted-foreground/80 mt-1 line-clamp-2">{a.description}</p>
              )}
            </Link>
          ))}
        </div>
      </section>
    </div>
  )
}
