import { useEffect, useState } from 'react'
import {
  FileText,
  ChevronLeft,
  ChevronRight,
  Loader2,
  Filter,
} from 'lucide-react'
import { fetchAuditLogs } from '@/api/client'
import type { AuditLogEntry } from '@/types'

const EVENT_TYPES = [
  'agent.created',
  'agent.updated',
  'agent.deleted',
  'agent.executed',
  'agent.budget_exceeded',
  'tool.approved',
  'tool.rejected',
  'tool.blocked',
  'schedule.triggered',
  'member.invited',
  'member.removed',
  'settings.changed',
]

function eventBadgeColor(event: string): string {
  if (event.includes('created') || event.includes('executed')) return 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30'
  if (event.includes('approved') || event.includes('triggered')) return 'bg-amber-500/20 text-amber-400 border-amber-500/30'
  if (event.includes('rejected') || event.includes('blocked') || event.includes('exceeded') || event.includes('deleted') || event.includes('removed')) return 'bg-red-500/20 text-red-400 border-red-500/30'
  if (event.includes('settings') || event.includes('updated') || event.includes('invited')) return 'bg-blue-500/20 text-blue-400 border-blue-500/30'
  return 'bg-zinc-500/20 text-zinc-400 border-zinc-500/30'
}

function timeAgo(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const seconds = Math.floor((now.getTime() - date.getTime()) / 1000)

  if (seconds < 60) return 'just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`
  return date.toLocaleDateString()
}

export function AuditLog() {
  const [entries, setEntries] = useState<AuditLogEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [eventFilter, setEventFilter] = useState<string>('')

  const loadLogs = (p: number, event?: string) => {
    setLoading(true)
    fetchAuditLogs({ page: p, event: event || undefined })
      .then((res) => {
        setEntries(res.data)
        setPage(res.current_page)
        setLastPage(res.last_page)
        setTotal(res.total)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadLogs(1, eventFilter)
  }, [eventFilter])

  const handlePrev = () => {
    if (page > 1) loadLogs(page - 1, eventFilter)
  }

  const handleNext = () => {
    if (page < lastPage) loadLogs(page + 1, eventFilter)
  }

  return (
    <div className="max-w-6xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold flex items-center gap-2">
            <FileText className="h-5 w-5 text-primary" />
            Audit Log
          </h1>
          <p className="text-sm text-muted-foreground">
            Track all actions across agents, tools, and settings
          </p>
        </div>
        <span className="text-xs text-muted-foreground">
          {total} {total === 1 ? 'entry' : 'entries'}
        </span>
      </div>

      {/* Filter bar */}
      <div className="flex items-center gap-3">
        <Filter className="h-4 w-4 text-muted-foreground" />
        <select
          value={eventFilter}
          onChange={(e) => setEventFilter(e.target.value)}
          className="px-3 py-1.5 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
        >
          <option value="">All events</option>
          {EVENT_TYPES.map((ev) => (
            <option key={ev} value={ev}>
              {ev}
            </option>
          ))}
        </select>
      </div>

      {/* Table */}
      {loading ? (
        <div className="flex items-center justify-center h-40">
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        </div>
      ) : entries.length === 0 ? (
        <div className="text-center py-16 text-muted-foreground text-sm">
          No audit log entries found.
        </div>
      ) : (
        <div className="border border-border overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-muted/30 text-xs text-muted-foreground uppercase tracking-wide">
                <th className="text-left px-4 py-2.5 font-medium">Time</th>
                <th className="text-left px-4 py-2.5 font-medium">Event</th>
                <th className="text-left px-4 py-2.5 font-medium">Description</th>
                <th className="text-left px-4 py-2.5 font-medium">User</th>
                <th className="text-left px-4 py-2.5 font-medium">Agent</th>
                <th className="text-left px-4 py-2.5 font-medium">Project</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {entries.map((entry) => (
                <tr key={entry.id} className="hover:bg-muted/20 transition-colors">
                  <td className="px-4 py-2.5 text-xs text-muted-foreground whitespace-nowrap" title={new Date(entry.created_at).toLocaleString()}>
                    {timeAgo(entry.created_at)}
                  </td>
                  <td className="px-4 py-2.5">
                    <span className={`inline-block px-2 py-0.5 text-[11px] font-medium border ${eventBadgeColor(entry.event)}`}>
                      {entry.event}
                    </span>
                  </td>
                  <td className="px-4 py-2.5 text-sm max-w-xs truncate">
                    {entry.description}
                  </td>
                  <td className="px-4 py-2.5 text-xs text-muted-foreground">
                    {entry.user?.name ?? '-'}
                  </td>
                  <td className="px-4 py-2.5 text-xs text-muted-foreground">
                    {entry.agent?.name ?? '-'}
                  </td>
                  <td className="px-4 py-2.5 text-xs text-muted-foreground">
                    {entry.project?.name ?? '-'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-between">
          <span className="text-xs text-muted-foreground">
            Page {page} of {lastPage}
          </span>
          <div className="flex items-center gap-2">
            <button
              onClick={handlePrev}
              disabled={page <= 1}
              className="flex items-center gap-1 px-3 py-1.5 text-xs border border-border hover:bg-muted disabled:opacity-30 transition-colors"
            >
              <ChevronLeft className="h-3 w-3" />
              Previous
            </button>
            <button
              onClick={handleNext}
              disabled={page >= lastPage}
              className="flex items-center gap-1 px-3 py-1.5 text-xs border border-border hover:bg-muted disabled:opacity-30 transition-colors"
            >
              Next
              <ChevronRight className="h-3 w-3" />
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
