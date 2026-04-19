import { X } from 'lucide-react'
import type { GateStatus } from '@/api/client'

type RunEntry = GateStatus['runs'][number]

interface Props {
  runEntry: RunEntry
  onClose: () => void
}

export function RegressionDeltaModal({ runEntry, onClose }: Props) {
  const delta = runEntry.delta

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-foreground/30">
      <div className="bg-background elevation-4 w-full max-w-2xl max-h-[85vh] flex flex-col border border-border rounded">
        <div className="flex items-center justify-between p-4 border-b border-border">
          <div>
            <h3 className="text-sm font-semibold">Regression delta — run #{runEntry.run_id}</h3>
            <p className="text-xs text-muted-foreground mt-0.5">
              Overall score: {runEntry.overall_score ?? '—'}
              {delta && (
                <>
                  {' · '}
                  <span className={delta.overall_delta < 0 ? 'text-destructive' : 'text-foreground'}>
                    Δ {delta.overall_delta > 0 ? '+' : ''}
                    {delta.overall_delta.toFixed(1)}
                  </span>
                </>
              )}
            </p>
          </div>
          <button onClick={onClose} className="p-1 hover:bg-muted rounded">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4">
          {!delta || delta.per_prompt.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No baseline available — this was the first completed run for this suite+model.
            </p>
          ) : (
            <table className="w-full text-xs">
              <thead>
                <tr className="border-b border-border text-left text-muted-foreground">
                  <th className="py-1.5 pr-2">Prompt</th>
                  <th className="py-1.5 px-2 text-right">Baseline</th>
                  <th className="py-1.5 px-2 text-right">Current</th>
                  <th className="py-1.5 pl-2 text-right">Δ</th>
                </tr>
              </thead>
              <tbody>
                {delta.per_prompt.map((p, i) => {
                  const d = p.delta
                  const badge =
                    d === null
                      ? 'text-muted-foreground'
                      : d < 0
                        ? 'text-destructive'
                        : d > 0
                          ? 'text-green-600 dark:text-green-400'
                          : 'text-muted-foreground'

                  return (
                    <tr key={i} className="border-b border-border">
                      <td className="py-1.5 pr-2">#{p.prompt_id ?? i + 1}</td>
                      <td className="py-1.5 px-2 text-right text-muted-foreground">
                        {p.baseline ?? '—'}
                      </td>
                      <td className="py-1.5 px-2 text-right">{p.current}</td>
                      <td className={`py-1.5 pl-2 text-right font-mono ${badge}`}>
                        {d === null ? '—' : d > 0 ? `+${d}` : d}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  )
}
