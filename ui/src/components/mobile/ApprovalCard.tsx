import { ShieldCheck, Clock, Bot, FolderOpen } from 'lucide-react'

interface ApprovalData {
  id: number
  uuid: string
  agent: { id: number; name: string } | null
  project: { id: number; name: string } | null
  type: string
  title: string
  description: string | null
  status: string
  requested_at: string | null
  expires_at: string | null
}

interface ApprovalCardProps {
  approval: ApprovalData
  onApprove: (id: number) => void
  onReject: (id: number) => void
  loading?: boolean
}

function timeAgo(dateStr: string | null): string {
  if (!dateStr) return ''
  const diff = Date.now() - new Date(dateStr).getTime()
  const minutes = Math.floor(diff / 60000)
  if (minutes < 1) return 'just now'
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

function timeUntil(dateStr: string | null): string | null {
  if (!dateStr) return null
  const diff = new Date(dateStr).getTime() - Date.now()
  if (diff <= 0) return 'expired'
  const minutes = Math.floor(diff / 60000)
  if (minutes < 60) return `${minutes}m left`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h left`
  const days = Math.floor(hours / 24)
  return `${days}d left`
}

export function ApprovalCard({
  approval,
  onApprove,
  onReject,
  loading = false,
}: ApprovalCardProps) {
  const expiresIn = timeUntil(approval.expires_at)

  return (
    <div className="rounded-xl border border-border bg-card p-4 space-y-3">
      {/* Header */}
      <div className="flex items-start justify-between gap-2">
        <div className="flex items-center gap-2 min-w-0">
          <ShieldCheck className="h-4 w-4 text-yellow-500 shrink-0" />
          <h3 className="text-sm font-semibold truncate">
            {approval.title}
          </h3>
        </div>
        <span className="inline-flex items-center gap-1 text-[10px] font-medium text-muted-foreground bg-muted px-1.5 py-0.5 rounded shrink-0">
          {approval.type}
        </span>
      </div>

      {/* Description */}
      {approval.description && (
        <p className="text-xs text-muted-foreground leading-relaxed line-clamp-2">
          {approval.description}
        </p>
      )}

      {/* Meta */}
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
        {approval.agent && (
          <span className="inline-flex items-center gap-1">
            <Bot className="h-3 w-3" />
            {approval.agent.name}
          </span>
        )}
        {approval.project && (
          <span className="inline-flex items-center gap-1">
            <FolderOpen className="h-3 w-3" />
            {approval.project.name}
          </span>
        )}
        {approval.requested_at && (
          <span className="inline-flex items-center gap-1">
            <Clock className="h-3 w-3" />
            {timeAgo(approval.requested_at)}
          </span>
        )}
        {expiresIn && (
          <span
            className={`inline-flex items-center gap-1 ${
              expiresIn === 'expired' ? 'text-red-500' : 'text-yellow-600'
            }`}
          >
            {expiresIn}
          </span>
        )}
      </div>

      {/* Actions */}
      <div className="flex items-center gap-2 pt-1">
        <button
          onClick={() => onApprove(approval.id)}
          disabled={loading}
          className="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-green-700 active:bg-green-800 disabled:opacity-50"
        >
          Approve
        </button>
        <button
          onClick={() => onReject(approval.id)}
          disabled={loading}
          className="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-red-700 active:bg-red-800 disabled:opacity-50"
        >
          Reject
        </button>
      </div>
    </div>
  )
}
