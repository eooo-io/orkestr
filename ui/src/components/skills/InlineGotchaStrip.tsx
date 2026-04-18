import { useEffect, useState } from 'react'
import { fetchGotchas } from '@/api/client'
import type { SkillGotcha } from '@/types'

interface InlineGotchaStripProps {
  skillId: number
  refreshKey?: number
  onManage: () => void
}

const SEVERITY_ORDER: SkillGotcha['severity'][] = ['critical', 'warning', 'info']

const SEVERITY_DOT: Record<SkillGotcha['severity'], string> = {
  critical: 'bg-red-500',
  warning: 'bg-yellow-500',
  info: 'bg-blue-500',
}

const SEVERITY_LABEL: Record<SkillGotcha['severity'], string> = {
  critical: 'critical',
  warning: 'warning',
  info: 'info',
}

export function InlineGotchaStrip({ skillId, refreshKey = 0, onManage }: InlineGotchaStripProps) {
  const [active, setActive] = useState<SkillGotcha[]>([])

  useEffect(() => {
    let cancelled = false
    fetchGotchas(skillId, true)
      .then((list) => {
        if (!cancelled) setActive(list)
      })
      .catch(() => {
        if (!cancelled) setActive([])
      })
    return () => {
      cancelled = true
    }
  }, [skillId, refreshKey])

  if (active.length === 0) return null

  const counts = SEVERITY_ORDER.map((sev) => ({
    sev,
    count: active.filter((g) => g.severity === sev).length,
  })).filter((c) => c.count > 0)

  const topCritical = active
    .filter((g) => g.severity === 'critical')
    .slice(0, 2)
    .map((g) => g.title)

  return (
    <div className="flex items-center gap-3 px-3 h-8 border-b border-border bg-yellow-500/5 text-xs">
      <div className="flex items-center gap-2 shrink-0">
        {counts.map(({ sev, count }) => (
          <span key={sev} className="flex items-center gap-1">
            <span className={`w-1.5 h-1.5 rounded-full ${SEVERITY_DOT[sev]}`} />
            <span className="text-muted-foreground">
              {count} {SEVERITY_LABEL[sev]}
            </span>
          </span>
        ))}
      </div>
      {topCritical.length > 0 && (
        <div className="flex-1 min-w-0 text-foreground/80 truncate">
          {topCritical.join(' · ')}
        </div>
      )}
      {topCritical.length === 0 && <div className="flex-1" />}
      <button
        onClick={onManage}
        className="shrink-0 text-primary hover:underline font-medium"
      >
        manage
      </button>
    </div>
  )
}
