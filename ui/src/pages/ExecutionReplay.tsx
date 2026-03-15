import { useState, useEffect, useRef, useCallback } from 'react'
import {
  Play,
  Pause,
  ChevronLeft,
  ChevronRight,
  GitCompare,
  Clock,
  Coins,
  Hash,
  Zap,
  Brain,
  AlertTriangle,
  Eye,
  MessageSquare,
  CheckCircle2,
  XCircle,
  Loader2,
  ArrowLeftRight,
} from 'lucide-react'
import { fetchExecutions, fetchExecution, fetchExecutionSteps, diffExecutions } from '@/api/client'
import type { ExecutionReplay as ExecutionReplayType, ExecutionReplayStep, ExecutionDiff } from '@/types'

const STEP_TYPE_CONFIG: Record<string, { color: string; bg: string; icon: typeof Zap; label: string }> = {
  tool_call: { color: 'text-blue-400', bg: 'bg-blue-500', icon: Zap, label: 'Tool Call' },
  llm_response: { color: 'text-purple-400', bg: 'bg-purple-500', icon: Brain, label: 'LLM Response' },
  decision: { color: 'text-orange-400', bg: 'bg-orange-500', icon: MessageSquare, label: 'Decision' },
  observation: { color: 'text-green-400', bg: 'bg-green-500', icon: Eye, label: 'Observation' },
  error: { color: 'text-red-400', bg: 'bg-red-500', icon: AlertTriangle, label: 'Error' },
}

const STATUS_BADGE: Record<string, { className: string; icon: typeof CheckCircle2 }> = {
  running: { className: 'bg-blue-500/20 text-blue-400', icon: Loader2 },
  completed: { className: 'bg-green-500/20 text-green-400', icon: CheckCircle2 },
  failed: { className: 'bg-red-500/20 text-red-400', icon: XCircle },
  cancelled: { className: 'bg-zinc-500/20 text-zinc-400', icon: XCircle },
}

function formatDuration(ms: number): string {
  if (ms < 1000) return `${ms}ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`
  return `${(ms / 60000).toFixed(1)}m`
}

function formatCost(microcents: number): string {
  const dollars = microcents / 1_000_000
  if (dollars < 0.01) return `$${dollars.toFixed(6)}`
  return `$${dollars.toFixed(4)}`
}

function formatTokens(tokens: number): string {
  if (tokens >= 1000) return `${(tokens / 1000).toFixed(1)}k`
  return String(tokens)
}

function StepDetail({ step }: { step: ExecutionReplayStep }) {
  const config = STEP_TYPE_CONFIG[step.type] || STEP_TYPE_CONFIG.observation
  const Icon = config.icon

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <div className={`p-2 rounded-lg ${config.bg}/20`}>
          <Icon className={`h-5 w-5 ${config.color}`} />
        </div>
        <div>
          <h3 className="font-semibold text-foreground">Step {step.step_number}: {config.label}</h3>
          {step.model && <p className="text-xs text-muted-foreground">{step.model}</p>}
        </div>
        <div className="ml-auto flex gap-3 text-xs text-muted-foreground">
          {step.tokens_used != null && (
            <span className="flex items-center gap-1"><Hash className="h-3 w-3" />{formatTokens(step.tokens_used)}</span>
          )}
          {step.cost_microcents != null && (
            <span className="flex items-center gap-1"><Coins className="h-3 w-3" />{formatCost(step.cost_microcents)}</span>
          )}
          {step.duration_ms != null && (
            <span className="flex items-center gap-1"><Clock className="h-3 w-3" />{formatDuration(step.duration_ms)}</span>
          )}
        </div>
      </div>

      {step.input && (
        <div>
          <p className="text-xs font-medium text-muted-foreground mb-1">Input</p>
          <pre className="text-xs bg-muted/50 p-3 rounded-lg overflow-auto max-h-60 text-foreground">
            {JSON.stringify(step.input, null, 2)}
          </pre>
        </div>
      )}

      {step.output && (
        <div>
          <p className="text-xs font-medium text-muted-foreground mb-1">Output</p>
          <pre className="text-xs bg-muted/50 p-3 rounded-lg overflow-auto max-h-60 text-foreground">
            {JSON.stringify(step.output, null, 2)}
          </pre>
        </div>
      )}
    </div>
  )
}

