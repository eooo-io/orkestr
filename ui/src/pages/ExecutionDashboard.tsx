import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  Activity,
  Clock,
  Hash,
  DollarSign,
  CheckCircle2,
  XCircle,
  Loader2,
  Eye,
  Brain,
  Zap,
  MessageSquare,
  ChevronDown,
  ChevronRight,
  BarChart3,
  TrendingUp,
} from 'lucide-react'
import {
  fetchExecutionRuns,
  fetchExecutionRun,
  fetchExecutionStats,
} from '@/api/client'
import type { ExecutionRun, ExecutionStep, ExecutionStats } from '@/types'

const PHASE_ICONS: Record<string, typeof Eye> = {
  perceive: Eye,
  reason: Brain,
  act: Zap,
  observe: MessageSquare,
}

const PHASE_COLORS: Record<string, string> = {
  perceive: 'text-blue-400',
  reason: 'text-purple-400',
  act: 'text-amber-400',
  observe: 'text-green-400',
}

function formatCost(microcents: number): string {
  const dollars = microcents / 1000000
  if (dollars < 0.01) return '< $0.01'
  return '$' + dollars.toFixed(4)
}

function formatDuration(ms: number): string {
  if (ms < 1000) return `${ms}ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`
  return `${(ms / 60000).toFixed(1)}m`
}

