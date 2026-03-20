import { useEffect, useState, useCallback } from 'react'
import {
  Gavel,
  Store,
  Users,
  Activity,
  RefreshCw,
  Check,
  Clock,
  XCircle,
  AlertTriangle,
  Zap,
  DollarSign,
  Timer,
  Shield,
  Plus,
  UserMinus,
  ChevronDown,
  ChevronRight,
  Loader2,
  Target,
  TrendingUp,
  Cpu,
} from 'lucide-react'
import api from '@/api/client'
import { useAppStore } from '@/store/useAppStore'

// ---- Types ----

interface BidAgent {
  id: number
  name: string
  slug: string
  icon: string | null
}

interface TaskBid {
  id: number
  uuid: string
  task_id: number
  agent_id: number
  agent: BidAgent | null
  project_id: number
  bid_score: number
  estimated_duration_ms: number
  estimated_cost_microcents: number
  confidence: number
  reasoning: string | null
  status: string
  expires_at: string
  created_at: string
}

interface CapabilityEntry {
  name: string
  proficiency: number
  cost_per_task: number
}

interface Advertisement {
  id: number
  agent_id: number
  agent: {
    id: number
    name: string
    slug: string
    role: string | null
    icon: string | null
    description: string | null
  } | null
  project_id: number
  capabilities: CapabilityEntry[]
  availability_status: string
  max_concurrent_tasks: number
  current_load: number
  advertised_at: string
  expires_at: string
}

interface TeamAgent {
  id: number
  name: string
  slug: string
  icon: string | null
  role: string | null
}

interface TeamFormation {
  id: number
  uuid: string
  project_id: number
  name: string
  objective: string
  formation_strategy: string
  agent_ids: number[]
  agents: TeamAgent[]
  status: string
  performance_score: number | null
  created_at: string
  disbanded_at: string | null
}

interface NegotiationLogEntry {
  id: number
  task_id: number | null
  team_formation_id: number | null
  agent_id: number
  agent: BidAgent | null
  action: string
  details: Record<string, unknown> | null
  created_at: string
}

interface TaskWithBids {
  task_id: number
  bids: TaskBid[]
}

// ---- Helpers ----

function formatDuration(ms: number): string {
  if (ms < 1000) return `${ms}ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`
  return `${(ms / 60000).toFixed(1)}m`
}

function formatCost(microcents: number): string {
  const dollars = microcents / 100_000_000
  if (dollars < 0.01) return '<$0.01'
  return `$${dollars.toFixed(4)}`
}

function timeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const seconds = Math.floor(diff / 1000)
  if (seconds < 60) return `${seconds}s ago`
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  return `${Math.floor(hours / 24)}d ago`
}

const STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-500/20 text-yellow-400',
  accepted: 'bg-green-500/20 text-green-400',
  rejected: 'bg-red-500/20 text-red-400',
  expired: 'bg-gray-500/20 text-gray-400',
  withdrawn: 'bg-orange-500/20 text-orange-400',
  available: 'bg-green-500/20 text-green-400',
  busy: 'bg-yellow-500/20 text-yellow-400',
  offline: 'bg-red-500/20 text-red-400',
  forming: 'bg-blue-500/20 text-blue-400',
  active: 'bg-green-500/20 text-green-400',
  disbanded: 'bg-gray-500/20 text-gray-400',
}

const ACTION_COLORS: Record<string, string> = {
  bid: 'bg-blue-500/20 text-blue-400',
  accept: 'bg-green-500/20 text-green-400',
  reject: 'bg-red-500/20 text-red-400',
  advertise: 'bg-purple-500/20 text-purple-400',
  form_team: 'bg-cyan-500/20 text-cyan-400',
  join_team: 'bg-teal-500/20 text-teal-400',
  leave_team: 'bg-orange-500/20 text-orange-400',
}

const STRATEGY_LABELS: Record<string, string> = {
  capability_match: 'Capability Match',
  cost_optimized: 'Cost Optimized',
  speed_optimized: 'Speed Optimized',
}