function TimelineScrubber({
  steps,
  currentIndex,
  onSelect,
}: {
  steps: ExecutionReplayStep[]
  currentIndex: number
  onSelect: (index: number) => void
}) {
  return (
    <div className="flex items-center gap-1 px-4 py-3 bg-muted/30 rounded-lg overflow-x-auto">
      {steps.map((step, i) => {
        const config = STEP_TYPE_CONFIG[step.type] || STEP_TYPE_CONFIG.observation
        const isActive = i === currentIndex
        return (
          <button
            key={step.id}
            onClick={() => onSelect(i)}
            className={`w-4 h-4 rounded-full flex-shrink-0 transition-all ${config.bg} ${
              isActive ? 'ring-2 ring-foreground ring-offset-2 ring-offset-background scale-125' : 'opacity-50 hover:opacity-80'
            }`}
            title={`Step ${step.step_number}: ${config.label}`}
          />
        )
      })}
    </div>
  )
}

function DiffView({ diff }: { diff: ExecutionDiff }) {
  const { left, right, summary } = diff

  return (
    <div className="space-y-4">
      {/* Summary stats */}
      <div className="grid grid-cols-4 gap-3">
        {[
          { label: 'Tokens', value: summary.tokens_diff, format: (v: number) => `${v >= 0 ? '+' : ''}${formatTokens(v)}` },
          { label: 'Cost', value: summary.cost_diff, format: (v: number) => `${v >= 0 ? '+' : ''}${formatCost(Math.abs(v))}` },
          { label: 'Duration', value: summary.duration_diff, format: (v: number) => `${v >= 0 ? '+' : ''}${formatDuration(Math.abs(v))}` },
          { label: 'Steps', value: summary.steps_diff, format: (v: number) => `${v >= 0 ? '+' : ''}${v}` },
        ].map(({ label, value, format }) => (
          <div key={label} className="bg-muted/30 rounded-lg p-3 text-center">
            <p className="text-xs text-muted-foreground">{label} diff</p>
            <p className={`text-sm font-semibold ${value > 0 ? 'text-red-400' : value < 0 ? 'text-green-400' : 'text-muted-foreground'}`}>
              {format(value)}
            </p>
          </div>
        ))}
      </div>

      {/* Side by side steps */}
      <div className="grid grid-cols-2 gap-4">
        <div>
          <p className="text-xs font-medium text-muted-foreground mb-2">Execution A</p>
          <div className="space-y-2">
            {left.map((step, i) => (
              <div key={i} className={`p-3 rounded-lg text-xs ${step ? 'bg-muted/30' : 'bg-muted/10 border border-dashed border-border'}`}>
                {step ? (
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <span className={`inline-block w-2 h-2 rounded-full ${STEP_TYPE_CONFIG[step.type]?.bg || 'bg-zinc-500'}`} />
                      <span className="font-medium text-foreground">Step {step.step_number}: {STEP_TYPE_CONFIG[step.type]?.label || step.type}</span>
                    </div>
                    {step.tokens_used != null && <p className="text-muted-foreground">{formatTokens(step.tokens_used)} tokens</p>}
                  </div>
                ) : (
                  <p className="text-muted-foreground italic">No matching step</p>
                )}
              </div>
            ))}
          </div>
        </div>
        <div>
          <p className="text-xs font-medium text-muted-foreground mb-2">Execution B</p>
          <div className="space-y-2">
            {right.map((step, i) => (
              <div key={i} className={`p-3 rounded-lg text-xs ${step ? 'bg-muted/30' : 'bg-muted/10 border border-dashed border-border'}`}>
                {step ? (
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <span className={`inline-block w-2 h-2 rounded-full ${STEP_TYPE_CONFIG[step.type]?.bg || 'bg-zinc-500'}`} />
                      <span className="font-medium text-foreground">Step {step.step_number}: {STEP_TYPE_CONFIG[step.type]?.label || step.type}</span>
                    </div>
                    {step.tokens_used != null && <p className="text-muted-foreground">{formatTokens(step.tokens_used)} tokens</p>}
                  </div>
                ) : (
                  <p className="text-muted-foreground italic">No matching step</p>
                )}
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}

export function ExecutionReplay() {
  const [executions, setExecutions] = useState<ExecutionReplayType[]>([])
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [selectedExecution, setSelectedExecution] = useState<ExecutionReplayType | null>(null)
  const [steps, setSteps] = useState<ExecutionReplayStep[]>([])
  const [currentStepIndex, setCurrentStepIndex] = useState(0)
  const [isPlaying, setIsPlaying] = useState(false)
  const [loading, setLoading] = useState(true)

  // Compare mode
  const [compareMode, setCompareMode] = useState(false)
  const [compareId, setCompareId] = useState<number | null>(null)
  const [diff, setDiff] = useState<ExecutionDiff | null>(null)
  const [diffLoading, setDiffLoading] = useState(false)

  const playIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null)

  useEffect(() => {
    fetchExecutions().then((res) => {
      setExecutions(res.data || [])
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [])

  useEffect(() => {
    if (selectedId) {
      fetchExecution(selectedId).then((data) => {
        setSelectedExecution(data)
      })
      fetchExecutionSteps(selectedId).then((data) => {
        setSteps(data)
        setCurrentStepIndex(0)
        setIsPlaying(false)
      })
    }
  }, [selectedId])

  // Auto-advance
  useEffect(() => {
    if (isPlaying && steps.length > 0) {
      playIntervalRef.current = setInterval(() => {
        setCurrentStepIndex((prev) => {
          if (prev >= steps.length - 1) {
            setIsPlaying(false)
            return prev
          }
          return prev + 1
        })
      }, 1000)
    }
    return () => {
      if (playIntervalRef.current) clearInterval(playIntervalRef.current)
    }
  }, [isPlaying, steps.length])

  const handleCompare = useCallback(async () => {
    if (!selectedId || !compareId) return
    setDiffLoading(true)
    try {
      const data = await diffExecutions(selectedId, compareId)
      setDiff(data)
    } catch {
      // ignore
    }
    setDiffLoading(false)
  }, [selectedId, compareId])

  useEffect(() => {
    if (compareMode && selectedId && compareId) {
      handleCompare()
    }
  }, [compareMode, selectedId, compareId, handleCompare])

  const currentStep = steps[currentStepIndex] || null

  return (
    <div className="h-full flex flex-col">
      <div className="flex items-center justify-between px-6 py-4 border-b border-border">
        <div>
          <h1 className="text-xl font-semibold text-foreground">Execution Replay</h1>
          <p className="text-sm text-muted-foreground">Review and compare agent execution traces</p>
        </div>
        <button
          onClick={() => { setCompareMode(!compareMode); setDiff(null); setCompareId(null) }}
          className={`flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg transition-colors ${
            compareMode ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground hover:bg-muted/80'
          }`}
        >
          <GitCompare className="h-4 w-4" />
          {compareMode ? 'Exit Compare' : 'Compare'}
        </button>
      </div>

      <div className="flex flex-1 overflow-hidden">
        {/* Left panel: execution list */}
        <div className="w-80 border-r border-border overflow-y-auto">
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
            </div>
          ) : executions.length === 0 ? (
            <div className="px-4 py-12 text-center text-sm text-muted-foreground">
              No execution traces recorded yet.
            </div>
          ) : (
            <div className="divide-y divide-border">
              {executions.map((exec) => {
                const isSelected = exec.id === selectedId
                const isCompareTarget = exec.id === compareId
                const status = STATUS_BADGE[exec.status] || STATUS_BADGE.cancelled
                const StatusIcon = status.icon

                return (
                  <button
                    key={exec.id}
                    onClick={() => {
                      if (compareMode && selectedId && exec.id !== selectedId) {
                        setCompareId(exec.id)
                      } else {
                        setSelectedId(exec.id)
                        setDiff(null)
                        setCompareId(null)
                      }
                    }}
                    className={`w-full text-left px-4 py-3 transition-colors ${
                      isSelected ? 'bg-primary/10' : isCompareTarget ? 'bg-orange-500/10' : 'hover:bg-muted/50'
                    }`}
                  >
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-sm font-medium text-foreground truncate">{exec.name}</span>
                      <span className={`flex items-center gap-1 text-xs px-1.5 py-0.5 rounded ${status.className}`}>
                        <StatusIcon className="h-3 w-3" />
                        {exec.status}
                      </span>
                    </div>
                    {exec.agent && (
                      <p className="text-xs text-muted-foreground mb-1">{exec.agent.name}</p>
                    )}
                    <div className="flex gap-3 text-xs text-muted-foreground">
                      <span>{formatDuration(exec.total_duration_ms)}</span>
                      <span>{formatTokens(exec.total_tokens)} tokens</span>
                      <span>{exec.total_steps} steps</span>
                    </div>
                    {isCompareTarget && (
                      <div className="mt-1 flex items-center gap-1 text-xs text-orange-400">
                        <ArrowLeftRight className="h-3 w-3" />
                        Comparing
                      </div>
                    )}
                  </button>
                )
              })}
            </div>
          )}
        </div>

        {/* Right panel: replay/diff view */}
        <div className="flex-1 overflow-y-auto p-6">
          {diff && compareMode ? (
            <DiffView diff={diff} />
          ) : diffLoading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
            </div>
          ) : !selectedId ? (
            <div className="flex items-center justify-center h-full text-muted-foreground text-sm">
              Select an execution to view its replay
            </div>
          ) : steps.length === 0 ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
            </div>
          ) : (
            <div className="space-y-4">
              {/* Header */}
              {selectedExecution && (
                <div className="flex items-center gap-4 mb-2">
                  <h2 className="text-lg font-semibold text-foreground">{selectedExecution.name}</h2>
                  <span className={`text-xs px-2 py-0.5 rounded ${STATUS_BADGE[selectedExecution.status]?.className || ''}`}>
                    {selectedExecution.status}
                  </span>
                </div>
              )}

              {/* Compare hint */}
              {compareMode && !compareId && (
                <div className="bg-orange-500/10 border border-orange-500/20 rounded-lg px-4 py-2 text-sm text-orange-400">
                  Select another execution from the list to compare.
                </div>
              )}

              {/* Timeline scrubber */}
              <TimelineScrubber steps={steps} currentIndex={currentStepIndex} onSelect={setCurrentStepIndex} />

              {/* Playback controls */}
              <div className="flex items-center gap-3">
                <button
                  onClick={() => setCurrentStepIndex(Math.max(0, currentStepIndex - 1))}
                  disabled={currentStepIndex <= 0}
                  className="p-1.5 rounded-lg bg-muted hover:bg-muted/80 disabled:opacity-30 text-foreground"
                >
                  <ChevronLeft className="h-4 w-4" />
                </button>
                <button
                  onClick={() => setIsPlaying(!isPlaying)}
                  className="p-1.5 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90"
                >
                  {isPlaying ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />}
                </button>
                <button
                  onClick={() => setCurrentStepIndex(Math.min(steps.length - 1, currentStepIndex + 1))}
                  disabled={currentStepIndex >= steps.length - 1}
                  className="p-1.5 rounded-lg bg-muted hover:bg-muted/80 disabled:opacity-30 text-foreground"
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
                <span className="text-xs text-muted-foreground">
                  Step {currentStepIndex + 1} of {steps.length}
                </span>
              </div>

              {/* Current step detail */}
              {currentStep && (
                <div className="bg-card border border-border rounded-lg p-5">
                  <StepDetail step={currentStep} />
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
