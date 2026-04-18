import { useEffect, useState } from 'react'
import {
  fetchEvalGate,
  updateEvalGate,
  runGateNow,
  type SkillEvalGateConfig,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import type { SkillEvalSuite } from '@/types'

interface Props {
  skillId: number
  skillName: string
  suites: SkillEvalSuite[]
}

export function GateConfigPanel({ skillId, skillName, suites }: Props) {
  const [gate, setGate] = useState<SkillEvalGateConfig | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [runningNow, setRunningNow] = useState(false)
  const registerPendingGate = useAppStore((s) => s.registerPendingGate)
  const showToast = useAppStore((s) => s.showToast)

  useEffect(() => {
    fetchEvalGate(skillId)
      .then(setGate)
      .finally(() => setLoading(false))
  }, [skillId])

  const save = async (patch: Partial<SkillEvalGateConfig>) => {
    setSaving(true)
    try {
      const next = await updateEvalGate(skillId, patch)
      setGate(next)
    } finally {
      setSaving(false)
    }
  }

  const toggleSuite = (suiteId: number) => {
    if (!gate) return
    const current = new Set(gate.required_suite_ids ?? [])
    if (current.has(suiteId)) current.delete(suiteId)
    else current.add(suiteId)
    save({ required_suite_ids: Array.from(current) })
  }

  const handleRunNow = async () => {
    setRunningNow(true)
    try {
      const decision = await runGateNow(skillId)
      if (decision.reason !== 'dispatched') {
        showToast(`Cannot run: ${decision.reason}`, 'error')
      } else {
        registerPendingGate({
          skillId,
          skillName,
          runIds: decision.enqueued_run_ids,
          baselineInfo: decision.baseline_info as PendingBaselineInfo[],
          estDurationSeconds: decision.est_duration_seconds,
          startedAt: Date.now(),
        })
        showToast(`Queued ${decision.enqueued_run_ids.length} eval run(s)`)
      }
    } catch {
      showToast('Failed to run eval gate', 'error')
    } finally {
      setRunningNow(false)
    }
  }

  if (loading || !gate) {
    return <div className="p-3 text-xs text-muted-foreground">Loading gate config…</div>
  }

  return (
    <div className="p-3 space-y-3 border-b border-border text-xs">
      <div className="flex items-center justify-between">
        <span className="font-semibold uppercase tracking-wide text-muted-foreground">
          Regression gate
        </span>
        <label className="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            checked={gate.enabled}
            disabled={saving}
            onChange={(e) => save({ enabled: e.target.checked })}
            className="rounded border-input"
          />
          <span>Enabled</span>
        </label>
      </div>

      {gate.enabled && (
        <>
          <div>
            <div className="font-medium mb-1">Required suites</div>
            {suites.length === 0 ? (
              <p className="text-muted-foreground">Create a suite above to use as a gate.</p>
            ) : (
              <div className="space-y-1">
                {suites.map((s) => {
                  const checked = (gate.required_suite_ids ?? []).includes(s.id)
                  return (
                    <label key={s.id} className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={checked}
                        disabled={saving}
                        onChange={() => toggleSuite(s.id)}
                        className="rounded border-input"
                      />
                      <span>{s.name}</span>
                    </label>
                  )
                })}
              </div>
            )}
          </div>

          <div className="flex items-center gap-2">
            <label className="text-muted-foreground">Fail threshold Δ</label>
            <input
              type="number"
              step="0.5"
              value={Number(gate.fail_threshold_delta)}
              disabled={saving}
              onChange={(e) => save({ fail_threshold_delta: parseFloat(e.target.value) })}
              className="w-20 px-2 py-1 text-xs border border-input bg-background rounded"
            />
          </div>

          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={gate.auto_run_on_save}
              disabled={saving}
              onChange={(e) => save({ auto_run_on_save: e.target.checked })}
              className="rounded border-input"
            />
            <span>Auto-run on save</span>
          </label>

          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={gate.block_sync}
              disabled={saving}
              onChange={(e) => save({ block_sync: e.target.checked })}
              className="rounded border-input"
            />
            <span>Block sync on failure</span>
          </label>

          <button
            onClick={handleRunNow}
            disabled={runningNow || !(gate.required_suite_ids ?? []).length}
            className="text-xs px-3 py-1.5 bg-primary text-primary-foreground rounded disabled:opacity-50"
          >
            {runningNow ? 'Queueing…' : 'Run gate now'}
          </button>
        </>
      )}
    </div>
  )
}

type PendingBaselineInfo = {
  suite_id: number
  suite_name: string
  run_id: number
  baseline_run_id: number | null
  baseline_score: number | null
}
