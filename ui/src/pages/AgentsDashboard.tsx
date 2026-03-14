import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  Brain,
  Activity,
  DollarSign,
  Users,
  CheckCircle2,
  XCircle,
  Loader2,
  Clock,
  Rocket,
  ArrowRight,
  Check,
  Sparkles,
  FolderOpen,
  Play,
  Calendar,
} from 'lucide-react'
import { fetchAgentsOverview, fetchOnboardingStatus, quickStart } from '@/api/client'
import { Button } from '@/components/ui/button'
import type { AgentsOverview, OnboardingStatus } from '@/types'

function formatCostUsd(usd: number): string {
  if (usd < 0.01 && usd > 0) return '< $0.01'
  return '$' + usd.toFixed(2)
}

function formatDuration(ms: number): string {
  if (ms < 1000) return `${Math.round(ms)}ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`
  return `${(ms / 60000).toFixed(1)}m`
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

const STATUS_CONFIG: Record<string, { icon: typeof CheckCircle2; color: string }> = {
  completed: { icon: CheckCircle2, color: 'text-emerald-400' },
  failed: { icon: XCircle, color: 'text-red-400' },
  running: { icon: Loader2, color: 'text-blue-400' },
  pending: { icon: Clock, color: 'text-muted-foreground' },
}

export function AgentsDashboard() {
  const navigate = useNavigate()
  const [overview, setOverview] = useState<AgentsOverview | null>(null)
  const [onboarding, setOnboarding] = useState<OnboardingStatus | null>(null)
  const [loading, setLoading] = useState(true)
  const [quickStarting, setQuickStarting] = useState(false)
  const [dismissed, setDismissed] = useState(() =>
    localStorage.getItem('eooo_onboarding_dismissed') === '1'
  )

  useEffect(() => {
    Promise.all([
      fetchAgentsOverview().then(setOverview).catch(() => setOverview(null)),
      fetchOnboardingStatus().then(setOnboarding).catch(() => setOnboarding(null)),
    ]).finally(() => setLoading(false))
  }, [])

  const handleQuickStart = async () => {
    setQuickStarting(true)
    try {
      const result = await quickStart()
      navigate(`/projects/${result.project_id}`)
    } catch {
      setQuickStarting(false)
    }
  }

  const dismissOnboarding = () => {
    setDismissed(true)
    localStorage.setItem('eooo_onboarding_dismissed', '1')
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  // Full-page onboarding for brand-new users (0 completed steps)
  if (onboarding && onboarding.completed_steps === 0) {
    return (
      <div className="p-4 md:p-6 flex items-center justify-center min-h-[70vh]">
        <div className="max-w-lg text-center">
          <div className="h-16 w-16 bg-primary/10 flex items-center justify-center mx-auto mb-6">
            <Rocket className="h-8 w-8 text-primary" />
          </div>
          <h1 className="text-2xl font-bold tracking-tight mb-3">Welcome to eooo.ai</h1>
          <p className="text-muted-foreground mb-2">
            Design AI agent teams, define their autonomy, and run them. Multi-model workflows,
            real tool calls, and cost tracking — all from one platform.
          </p>
          <p className="text-sm text-muted-foreground mb-8">
            Get started by creating your first project and agent, or let us set things up for you.
          </p>
          <div className="flex items-center justify-center gap-3">
            <Button onClick={handleQuickStart} disabled={quickStarting} className="gap-2">
              {quickStarting ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Sparkles className="h-4 w-4" />
              )}
              Quick Start
            </Button>
            <Link to="/projects/new">
              <Button variant="outline" className="gap-2">
                <FolderOpen className="h-4 w-4" />
                Create Project
              </Button>
            </Link>
          </div>
        </div>
      </div>
    )
  }

  const ONBOARDING_STEPS = [
    { key: 'has_project', label: 'Create Project', link: '/projects/new', icon: FolderOpen },
    { key: 'has_agent', label: 'Add Agent', link: '/agents/new', icon: Brain },
    { key: 'has_skill', label: 'Create Skill', link: '/library', icon: Sparkles },
    { key: 'has_run', label: 'Run Agent', link: '/agents', icon: Play },
    { key: 'has_schedule', label: 'Set Schedule', link: '/projects', icon: Calendar },
  ] as const

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-xl font-semibold tracking-tight">Your Agent Team</h1>
        <p className="text-sm text-muted-foreground">Overview of your agents and recent activity</p>
      </div>

      {/* Onboarding Banner */}
      {onboarding && onboarding.completed_steps < onboarding.total_steps && !dismissed && (
        <div className="rounded-lg border border-primary/30 bg-primary/5 p-4">
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <Rocket className="h-4 w-4 text-primary" />
              <span className="text-sm font-medium">Get Started</span>
              <span className="text-xs text-muted-foreground">
                {onboarding.completed_steps}/{onboarding.total_steps} complete
              </span>
            </div>
            <div className="flex items-center gap-2">
              <Button size="sm" variant="ghost" onClick={handleQuickStart} disabled={quickStarting}>
                {quickStarting ? <Loader2 className="h-3 w-3 animate-spin mr-1" /> : <Sparkles className="h-3 w-3 mr-1" />}
                Quick Start
              </Button>
              <button onClick={dismissOnboarding} className="text-xs text-muted-foreground hover:text-foreground">
                Dismiss
              </button>
            </div>
          </div>
          {/* Progress bar */}
          <div className="h-1.5 bg-muted rounded-full overflow-hidden mb-3">
            <div
              className="h-full bg-primary rounded-full transition-all duration-300"
              style={{ width: `${(onboarding.completed_steps / onboarding.total_steps) * 100}%` }}
            />
          </div>
          <div className="flex flex-wrap gap-3">
            {ONBOARDING_STEPS.map(step => {
              const done = onboarding[step.key as keyof OnboardingStatus] as boolean
              return (
                <Link
                  key={step.key}
                  to={done ? '#' : step.link}
                  className={`flex items-center gap-1.5 text-xs px-2 py-1 rounded transition-colors ${
                    done
                      ? 'text-emerald-500 bg-emerald-500/10'
                      : 'text-muted-foreground hover:text-foreground hover:bg-muted'
                  }`}
                >
                  {done ? <Check className="h-3 w-3" /> : <step.icon className="h-3 w-3" />}
                  {step.label}
                </Link>
              )
            })}
          </div>
        </div>
      )}

      {/* Quick Stats */}
      {overview && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard
            icon={<Users className="h-4 w-4 text-blue-400" />}
            label="Total Agents"
            value={overview.total_agents.toString()}
          />
          <StatCard
            icon={<Activity className="h-4 w-4 text-emerald-400" />}
            label="Active Today"
            value={overview.active_agents.toString()}
          />
          <StatCard
            icon={<Play className="h-4 w-4 text-purple-400" />}
            label="Runs Today"
            value={overview.total_runs_today.toString()}
          />
          <StatCard
            icon={<DollarSign className="h-4 w-4 text-amber-400" />}
            label="Cost Today"
            value={formatCostUsd(overview.total_cost_today)}
          />
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Activity */}
        <div className="rounded-lg border border-border bg-card p-4">
          <h3 className="text-sm font-medium text-foreground mb-3">Recent Activity</h3>
          {overview && overview.recent_runs.length > 0 ? (
            <div className="space-y-1">
              {overview.recent_runs.slice(0, 5).map(run => {
                const cfg = STATUS_CONFIG[run.status] ?? STATUS_CONFIG.pending
                const Icon = cfg.icon
                return (
                  <div
                    key={run.id}
                    className="flex items-center gap-3 px-2 py-2 hover:bg-muted/30 rounded transition-colors text-sm"
                  >
                    <Icon className={`h-4 w-4 ${cfg.color} flex-shrink-0 ${run.status === 'running' ? 'animate-spin' : ''}`} />
                    <span className="flex-1 truncate font-medium text-foreground">{run.agent_name}</span>
                    {run.duration_ms != null && (
                      <span className="text-xs text-muted-foreground tabular-nums">{formatDuration(run.duration_ms)}</span>
                    )}
                    <span className="text-xs text-muted-foreground tabular-nums">{formatCostUsd(run.cost_usd)}</span>
                    <span className="text-xs text-muted-foreground">{timeAgo(run.created_at)}</span>
                  </div>
                )
              })}
            </div>
          ) : (
            <p className="text-sm text-muted-foreground text-center py-6">No recent runs</p>
          )}
        </div>

        {/* Top Agents */}
        <div className="rounded-lg border border-border bg-card p-4">
          <h3 className="text-sm font-medium text-foreground mb-3">Top Agents</h3>
          {overview && overview.top_agents.length > 0 ? (
            <div className="grid gap-3">
              {overview.top_agents.slice(0, 3).map(agent => (
                <Link
                  key={agent.id}
                  to={`/agents/${agent.id}`}
                  className="flex items-center gap-3 p-3 border border-border rounded-lg hover:bg-muted/30 transition-colors group"
                >
                  <div className="h-10 w-10 bg-primary/10 flex items-center justify-center rounded-lg">
                    <Brain className="h-5 w-5 text-primary" />
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-foreground truncate">{agent.name}</p>
                    <p className="text-xs text-muted-foreground">{agent.run_count} runs</p>
                  </div>
                  <ArrowRight className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
                </Link>
              ))}
            </div>
          ) : (
            <div className="text-center py-6">
              <p className="text-sm text-muted-foreground mb-2">No agents yet</p>
              <Link to="/agents/new">
                <Button size="sm" variant="outline" className="gap-1.5">
                  <Brain className="h-3.5 w-3.5" />
                  Create Agent
                </Button>
              </Link>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

function StatCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center gap-2 mb-1">
        {icon}
        <span className="text-xs text-muted-foreground">{label}</span>
      </div>
      <div className="text-lg font-semibold text-foreground">{value}</div>
    </div>
  )
}
