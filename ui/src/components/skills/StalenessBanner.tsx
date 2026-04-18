import { useEffect, useState } from 'react'
import { fetchStaleness, type StalenessStatus } from '@/api/client'

interface StalenessBannerProps {
  skillId: number
  currentModel?: string | null
  refreshKey?: number
}

const REASON_STYLES: Record<
  StalenessStatus['reason'],
  { bg: string; border: string; text: string; label: string } | null
> = {
  ok: null,
  needs_tuning: {
    bg: 'bg-muted/40',
    border: 'border-border',
    text: 'text-muted-foreground',
    label: 'Needs tuning',
  },
  needs_revalidation: {
    bg: 'bg-yellow-500/10',
    border: 'border-yellow-500/30',
    text: 'text-yellow-700 dark:text-yellow-400',
    label: 'Needs revalidation',
  },
  model_deprecated: {
    bg: 'bg-red-500/10',
    border: 'border-red-500/30',
    text: 'text-red-700 dark:text-red-400',
    label: 'Model deprecated',
  },
}

export function StalenessBanner({ skillId, currentModel, refreshKey = 0 }: StalenessBannerProps) {
  const [status, setStatus] = useState<StalenessStatus | null>(null)

  useEffect(() => {
    let cancelled = false
    fetchStaleness(skillId, currentModel ?? undefined)
      .then((s) => {
        if (!cancelled) setStatus(s)
      })
      .catch(() => {
        if (!cancelled) setStatus(null)
      })
    return () => {
      cancelled = true
    }
  }, [skillId, currentModel, refreshKey])

  if (!status || status.reason === 'ok') return null

  const style = REASON_STYLES[status.reason]
  if (!style) return null

  return (
    <div
      className={`flex items-center gap-3 px-3 h-8 border-b ${style.border} ${style.bg} text-xs`}
    >
      <span className={`shrink-0 font-semibold ${style.text}`}>{style.label}</span>
      <span className="flex-1 min-w-0 text-foreground/80 truncate">
        {status.suggested_action}
      </span>
      {status.tuned_for_model && (
        <span className="shrink-0 text-muted-foreground">
          tuned: {status.tuned_for_model}
        </span>
      )}
    </div>
  )
}
