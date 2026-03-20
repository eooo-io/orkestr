import { useEffect, useState, useCallback } from 'react'
import {
  Activity,
  Cpu,
  CircleDot,
  RefreshCw,
  PlayCircle,
  StopCircle,
  RotateCcw,
  AlertTriangle,
  CheckCircle,
  Clock,
  Zap,
  DollarSign,
  BarChart3,
  Gauge,
} from 'lucide-react'
import {
  fetchAgentFleet,
  stopAgentProcess,
  restartAgentProcess,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import { useAppStore } from '@/store/useAppStore'
import type { AgentProcessInfo } from '@/types'

const COST_PER_1K_INPUT = 0.003
const COST_PER_1K_OUTPUT = 0.015

function formatNumber(n: number): string {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (n >= 1_000) return `${(n / 1_000).toFixed(1)}K`
  return n.toString()
}

function formatCost(dollars: number): string {
  if (dollars < 0.01) return '<$0.01'
  return `$${dollars.toFixed(2)}`
}

function formatUptime(seconds: number): string {
  if (seconds < 60) return `${seconds}s`
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  return `${h}h ${m}m`
}

const STATUS_CONFIG: Record<string, { color: string; icon: React.ReactNode; label: string }> = {
  starting: { color: 'text-yellow-500', icon: <Zap className="h-3.5 w-3.5" />, label: 'Starting' },
  running: { color: 'text-green-500', icon: <Activity className="h-3.5 w-3.5 animate-pulse" />, label: 'Running' },
  idle: { color: 'text-blue-500', icon: <Clock className="h-3.5 w-3.5" />, label: 'Idle' },
  stopping: { color: 'text-orange-500', icon: <StopCircle className="h-3.5 w-3.5" />, label: 'Stopping' },
  stopped: { color: 'text-muted-foreground', icon: <CircleDot className="h-3.5 w-3.5" />, label: 'Stopped' },
  crashed: { color: 'text-red-500', icon: <AlertTriangle className="h-3.5 w-3.5" />, label: 'Crashed' },
}

export function RuntimeDashboard() {
  const [fleet, setFleet] = useState<AgentProcessInfo[]>([])
  const [loading, setLoading] = useState(true)
  const confirm = useConfirm()
  const { showToast } = useAppStore()

  const load = useCallback(() => {
    fetchAgentFleet()
      .then(setFleet)
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    load()
    // Auto-refresh every 10 seconds
    const interval = setInterval(load, 10000)
    return () => clearInterval(interval)
  }, [load])

  const handleStop = async (p: AgentProcessInfo) => {
    if (!(await confirm({ message: `Stop daemon for ${p.agent.name}?`, title: 'Stop Daemon' })))
      return
    try {
      await stopAgentProcess(p.project.id, p.agent.id)
      showToast('Stop signal sent')
      load()
    } catch {
      showToast('Failed to stop', 'error')
    }
  }

  const handleRestart = async (p: AgentProcessInfo) => {
    try {
      await restartAgentProcess(p.project.id, p.agent.id)
      showToast('Restart initiated')
      load()
    } catch {
      showToast('Failed to restart', 'error')
    }
  }

  // Stats
  const totalRunning = fleet.filter((p) => p.status === 'running' || p.status === 'idle').length
  const totalHealthy = fleet.filter((p) => p.healthy).length
  const totalUnhealthy = fleet.filter((p) => !p.healthy).length
  const avgUptime = fleet.length > 0
    ? Math.round(fleet.reduce((sum, p) => sum + p.uptime_seconds, 0) / fleet.length)
    : 0

  // Token burn rate & cost
  const totalInputTokens = fleet.reduce((s, p) => s + (p.total_input_tokens ?? 0), 0)
  const totalOutputTokens = fleet.reduce((s, p) => s + (p.total_output_tokens ?? 0), 0)
  const totalTokens = totalInputTokens + totalOutputTokens
  const totalUptimeHours = fleet.reduce((s, p) => s + p.uptime_seconds, 0) / 3600
  const burnRate = totalUptimeHours > 0 ? Math.round(totalTokens / totalUptimeHours) : 0

  // APM telemetry
  const totalIterations = fleet.reduce((s, p) => s + (p.iterations_completed ?? 0), 0)
  const totalErrors = fleet.reduce((s, p) => s + (p.error_count ?? 0), 0)
  const errorRate = totalIterations > 0 ? ((totalErrors / totalIterations) * 100).toFixed(1) : '0.0'
  const avgResponseMs = fleet.length > 0
    ? Math.round(fleet.reduce((s, p) => s + (p.avg_response_ms ?? 0), 0) / fleet.length)
    : 0

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-pulse text-muted-foreground">Loading runtime dashboard...</div>
      </div>
    )
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold flex items-center gap-2">
            <Cpu className="h-5 w-5 text-primary" />
            Agent Runtime
          </h1>
          <p className="text-sm text-muted-foreground mt-0.5">
            Live daemon processes and fleet health
          </p>
        </div>
        <button
          onClick={load}
          className="flex items-center gap-1.5 text-sm px-3 py-1.5 bg-muted rounded hover:bg-muted/80"
        >
          <RefreshCw className="h-3.5 w-3.5" /> Refresh
        </button>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-card elevation-1 rounded-lg p-4">
          <div className="text-xs text-muted-foreground uppercase tracking-wider">Active</div>
          <div className="text-2xl font-bold mt-1">{totalRunning}</div>
        </div>
        <div className="bg-card elevation-1 rounded-lg p-4">
          <div className="text-xs text-muted-foreground uppercase tracking-wider">Healthy</div>
          <div className="text-2xl font-bold mt-1 text-green-500">{totalHealthy}</div>
        </div>
        <div className="bg-card elevation-1 rounded-lg p-4">
          <div className="text-xs text-muted-foreground uppercase tracking-wider">Unhealthy</div>
          <div className={`text-2xl font-bold mt-1 ${totalUnhealthy > 0 ? 'text-red-500' : ''}`}>
            {totalUnhealthy}
          </div>
        </div>
        <div className="bg-card elevation-1 rounded-lg p-4">
          <div className="text-xs text-muted-foreground uppercase tracking-wider">Avg Uptime</div>
          <div className="text-2xl font-bold mt-1">{formatUptime(avgUptime)}</div>
        </div>
      </div>

      {/* Fleet Table */}
      <div className="bg-card elevation-1 rounded-lg overflow-hidden">
        <div className="px-4 py-3 border-b border-border">
          <h2 className="text-sm font-semibold">Fleet Overview</h2>
        </div>

        {fleet.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
            <Cpu className="h-8 w-8 mb-2 opacity-30" />
            <p className="text-sm">No daemon processes running</p>
            <p className="text-xs mt-1">Start a daemon from any agent's settings</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-xs text-muted-foreground uppercase">
                  <th className="text-left px-4 py-2">Agent</th>
                  <th className="text-left px-4 py-2">Project</th>
                  <th className="text-left px-4 py-2">Status</th>
                  <th className="text-left px-4 py-2">Health</th>
                  <th className="text-left px-4 py-2">Uptime</th>
                  <th className="text-left px-4 py-2">Restarts</th>
                  <th className="text-left px-4 py-2">Last Heartbeat</th>
                  <th className="text-right px-4 py-2">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {fleet.map((p) => {
                  const sc = STATUS_CONFIG[p.status] || STATUS_CONFIG.stopped
                  return (
                    <tr key={p.id} className="hover:bg-muted/20">
                      <td className="px-4 py-3 font-medium">{p.agent.name}</td>
                      <td className="px-4 py-3 text-muted-foreground">{p.project.name}</td>
                      <td className="px-4 py-3">
                        <span className={`flex items-center gap-1.5 ${sc.color}`}>
                          {sc.icon} {sc.label}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        {p.healthy ? (
                          <CheckCircle className="h-4 w-4 text-green-500" />
                        ) : (
                          <AlertTriangle className="h-4 w-4 text-red-500" />
                        )}
                      </td>
                      <td className="px-4 py-3 font-mono text-xs">
                        {formatUptime(p.uptime_seconds)}
                      </td>
                      <td className="px-4 py-3">
                        <span className={p.restart_count > 0 ? 'text-yellow-500' : ''}>
                          {p.restart_count}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-xs text-muted-foreground">
                        {p.last_heartbeat_at
                          ? new Date(p.last_heartbeat_at).toLocaleTimeString()
                          : '—'}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-1">
                          <button
                            onClick={() => handleRestart(p)}
                            className="p-1.5 text-muted-foreground hover:text-foreground rounded hover:bg-muted"
                            title="Restart"
                          >
                            <RotateCcw className="h-3.5 w-3.5" />
                          </button>
                          <button
                            onClick={() => handleStop(p)}
                            className="p-1.5 text-muted-foreground hover:text-destructive rounded hover:bg-destructive/10"
                            title="Stop"
                          >
                            <StopCircle className="h-3.5 w-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Token Burn Rate */}
      <div>
        <h2 className="text-sm font-semibold mb-3 flex items-center gap-1.5">
          <Zap className="h-4 w-4 text-yellow-500" /> Token Burn Rate
        </h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-card elevation-1 rounded-lg p-4">
            <div className="text-xs text-muted-foreground uppercase tracking-wider">Total Tokens</div>
            <div className="text-2xl font-bold mt-1">{formatNumber(totalTokens)}</div>
          </div>
          <div className="bg-card elevation-1 rounded-lg p-4">
            <div className="text-xs text-muted-foreground uppercase tracking-wider">Input</div>
            <div className="text-2xl font-bold mt-1">{formatNumber(totalInputTokens)}</div>
          </div>
          <div className="bg-card elevation-1 rounded-lg p-4">
            <div className="text-xs text-muted-foreground uppercase tracking-wider">Output</div>
            <div className="text-2xl font-bold mt-1">{formatNumber(totalOutputTokens)}</div>
          </div>
          <div className="bg-card elevation-1 rounded-lg p-4">
            <div className="text-xs text-muted-foreground uppercase tracking-wider">Burn Rate</div>
            <div className="text-2xl font-bold mt-1">{formatNumber(burnRate)}/hr</div>
          </div>
        </div>
      </div>

      {/* Cost Attribution */}
      {fleet.length > 0 && (
        <div className="bg-card elevation-1 rounded-lg overflow-hidden">
          <div className="px-4 py-3 border-b border-border">
            <h2 className="text-sm font-semibold flex items-center gap-1.5">
              <DollarSign className="h-4 w-4 text-green-500" /> Cost Attribution
            </h2>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-xs text-muted-foreground uppercase">
                  <th className="text-left px-4 py-2">Agent</th>
                  <th className="text-right px-4 py-2">Input Tokens</th>
                  <th className="text-right px-4 py-2">Output Tokens</th>
                  <th className="text-right px-4 py-2">Est. Cost</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {fleet.map((p) => {
                  const inputCost = ((p.total_input_tokens ?? 0) / 1000) * COST_PER_1K_INPUT
                  const outputCost = ((p.total_output_tokens ?? 0) / 1000) * COST_PER_1K_OUTPUT
                  return (
                    <tr key={p.id} className="hover:bg-muted/20">
                      <td className="px-4 py-2.5 font-medium">{p.agent.name}</td>
                      <td className="px-4 py-2.5 text-right font-mono text-xs">
                        {formatNumber(p.total_input_tokens ?? 0)}
                      </td>
                      <td className="px-4 py-2.5 text-right font-mono text-xs">
                        {formatNumber(p.total_output_tokens ?? 0)}
                      </td>
                      <td className="px-4 py-2.5 text-right font-mono text-xs">
                        {formatCost(inputCost + outputCost)}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* APM Telemetry */}
      <div>
        <h2 className="text-sm font-semibold mb-3 flex items-center gap-1.5">
          <Gauge className="h-4 w-4 text-blue-500" /> APM Telemetry
        </h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-card elevation-1 rounded-lg p-4">
            <div className="text-xs text-muted-foreground uppercase tracking-wider">Avg Response</div>
            <div className="text-2xl font-bold mt-1">{avgResponseMs}ms</div>
          </div>
          <div className="bg-card elevation-1 rounded-lg p-4">
            <div className="text-xs text-muted-foreground uppercase tracking-wider">Iterations</div>
            <div className="text-2xl font-bold mt-1">{formatNumber(totalIterations)}</div>
          </div>
          <div className="bg-card elevation-1 rounded-lg p-4">
            <div className="text-xs text-muted-foreground uppercase tracking-wider">Errors</div>
            <div className={`text-2xl font-bold mt-1 ${totalErrors > 0 ? 'text-red-500' : ''}`}>
              {totalErrors}
            </div>
          </div>
          <div className="bg-card elevation-1 rounded-lg p-4">
            <div className="text-xs text-muted-foreground uppercase tracking-wider">Error Rate</div>
            <div className={`text-2xl font-bold mt-1 ${parseFloat(errorRate) > 5 ? 'text-red-500' : ''}`}>
              {errorRate}%
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
