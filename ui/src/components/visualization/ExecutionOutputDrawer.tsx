import { useState, useEffect, useRef, useCallback } from 'react'
import {
  X,
  Minus,
  Maximize2,
  Square,
  Bot,
  Wrench,
  MessageSquare,
  ArrowRightLeft,
  AlertCircle,
  Loader2,
  CheckCircle2,
  XCircle,
  Clock,
  GripHorizontal,
  ChevronUp,
  Brain,
  Save,
  FileText,
  Upload,
} from 'lucide-react'
import { cancelExecution } from '@/api/client'
import type { ExecutionRun } from '@/types'

/* ─── Types ─────────────────────────────────────────────────── */

export interface ExecutionStreamStep {
  type: 'message' | 'tool_call' | 'tool_result' | 'delegation' | 'error' | 'status' | 'complete' | 'memory_recall' | 'memory_store' | 'document_read' | 'document_write'
  content: string
  agent_name?: string
  agent_id?: number
  tool_name?: string
  timestamp: string
  tokens?: number
  cost_microcents?: number
  status?: string
  duration_ms?: number
}

export interface ExecutionDrawerState {
  executionId: number
  streamUrl: string
  agentName: string
  agentAvatar?: string
  agentId: number
}

interface Props {
  execution: ExecutionDrawerState | null
  /** For replaying historical executions (#385) */
  replayExecution?: ExecutionRun | null
  onClose: () => void
  onStatusChange?: (agentId: number, status: 'running' | 'completed' | 'failed' | 'idle') => void
  onStepEvent?: (step: ExecutionStreamStep) => void
}

/* ─── Helpers ───────────────────────────────────────────────── */

function formatDuration(ms: number): string {
  if (ms < 1000) return `${ms}ms`
  const secs = Math.floor(ms / 1000)
  if (secs < 60) return `${secs}s`
  const mins = Math.floor(secs / 60)
  return `${mins}m ${secs % 60}s`
}

function formatCost(microcents: number): string {
  const dollars = microcents / 100_000_000
  if (dollars < 0.001) return `$${(microcents / 100_0000).toFixed(4)}`
  return `$${dollars.toFixed(4)}`
}

function relativeTime(ts: string): string {
  const diff = Date.now() - new Date(ts).getTime()
  if (diff < 60_000) return 'just now'
  if (diff < 3_600_000) return `${Math.floor(diff / 60_000)}m ago`
  return `${Math.floor(diff / 3_600_000)}h ago`
}

const STEP_ICONS: Record<string, React.ReactNode> = {
  message: <MessageSquare className="h-3.5 w-3.5 text-blue-400" />,
  tool_call: <Wrench className="h-3.5 w-3.5 text-amber-400" />,
  tool_result: <Wrench className="h-3.5 w-3.5 text-emerald-400" />,
  delegation: <ArrowRightLeft className="h-3.5 w-3.5 text-violet-400" />,
  error: <AlertCircle className="h-3.5 w-3.5 text-red-400" />,
  status: <Bot className="h-3.5 w-3.5 text-zinc-400" />,
  complete: <CheckCircle2 className="h-3.5 w-3.5 text-emerald-400" />,
  // #420 — Memory & document operation icons
  memory_recall: <Brain className="h-3.5 w-3.5 text-purple-400" />,
  memory_store: <Save className="h-3.5 w-3.5 text-purple-400" />,
  document_read: <FileText className="h-3.5 w-3.5 text-orange-400" />,
  document_write: <Upload className="h-3.5 w-3.5 text-orange-400" />,
}

