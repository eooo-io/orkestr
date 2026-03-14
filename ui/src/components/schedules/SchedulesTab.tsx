import { useEffect, useState } from 'react'
import {
  Plus,
  Loader2,
  Clock,
  Webhook,
  Zap,
  Play,
  Pencil,
  Trash2,
  Calendar,
  MoreHorizontal,
  AlertTriangle,
  History,
} from 'lucide-react'
import {
  fetchSchedules,
  deleteSchedule,
  toggleSchedule,
  triggerSchedule,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import { ScheduleFormModal } from './ScheduleFormModal'
import { cronToHuman } from './CronBuilder'
import type { AgentSchedule } from '@/types'

function relativeTime(dateStr: string | null): string {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const absDiff = Math.abs(diffMs)
  const isFuture = diffMs < 0

  const seconds = Math.floor(absDiff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)

  let text: string
  if (seconds < 60) {
    text = 'just now'
    return text
  } else if (minutes < 60) {
    text = `${minutes}m`
  } else if (hours < 24) {
    text = `${hours}h`
  } else if (days < 30) {
    text = `${days}d`
  } else {
    text = date.toLocaleDateString()
    return isFuture ? text : text
  }

  return isFuture ? `in ${text}` : `${text} ago`
}

const TRIGGER_ICONS: Record<string, React.ElementType> = {
  cron: Clock,
  webhook: Webhook,
  event: Zap,
}

const TRIGGER_COLORS: Record<string, string> = {
  cron: 'bg-blue-500/10 text-blue-500',
  webhook: 'bg-purple-500/10 text-purple-500',
  event: 'bg-amber-500/10 text-amber-500',
}

interface Props {
  projectId: number
}

export function SchedulesTab({ projectId }: Props) {
  const { showToast } = useAppStore()
  const [schedules, setSchedules] = useState<AgentSchedule[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [editingSchedule, setEditingSchedule] = useState<AgentSchedule | undefined>(undefined)
  const [triggeringId, setTriggeringId] = useState<number | null>(null)
  const [openMenuId, setOpenMenuId] = useState<number | null>(null)

  const loadSchedules = () => {
    fetchSchedules(projectId)
      .then(setSchedules)
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadSchedules()
  }, [projectId])

  // Close menu on outside click
  useEffect(() => {
    if (openMenuId === null) return
    const handler = () => setOpenMenuId(null)
    document.addEventListener('click', handler)
    return () => document.removeEventListener('click', handler)
  }, [openMenuId])

  const handleToggle = async (schedule: AgentSchedule) => {
    const newState = !schedule.is_enabled
    // Optimistic update
    setSchedules((prev) =>
      prev.map((s) => (s.id === schedule.id ? { ...s, is_enabled: newState } : s)),
    )
    try {
      await toggleSchedule(schedule.id, newState)
    } catch {
      setSchedules((prev) =>
        prev.map((s) => (s.id === schedule.id ? { ...s, is_enabled: !newState } : s)),
      )
      showToast('Failed to toggle schedule', 'error')
    }
  }

  const handleTrigger = async (schedule: AgentSchedule) => {
    setTriggeringId(schedule.id)
    setOpenMenuId(null)
    try {
      await triggerSchedule(schedule.id)
      showToast(`Triggered "${schedule.name}"`)
      loadSchedules()
    } catch {
      showToast('Failed to trigger schedule', 'error')
    } finally {
      setTriggeringId(null)
    }
  }

  const handleDelete = async (schedule: AgentSchedule) => {
    setOpenMenuId(null)
    if (!confirm(`Delete schedule "${schedule.name}"?`)) return
    try {
      await deleteSchedule(schedule.id)
      showToast('Schedule deleted')
      loadSchedules()
    } catch {
      showToast('Failed to delete schedule', 'error')
    }
  }

  const handleEdit = (schedule: AgentSchedule) => {
    setOpenMenuId(null)
    setEditingSchedule(schedule)
    setShowForm(true)
  }

  const handleSaved = () => {
    setShowForm(false)
    setEditingSchedule(undefined)
    loadSchedules()
  }

  const triggerInfo = (schedule: AgentSchedule): string => {
    if (schedule.trigger_type === 'cron' && schedule.cron_expression) {
      return cronToHuman(schedule.cron_expression)
    }
    if (schedule.trigger_type === 'webhook' && schedule.webhook_url) {
      return schedule.webhook_url
    }
    if (schedule.trigger_type === 'event' && schedule.event_name) {
      return schedule.event_name
    }
    return '-'
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-medium">Schedules</h3>
          <p className="text-xs text-muted-foreground mt-0.5">
            Automate agent execution with cron, webhooks, or events
          </p>
        </div>
        <Button
          size="sm"
          onClick={() => {
            setEditingSchedule(undefined)
            setShowForm(true)
          }}
        >
          <Plus className="h-4 w-4 mr-1" />
          New Schedule
        </Button>
      </div>

      {schedules.length === 0 ? (
        <div className="text-center py-12 text-muted-foreground">
          <Calendar className="h-8 w-8 mx-auto mb-2 opacity-40" />
          <p className="text-sm">No schedules configured.</p>
          <p className="text-xs mt-1">
            Create a schedule to automate agent execution.
          </p>
        </div>
      ) : (
        <div className="border border-border rounded overflow-hidden">
          {/* Table Header */}
          <div className="grid grid-cols-[1fr_140px_100px_1fr_100px_100px_60px_60px_40px] gap-2 px-4 py-2 bg-muted/30 border-b border-border text-xs font-medium text-muted-foreground">
            <span>Name</span>
            <span>Agent</span>
            <span>Type</span>
            <span>Schedule / Config</span>
            <span>Next Run</span>
            <span>Last Run</span>
            <span className="text-center">Runs</span>
            <span className="text-center">Status</span>
            <span />
          </div>

          {/* Table Body */}
          {schedules.map((schedule) => {
            const TriggerIcon = TRIGGER_ICONS[schedule.trigger_type] || Clock
            const triggerColor = TRIGGER_COLORS[schedule.trigger_type] || ''

            return (
              <div
                key={schedule.id}
                className="grid grid-cols-[1fr_140px_100px_1fr_100px_100px_60px_60px_40px] gap-2 px-4 py-3 border-b border-border last:border-b-0 items-center hover:bg-muted/20 transition-colors"
              >
                {/* Name */}
                <div className="min-w-0">
                  <button
                    onClick={() => handleEdit(schedule)}
                    className="text-sm font-medium text-foreground hover:text-primary truncate block text-left"
                  >
                    {schedule.name}
                  </button>
                  {schedule.last_error && (
                    <div className="flex items-center gap-1 text-xs text-red-500 mt-0.5">
                      <AlertTriangle className="h-3 w-3 shrink-0" />
                      <span className="truncate">{schedule.last_error}</span>
                    </div>
                  )}
                </div>

                {/* Agent */}
                <span className="text-sm text-muted-foreground truncate">
                  {schedule.agent?.name || '-'}
                </span>

                {/* Type Badge */}
                <span>
                  <span
                    className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium ${triggerColor}`}
                  >
                    <TriggerIcon className="h-3 w-3" />
                    {schedule.trigger_type.charAt(0).toUpperCase() + schedule.trigger_type.slice(1)}
                  </span>
                </span>

                {/* Schedule/Config */}
                <span className="text-xs text-muted-foreground font-mono truncate">
                  {triggerInfo(schedule)}
                </span>

                {/* Next Run */}
                <span className="text-xs text-muted-foreground">
                  {relativeTime(schedule.next_run_at)}
                </span>

                {/* Last Run */}
                <span className="text-xs text-muted-foreground">
                  {relativeTime(schedule.last_run_at)}
                </span>

                {/* Run Count */}
                <span className="text-xs text-muted-foreground text-center">
                  {schedule.run_count}
                  {schedule.failure_count > 0 && (
                    <span className="text-red-500 ml-0.5">
                      /{schedule.failure_count}
                    </span>
                  )}
                </span>

                {/* Toggle */}
                <div className="flex justify-center">
                  <button
                    onClick={() => handleToggle(schedule)}
                    className={`relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 ${
                      schedule.is_enabled ? 'bg-primary' : 'bg-muted'
                    }`}
                  >
                    <span
                      className={`pointer-events-none block h-4 w-4 rounded-full bg-background shadow-lg ring-0 transition-transform ${
                        schedule.is_enabled ? 'translate-x-4' : 'translate-x-0'
                      }`}
                    />
                  </button>
                </div>

                {/* Actions Menu */}
                <div className="relative flex justify-center">
                  <button
                    onClick={(e) => {
                      e.stopPropagation()
                      setOpenMenuId(openMenuId === schedule.id ? null : schedule.id)
                    }}
                    className="text-muted-foreground hover:text-foreground p-1 rounded"
                  >
                    <MoreHorizontal className="h-4 w-4" />
                  </button>
                  {openMenuId === schedule.id && (
                    <div
                      className="absolute right-0 top-8 z-20 bg-background border border-border rounded shadow-lg py-1 min-w-[140px]"
                      onClick={(e) => e.stopPropagation()}
                    >
                      <button
                        onClick={() => handleEdit(schedule)}
                        className="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-left hover:bg-muted transition-colors"
                      >
                        <Pencil className="h-3.5 w-3.5" />
                        Edit
                      </button>
                      <button
                        onClick={() => handleTrigger(schedule)}
                        disabled={triggeringId === schedule.id}
                        className="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-left hover:bg-muted transition-colors"
                      >
                        {triggeringId === schedule.id ? (
                          <Loader2 className="h-3.5 w-3.5 animate-spin" />
                        ) : (
                          <Play className="h-3.5 w-3.5" />
                        )}
                        Trigger Now
                      </button>
                      <button
                        onClick={() => {
                          setOpenMenuId(null)
                          // Could navigate to runs view in the future
                          showToast('View runs coming soon')
                        }}
                        className="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-left hover:bg-muted transition-colors"
                      >
                        <History className="h-3.5 w-3.5" />
                        View Runs
                      </button>
                      <div className="border-t border-border my-1" />
                      <button
                        onClick={() => handleDelete(schedule)}
                        className="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-left hover:bg-muted text-red-500 transition-colors"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                        Delete
                      </button>
                    </div>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      )}

      {showForm && (
        <ScheduleFormModal
          projectId={projectId}
          schedule={editingSchedule}
          onClose={() => {
            setShowForm(false)
            setEditingSchedule(undefined)
          }}
          onSaved={handleSaved}
        />
      )}
    </div>
  )
}