export function ExecutionDashboard() {
  const { id: projectId } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const pid = Number(projectId)

  const [stats, setStats] = useState<ExecutionStats | null>(null)
  const [runs, setRuns] = useState<ExecutionRun[]>([])
  const [selectedRun, setSelectedRun] = useState<ExecutionRun | null>(null)
  const [expandedSteps, setExpandedSteps] = useState<Set<number>>(new Set())
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!pid) return
    Promise.all([
      fetchExecutionStats(pid).then(setStats),
      fetchExecutionRuns(pid).then(setRuns),
    ]).finally(() => setLoading(false))
  }, [pid])

  const filteredRuns = statusFilter
    ? runs.filter((r) => r.status === statusFilter)
    : runs

  const handleViewRun = async (runId: number) => {
    const run = await fetchExecutionRun(runId)
    setSelectedRun(run)
    setExpandedSteps(new Set())
  }

  const toggleStep = (stepId: number) => {
    setExpandedSteps((prev) => {
      const next = new Set(prev)
      next.has(stepId) ? next.delete(stepId) : next.add(stepId)
      return next
    })
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-6 w-6 animate-spin text-zinc-500" />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold text-zinc-100 flex items-center gap-2">
            <BarChart3 className="h-5 w-5 text-emerald-400" />
            Execution Dashboard
          </h2>
          <p className="text-sm text-zinc-500">Run history, cost analytics, and execution traces</p>
        </div>
        <button
          onClick={() => navigate(`/projects/${pid}/execute`)}
          className="text-sm text-emerald-400 hover:text-emerald-300 transition-colors"
        >
          Open Playground
        </button>
      </div>

      {/* Stats cards */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
          <StatCard
            icon={<Activity className="h-4 w-4 text-blue-400" />}
            label="Total Runs"
            value={stats.total_runs.toString()}
          />
          <StatCard
            icon={<Hash className="h-4 w-4 text-purple-400" />}
            label="Total Tokens"
            value={stats.total_tokens.toLocaleString()}
          />
          <StatCard
            icon={<DollarSign className="h-4 w-4 text-amber-400" />}
            label="Total Cost"
            value={stats.total_cost_formatted}
          />
          <StatCard
            icon={<TrendingUp className="h-4 w-4 text-emerald-400" />}
            label="Success Rate"
            value={`${stats.success_rate}%`}
            subtext={`${stats.completed_count} / ${stats.total_runs}`}
          />
          <StatCard
            icon={<Clock className="h-4 w-4 text-zinc-400" />}
            label="Total Duration"
            value={formatDuration(stats.total_duration_ms)}
          />
        </div>
      )}

      {/* Model breakdown */}
      {stats && Object.keys(stats.by_model).length > 0 && (
        <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
          <h3 className="text-sm font-medium text-zinc-300 mb-3">Cost by Model</h3>
          <div className="space-y-2">
            {Object.entries(stats.by_model).map(([model, data]) => {
              const pct = stats.total_cost_microcents > 0
                ? (data.cost / stats.total_cost_microcents) * 100
                : 0
              return (
                <div key={model} className="flex items-center gap-3">
                  <span className="text-xs text-zinc-400 w-48 truncate font-mono">{model}</span>
                  <div className="flex-1 h-2 bg-zinc-800 rounded-full overflow-hidden">
                    <div
                      className="h-full bg-emerald-500/60 rounded-full"
                      style={{ width: `${Math.max(pct, 2)}%` }}
                    />
                  </div>
                  <span className="text-xs text-zinc-500 w-20 text-right">{formatCost(data.cost)}</span>
                  <span className="text-xs text-zinc-600 w-16 text-right">{data.runs} runs</span>
                </div>
              )
            })}
          </div>
        </div>
      )}

      {/* Runs table + trace viewer */}
      <div className="flex gap-6">
        {/* Runs list */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-sm font-medium text-zinc-300">Execution Runs</h3>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="text-xs rounded border border-zinc-700 bg-zinc-800 px-2 py-1 text-zinc-300"
            >
              <option value="">All statuses</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
              <option value="running">Running</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>

          <div className="space-y-1">
            {filteredRuns.map((run) => (
              <button
                key={run.id}
                onClick={() => handleViewRun(run.id)}
                className={`w-full text-left p-3 rounded-md text-sm transition-colors ${
                  selectedRun?.id === run.id
                    ? 'bg-zinc-800 border border-zinc-600'
                    : 'bg-zinc-900/30 border border-transparent hover:border-zinc-700/50'
                }`}
              >
                <div className="flex items-center gap-3">
                  <RunStatusIcon status={run.status} />
                  <span className="text-zinc-200 truncate flex-1">
                    {run.agent?.name ?? `Agent #${run.agent_id}`}
                  </span>
                  <span className="text-xs text-zinc-500">{run.total_tokens.toLocaleString()} tok</span>
                  <span className="text-xs text-zinc-600">{formatCost(run.total_cost_microcents)}</span>
                  <span className="text-xs text-zinc-600">{formatDuration(run.total_duration_ms)}</span>
                  <span className="text-xs text-zinc-600">
                    {new Date(run.created_at).toLocaleDateString()}{' '}
                    {new Date(run.created_at).toLocaleTimeString()}
                  </span>
                </div>
                {run.error && (
                  <p className="mt-1 text-xs text-red-400 truncate pl-7">{run.error}</p>
                )}
              </button>
            ))}
            {filteredRuns.length === 0 && (
              <p className="text-sm text-zinc-600 text-center py-8">No execution runs found</p>
            )}
          </div>
        </div>

        {/* Trace viewer sidebar */}
        {selectedRun && (
          <div className="w-96 flex-shrink-0 border-l border-zinc-800 pl-4 overflow-auto max-h-[70vh]">
            <div className="mb-3">
              <div className="flex items-center gap-2 mb-1">
                <RunStatusIcon status={selectedRun.status} />
                <span className="text-sm font-medium text-zinc-200">
                  {selectedRun.agent?.name ?? `Agent #${selectedRun.agent_id}`}
                </span>
              </div>
              <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
                <span>{selectedRun.total_tokens.toLocaleString()} tokens</span>
                <span>{formatCost(selectedRun.total_cost_microcents)}</span>
                <span>{formatDuration(selectedRun.total_duration_ms)}</span>
              </div>
              {selectedRun.error && (
                <p className="mt-2 text-xs text-red-400 bg-red-900/20 p-2 rounded">{selectedRun.error}</p>
              )}
            </div>

            {/* Steps trace */}
            <h4 className="text-xs font-medium text-zinc-400 mb-2 uppercase">Execution Trace</h4>
            <div className="space-y-1">
              {selectedRun.steps?.map((step) => (
                <TraceStep
                  key={step.id}
                  step={step}
                  expanded={expandedSteps.has(step.id)}
                  onToggle={() => toggleStep(step.id)}
                />
              ))}
              {(!selectedRun.steps || selectedRun.steps.length === 0) && (
                <p className="text-xs text-zinc-600">No steps recorded</p>
              )}
            </div>

            {/* Output */}
            {selectedRun.status === 'completed' && selectedRun.output && (
              <div className="mt-4 p-3 rounded bg-zinc-800 border border-zinc-700">
                <h4 className="text-xs font-medium text-zinc-400 mb-1 uppercase">Final Output</h4>
                <pre className="text-xs text-zinc-300 whitespace-pre-wrap overflow-auto max-h-40">
                  {typeof selectedRun.output === 'object' && 'response' in selectedRun.output
                    ? String(selectedRun.output.response)
                    : JSON.stringify(selectedRun.output, null, 2)}
                </pre>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}

function StatCard({
  icon,
  label,
  value,
  subtext,
}: {
  icon: React.ReactNode
  label: string
  value: string
  subtext?: string
}) {
  return (
    <div className="rounded-lg border border-zinc-800 bg-zinc-900/50 p-4">
      <div className="flex items-center gap-2 mb-1">
        {icon}
        <span className="text-xs text-zinc-500">{label}</span>
      </div>
      <div className="text-lg font-semibold text-zinc-100">{value}</div>
      {subtext && <div className="text-xs text-zinc-500 mt-0.5">{subtext}</div>}
    </div>
  )
}

function RunStatusIcon({ status }: { status: string }) {
  const map: Record<string, { icon: typeof CheckCircle2; color: string }> = {
    completed: { icon: CheckCircle2, color: 'text-emerald-400' },
    failed: { icon: XCircle, color: 'text-red-400' },
    running: { icon: Loader2, color: 'text-blue-400' },
    cancelled: { icon: XCircle, color: 'text-amber-400' },
    pending: { icon: Clock, color: 'text-zinc-500' },
  }
  const { icon: Icon, color } = map[status] ?? map.pending
  return <Icon className={`h-4 w-4 ${color} ${status === 'running' ? 'animate-spin' : ''} flex-shrink-0`} />
}

function TraceStep({
  step,
  expanded,
  onToggle,
}: {
  step: ExecutionStep
  expanded: boolean
  onToggle: () => void
}) {
  const PhaseIcon = PHASE_ICONS[step.phase] ?? Eye
  const phaseColor = PHASE_COLORS[step.phase] ?? 'text-zinc-400'

  return (
    <div className="rounded border border-zinc-800/50 bg-zinc-900/30">
      <button
        onClick={onToggle}
        className="w-full flex items-center gap-2 px-2 py-1.5 text-left hover:bg-zinc-800/30 transition-colors"
      >
        {expanded ? (
          <ChevronDown className="h-3 w-3 text-zinc-600 flex-shrink-0" />
        ) : (
          <ChevronRight className="h-3 w-3 text-zinc-600 flex-shrink-0" />
        )}
        <PhaseIcon className={`h-3.5 w-3.5 ${phaseColor} flex-shrink-0`} />
        <span className={`text-[10px] font-medium uppercase ${phaseColor}`}>{step.phase}</span>
        <span className="text-[10px] text-zinc-600">#{step.step_number}</span>
        <span className="ml-auto text-[10px] text-zinc-600">
          {step.duration_ms > 0 && `${step.duration_ms}ms`}
        </span>
        {step.token_usage && (
          <span className="text-[10px] text-zinc-600">
            {(step.token_usage.input_tokens ?? 0) + (step.token_usage.output_tokens ?? 0)} tok
          </span>
        )}
      </button>

      {expanded && (
        <div className="px-2 pb-2 border-t border-zinc-800/30 space-y-2">
          {step.input && (
            <div className="mt-1">
              <span className="text-[10px] uppercase text-zinc-600 font-medium">Input</span>
              <pre className="text-[11px] text-zinc-400 mt-0.5 overflow-auto max-h-32 bg-zinc-950/50 rounded p-1.5">
                {JSON.stringify(step.input, null, 2)}
              </pre>
            </div>
          )}
          {step.output && (
            <div>
              <span className="text-[10px] uppercase text-zinc-600 font-medium">Output</span>
              <pre className="text-[11px] text-zinc-400 mt-0.5 overflow-auto max-h-32 bg-zinc-950/50 rounded p-1.5">
                {JSON.stringify(step.output, null, 2)}
              </pre>
            </div>
          )}
          {step.tool_calls && step.tool_calls.length > 0 && (
            <div>
              <span className="text-[10px] uppercase text-zinc-600 font-medium">Tool Calls</span>
              {step.tool_calls.map((tc, i) => (
                <div key={i} className="mt-0.5 p-1.5 bg-zinc-950/50 rounded text-[11px]">
                  <span className="text-amber-400 font-mono">{tc.tool_name}</span>
                  <span className="text-zinc-600 ml-2">{tc.duration_ms}ms</span>
                  {tc.is_error && <span className="text-red-400 ml-1">error</span>}
                </div>
              ))}
            </div>
          )}
          {step.token_usage && (
            <div className="flex gap-3 text-[10px] text-zinc-600">
              <span>In: {step.token_usage.input_tokens}</span>
              <span>Out: {step.token_usage.output_tokens}</span>
            </div>
          )}
          {step.error && (
            <div className="p-1.5 bg-red-900/20 rounded text-[11px] text-red-400">{step.error}</div>
          )}
        </div>
      )}
    </div>
  )
}
