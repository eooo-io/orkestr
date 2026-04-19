import { useEffect, useState, useCallback } from 'react'
import { useParams } from 'react-router-dom'
import {
  Play,
  Square,
  Eye,
  Brain,
  Zap,
  MessageSquare,
  Clock,
  Hash,
  AlertCircle,
  CheckCircle2,
  XCircle,
  Loader2,
  ChevronDown,
  ChevronRight,
} from 'lucide-react'
import {
  fetchProjectAgents,
  executeAgent,
  fetchExecutionRuns,
  fetchExecutionRun,
  cancelExecutionRun,
} from '@/api/client'
import { Button } from '@/components/ui/button'
import type { ProjectAgent, ExecutionRun, ExecutionStep } from '@/types'

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

const STATUS_ICONS: Record<string, typeof CheckCircle2> = {
  pending: Clock,
  running: Loader2,
  completed: CheckCircle2,
  failed: XCircle,
  cancelled: XCircle,
}

export function ExecutionPlayground() {
  const { id: projectId } = useParams<{ id: string }>()
  const pid = Number(projectId)

  const [agents, setAgents] = useState<ProjectAgent[]>([])
  const [selectedAgentId, setSelectedAgentId] = useState<number | null>(null)
  const [userInput, setUserInput] = useState('')
  const [currentRun, setCurrentRun] = useState<ExecutionRun | null>(null)
  const [recentRuns, setRecentRuns] = useState<ExecutionRun[]>([])
  const [isExecuting, setIsExecuting] = useState(false)
  const [expandedSteps, setExpandedSteps] = useState<Set<number>>(new Set())

  useEffect(() => {
    if (!pid) return
    fetchProjectAgents(pid).then(setAgents)
    fetchExecutionRuns(pid).then(setRecentRuns)
  }, [pid])

  const handleExecute = useCallback(async () => {
    if (!selectedAgentId || !userInput.trim() || !pid) return

    setIsExecuting(true)
    setExpandedSteps(new Set())

    try {
      const run = await executeAgent(pid, selectedAgentId, {
        message: userInput.trim(),
      })
      setCurrentRun(run)
      // Refresh recent runs
      fetchExecutionRuns(pid).then(setRecentRuns)
    } catch (err: unknown) {
      console.error('Execution failed:', err)
    } finally {
      setIsExecuting(false)
    }
  }, [pid, selectedAgentId, userInput])

  const handleCancel = useCallback(async () => {
    if (!currentRun) return
    await cancelExecutionRun(currentRun.id)
    const updated = await fetchExecutionRun(currentRun.id)
    setCurrentRun(updated)
  }, [currentRun])

  const handleViewRun = useCallback(async (runId: number) => {
    const run = await fetchExecutionRun(runId)
    setCurrentRun(run)
    setExpandedSteps(new Set())
  }, [])

  const toggleStep = (stepId: number) => {
    setExpandedSteps((prev) => {
      const next = new Set(prev)
      if (next.has(stepId)) {
        next.delete(stepId)
      } else {
        next.add(stepId)
      }
      return next
    })
  }

  const selectedAgent = agents.find((a) => a.agent.id === selectedAgentId)

  return (
    <div className="flex h-full gap-6">
      {/* Main execution panel */}
      <div className="flex-1 flex flex-col min-w-0">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-zinc-100">Agent Execution</h2>
          <p className="text-sm text-zinc-500">Run agents with real tool calls through the full execution loop</p>
        </div>

        {/* Agent selector */}
        <div className="mb-4">
          <label className="block text-sm font-medium text-zinc-400 mb-1">Agent</label>
          <select
            value={selectedAgentId ?? ''}
            onChange={(e) => setSelectedAgentId(Number(e.target.value) || null)}
            className="w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-zinc-100"
          >
            <option value="">Select an agent...</option>
            {agents.map((pa) => (
              <option key={pa.agent.id} value={pa.agent.id}>
                {pa.agent.icon} {pa.agent.name} — {pa.agent.role}
              </option>
            ))}
          </select>
        </div>

        {/* Input */}
        <div className="mb-4">
          <label className="block text-sm font-medium text-zinc-400 mb-1">Goal / Message</label>
          <textarea
            value={userInput}
            onChange={(e) => setUserInput(e.target.value)}
            placeholder="Describe the goal or task for the agent..."
            rows={3}
            className="w-full rounded-md border border-zinc-700 bg-zinc-800 px-3 py-2 text-sm text-zinc-100 resize-none"
            onKeyDown={(e) => {
              if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
                handleExecute()
              }
            }}
          />
        </div>

        {/* Execute button */}
        <div className="flex gap-2 mb-6">
          <Button
            onClick={handleExecute}
            disabled={!selectedAgentId || !userInput.trim() || isExecuting}
            className="bg-emerald-600 hover:bg-emerald-500"
          >
            {isExecuting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin mr-1" /> Running...
              </>
            ) : (
              <>
                <Play className="h-4 w-4 mr-1" /> Execute
              </>
            )}
          </Button>
          {currentRun?.status === 'running' && (
            <Button onClick={handleCancel} variant="destructive" size="sm">
              <Square className="h-4 w-4 mr-1" /> Cancel
            </Button>
          )}
        </div>

        {/* Execution result */}
        {currentRun && (
          <div className="flex-1 overflow-auto">
            {/* Guardrail halt banner */}
            {currentRun.status === 'halted_guardrail' && currentRun.halt_reason && (
              <div className="mb-3 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                <div className="font-semibold">
                  Halted by guardrail: {formatHaltReason(currentRun.halt_reason)}
                </div>
                <div className="text-xs text-red-400/80 mt-0.5">
                  Review the execution trace — the run was stopped before completion to protect budget/resources.
                </div>
              </div>
            )}

            {/* Run header */}
            <div className="flex items-center gap-3 mb-4 p-3 rounded-lg bg-zinc-800/50 border border-zinc-700">
              <RunStatusBadge status={currentRun.status} />
              <span className="text-sm text-zinc-300">
                {currentRun.agent?.name ?? `Agent #${currentRun.agent_id}`}
              </span>
              <span className="text-xs text-zinc-500">
                <Hash className="h-3 w-3 inline" /> {currentRun.total_tokens}
                {currentRun.token_budget != null && `/${currentRun.token_budget}`} tokens
              </span>
              <span className="text-xs text-zinc-500">
                <Clock className="h-3 w-3 inline" /> {currentRun.total_duration_ms}ms
              </span>
              {currentRun.error && (
                <span className="text-xs text-red-400 ml-auto">
                  <AlertCircle className="h-3 w-3 inline" /> {currentRun.error}
                </span>
              )}
            </div>

            {/* Steps timeline */}
            <div className="space-y-2">
              {currentRun.steps?.map((step) => (
                <StepCard
                  key={step.id}
                  step={step}
                  expanded={expandedSteps.has(step.id)}
                  onToggle={() => toggleStep(step.id)}
                />
              ))}
            </div>

            {/* Final output */}
            {currentRun.status === 'completed' && currentRun.output && (
              <div className="mt-4 p-4 rounded-lg bg-zinc-800 border border-zinc-700">
                <h4 className="text-sm font-medium text-zinc-300 mb-2">Output</h4>
                <div className="text-sm text-zinc-100 whitespace-pre-wrap">
                  {typeof currentRun.output === 'object' && 'response' in currentRun.output
                    ? String(currentRun.output.response)
                    : JSON.stringify(currentRun.output, null, 2)}
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Recent runs sidebar */}
      <div className="w-72 flex-shrink-0 border-l border-zinc-800 pl-4">
        <h3 className="text-sm font-medium text-zinc-400 mb-3">Recent Runs</h3>
        <div className="space-y-2">
          {recentRuns.map((run) => (
            <button
              key={run.id}
              onClick={() => handleViewRun(run.id)}
              className={`w-full text-left p-2 rounded-md text-xs transition-colors ${
                currentRun?.id === run.id
                  ? 'bg-zinc-700 border border-zinc-600'
                  : 'bg-zinc-800/50 border border-transparent hover:border-zinc-700'
              }`}
            >
              <div className="flex items-center gap-2 mb-1">
                <RunStatusBadge status={run.status} size="sm" />
                <span className="text-zinc-300 truncate">
                  {run.agent?.name ?? `Agent #${run.agent_id}`}
                </span>
              </div>
              <div className="flex gap-3 text-zinc-500">
                <span>{run.total_tokens} tok</span>
                <span>{run.total_duration_ms}ms</span>
                <span>{new Date(run.created_at).toLocaleTimeString()}</span>
              </div>
            </button>
          ))}
          {recentRuns.length === 0 && (
            <p className="text-xs text-zinc-600">No runs yet</p>
          )}
        </div>
      </div>
    </div>
  )
}

function formatHaltReason(reason: string): string {
  const map: Record<string, string> = {
    loop_detected: 'Loop detected',
    turn_cap_exceeded: 'Turn cap exceeded',
    budget_token_exceeded: 'Token budget exhausted',
    budget_cost_exceeded: 'Cost budget exhausted',
  }
  return map[reason] ?? reason
}

function RunStatusBadge({ status, size = 'md' }: { status: string; size?: 'sm' | 'md' }) {
  const colors: Record<string, string> = {
    pending: 'bg-zinc-600 text-zinc-300',
    running: 'bg-blue-600/20 text-blue-400',
    completed: 'bg-emerald-600/20 text-emerald-400',
    failed: 'bg-red-600/20 text-red-400',
    cancelled: 'bg-amber-600/20 text-amber-400',
    halted_guardrail: 'bg-red-600/20 text-red-400',
  }

  const Icon = STATUS_ICONS[status] ?? Clock

  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium ${colors[status] ?? colors.pending} ${
        size === 'sm' ? 'text-[10px]' : 'text-xs'
      }`}
    >
      <Icon className={`${size === 'sm' ? 'h-2.5 w-2.5' : 'h-3 w-3'} ${status === 'running' ? 'animate-spin' : ''}`} />
      {status}
    </span>
  )
}

function StepCard({ step, expanded, onToggle }: { step: ExecutionStep; expanded: boolean; onToggle: () => void }) {
  const PhaseIcon = PHASE_ICONS[step.phase] ?? Eye
  const phaseColor = PHASE_COLORS[step.phase] ?? 'text-zinc-400'

  return (
    <div className="rounded-md border border-zinc-700/50 bg-zinc-800/30">
      <button
        onClick={onToggle}
        className="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-zinc-800/50 transition-colors"
      >
        {expanded ? (
          <ChevronDown className="h-3.5 w-3.5 text-zinc-500 flex-shrink-0" />
        ) : (
          <ChevronRight className="h-3.5 w-3.5 text-zinc-500 flex-shrink-0" />
        )}
        <PhaseIcon className={`h-4 w-4 ${phaseColor} flex-shrink-0`} />
        <span className={`text-xs font-medium uppercase ${phaseColor}`}>{step.phase}</span>
        <span className="text-xs text-zinc-500">#{step.step_number}</span>
        <StepStatusBadge status={step.status} />
        {step.duration_ms > 0 && (
          <span className="text-xs text-zinc-600 ml-auto">{step.duration_ms}ms</span>
        )}
        {step.token_usage && (
          <span className="text-xs text-zinc-600">
            {(step.token_usage.input_tokens ?? 0) + (step.token_usage.output_tokens ?? 0)} tok
          </span>
        )}
      </button>

      {expanded && (
        <div className="px-3 pb-3 border-t border-zinc-700/30">
          {step.input && (
            <div className="mt-2">
              <span className="text-[10px] uppercase text-zinc-500 font-medium">Input</span>
              <pre className="text-xs text-zinc-400 mt-1 overflow-auto max-h-40 bg-zinc-900/50 rounded p-2">
                {JSON.stringify(step.input, null, 2)}
              </pre>
            </div>
          )}

          {step.output && (
            <div className="mt-2">
              <span className="text-[10px] uppercase text-zinc-500 font-medium">Output</span>
              <pre className="text-xs text-zinc-400 mt-1 overflow-auto max-h-40 bg-zinc-900/50 rounded p-2">
                {JSON.stringify(step.output, null, 2)}
              </pre>
            </div>
          )}

          {step.tool_calls && step.tool_calls.length > 0 && (
            <div className="mt-2">
              <span className="text-[10px] uppercase text-zinc-500 font-medium">Tool Calls</span>
              {step.tool_calls.map((tc, i) => (
                <div key={i} className="mt-1 p-2 bg-zinc-900/50 rounded text-xs">
                  <div className="flex items-center gap-2">
                    <Zap className="h-3 w-3 text-amber-400" />
                    <span className="text-zinc-300 font-mono">{tc.tool_name}</span>
                    <span className="text-zinc-600">{tc.duration_ms}ms</span>
                    {tc.is_error && <span className="text-red-400">error</span>}
                  </div>
                  <pre className="text-zinc-500 mt-1 overflow-auto max-h-20">
                    {tc.content?.map((c) => c.text).join('\n')}
                  </pre>
                </div>
              ))}
            </div>
          )}

          {step.error && (
            <div className="mt-2 p-2 bg-red-900/20 rounded">
              <span className="text-xs text-red-400">{step.error}</span>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

function StepStatusBadge({ status }: { status: string }) {
  const colors: Record<string, string> = {
    pending: 'text-zinc-500',
    running: 'text-blue-400',
    completed: 'text-emerald-400',
    failed: 'text-red-400',
  }

  return (
    <span className={`text-[10px] ${colors[status] ?? 'text-zinc-500'}`}>
      {status === 'running' && <Loader2 className="h-2.5 w-2.5 inline animate-spin" />}
      {status === 'completed' && <CheckCircle2 className="h-2.5 w-2.5 inline" />}
      {status === 'failed' && <XCircle className="h-2.5 w-2.5 inline" />}
    </span>
  )
}