const STATUS_BADGES: Record<string, { color: string; icon: React.ReactNode }> = {
  running: { color: 'bg-blue-500/20 text-blue-400 border-blue-500/40', icon: <Loader2 className="h-3 w-3 animate-spin" /> },
  completed: { color: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40', icon: <CheckCircle2 className="h-3 w-3" /> },
  failed: { color: 'bg-red-500/20 text-red-400 border-red-500/40', icon: <XCircle className="h-3 w-3" /> },
  cancelled: { color: 'bg-zinc-500/20 text-zinc-400 border-zinc-500/40', icon: <Square className="h-3 w-3" /> },
  pending: { color: 'bg-yellow-500/20 text-yellow-400 border-yellow-500/40', icon: <Clock className="h-3 w-3" /> },
}

/* ─── Component ─────────────────────────────────────────────── */

export default function ExecutionOutputDrawer({ execution, replayExecution, onClose, onStatusChange, onStepEvent }: Props) {
  const [steps, setSteps] = useState<ExecutionStreamStep[]>([])
  const [status, setStatus] = useState<string>('running')
  const [isMinimized, setIsMinimized] = useState(false)
  const [drawerHeight, setDrawerHeight] = useState(300)
  const [totalTokens, setTotalTokens] = useState(0)
  const [totalCost, setTotalCost] = useState(0)
  const [startTime] = useState(Date.now())
  const [elapsed, setElapsed] = useState(0)
  const logEndRef = useRef<HTMLDivElement>(null)
  const eventSourceRef = useRef<EventSource | null>(null)
  const dragStartYRef = useRef<number | null>(null)
  const dragStartHeightRef = useRef<number>(300)

  // Timer for elapsed display
  useEffect(() => {
    if (status !== 'running') return
    const interval = setInterval(() => {
      setElapsed(Date.now() - startTime)
    }, 1000)
    return () => clearInterval(interval)
  }, [status, startTime])

  // Auto-scroll
  useEffect(() => {
    logEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [steps])

  // SSE connection for live execution
  useEffect(() => {
    if (!execution) return

    setSteps([])
    setStatus('running')
    setTotalTokens(0)
    setTotalCost(0)

    const baseUrl = import.meta.env.VITE_API_BASE_URL || ''
    const url = `${baseUrl}${execution.streamUrl}`
    const es = new EventSource(url, { withCredentials: true })
    eventSourceRef.current = es

    es.onmessage = (event) => {
      try {
        const step = JSON.parse(event.data) as ExecutionStreamStep
        setSteps((prev) => [...prev, step])

        if (step.tokens) setTotalTokens((t) => t + step.tokens!)
        if (step.cost_microcents) setTotalCost((c) => c + step.cost_microcents!)

        // Notify parent of step for node status updates (#384)
        onStepEvent?.(step)

        if (step.type === 'complete' || step.type === 'error') {
          const finalStatus = step.type === 'error' ? 'failed' : 'completed'
          setStatus(finalStatus)
          onStatusChange?.(execution.agentId, finalStatus)
          es.close()
        }

        if (step.status) {
          setStatus(step.status)
        }
      } catch {
        // ignore parse errors
      }
    }

    es.onerror = () => {
      if (status === 'running') {
        setStatus('failed')
        onStatusChange?.(execution.agentId, 'failed')
      }
      es.close()
    }

    return () => {
      es.close()
      eventSourceRef.current = null
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [execution?.executionId])

  // Replay mode: load steps from historical execution (#385)
  useEffect(() => {
    if (!replayExecution) return
    setStatus(replayExecution.status)
    setTotalTokens(replayExecution.total_tokens)
    setTotalCost(replayExecution.total_cost_microcents)
    setElapsed(replayExecution.total_duration_ms)

    if (replayExecution.steps) {
      const replaySteps: ExecutionStreamStep[] = replayExecution.steps.map((s) => ({
        type: s.phase === 'act' && s.tool_calls?.length
          ? 'tool_call'
          : s.status === 'failed'
            ? 'error'
            : 'message',
        content: s.output ? JSON.stringify(s.output) : s.error || '(no output)',
        timestamp: s.created_at,
        tokens: s.token_usage ? s.token_usage.input_tokens + s.token_usage.output_tokens : undefined,
        cost_microcents: undefined,
        tool_name: s.tool_calls?.[0]?.tool_name,
      }))
      setSteps(replaySteps)
    }
  }, [replayExecution])

  const handleCancel = useCallback(async () => {
    if (!execution) return
    try {
      await cancelExecution(execution.executionId)
      setStatus('cancelled')
      onStatusChange?.(execution.agentId, 'idle')
      eventSourceRef.current?.close()
    } catch {
      // ignore
    }
  }, [execution, onStatusChange])

  // Drag handle for resize
  const handleDragStart = useCallback((e: React.MouseEvent) => {
    e.preventDefault()
    dragStartYRef.current = e.clientY
    dragStartHeightRef.current = drawerHeight

    const onMouseMove = (ev: MouseEvent) => {
      if (dragStartYRef.current === null) return
      const delta = dragStartYRef.current - ev.clientY
      const newHeight = Math.max(100, Math.min(600, dragStartHeightRef.current + delta))
      setDrawerHeight(newHeight)
    }

    const onMouseUp = () => {
      dragStartYRef.current = null
      window.removeEventListener('mousemove', onMouseMove)
      window.removeEventListener('mouseup', onMouseUp)
    }

    window.addEventListener('mousemove', onMouseMove)
    window.addEventListener('mouseup', onMouseUp)
  }, [drawerHeight])

  // Nothing to show
  if (!execution && !replayExecution) return null

  const agentName = execution?.agentName ?? replayExecution?.agent?.name ?? 'Agent'
  const agentAvatar = execution?.agentAvatar ?? replayExecution?.agent?.icon

  const statusBadge = STATUS_BADGES[status] ?? STATUS_BADGES.pending

  // Minimized bar
  if (isMinimized) {
    return (
      <div className="absolute bottom-0 left-0 right-0 z-30 h-8 bg-zinc-900/95 border-t border-zinc-700 flex items-center px-3 gap-2 backdrop-blur-sm">
        <button onClick={() => setIsMinimized(false)} className="text-zinc-400 hover:text-white">
          <ChevronUp className="h-3.5 w-3.5" />
        </button>
        {status === 'running' && <Loader2 className="h-3 w-3 text-blue-400 animate-spin" />}
        <span className="text-[11px] text-zinc-300 truncate">
          {status === 'running' ? `Running: ${agentName}...` : `${agentName} - ${status}`}
        </span>
        <span className="text-[10px] text-zinc-500 ml-auto">{formatDuration(elapsed)}</span>
        <button onClick={onClose} className="text-zinc-500 hover:text-zinc-300 ml-1">
          <X className="h-3 w-3" />
        </button>
      </div>
    )
  }

  return (
    <div
      className="absolute bottom-0 left-0 right-0 z-30 bg-zinc-900/95 border-t border-zinc-700 flex flex-col backdrop-blur-sm"
      style={{ height: drawerHeight }}
    >
      {/* Drag handle */}
      <div
        className="flex items-center justify-center h-3 cursor-ns-resize hover:bg-zinc-800/50 transition-colors"
        onMouseDown={handleDragStart}
      >
        <GripHorizontal className="h-3 w-4 text-zinc-600" />
      </div>

      {/* Header */}
      <div className="flex items-center gap-2 px-3 py-1.5 border-b border-zinc-800 shrink-0">
        {agentAvatar ? (
          <span className="text-sm">{agentAvatar}</span>
        ) : (
          <Bot className="h-4 w-4 text-violet-400" />
        )}
        <span className="text-sm font-semibold text-zinc-200 truncate">{agentName}</span>

        {/* Status badge */}
        <span className={`flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded border ${statusBadge.color}`}>
          {statusBadge.icon}
          <span className="capitalize">{status}</span>
        </span>

        {/* Elapsed timer */}
        <span className="text-[10px] text-zinc-500 font-mono">{formatDuration(elapsed)}</span>

        {/* Token count */}
        {totalTokens > 0 && (
          <span className="text-[10px] text-zinc-500">{totalTokens.toLocaleString()} tok</span>
        )}

        {/* Cost */}
        {totalCost > 0 && (
          <span className="text-[10px] text-zinc-500">{formatCost(totalCost)}</span>
        )}

        <div className="ml-auto flex items-center gap-1">
          <button
            onClick={() => setIsMinimized(true)}
            className="p-1 text-zinc-500 hover:text-zinc-300 rounded transition-colors"
            title="Minimize"
          >
            <Minus className="h-3.5 w-3.5" />
          </button>
          <button
            onClick={() => setDrawerHeight((h) => (h < 400 ? 500 : 300))}
            className="p-1 text-zinc-500 hover:text-zinc-300 rounded transition-colors"
            title="Toggle size"
          >
            <Maximize2 className="h-3.5 w-3.5" />
          </button>
          <button
            onClick={onClose}
            className="p-1 text-zinc-500 hover:text-zinc-300 rounded transition-colors"
            title="Close"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      {/* Body — scrollable log */}
      <div className="flex-1 overflow-y-auto px-3 py-2 space-y-1.5">
        {steps.length === 0 && status === 'running' && (
          <div className="flex items-center gap-2 text-sm text-zinc-500 py-4 justify-center">
            <Loader2 className="h-4 w-4 animate-spin" />
            <span>Waiting for response...</span>
          </div>
        )}
        {steps.map((step, i) => (
          <div key={i} className="flex items-start gap-2 text-[12px]">
            <div className="shrink-0 mt-0.5">{STEP_ICONS[step.type] || STEP_ICONS.status}</div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 text-zinc-500 mb-0.5">
                {step.tool_name && (
                  <span className="font-mono text-amber-400/80">{step.tool_name}</span>
                )}
                {step.agent_name && (
                  <span className="text-violet-400/80">{step.agent_name}</span>
                )}
                <span className="text-zinc-600">{relativeTime(step.timestamp)}</span>
                {step.tokens && <span className="text-zinc-600">{step.tokens} tok</span>}
              </div>
              <div className={`text-zinc-300 whitespace-pre-wrap break-words ${step.type === 'error' ? 'text-red-400' : ''}`}>
                {step.content.length > 2000 ? step.content.slice(0, 2000) + '...' : step.content}
              </div>
            </div>
          </div>
        ))}
        <div ref={logEndRef} />
      </div>

      {/* Footer */}
      <div className="flex items-center gap-2 px-3 py-1.5 border-t border-zinc-800 shrink-0">
        {status === 'running' && (
          <button
            onClick={handleCancel}
            className="flex items-center gap-1.5 px-3 py-1 text-[11px] bg-red-600/20 hover:bg-red-600/30 text-red-400 border border-red-500/30 rounded transition-colors"
          >
            <Square className="h-3 w-3" />
            Cancel
          </button>
        )}
        <div className="ml-auto text-[10px] text-zinc-600">
          {steps.length} step{steps.length !== 1 ? 's' : ''}
        </div>
      </div>
    </div>
  )
}
