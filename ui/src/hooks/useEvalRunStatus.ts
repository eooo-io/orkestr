import { useEffect, useState } from 'react'
import { fetchEvalRun } from '@/api/client'
import type { SkillEvalRun } from '@/types'

/**
 * Poll an eval run until it leaves `pending`/`running`. Returns the latest
 * run snapshot, whether it's still live, and a `version` integer that bumps
 * on every update so callers can `useEffect` off of it.
 */
export function useEvalRunStatus(runId: number | null, intervalMs = 3000) {
  const [run, setRun] = useState<SkillEvalRun | null>(null)
  const [version, setVersion] = useState(0)

  useEffect(() => {
    if (!runId) {
      setRun(null)
      return
    }

    let cancelled = false
    let timer: ReturnType<typeof setTimeout> | null = null

    const tick = async () => {
      try {
        const latest = await fetchEvalRun(runId)
        if (cancelled) return
        setRun(latest)
        setVersion((v) => v + 1)

        if (latest.status === 'pending' || latest.status === 'running') {
          timer = setTimeout(tick, intervalMs)
        }
      } catch {
        if (!cancelled) {
          timer = setTimeout(tick, intervalMs * 2)
        }
      }
    }

    tick()

    return () => {
      cancelled = true
      if (timer) clearTimeout(timer)
    }
  }, [runId, intervalMs])

  const isLive = run?.status === 'pending' || run?.status === 'running'

  return { run, isLive, version }
}
