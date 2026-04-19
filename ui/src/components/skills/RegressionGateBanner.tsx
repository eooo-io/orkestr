import { useEffect, useState } from 'react'
import { fetchGateStatus, type GateStatus } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { RegressionDeltaModal } from '@/components/skills/RegressionDeltaModal'

interface Props {
  skillId: number
  failThresholdDelta?: number
}

export function RegressionGateBanner({ skillId, failThresholdDelta = -5 }: Props) {
  const pending = useAppStore((s) => s.pendingEvalGates[skillId])
  const clearPendingGate = useAppStore((s) => s.clearPendingGate)
  const [status, setStatus] = useState<GateStatus | null>(null)
  const [deltaRunId, setDeltaRunId] = useState<number | null>(null)

  useEffect(() => {
    let cancelled = false
    let timer: ReturnType<typeof setTimeout> | null = null

    const tick = async () => {
      try {
        const s = await fetchGateStatus(skillId)
        if (cancelled) return
        setStatus(s)

        if (s.pending_count > 0) {
          timer = setTimeout(tick, 3000)
        } else if (pending) {
          clearPendingGate(skillId)
        }
      } catch {
        if (!cancelled) timer = setTimeout(tick, 6000)
      }
    }

    tick()

    return () => {
      cancelled = true
      if (timer) clearTimeout(timer)
    }
  }, [skillId, pending, clearPendingGate])

  if (!status || !status.enabled) return null

  const runsCount = status.runs.length
  const completed = status.runs.filter((r) => r.status === 'completed')
  const pendingCount = status.pending_count
  const failed = completed.find(
    (r) => r.delta && r.delta.overall_delta < failThresholdDelta,
  )

  let tone: 'info' | 'warn' | 'danger' = 'info'
  let label = ''
  let body = ''

  if (pendingCount > 0) {
    tone = 'info'
    const total = pending?.runIds.length ?? runsCount
    label = pendingCount === total ? 'Queued' : `Running (${total - pendingCount}/${total})`
    body = 'Regression evals in progress…'
  } else if (failed) {
    tone = 'danger'
    label = `Δ ${failed.delta!.overall_delta.toFixed(1)}`
    body = 'Regression threshold breached — review the delta.'
  } else if (completed.length > 0) {
    const worst = completed.reduce((min, r) => {
      const d = r.delta?.overall_delta ?? 0
      return d < min ? d : min
    }, 0)
    if (worst < 0) {
      tone = 'warn'
      label = `Δ ${worst.toFixed(1)}`
      body = 'Score dropped but within threshold.'
    } else {
      tone = 'info'
      label = `Δ +${worst.toFixed(1)}`
      body = 'Latest eval improved or held.'
    }
  } else {
    return null
  }

  const style = {
    info: { bg: 'bg-muted/40', border: 'border-border', text: 'text-muted-foreground' },
    warn: { bg: 'bg-yellow-500/10', border: 'border-yellow-500/30', text: 'text-yellow-700 dark:text-yellow-400' },
    danger: { bg: 'bg-red-500/10', border: 'border-red-500/30', text: 'text-red-700 dark:text-red-400' },
  }[tone]

  return (
    <>
      <div className={`flex items-center gap-3 px-3 h-8 border-b ${style.border} ${style.bg} text-xs`}>
        <span className={`shrink-0 font-semibold ${style.text}`}>{label}</span>
        <span className="flex-1 min-w-0 text-foreground/80 truncate">{body}</span>
        {failed && (
          <button
            onClick={() => setDeltaRunId(failed.run_id)}
            className="shrink-0 text-primary hover:underline font-medium"
          >
            see delta
          </button>
        )}
      </div>

      {deltaRunId !== null && status && (
        <RegressionDeltaModal
          runEntry={status.runs.find((r) => r.run_id === deltaRunId)!}
          onClose={() => setDeltaRunId(null)}
        />
      )}
    </>
  )
}
