import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import axios from 'axios'

interface SharedPayload {
  uuid: string
  content: string
  token_estimate: number
  target_model: string | null
  model_context_window: number
  skill_count: number
  skill_breakdown: Array<{
    slug: string
    name: string
    token_estimate: number
    starts_at_char: number
    ends_at_char: number
    tuned_for_model: string | null
    last_validated_model: string | null
  }>
  agent: { name: string; display_name?: string }
  expires_at: string | null
  is_snapshot: boolean
}

type LoadState =
  | { status: 'loading' }
  | { status: 'ok'; payload: SharedPayload }
  | { status: 'expired' }
  | { status: 'not_found' }
  | { status: 'error'; message: string }

export function SharedCompose() {
  const { uuid } = useParams<{ uuid: string }>()
  const [state, setState] = useState<LoadState>({ status: 'loading' })

  useEffect(() => {
    if (!uuid) return
    axios
      .get<{ data: SharedPayload }>(`/api/share/compose/${uuid}`)
      .then((r) => setState({ status: 'ok', payload: r.data.data }))
      .catch((err) => {
        const code = err?.response?.status
        if (code === 410) setState({ status: 'expired' })
        else if (code === 404) setState({ status: 'not_found' })
        else setState({ status: 'error', message: err?.message ?? 'Failed to load' })
      })
  }, [uuid])

  if (state.status === 'loading') {
    return (
      <div className="flex items-center justify-center min-h-screen bg-background text-muted-foreground text-sm">
        Loading…
      </div>
    )
  }

  if (state.status === 'expired') {
    return (
      <Message title="Share link expired" body="This shared preview is no longer available." />
    )
  }

  if (state.status === 'not_found') {
    return <Message title="Not found" body="This share link does not exist." />
  }

  if (state.status === 'error') {
    return <Message title="Something went wrong" body={state.message} />
  }

  const { payload } = state
  const usagePct = payload.model_context_window
    ? Math.min(100, Math.round((payload.token_estimate / payload.model_context_window) * 100))
    : null

  return (
    <div className="min-h-screen bg-background text-foreground">
      <header className="border-b border-border px-6 py-4">
        <div className="max-w-5xl mx-auto flex items-center justify-between gap-4">
          <div>
            <h1 className="text-base font-semibold">
              {payload.agent.display_name || payload.agent.name}
            </h1>
            <p className="text-xs text-muted-foreground mt-0.5">
              Shared compose preview · {payload.is_snapshot ? 'snapshot' : 'live render'}
              {payload.expires_at && ` · expires ${new Date(payload.expires_at).toLocaleDateString()}`}
            </p>
          </div>
          <div className="text-right text-xs text-muted-foreground">
            <div>{payload.target_model ?? 'no model'}</div>
            <div>
              {payload.token_estimate.toLocaleString()} tokens
              {usagePct !== null && ` · ${usagePct}% of context`}
            </div>
          </div>
        </div>
      </header>

      <main className="max-w-5xl mx-auto px-6 py-6 grid grid-cols-1 lg:grid-cols-[1fr_260px] gap-6">
        <article>
          <pre className="whitespace-pre-wrap text-sm leading-relaxed font-mono bg-muted/30 rounded p-4 border border-border">
            {payload.content}
          </pre>
        </article>
        <aside className="space-y-3">
          <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Skill breakdown ({payload.skill_breakdown.length})
          </h2>
          {payload.skill_breakdown.length === 0 && (
            <p className="text-xs text-muted-foreground">No skills in this compose.</p>
          )}
          <ul className="space-y-2">
            {payload.skill_breakdown.map((skill) => (
              <li key={skill.slug} className="text-xs border border-border rounded p-2">
                <div className="font-medium text-foreground">{skill.name}</div>
                <div className="text-muted-foreground">{skill.token_estimate} tokens</div>
                {(skill.tuned_for_model || skill.last_validated_model) && (
                  <div className="mt-1 flex flex-wrap gap-1">
                    {skill.tuned_for_model && (
                      <span className="font-mono px-1.5 py-0.5 bg-muted/60 rounded text-[10px]">
                        tuned: {skill.tuned_for_model}
                      </span>
                    )}
                    {skill.last_validated_model && (
                      <span className="font-mono px-1.5 py-0.5 bg-muted/60 rounded text-[10px]">
                        validated: {skill.last_validated_model}
                      </span>
                    )}
                  </div>
                )}
              </li>
            ))}
          </ul>
        </aside>
      </main>
    </div>
  )
}

function Message({ title, body }: { title: string; body: string }) {
  return (
    <div className="flex items-center justify-center min-h-screen bg-background">
      <div className="text-center max-w-sm px-6">
        <h1 className="text-lg font-semibold mb-2">{title}</h1>
        <p className="text-sm text-muted-foreground">{body}</p>
      </div>
    </div>
  )
}
