import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Brain,
  Loader2,
  Clock,
  DollarSign,
  CheckCircle2,
  Calendar,
} from 'lucide-react'
import { fetchAgentTeam } from '@/api/client'
import type { ProjectAgent } from '@/types'

function formatCostUsd(usd: number): string {
  if (usd < 0.01 && usd > 0) return '< $0.01'
  return '$' + usd.toFixed(2)
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  return `${days}d ago`
}

const AUTONOMY_COLORS: Record<string, string> = {
  supervised: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  semi_autonomous: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
  autonomous: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
}

const AUTONOMY_LABELS: Record<string, string> = {
  supervised: 'Supervised',
  semi_autonomous: 'Semi-Auto',
  autonomous: 'Autonomous',
}

interface Props {
  projectId: number
}

// Extended type with optional team stats from the API
interface AgentTeamMember extends ProjectAgent {
  run_count?: number
  success_rate?: number
  avg_cost_usd?: number
  last_run_at?: string | null
  next_run_at?: string | null
}

export function AgentTeamTab({ projectId }: Props) {
  const [agents, setAgents] = useState<AgentTeamMember[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchAgentTeam(projectId)
      .then((data) => setAgents(data as AgentTeamMember[]))
      .catch(() => setAgents([]))
      .finally(() => setLoading(false))
  }, [projectId])

  if (loading) {
    return (
      <div className="flex items-center justify-center h-32">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  if (agents.length === 0) {
    return (
      <div className="text-center py-12 text-muted-foreground">
        <Brain className="h-8 w-8 mx-auto mb-3 opacity-40" />
        <p className="text-sm">No agents assigned to this project yet.</p>
        <p className="text-xs mt-1">
          Go to the Agents tab to enable agents for this project.
        </p>
      </div>
    )
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {agents.map(agent => {
        const autonomy = agent.autonomy_level || 'supervised'
        const autonomyClass = AUTONOMY_COLORS[autonomy] || AUTONOMY_COLORS.supervised
        const autonomyLabel = AUTONOMY_LABELS[autonomy] || autonomy

        return (
          <Link
            key={agent.id}
            to={`/agents/${agent.id}`}
            className="block border border-border bg-card rounded-lg p-4 hover:bg-muted/30 transition-colors group"
          >
            {/* Header */}
            <div className="flex items-start gap-3 mb-3">
              <div className="h-10 w-10 bg-primary/10 flex items-center justify-center rounded-lg flex-shrink-0">
                <Brain className="h-5 w-5 text-primary" />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <h4 className="text-sm font-medium text-foreground truncate">{agent.name}</h4>
                  {/* Status indicator */}
                  <span
                    className={`h-2 w-2 rounded-full flex-shrink-0 ${
                      agent.is_enabled ? 'bg-emerald-400' : 'bg-muted-foreground/30'
                    }`}
                    title={agent.is_enabled ? 'Active' : 'Idle'}
                  />
                </div>
                <p className="text-xs text-muted-foreground truncate">{agent.role}</p>
              </div>
            </div>

            {/* Badges */}
            <div className="flex flex-wrap gap-1.5 mb-3">
              {agent.model && (
                <span className="text-[10px] px-1.5 py-0.5 bg-muted text-muted-foreground font-mono rounded">
                  {agent.model}
                </span>
              )}
              <span className={`text-[10px] px-1.5 py-0.5 border rounded ${autonomyClass}`}>
                {autonomyLabel}
              </span>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 gap-2 text-xs">
              <div className="flex items-center gap-1.5 text-muted-foreground">
                <CheckCircle2 className="h-3 w-3" />
                <span>{agent.run_count ?? 0} runs</span>
                {agent.success_rate != null && (
                  <span className="text-muted-foreground/60">({agent.success_rate.toFixed(0)}%)</span>
                )}
              </div>
              <div className="flex items-center gap-1.5 text-muted-foreground">
                <DollarSign className="h-3 w-3" />
                <span>{formatCostUsd(agent.avg_cost_usd ?? 0)} avg</span>
              </div>
              <div className="flex items-center gap-1.5 text-muted-foreground">
                <Clock className="h-3 w-3" />
                <span>{agent.last_run_at ? timeAgo(agent.last_run_at) : 'Never run'}</span>
              </div>
              {agent.next_run_at && (
                <div className="flex items-center gap-1.5 text-muted-foreground">
                  <Calendar className="h-3 w-3" />
                  <span>Next: {timeAgo(agent.next_run_at)}</span>
                </div>
              )}
            </div>
          </Link>
        )
      })}
    </div>
  )
}
