import { useEffect, useState } from 'react'
import {
  X,
  Loader2,
  Clock,
  Webhook,
  Zap,
  ChevronDown,
  ChevronRight,
  Copy,
  Check,
} from 'lucide-react'
import { fetchProjectAgents, createSchedule, updateSchedule } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import { CronBuilder } from './CronBuilder'
import type { AgentSchedule, ProjectAgent } from '@/types'

const TIMEZONES = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'Europe/London',
  'Europe/Berlin',
  'Asia/Tokyo',
]

const SCHEDULE_EVENTS = [
  { value: 'skill.created', label: 'Skill Created' },
  { value: 'skill.updated', label: 'Skill Updated' },
  { value: 'skill.deleted', label: 'Skill Deleted' },
  { value: 'project.synced', label: 'Project Synced' },
  { value: 'agent.executed', label: 'Agent Executed' },
]

interface Props {
  projectId: number
  schedule?: AgentSchedule
  onClose: () => void
  onSaved: (schedule: AgentSchedule) => void
}

export function ScheduleFormModal({ projectId, schedule, onClose, onSaved }: Props) {
  const { showToast } = useAppStore()
  const [agents, setAgents] = useState<ProjectAgent[]>([])
  const [loadingAgents, setLoadingAgents] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [showAdvanced, setShowAdvanced] = useState(false)
  const [copied, setCopied] = useState(false)

  // Form fields
  const [name, setName] = useState(schedule?.name || '')
  const [agentId, setAgentId] = useState<number>(schedule?.agent_id || 0)
  const [triggerType, setTriggerType] = useState<'cron' | 'webhook' | 'event'>(
    schedule?.trigger_type || 'cron',
  )
  const [cronExpression, setCronExpression] = useState(
    schedule?.cron_expression || '0 9 * * *',
  )
  const [timezone, setTimezone] = useState(schedule?.timezone || 'UTC')
  const [eventName, setEventName] = useState(schedule?.event_name || 'skill.updated')
  const [inputTemplate, setInputTemplate] = useState(
    schedule?.input_template ? JSON.stringify(schedule.input_template, null, 2) : '',
  )
  const [maxRetries, setMaxRetries] = useState(schedule?.max_retries ?? 3)

  useEffect(() => {
    fetchProjectAgents(projectId)
      .then((data) => {
        setAgents(data)
        if (!schedule && data.length > 0 && !agentId) {
          setAgentId(data[0].id)
        }
      })
      .finally(() => setLoadingAgents(false))
  }, [projectId])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!name.trim() || !agentId) return

    let parsedInputTemplate: Record<string, unknown> | null = null
    if (inputTemplate.trim()) {
      try {
        parsedInputTemplate = JSON.parse(inputTemplate)
      } catch {
        showToast('Invalid JSON in input template', 'error')
        return
      }
    }

    setSubmitting(true)
    try {
      const payload: Partial<AgentSchedule> = {
        name: name.trim(),
        agent_id: agentId,
        trigger_type: triggerType,
        cron_expression: triggerType === 'cron' ? cronExpression : null,
        timezone,
        event_name: triggerType === 'event' ? eventName : null,
        input_template: parsedInputTemplate,
        max_retries: maxRetries,
      }

      let saved: AgentSchedule
      if (schedule) {
        saved = await updateSchedule(schedule.id, payload)
        showToast('Schedule updated')
      } else {
        saved = await createSchedule(projectId, payload)
        showToast('Schedule created')
      }
      onSaved(saved)
    } catch {
      showToast('Failed to save schedule', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const copyWebhookUrl = () => {
    if (schedule?.webhook_url) {
      navigator.clipboard.writeText(schedule.webhook_url)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }
  }

  const selectClass =
    'w-full border border-border bg-background px-3 py-2 text-sm rounded'
  const inputClass =
    'w-full border border-border bg-background px-3 py-2 text-sm rounded'

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-background border border-border rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between px-6 py-4 border-b border-border">
          <h2 className="text-lg font-semibold">
            {schedule ? 'Edit Schedule' : 'New Schedule'}
          </h2>
          <button onClick={onClose} className="text-muted-foreground hover:text-foreground">
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-4 space-y-5">
          {/* Name */}
          <div>
            <label className="text-sm font-medium mb-1.5 block">Name</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Daily code review"
              required
              className={inputClass}
            />
          </div>

          {/* Agent */}
          <div>
            <label className="text-sm font-medium mb-1.5 block">Agent</label>
            {loadingAgents ? (
              <div className="flex items-center gap-2 text-sm text-muted-foreground py-2">
                <Loader2 className="h-4 w-4 animate-spin" />
                Loading agents...
              </div>
            ) : agents.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No agents configured for this project. Add agents first.
              </p>
            ) : (
              <select
                value={agentId}
                onChange={(e) => setAgentId(parseInt(e.target.value))}
                className={selectClass}
                required
              >
                <option value={0} disabled>Select an agent</option>
                {agents.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.name} ({a.role})
                  </option>
                ))}
              </select>
            )}
          </div>

          {/* Trigger Type */}
          <div>
            <label className="text-sm font-medium mb-1.5 block">Trigger Type</label>
            <div className="flex items-center gap-1 p-1 bg-muted/40 border border-border rounded">
              <button
                type="button"
                onClick={() => setTriggerType('cron')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-sm rounded transition-colors flex-1 justify-center ${
                  triggerType === 'cron'
                    ? 'bg-background shadow text-foreground'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <Clock className="h-3.5 w-3.5" />
                Cron
              </button>
              <button
                type="button"
                onClick={() => setTriggerType('webhook')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-sm rounded transition-colors flex-1 justify-center ${
                  triggerType === 'webhook'
                    ? 'bg-background shadow text-foreground'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <Webhook className="h-3.5 w-3.5" />
                Webhook
              </button>
              <button
                type="button"
                onClick={() => setTriggerType('event')}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-sm rounded transition-colors flex-1 justify-center ${
                  triggerType === 'event'
                    ? 'bg-background shadow text-foreground'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                <Zap className="h-3.5 w-3.5" />
                Event
              </button>
            </div>
          </div>

          {/* Cron Panel */}
          {triggerType === 'cron' && (
            <div className="space-y-3">
              <CronBuilder value={cronExpression} onChange={setCronExpression} />
              <div>
                <label className="text-sm font-medium mb-1.5 block">Timezone</label>
                <select
                  value={timezone}
                  onChange={(e) => setTimezone(e.target.value)}
                  className={selectClass}
                >
                  {TIMEZONES.map((tz) => (
                    <option key={tz} value={tz}>{tz}</option>
                  ))}
                </select>
              </div>
            </div>
          )}

          {/* Webhook Panel */}
          {triggerType === 'webhook' && (
            <div className="space-y-2">
              {schedule?.webhook_url ? (
                <div>
                  <label className="text-sm font-medium mb-1.5 block">Webhook URL</label>
                  <div className="flex items-center gap-2">
                    <input
                      type="text"
                      value={schedule.webhook_url}
                      readOnly
                      className={`${inputClass} font-mono text-xs bg-muted/30`}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={copyWebhookUrl}
                    >
                      {copied ? (
                        <Check className="h-3.5 w-3.5 text-green-500" />
                      ) : (
                        <Copy className="h-3.5 w-3.5" />
                      )}
                    </Button>
                  </div>
                  {schedule.webhook_token && (
                    <p className="text-xs text-muted-foreground mt-1.5 font-mono">
                      Token: {schedule.webhook_token}
                    </p>
                  )}
                </div>
              ) : (
                <div className="bg-muted/30 border border-border rounded px-4 py-3">
                  <p className="text-sm text-muted-foreground">
                    A unique webhook URL and token will be generated when you save this schedule.
                    You can then use the URL to trigger the agent externally.
                  </p>
                </div>
              )}
            </div>
          )}

          {/* Event Panel */}
          {triggerType === 'event' && (
            <div>
              <label className="text-sm font-medium mb-1.5 block">Event Name</label>
              <select
                value={eventName}
                onChange={(e) => setEventName(e.target.value)}
                className={selectClass}
              >
                {SCHEDULE_EVENTS.map((ev) => (
                  <option key={ev.value} value={ev.value}>
                    {ev.label}
                  </option>
                ))}
              </select>
            </div>
          )}

          {/* Advanced Section */}
          <div className="border border-border rounded">
            <button
              type="button"
              onClick={() => setShowAdvanced(!showAdvanced)}
              className="flex items-center gap-2 w-full px-4 py-2.5 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors"
            >
              {showAdvanced ? (
                <ChevronDown className="h-4 w-4" />
              ) : (
                <ChevronRight className="h-4 w-4" />
              )}
              Advanced
            </button>
            {showAdvanced && (
              <div className="px-4 pb-4 space-y-3 border-t border-border pt-3">
                <div>
                  <label className="text-sm font-medium mb-1.5 block">
                    Input Template (JSON)
                  </label>
                  <textarea
                    value={inputTemplate}
                    onChange={(e) => setInputTemplate(e.target.value)}
                    placeholder='{"key": "value"}'
                    rows={4}
                    className={`${inputClass} font-mono text-xs`}
                  />
                  <p className="text-xs text-muted-foreground mt-1">
                    JSON object passed as input to the agent on each run.
                  </p>
                </div>
                <div>
                  <label className="text-sm font-medium mb-1.5 block">
                    Max Retries
                  </label>
                  <input
                    type="number"
                    value={maxRetries}
                    onChange={(e) => setMaxRetries(parseInt(e.target.value) || 0)}
                    min={0}
                    max={10}
                    className={`${inputClass} w-24`}
                  />
                </div>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="flex items-center justify-end gap-2 pt-2 border-t border-border">
            <Button type="button" variant="ghost" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" disabled={submitting || !name.trim() || !agentId}>
              {submitting && <Loader2 className="h-4 w-4 animate-spin mr-1" />}
              {schedule ? 'Update Schedule' : 'Create Schedule'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
