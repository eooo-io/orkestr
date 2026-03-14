import { useEffect, useState } from 'react'
import {
  Activity,
  DollarSign,
  Clock,
  Brain,
  TrendingUp,
  Loader2,
  ArrowUpDown,
  Cpu,
} from 'lucide-react'
import {
  fetchPerformanceOverview,
  fetchAgentPerformance,
  fetchPerformanceTrends,
  fetchModelUsage,
  fetchCostBreakdown,
} from '@/api/client'
import type {
  PerformanceOverview,
  AgentPerformance,
  PerformanceTrend,
  ModelUsage,
} from '@/types'

type Period = '7d' | '30d' | '90d'
type CostGroupBy = 'agent' | 'model' | 'project'
type SortField = 'run_count' | 'success_rate' | 'avg_cost_usd' | 'avg_duration_ms' | 'total_cost_usd'

function formatCostUsd(usd: number): string {
  if (usd < 0.01 && usd > 0) return '< $0.01'
  return '$' + usd.toFixed(2)
}

function formatDuration(ms: number): string {
  if (ms < 1000) return `${Math.round(ms)}ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`
  return `${(ms / 60000).toFixed(1)}m`
}

function formatDate(date: string): string {
  const d = new Date(date)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function successRateColor(rate: number): string {
  if (rate >= 90) return 'text-emerald-400'
  if (rate >= 70) return 'text-amber-400'
  return 'text-red-400'
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `${mins}m ago`
  const hrs = Math.floor(mins / 60)
  if (hrs < 24) return `${hrs}h ago`
  const days = Math.floor(hrs / 24)
  return `${days}d ago`
}

export function PerformanceDashboard() {
  const [period, setPeriod] = useState<Period>('30d')
  const [overview, setOverview] = useState<PerformanceOverview | null>(null)
  const [agents, setAgents] = useState<AgentPerformance[]>([])
  const [trends, setTrends] = useState<PerformanceTrend[]>([])
  const [models, setModels] = useState<ModelUsage[]>([])
  const [costBreakdown, setCostBreakdown] = useState<Array<{ name: string; total_cost_usd: number; run_count: number }>>([])
  const [costGroupBy, setCostGroupBy] = useState<CostGroupBy>('agent')
  const [sortField, setSortField] = useState<SortField>('run_count')
  const [sortAsc, setSortAsc] = useState(false)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    Promise.all([
      fetchPerformanceOverview({ period }).then(setOverview).catch(() => setOverview(null)),
      fetchAgentPerformance({ period }).then(setAgents).catch(() => setAgents([])),
      fetchPerformanceTrends({ period }).then(setTrends).catch(() => setTrends([])),
      fetchModelUsage({ period }).then(setModels).catch(() => setModels([])),
      fetchCostBreakdown({ period, group_by: costGroupBy }).then(setCostBreakdown).catch(() => setCostBreakdown([])),
    ]).finally(() => setLoading(false))
  }, [period])

  useEffect(() => {
    fetchCostBreakdown({ period, group_by: costGroupBy }).then(setCostBreakdown).catch(() => setCostBreakdown([]))
  }, [costGroupBy, period])

  const handleSort = (field: SortField) => {
    if (sortField === field) {
      setSortAsc(!sortAsc)
    } else {
      setSortField(field)
      setSortAsc(false)
    }
  }

  const sortedAgents = [...agents].sort((a, b) => {
    const aVal = a[sortField]
    const bVal = b[sortField]
    return sortAsc ? (aVal as number) - (bVal as number) : (bVal as number) - (aVal as number)
  })

  const maxDailyRuns = Math.max(...trends.map(t => t.run_count), 1)
  const maxCost = Math.max(...costBreakdown.map(c => c.total_cost_usd), 0.01)

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold tracking-tight flex items-center gap-2">
            <TrendingUp className="h-5 w-5 text-primary" />
            Performance
          </h1>
          <p className="text-sm text-muted-foreground">Agent analytics and cost tracking</p>
        </div>
        <div className="flex items-center gap-1 bg-muted p-1">
          {(['7d', '30d', '90d'] as Period[]).map(p => (
            <button
              key={p}
              onClick={() => setPeriod(p)}
              className={`px-3 py-1.5 text-xs font-medium transition-colors ${
                period === p
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              {p}
            </button>
          ))}
        </div>
      </div>

      {/* KPI Cards */}
      {overview && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <KpiCard
            icon={<Activity className="h-4 w-4 text-blue-400" />}
            label="Total Runs"
            value={overview.total_runs.toLocaleString()}
            subtext={`${overview.success_rate.toFixed(1)}% success rate`}
          />
          <KpiCard
            icon={<DollarSign className="h-4 w-4 text-amber-400" />}
            label="Total Cost"
            value={formatCostUsd(overview.total_cost_usd)}
            subtext={`${formatCostUsd(overview.avg_cost_per_run_usd)} avg/run`}
          />
          <KpiCard
            icon={<Clock className="h-4 w-4 text-purple-400" />}
            label="Avg Duration"
            value={formatDuration(overview.avg_duration_ms)}
          />
          <KpiCard
            icon={<Brain className="h-4 w-4 text-emerald-400" />}
            label="Active Agents"
            value={overview.active_agents.toString()}
          />
        </div>
      )}

      {/* Trends Chart */}
      {trends.length > 0 && (
        <div className="rounded-lg border border-border bg-card p-4">
          <h3 className="text-sm font-medium text-foreground mb-4">Daily Run Trends</h3>
          <div className="flex items-end gap-1" style={{ height: 160 }}>
            {trends.map(t => {
              const successPct = (t.success_count / maxDailyRuns) * 100
              const failurePct = (t.failure_count / maxDailyRuns) * 100
              return (
                <div key={t.date} className="flex-1 flex flex-col items-center gap-0.5" title={`${t.date}: ${t.run_count} runs`}>
                  <div className="w-full flex flex-col justify-end" style={{ height: 140 }}>
                    <div
                      className="w-full bg-red-500/60 rounded-t-sm"
                      style={{ height: `${failurePct}%`, minHeight: t.failure_count > 0 ? 2 : 0 }}
                    />
                    <div
                      className="w-full bg-emerald-500/60"
                      style={{ height: `${successPct}%`, minHeight: t.success_count > 0 ? 2 : 0 }}
                    />
                  </div>
                  <span className="text-[9px] text-muted-foreground truncate w-full text-center">
                    {formatDate(t.date)}
                  </span>
                </div>
              )
            })}
          </div>
          <div className="flex items-center gap-4 mt-3 text-xs text-muted-foreground">
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 bg-emerald-500/60 rounded-sm" /> Success
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 bg-red-500/60 rounded-sm" /> Failure
            </span>
          </div>
        </div>
      )}

      {/* Agent Leaderboard */}
      {sortedAgents.length > 0 && (
        <div className="rounded-lg border border-border bg-card p-4">
          <h3 className="text-sm font-medium text-foreground mb-3">Agent Leaderboard</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-xs text-muted-foreground">
                  <th className="text-left py-2 px-2 font-medium">Agent</th>
                  <SortHeader label="Runs" field="run_count" current={sortField} asc={sortAsc} onSort={handleSort} />
                  <SortHeader label="Success" field="success_rate" current={sortField} asc={sortAsc} onSort={handleSort} />
                  <SortHeader label="Avg Cost" field="avg_cost_usd" current={sortField} asc={sortAsc} onSort={handleSort} />
                  <SortHeader label="Avg Duration" field="avg_duration_ms" current={sortField} asc={sortAsc} onSort={handleSort} />
                  <SortHeader label="Total Cost" field="total_cost_usd" current={sortField} asc={sortAsc} onSort={handleSort} />
                  <th className="text-left py-2 px-2 font-medium">Last Run</th>
                </tr>
              </thead>
              <tbody>
                {sortedAgents.map(a => (
                  <tr key={a.agent_id} className="border-b border-border/50 hover:bg-muted/30 transition-colors">
                    <td className="py-2 px-2">
                      <span className="flex items-center gap-2">
                        <Brain className="h-4 w-4 text-muted-foreground" />
                        <span className="font-medium text-foreground">{a.agent_name}</span>
                      </span>
                    </td>
                    <td className="py-2 px-2 tabular-nums">{a.run_count}</td>
                    <td className={`py-2 px-2 tabular-nums font-medium ${successRateColor(a.success_rate)}`}>
                      {a.success_rate.toFixed(1)}%
                    </td>
                    <td className="py-2 px-2 tabular-nums">{formatCostUsd(a.avg_cost_usd)}</td>
                    <td className="py-2 px-2 tabular-nums">{formatDuration(a.avg_duration_ms)}</td>
                    <td className="py-2 px-2 tabular-nums">{formatCostUsd(a.total_cost_usd)}</td>
                    <td className="py-2 px-2 text-muted-foreground">
                      {a.last_run_at ? timeAgo(a.last_run_at) : '--'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Model Usage */}
      {models.length > 0 && (
        <div className="rounded-lg border border-border bg-card p-4">
          <h3 className="text-sm font-medium text-foreground mb-3 flex items-center gap-2">
            <Cpu className="h-4 w-4 text-muted-foreground" />
            Model Usage
          </h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-xs text-muted-foreground">
                  <th className="text-left py-2 px-2 font-medium">Model</th>
                  <th className="text-left py-2 px-2 font-medium">Runs</th>
                  <th className="text-left py-2 px-2 font-medium">Tokens</th>
                  <th className="text-left py-2 px-2 font-medium">Cost</th>
                  <th className="text-left py-2 px-2 font-medium">Avg Latency</th>
                </tr>
              </thead>
              <tbody>
                {models.map(m => (
                  <tr key={m.model_name} className="border-b border-border/50 hover:bg-muted/30 transition-colors">
                    <td className="py-2 px-2 font-mono text-xs">{m.model_name}</td>
                    <td className="py-2 px-2 tabular-nums">{m.run_count}</td>
                    <td className="py-2 px-2 tabular-nums">{m.total_tokens.toLocaleString()}</td>
                    <td className="py-2 px-2 tabular-nums">{formatCostUsd(m.total_cost_usd)}</td>
                    <td className="py-2 px-2 tabular-nums">{formatDuration(m.avg_latency_ms)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Cost Breakdown */}
      <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-sm font-medium text-foreground">Cost Breakdown</h3>
          <div className="flex items-center gap-1 bg-muted p-0.5">
            {(['agent', 'model', 'project'] as CostGroupBy[]).map(g => (
              <button
                key={g}
                onClick={() => setCostGroupBy(g)}
                className={`px-2.5 py-1 text-xs transition-colors ${
                  costGroupBy === g
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                {g.charAt(0).toUpperCase() + g.slice(1)}
              </button>
            ))}
          </div>
        </div>
        {costBreakdown.length > 0 ? (
          <div className="space-y-2">
            {costBreakdown.map(item => (
              <div key={item.name} className="flex items-center gap-3">
                <span className="text-xs text-muted-foreground w-40 truncate">{item.name}</span>
                <div className="flex-1 h-2 bg-muted rounded-full overflow-hidden">
                  <div
                    className="h-full bg-primary/60 rounded-full"
                    style={{ width: `${Math.max((item.total_cost_usd / maxCost) * 100, 2)}%` }}
                  />
                </div>
                <span className="text-xs text-muted-foreground w-16 text-right tabular-nums">{formatCostUsd(item.total_cost_usd)}</span>
                <span className="text-xs text-muted-foreground/60 w-16 text-right tabular-nums">{item.run_count} runs</span>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-sm text-muted-foreground text-center py-6">No cost data for this period</p>
        )}
      </div>

      {/* Empty state */}
      {!overview && agents.length === 0 && (
        <div className="text-center py-16 text-muted-foreground">
          <TrendingUp className="h-8 w-8 mx-auto mb-3 opacity-40" />
          <p className="text-sm">No performance data yet. Run some agents to see analytics here.</p>
        </div>
      )}
    </div>
  )
}

function KpiCard({ icon, label, value, subtext }: { icon: React.ReactNode; label: string; value: string; subtext?: string }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center gap-2 mb-1">
        {icon}
        <span className="text-xs text-muted-foreground">{label}</span>
      </div>
      <div className="text-lg font-semibold text-foreground">{value}</div>
      {subtext && <div className="text-xs text-muted-foreground mt-0.5">{subtext}</div>}
    </div>
  )
}

function SortHeader({
  label,
  field,
  current,
  asc,
  onSort,
}: {
  label: string
  field: SortField
  current: SortField
  asc: boolean
  onSort: (f: SortField) => void
}) {
  return (
    <th className="text-left py-2 px-2 font-medium">
      <button
        onClick={() => onSort(field)}
        className="flex items-center gap-1 hover:text-foreground transition-colors"
      >
        {label}
        <ArrowUpDown className={`h-3 w-3 ${current === field ? 'text-primary' : 'opacity-30'}`} />
      </button>
    </th>
  )
}