function StatusBadge({ status }: { status: string }) {
  return (
    <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[status] || 'bg-gray-500/20 text-gray-400'}`}>
      {status}
    </span>
  )
}

function ScoreBar({ score, max = 1 }: { score: number; max?: number }) {
  const pct = Math.min(100, (score / max) * 100)
  const color = pct >= 70 ? 'bg-green-500' : pct >= 40 ? 'bg-yellow-500' : 'bg-red-500'
  return (
    <div className="flex items-center gap-2">
      <div className="h-1.5 flex-1 rounded-full bg-white/10">
        <div className={`h-full rounded-full ${color}`} style={{ width: `${pct}%` }} />
      </div>
      <span className="text-xs text-muted-foreground tabular-nums">{score.toFixed(2)}</span>
    </div>
  )
}

// ---- Tab: Bidding ----

function BiddingTab({ projectId }: { projectId: number }) {
  const [taskBids, setTaskBids] = useState<TaskWithBids[]>([])
  const [loading, setLoading] = useState(true)
  const [expanded, setExpanded] = useState<number | null>(null)
  const [accepting, setAccepting] = useState<number | null>(null)
  const { showToast } = useAppStore()

  const load = useCallback(() => {
    // Fetch all bids for this project's tasks
    api.get(`/api/negotiation/project/${projectId}/bids`)
      .then((res) => {
        const bids: TaskBid[] = res.data.data || []
        // Group bids by task_id
        const groups: Record<number, TaskBid[]> = {}
        bids.forEach((b) => {
          if (b.task_id) {
            if (!groups[b.task_id]) groups[b.task_id] = []
            groups[b.task_id].push(b)
          }
        })
        setTaskBids(
          Object.entries(groups).map(([tid, bids]) => ({
            task_id: Number(tid),
            bids: bids.sort((a, b) => b.bid_score - a.bid_score),
          }))
        )
      })
      .catch(() => setTaskBids([]))
      .finally(() => setLoading(false))
  }, [projectId])

  useEffect(() => { load() }, [load])

  const handleAccept = async (bid: TaskBid) => {
    setAccepting(bid.id)
    try {
      await api.post(`/api/negotiation/bids/${bid.id}/accept`)
      showToast('Bid accepted')
      load()
    } catch {
      showToast('Failed to accept bid', 'error')
    } finally {
      setAccepting(null)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  if (taskBids.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
        <Gavel className="mb-2 h-8 w-8 opacity-40" />
        <p className="text-sm">No open bids</p>
        <p className="text-xs opacity-60">Bids appear when tasks are opened for negotiation</p>
      </div>
    )
  }

  return (
    <div className="space-y-2">
      {taskBids.map((group) => {
        const isExpanded = expanded === group.task_id
        const hasPending = group.bids.some((b) => b.status === 'pending')
        return (
          <div key={group.task_id} className="rounded-lg border border-white/10 bg-white/[0.02]">
            <button
              onClick={() => setExpanded(isExpanded ? null : group.task_id)}
              className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-white/[0.02]"
            >
              <div className="flex items-center gap-3">
                {isExpanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                <span className="text-sm font-medium">Task #{group.task_id}</span>
                <span className="text-xs text-muted-foreground">{group.bids.length} bids</span>
                {hasPending && (
                  <span className="rounded-full bg-yellow-500/20 px-2 py-0.5 text-xs text-yellow-400">
                    awaiting decision
                  </span>
                )}
              </div>
            </button>

            {isExpanded && (
              <div className="border-t border-white/5 px-4 pb-3 pt-2">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-xs text-muted-foreground">
                      <th className="pb-2 pr-3">Agent</th>
                      <th className="pb-2 pr-3">Score</th>
                      <th className="pb-2 pr-3">Duration</th>
                      <th className="pb-2 pr-3">Cost</th>
                      <th className="pb-2 pr-3">Confidence</th>
                      <th className="pb-2 pr-3">Status</th>
                      <th className="pb-2" />
                    </tr>
                  </thead>
                  <tbody>
                    {group.bids.map((bid) => (
                      <tr key={bid.id} className="border-t border-white/5">
                        <td className="py-2 pr-3">
                          <div className="flex items-center gap-2">
                            {bid.agent?.icon ? (
                              <span className="text-lg">{bid.agent.icon}</span>
                            ) : (
                              <Cpu className="h-4 w-4 text-muted-foreground" />
                            )}
                            <span className="font-medium">{bid.agent?.name || `Agent #${bid.agent_id}`}</span>
                          </div>
                        </td>
                        <td className="w-32 py-2 pr-3">
                          <ScoreBar score={bid.bid_score} />
                        </td>
                        <td className="py-2 pr-3">
                          <div className="flex items-center gap-1 text-xs text-muted-foreground">
                            <Timer className="h-3 w-3" />
                            {formatDuration(bid.estimated_duration_ms)}
                          </div>
                        </td>
                        <td className="py-2 pr-3">
                          <div className="flex items-center gap-1 text-xs text-muted-foreground">
                            <DollarSign className="h-3 w-3" />
                            {formatCost(bid.estimated_cost_microcents)}
                          </div>
                        </td>
                        <td className="w-28 py-2 pr-3">
                          <ScoreBar score={bid.confidence} />
                        </td>
                        <td className="py-2 pr-3">
                          <StatusBadge status={bid.status} />
                        </td>
                        <td className="py-2">
                          {bid.status === 'pending' && (
                            <button
                              onClick={() => handleAccept(bid)}
                              disabled={accepting === bid.id}
                              className="inline-flex items-center gap-1 rounded bg-green-600 px-2 py-1 text-xs font-medium text-white hover:bg-green-500 disabled:opacity-50"
                            >
                              {accepting === bid.id ? (
                                <Loader2 className="h-3 w-3 animate-spin" />
                              ) : (
                                <Check className="h-3 w-3" />
                              )}
                              Accept
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                {group.bids[0]?.reasoning && (
                  <div className="mt-2 rounded bg-white/[0.03] p-2 text-xs text-muted-foreground">
                    <strong>Top bid reasoning:</strong> {group.bids[0].reasoning}
                  </div>
                )}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}

// ---- Tab: Marketplace ----

function MarketplaceTab({ projectId }: { projectId: number }) {
  const [advertisements, setAdvertisements] = useState<Advertisement[]>([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const { showToast } = useAppStore()

  const load = useCallback(() => {
    api.get(`/api/projects/${projectId}/advertisements`)
      .then((res) => setAdvertisements(res.data.data || []))
      .catch(() => setAdvertisements([]))
      .finally(() => setLoading(false))
  }, [projectId])

  useEffect(() => { load() }, [load])

  const handleRefresh = async () => {
    setRefreshing(true)
    try {
      const res = await api.post(`/api/projects/${projectId}/advertisements/refresh`)
      setAdvertisements(res.data.data || [])
      showToast(`Refreshed ${res.data.refreshed} advertisements`)
    } catch {
      showToast('Failed to refresh', 'error')
    } finally {
      setRefreshing(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <p className="text-xs text-muted-foreground">{advertisements.length} agent(s) advertising capabilities</p>
        <button
          onClick={handleRefresh}
          disabled={refreshing}
          className="inline-flex items-center gap-1.5 rounded-md bg-white/10 px-3 py-1.5 text-xs font-medium hover:bg-white/15 disabled:opacity-50"
        >
          <RefreshCw className={`h-3.5 w-3.5 ${refreshing ? 'animate-spin' : ''}`} />
          Refresh All
        </button>
      </div>

      {advertisements.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
          <Store className="mb-2 h-8 w-8 opacity-40" />
          <p className="text-sm">No active advertisements</p>
          <p className="text-xs opacity-60">Click "Refresh All" to advertise agent capabilities</p>
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {advertisements.map((ad) => (
            <div key={ad.id} className="rounded-lg border border-white/10 bg-white/[0.02] p-4">
              {/* Agent header */}
              <div className="mb-3 flex items-start justify-between">
                <div className="flex items-center gap-2">
                  {ad.agent?.icon ? (
                    <span className="text-2xl">{ad.agent.icon}</span>
                  ) : (
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-white/10">
                      <Cpu className="h-4 w-4" />
                    </div>
                  )}
                  <div>
                    <div className="text-sm font-medium">{ad.agent?.name || `Agent #${ad.agent_id}`}</div>
                    {ad.agent?.role && (
                      <div className="text-xs text-muted-foreground">{ad.agent.role}</div>
                    )}
                  </div>
                </div>
                <StatusBadge status={ad.availability_status} />
              </div>

              {/* Capabilities */}
              <div className="mb-3 space-y-1.5">
                {ad.capabilities.map((cap, i) => (
                  <div key={i} className="flex items-center justify-between gap-2">
                    <span className="truncate text-xs text-muted-foreground">{cap.name}</span>
                    <div className="w-24">
                      <ScoreBar score={cap.proficiency} />
                    </div>
                  </div>
                ))}
              </div>

              {/* Load indicator */}
              <div className="flex items-center justify-between border-t border-white/5 pt-2">
                <div className="flex items-center gap-1 text-xs text-muted-foreground">
                  <Activity className="h-3 w-3" />
                  Load: {ad.current_load}/{ad.max_concurrent_tasks}
                </div>
                <div className="text-xs text-muted-foreground">
                  {timeAgo(ad.advertised_at)}
                </div>
              </div>

              {/* Load bar */}
              <div className="mt-1.5 h-1 rounded-full bg-white/10">
                <div
                  className={`h-full rounded-full ${
                    ad.current_load / ad.max_concurrent_tasks >= 0.8
                      ? 'bg-red-500'
                      : ad.current_load / ad.max_concurrent_tasks >= 0.5
                        ? 'bg-yellow-500'
                        : 'bg-green-500'
                  }`}
                  style={{ width: `${Math.min(100, (ad.current_load / ad.max_concurrent_tasks) * 100)}%` }}
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

// ---- Tab: Teams ----

function TeamsTab({ projectId }: { projectId: number }) {
  const [formations, setFormations] = useState<TeamFormation[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [formName, setFormName] = useState('')
  const [formObjective, setFormObjective] = useState('')
  const [formStrategy, setFormStrategy] = useState('capability_match')
  const [submitting, setSubmitting] = useState(false)
  const [disbanding, setDisbanding] = useState<number | null>(null)
  const { showToast } = useAppStore()

  const load = useCallback(() => {
    api.get(`/api/projects/${projectId}/team-formations`)
      .then((res) => setFormations(res.data.data || []))
      .catch(() => setFormations([]))
      .finally(() => setLoading(false))
  }, [projectId])

  useEffect(() => { load() }, [load])

  const handleFormTeam = async () => {
    if (!formObjective.trim()) return
    setSubmitting(true)
    try {
      await api.post(`/api/projects/${projectId}/team-formations`, {
        name: formName || undefined,
        objective: formObjective,
        strategy: formStrategy,
      })
      showToast('Team formed')
      setShowForm(false)
      setFormName('')
      setFormObjective('')
      setFormStrategy('capability_match')
      load()
    } catch {
      showToast('Failed to form team', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDisband = async (formation: TeamFormation) => {
    setDisbanding(formation.id)
    try {
      await api.post(`/api/team-formations/${formation.id}/disband`)
      showToast('Team disbanded')
      load()
    } catch {
      showToast('Failed to disband', 'error')
    } finally {
      setDisbanding(null)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <p className="text-xs text-muted-foreground">
          {formations.filter((f) => f.status !== 'disbanded').length} active team(s)
        </p>
        <button
          onClick={() => setShowForm(!showForm)}
          className="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500"
        >
          <Plus className="h-3.5 w-3.5" />
          Form Team
        </button>
      </div>

      {/* Form Team Modal */}
      {showForm && (
        <div className="mb-4 rounded-lg border border-white/10 bg-white/[0.03] p-4">
          <h4 className="mb-3 text-sm font-medium">Form a New Team</h4>
          <div className="space-y-3">
            <div>
              <label className="mb-1 block text-xs text-muted-foreground">Team Name (optional)</label>
              <input
                value={formName}
                onChange={(e) => setFormName(e.target.value)}
                placeholder="e.g. Security Review Squad"
                className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-1.5 text-sm placeholder:text-muted-foreground/50 focus:border-blue-500 focus:outline-none"
              />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted-foreground">Objective</label>
              <textarea
                value={formObjective}
                onChange={(e) => setFormObjective(e.target.value)}
                placeholder="Describe the team's mission, e.g. 'Perform a comprehensive security audit and code review of the payment module'"
                rows={3}
                className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-1.5 text-sm placeholder:text-muted-foreground/50 focus:border-blue-500 focus:outline-none"
              />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted-foreground">Strategy</label>
              <select
                value={formStrategy}
                onChange={(e) => setFormStrategy(e.target.value)}
                className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
              >
                <option value="capability_match">Capability Match</option>
                <option value="cost_optimized">Cost Optimized</option>
                <option value="speed_optimized">Speed Optimized</option>
              </select>
            </div>
            <div className="flex justify-end gap-2">
              <button
                onClick={() => setShowForm(false)}
                className="rounded-md px-3 py-1.5 text-xs hover:bg-white/10"
              >
                Cancel
              </button>
              <button
                onClick={handleFormTeam}
                disabled={!formObjective.trim() || submitting}
                className="inline-flex items-center gap-1 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500 disabled:opacity-50"
              >
                {submitting && <Loader2 className="h-3 w-3 animate-spin" />}
                Form Team
              </button>
            </div>
          </div>
        </div>
      )}

      {formations.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
          <Users className="mb-2 h-8 w-8 opacity-40" />
          <p className="text-sm">No team formations</p>
          <p className="text-xs opacity-60">Form a team to auto-select agents for an objective</p>
        </div>
      ) : (
        <div className="space-y-3">
          {formations.map((f) => (
            <div key={f.id} className="rounded-lg border border-white/10 bg-white/[0.02] p-4">
              <div className="mb-2 flex items-start justify-between">
                <div>
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{f.name}</span>
                    <StatusBadge status={f.status} />
                    <span className={`rounded-full px-2 py-0.5 text-xs ${
                      f.formation_strategy === 'cost_optimized'
                        ? 'bg-emerald-500/20 text-emerald-400'
                        : f.formation_strategy === 'speed_optimized'
                          ? 'bg-purple-500/20 text-purple-400'
                          : 'bg-blue-500/20 text-blue-400'
                    }`}>
                      {STRATEGY_LABELS[f.formation_strategy] || f.formation_strategy}
                    </span>
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground line-clamp-2">{f.objective}</p>
                </div>
                {f.status !== 'disbanded' && (
                  <button
                    onClick={() => handleDisband(f)}
                    disabled={disbanding === f.id}
                    className="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-red-400 hover:bg-red-500/10 disabled:opacity-50"
                  >
                    {disbanding === f.id ? (
                      <Loader2 className="h-3 w-3 animate-spin" />
                    ) : (
                      <UserMinus className="h-3 w-3" />
                    )}
                    Disband
                  </button>
                )}
              </div>

              {/* Members */}
              <div className="flex items-center gap-3 border-t border-white/5 pt-2">
                <div className="flex -space-x-2">
                  {f.agents.slice(0, 5).map((agent) => (
                    <div
                      key={agent.id}
                      title={agent.name}
                      className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-[#0a0a0a] bg-white/10 text-xs"
                    >
                      {agent.icon || agent.name.charAt(0).toUpperCase()}
                    </div>
                  ))}
                  {f.agents.length > 5 && (
                    <div className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-[#0a0a0a] bg-white/10 text-xs">
                      +{f.agents.length - 5}
                    </div>
                  )}
                </div>
                <span className="text-xs text-muted-foreground">{f.agents.length} member(s)</span>

                {f.performance_score !== null && (
                  <div className="ml-auto flex items-center gap-1 text-xs text-muted-foreground">
                    <TrendingUp className="h-3 w-3" />
                    Performance: {(f.performance_score * 100).toFixed(0)}%
                  </div>
                )}
              </div>

              {f.disbanded_at && (
                <div className="mt-2 text-xs text-muted-foreground">
                  Disbanded {timeAgo(f.disbanded_at)}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

// ---- Tab: Activity ----

function ActivityTab({ projectId }: { projectId: number }) {
  const [logs, setLogs] = useState<NegotiationLogEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [actionFilter, setActionFilter] = useState<string>('')
  const [page, setPage] = useState(1)
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })

  const load = useCallback(() => {
    setLoading(true)
    const params = new URLSearchParams({ per_page: '30', page: String(page) })
    if (actionFilter) params.set('action', actionFilter)

    api.get(`/api/projects/${projectId}/negotiation-log?${params}`)
      .then((res) => {
        setLogs(res.data.data || [])
        setMeta(res.data.meta || { current_page: 1, last_page: 1, total: 0 })
      })
      .catch(() => setLogs([]))
      .finally(() => setLoading(false))
  }, [projectId, actionFilter, page])

  useEffect(() => { load() }, [load])

  const actionTypes = ['bid', 'accept', 'reject', 'advertise', 'form_team', 'join_team', 'leave_team']

  return (
    <div>
      {/* Filters */}
      <div className="mb-4 flex items-center gap-2">
        <select
          value={actionFilter}
          onChange={(e) => { setActionFilter(e.target.value); setPage(1) }}
          className="rounded-md border border-white/10 bg-white/5 px-3 py-1.5 text-xs focus:border-blue-500 focus:outline-none"
        >
          <option value="">All Actions</option>
          {actionTypes.map((a) => (
            <option key={a} value={a}>{a}</option>
          ))}
        </select>
        <span className="text-xs text-muted-foreground">{meta.total} entries</span>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        </div>
      ) : logs.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
          <Activity className="mb-2 h-8 w-8 opacity-40" />
          <p className="text-sm">No activity yet</p>
        </div>
      ) : (
        <>
          <div className="space-y-1">
            {logs.map((log) => (
              <div
                key={log.id}
                className="flex items-center gap-3 rounded-md px-3 py-2 hover:bg-white/[0.02]"
              >
                <div className="w-20 shrink-0 text-xs text-muted-foreground tabular-nums">
                  {log.created_at ? timeAgo(log.created_at) : '-'}
                </div>
                <div className="flex items-center gap-2">
                  {log.agent?.icon ? (
                    <span className="text-sm">{log.agent.icon}</span>
                  ) : (
                    <Cpu className="h-3.5 w-3.5 text-muted-foreground" />
                  )}
                  <span className="text-sm">{log.agent?.name || `Agent #${log.agent_id}`}</span>
                </div>
                <span className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${ACTION_COLORS[log.action] || 'bg-gray-500/20 text-gray-400'}`}>
                  {log.action}
                </span>
                {log.details && (
                  <span className="truncate text-xs text-muted-foreground">
                    {log.details.score !== undefined && `score: ${log.details.score}`}
                    {log.details.reason && String(log.details.reason)}
                    {log.details.team_id && `team #${log.details.team_id}`}
                    {log.details.agent_count !== undefined && ` (${log.details.agent_count} agents)`}
                  </span>
                )}
              </div>
            ))}
          </div>

          {/* Pagination */}
          {meta.last_page > 1 && (
            <div className="mt-4 flex items-center justify-center gap-2">
              <button
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                className="rounded-md px-3 py-1 text-xs hover:bg-white/10 disabled:opacity-30"
              >
                Prev
              </button>
              <span className="text-xs text-muted-foreground">
                {meta.current_page} / {meta.last_page}
              </span>
              <button
                disabled={page >= meta.last_page}
                onClick={() => setPage((p) => p + 1)}
                className="rounded-md px-3 py-1 text-xs hover:bg-white/10 disabled:opacity-30"
              >
                Next
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}

// ---- Main Panel ----

const TABS = [
  { key: 'bidding', label: 'Bidding', icon: Gavel },
  { key: 'marketplace', label: 'Marketplace', icon: Store },
  { key: 'teams', label: 'Teams', icon: Users },
  { key: 'activity', label: 'Activity', icon: Activity },
] as const

type TabKey = (typeof TABS)[number]['key']

interface NegotiationPanelProps {
  projectId: number
}

export function NegotiationPanel({ projectId }: NegotiationPanelProps) {
  const [activeTab, setActiveTab] = useState<TabKey>('bidding')

  return (
    <div>
      {/* Tab bar */}
      <div className="mb-4 flex gap-1 rounded-lg bg-white/[0.03] p-1">
        {TABS.map((tab) => {
          const Icon = tab.icon
          const isActive = activeTab === tab.key
          return (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-xs font-medium transition-colors ${
                isActive
                  ? 'bg-white/10 text-white'
                  : 'text-muted-foreground hover:text-white hover:bg-white/5'
              }`}
            >
              <Icon className="h-3.5 w-3.5" />
              {tab.label}
            </button>
          )
        })}
      </div>

      {/* Tab content */}
      {activeTab === 'bidding' && <BiddingTab projectId={projectId} />}
      {activeTab === 'marketplace' && <MarketplaceTab projectId={projectId} />}
      {activeTab === 'teams' && <TeamsTab projectId={projectId} />}
      {activeTab === 'activity' && <ActivityTab projectId={projectId} />}
    </div>
  )
}
