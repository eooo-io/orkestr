import { useCallback, useEffect, useState } from 'react'
import {
  Route,
  Zap,
  BarChart3,
  Clock,
  DollarSign,
  Shield,
  Play,
  Plus,
  Trash2,
  Pencil,
  ChevronDown,
  ChevronRight,
  Brain,
  CheckCircle2,
  XCircle,
  ToggleLeft,
  ToggleRight,
  Target,
} from 'lucide-react'
import api from '@/api/client'

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

interface RoutingRule {
  id: number
  uuid: string
  project_id: number
  name: string
  description: string | null
  conditions: {
    task_type?: string
    priority?: string[]
    tags?: string[]
    keywords?: string[]
  }
  target_strategy: string
  target_agents: number[] | null
  sla_config: {
    max_wait_seconds?: number
    max_cost?: number
    priority_boost?: boolean
  } | null
  priority: number
  enabled: boolean
}

interface AgentCapabilityRow {
  id: number
  agent_id: number
  agent_name: string
  agent_slug: string
  agent_icon: string | null
  capability: string
  proficiency: number
  success_rate: number
  avg_duration_ms: number
  avg_cost_microcents: number
  task_count: number
  last_used_at: string | null
}

interface ScoredCandidate {
  agent_id: number
  agent_name: string
  score: number
  available: boolean
  load_factor: number
  proficiency: number
  success_rate: number
  avg_duration_ms: number
  avg_cost_microcents: number
}

interface SimulationResult {
  selected_agent: ScoredCandidate | null
  strategy: string
  rule: { id: number; name: string; conditions: Record<string, unknown> } | null
  candidates: ScoredCandidate[]
  task_type: string
  reasoning: string
}

interface RoutingDecisionRow {
  id: number
  task_id: number | null
  task_title: string | null
  task_priority: string | null
  selected_agent_id: number | null
  selected_agent_name: string | null
  strategy_used: string
  candidates: ScoredCandidate[]
  reasoning: string | null
  sla_met: boolean
  created_at: string
}

interface ProjectAgent {
  id: number
  name: string
}

/* ------------------------------------------------------------------ */
/*  Props                                                              */
/* ------------------------------------------------------------------ */

interface RoutingPanelProps {
  projectId: number
}

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

const STRATEGIES = [
  { value: 'best_fit', label: 'Best Fit', icon: Target },
  { value: 'round_robin', label: 'Round Robin', icon: Route },
  { value: 'least_loaded', label: 'Least Loaded', icon: BarChart3 },
  { value: 'cost_optimized', label: 'Cost Optimized', icon: DollarSign },
  { value: 'fastest', label: 'Fastest', icon: Zap },
]

const TASK_TYPES = [
  'code_review',
  'security_audit',
  'testing',
  'documentation',
  'performance',
  'deployment',
  'research',
  'design',
  'bug_fix',
  'feature',
  'refactor',
  'general',
]

function strategyBadge(strategy: string) {
  const s = STRATEGIES.find((x) => x.value === strategy)
  const Icon = s?.icon ?? Route
  return (
    <span className="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary font-medium">
      <Icon size={12} />
      {s?.label ?? strategy}
    </span>
  )
}

function proficiencyBar(value: number) {
  const pct = Math.round(value * 100)
  const color =
    value >= 0.7
      ? 'bg-green-500'
      : value >= 0.3
        ? 'bg-yellow-500'
        : 'bg-red-500'
  return (
    <div className="flex items-center gap-2">
      <div className="w-20 h-2 rounded-full bg-muted overflow-hidden">
        <div className={`h-full ${color} rounded-full`} style={{ width: `${pct}%` }} />
      </div>
      <span className="text-xs text-muted-foreground">{pct}%</span>
    </div>
  )
}

function formatDuration(ms: number): string {
  if (ms === 0) return '--'
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(1)}s`
}

function formatCost(microcents: number): string {
  if (microcents === 0) return '--'
  return `$${(microcents / 1_000_000).toFixed(4)}`
}

/* ------------------------------------------------------------------ */
/*  Main Component                                                     */
/* ------------------------------------------------------------------ */

export function RoutingPanel({ projectId }: RoutingPanelProps) {
  const [tab, setTab] = useState<'rules' | 'capabilities' | 'simulator' | 'decisions'>('rules')

  return (
    <div className="flex flex-col h-full">
      {/* Tab bar */}
      <div className="flex border-b border-border">
        {[
          { key: 'rules' as const, label: 'Routing Rules', icon: Route },
          { key: 'capabilities' as const, label: 'Capabilities', icon: Brain },
          { key: 'simulator' as const, label: 'Simulator', icon: Play },
          { key: 'decisions' as const, label: 'Decisions', icon: Clock },
        ].map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
              tab === t.key
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            <t.icon size={14} />
            {t.label}
          </button>
        ))}
      </div>

      {/* Tab content */}
      <div className="flex-1 overflow-y-auto">
        {tab === 'rules' && <RulesSection projectId={projectId} />}
        {tab === 'capabilities' && <CapabilitiesSection projectId={projectId} />}
        {tab === 'simulator' && <SimulatorSection projectId={projectId} />}
        {tab === 'decisions' && <DecisionsSection projectId={projectId} />}
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Rules Section                                                      */
/* ------------------------------------------------------------------ */

function RulesSection({ projectId }: { projectId: number }) {
  const [rules, setRules] = useState<RoutingRule[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [editingRule, setEditingRule] = useState<RoutingRule | null>(null)
  const [agents, setAgents] = useState<ProjectAgent[]>([])

  const load = useCallback(() => {
    setLoading(true)
    api
      .get(`/api/projects/${projectId}/routing-rules`)
      .then((r) => setRules(r.data.data))
      .finally(() => setLoading(false))
  }, [projectId])

  useEffect(() => {
    load()
    api.get(`/api/projects/${projectId}/agents`).then((r) => {
      const list = r.data.data ?? r.data
      setAgents(Array.isArray(list) ? list : [])
    })
  }, [projectId, load])

  const handleToggle = async (rule: RoutingRule) => {
    await api.put(`/api/routing-rules/${rule.id}`, { enabled: !rule.enabled })
    load()
  }

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this routing rule?')) return
    await api.delete(`/api/routing-rules/${id}`)
    load()
  }

  const handleEdit = (rule: RoutingRule) => {
    setEditingRule(rule)
    setShowForm(true)
  }

  const handleFormClose = () => {
    setShowForm(false)
    setEditingRule(null)
    load()
  }

  if (loading) {
    return <div className="p-4 text-sm text-muted-foreground animate-pulse">Loading rules...</div>
  }

  return (
    <div className="p-4 space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold">Routing Rules</h3>
        <button
          onClick={() => { setEditingRule(null); setShowForm(true) }}
          className="flex items-center gap-1 text-xs px-3 py-1.5 bg-primary text-primary-foreground rounded hover:bg-primary/90"
        >
          <Plus size={12} />
          New Rule
        </button>
      </div>

      {showForm && (
        <RuleForm
          projectId={projectId}
          rule={editingRule}
          agents={agents}
          onClose={handleFormClose}
        />
      )}

      {rules.length === 0 ? (
        <div className="text-sm text-muted-foreground text-center py-8">
          No routing rules configured. Tasks will use default best-fit routing.
        </div>
      ) : (
        <div className="space-y-2">
          {rules.map((rule) => (
            <div
              key={rule.id}
              className={`border rounded-lg p-3 ${rule.enabled ? 'border-border' : 'border-border/50 opacity-60'}`}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium">{rule.name}</span>
                  {strategyBadge(rule.target_strategy)}
                  <span className="text-xs text-muted-foreground">
                    Priority: {rule.priority}
                  </span>
                </div>
                <div className="flex items-center gap-1">
                  <button
                    onClick={() => handleToggle(rule)}
                    className="p-1 text-muted-foreground hover:text-foreground"
                    title={rule.enabled ? 'Disable' : 'Enable'}
                  >
                    {rule.enabled ? <ToggleRight size={16} className="text-green-500" /> : <ToggleLeft size={16} />}
                  </button>
                  <button
                    onClick={() => handleEdit(rule)}
                    className="p-1 text-muted-foreground hover:text-foreground"
                  >
                    <Pencil size={14} />
                  </button>
                  <button
                    onClick={() => handleDelete(rule.id)}
                    className="p-1 text-muted-foreground hover:text-destructive"
                  >
                    <Trash2 size={14} />
                  </button>
                </div>
              </div>
              {rule.description && (
                <p className="text-xs text-muted-foreground mt-1">{rule.description}</p>
              )}
              <div className="flex flex-wrap gap-1 mt-2">
                {rule.conditions.task_type && (
                  <span className="text-xs px-1.5 py-0.5 bg-muted rounded">
                    type: {rule.conditions.task_type}
                  </span>
                )}
                {rule.conditions.priority?.map((p) => (
                  <span key={p} className="text-xs px-1.5 py-0.5 bg-muted rounded">
                    priority: {p}
                  </span>
                ))}
                {rule.conditions.tags?.map((t) => (
                  <span key={t} className="text-xs px-1.5 py-0.5 bg-muted rounded">
                    tag: {t}
                  </span>
                ))}
                {rule.sla_config?.max_wait_seconds && (
                  <span className="text-xs px-1.5 py-0.5 bg-yellow-500/10 text-yellow-600 rounded">
                    SLA: {rule.sla_config.max_wait_seconds}s max wait
                  </span>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Rule Form                                                          */
/* ------------------------------------------------------------------ */

function RuleForm({
  projectId,
  rule,
  agents,
  onClose,
}: {
  projectId: number
  rule: RoutingRule | null
  agents: ProjectAgent[]
  onClose: () => void
}) {
  const [name, setName] = useState(rule?.name ?? '')
  const [description, setDescription] = useState(rule?.description ?? '')
  const [strategy, setStrategy] = useState(rule?.target_strategy ?? 'best_fit')
  const [taskType, setTaskType] = useState(rule?.conditions?.task_type ?? '')
  const [priorities, setPriorities] = useState<string[]>(rule?.conditions?.priority ?? [])
  const [tags, setTags] = useState(rule?.conditions?.tags?.join(', ') ?? '')
  const [targetAgents, setTargetAgents] = useState<number[]>(rule?.target_agents ?? [])
  const [maxWaitSeconds, setMaxWaitSeconds] = useState(
    rule?.sla_config?.max_wait_seconds?.toString() ?? '',
  )
  const [maxCost, setMaxCost] = useState(rule?.sla_config?.max_cost?.toString() ?? '')
  const [priority, setPriority] = useState(rule?.priority?.toString() ?? '0')
  const [saving, setSaving] = useState(false)

  const handleSave = async () => {
    if (!name.trim()) return
    setSaving(true)

    const conditions: Record<string, unknown> = {}
    if (taskType) conditions.task_type = taskType
    if (priorities.length > 0) conditions.priority = priorities
    if (tags.trim()) conditions.tags = tags.split(',').map((t) => t.trim()).filter(Boolean)

    const slaConfig: Record<string, unknown> = {}
    if (maxWaitSeconds) slaConfig.max_wait_seconds = parseInt(maxWaitSeconds)
    if (maxCost) slaConfig.max_cost = parseInt(maxCost)

    const payload = {
      name,
      description: description || null,
      conditions,
      target_strategy: strategy,
      target_agents: targetAgents.length > 0 ? targetAgents : null,
      sla_config: Object.keys(slaConfig).length > 0 ? slaConfig : null,
      priority: parseInt(priority) || 0,
      enabled: true,
    }

    try {
      if (rule) {
        await api.put(`/api/routing-rules/${rule.id}`, payload)
      } else {
        await api.post(`/api/projects/${projectId}/routing-rules`, payload)
      }
      onClose()
    } finally {
      setSaving(false)
    }
  }

  const togglePriority = (p: string) => {
    setPriorities((prev) =>
      prev.includes(p) ? prev.filter((x) => x !== p) : [...prev, p],
    )
  }

  const toggleAgent = (id: number) => {
    setTargetAgents((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
    )
  }

  return (
    <div className="border border-border rounded-lg p-4 bg-muted/20 space-y-3">
      <h4 className="text-sm font-semibold">{rule ? 'Edit Rule' : 'New Routing Rule'}</h4>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <label className="text-xs text-muted-foreground">Name</label>
          <input
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
            placeholder="e.g. Security tasks to security agent"
          />
        </div>
        <div>
          <label className="text-xs text-muted-foreground">Strategy</label>
          <select
            value={strategy}
            onChange={(e) => setStrategy(e.target.value)}
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
          >
            {STRATEGIES.map((s) => (
              <option key={s.value} value={s.value}>{s.label}</option>
            ))}
          </select>
        </div>
      </div>

      <div>
        <label className="text-xs text-muted-foreground">Description</label>
        <input
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
          placeholder="Optional description"
        />
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <label className="text-xs text-muted-foreground">Task Type</label>
          <select
            value={taskType}
            onChange={(e) => setTaskType(e.target.value)}
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
          >
            <option value="">Any</option>
            {TASK_TYPES.map((t) => (
              <option key={t} value={t}>{t.replace('_', ' ')}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="text-xs text-muted-foreground">Tags (comma-separated)</label>
          <input
            value={tags}
            onChange={(e) => setTags(e.target.value)}
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
            placeholder="security, auth"
          />
        </div>
      </div>

      <div>
        <label className="text-xs text-muted-foreground mb-1 block">Priority Filter</label>
        <div className="flex gap-2">
          {['low', 'medium', 'high', 'critical'].map((p) => (
            <button
              key={p}
              onClick={() => togglePriority(p)}
              className={`text-xs px-2 py-1 rounded border ${
                priorities.includes(p)
                  ? 'bg-primary text-primary-foreground border-primary'
                  : 'border-input bg-background text-muted-foreground'
              }`}
            >
              {p}
            </button>
          ))}
        </div>
      </div>

      {agents.length > 0 && (
        <div>
          <label className="text-xs text-muted-foreground mb-1 block">
            Target Agents (leave empty for all)
          </label>
          <div className="flex flex-wrap gap-1">
            {agents.map((a) => (
              <button
                key={a.id}
                onClick={() => toggleAgent(a.id)}
                className={`text-xs px-2 py-1 rounded border ${
                  targetAgents.includes(a.id)
                    ? 'bg-primary text-primary-foreground border-primary'
                    : 'border-input bg-background text-muted-foreground'
                }`}
              >
                {a.name}
              </button>
            ))}
          </div>
        </div>
      )}

      <div className="grid grid-cols-3 gap-3">
        <div>
          <label className="text-xs text-muted-foreground">Priority</label>
          <input
            type="number"
            value={priority}
            onChange={(e) => setPriority(e.target.value)}
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
            min={0}
          />
        </div>
        <div>
          <label className="text-xs text-muted-foreground">SLA Max Wait (sec)</label>
          <input
            type="number"
            value={maxWaitSeconds}
            onChange={(e) => setMaxWaitSeconds(e.target.value)}
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
            placeholder="300"
          />
        </div>
        <div>
          <label className="text-xs text-muted-foreground">SLA Max Cost (microcents)</label>
          <input
            type="number"
            value={maxCost}
            onChange={(e) => setMaxCost(e.target.value)}
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
            placeholder="10000"
          />
        </div>
      </div>

      <div className="flex gap-2 pt-1">
        <button
          onClick={handleSave}
          disabled={saving || !name.trim()}
          className="text-xs px-4 py-1.5 bg-primary text-primary-foreground rounded hover:bg-primary/90 disabled:opacity-50"
        >
          {saving ? 'Saving...' : rule ? 'Update' : 'Create'}
        </button>
        <button onClick={onClose} className="text-xs px-4 py-1.5 text-muted-foreground hover:text-foreground">
          Cancel
        </button>
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Capabilities Section                                               */
/* ------------------------------------------------------------------ */

function CapabilitiesSection({ projectId }: { projectId: number }) {
  const [capabilities, setCapabilities] = useState<AgentCapabilityRow[]>([])
  const [loading, setLoading] = useState(true)
  const [inferring, setInferring] = useState<number | null>(null)
  const [agents, setAgents] = useState<ProjectAgent[]>([])

  const load = useCallback(() => {
    setLoading(true)
    api
      .get(`/api/projects/${projectId}/capabilities`)
      .then((r) => setCapabilities(r.data.data))
      .finally(() => setLoading(false))
  }, [projectId])

  useEffect(() => {
    load()
    api.get(`/api/projects/${projectId}/agents`).then((r) => {
      const list = r.data.data ?? r.data
      setAgents(Array.isArray(list) ? list : [])
    })
  }, [projectId, load])

  const handleInfer = async (agentId: number) => {
    setInferring(agentId)
    try {
      await api.post(`/api/agents/${agentId}/infer-capabilities`, { project_id: projectId })
      load()
    } finally {
      setInferring(null)
    }
  }

  if (loading) {
    return <div className="p-4 text-sm text-muted-foreground animate-pulse">Loading capabilities...</div>
  }

  // Group by agent
  const grouped = capabilities.reduce<Record<number, AgentCapabilityRow[]>>((acc, cap) => {
    if (!acc[cap.agent_id]) acc[cap.agent_id] = []
    acc[cap.agent_id].push(cap)
    return acc
  }, {})

  return (
    <div className="p-4 space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold">Agent Capabilities</h3>
        <div className="flex gap-2">
          {agents.map((a) => (
            <button
              key={a.id}
              onClick={() => handleInfer(a.id)}
              disabled={inferring === a.id}
              className="text-xs px-2 py-1 border border-input rounded hover:bg-muted disabled:opacity-50"
              title={`Infer capabilities for ${a.name}`}
            >
              <Brain size={12} className="inline mr-1" />
              {inferring === a.id ? 'Inferring...' : `Infer ${a.name}`}
            </button>
          ))}
        </div>
      </div>

      {capabilities.length === 0 ? (
        <div className="text-sm text-muted-foreground text-center py-8">
          No capabilities tracked yet. Use the Infer button to bootstrap capabilities from agent configuration.
        </div>
      ) : (
        <div className="border border-border rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr>
                <th className="text-left px-3 py-2 text-xs font-medium text-muted-foreground">Agent</th>
                <th className="text-left px-3 py-2 text-xs font-medium text-muted-foreground">Capability</th>
                <th className="text-left px-3 py-2 text-xs font-medium text-muted-foreground">Proficiency</th>
                <th className="text-left px-3 py-2 text-xs font-medium text-muted-foreground">Success Rate</th>
                <th className="text-right px-3 py-2 text-xs font-medium text-muted-foreground">Avg Duration</th>
                <th className="text-right px-3 py-2 text-xs font-medium text-muted-foreground">Tasks</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {Object.entries(grouped).map(([agentId, caps]) =>
                caps.map((cap, idx) => (
                  <tr key={cap.id} className="hover:bg-muted/20">
                    <td className="px-3 py-2">
                      {idx === 0 ? (
                        <span className="font-medium">{cap.agent_name}</span>
                      ) : null}
                    </td>
                    <td className="px-3 py-2">
                      <span className="text-xs px-1.5 py-0.5 bg-muted rounded">
                        {cap.capability.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-3 py-2">{proficiencyBar(cap.proficiency)}</td>
                    <td className="px-3 py-2">{proficiencyBar(cap.success_rate)}</td>
                    <td className="px-3 py-2 text-right text-xs text-muted-foreground">
                      {formatDuration(cap.avg_duration_ms)}
                    </td>
                    <td className="px-3 py-2 text-right text-xs text-muted-foreground">
                      {cap.task_count}
                    </td>
                  </tr>
                )),
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Simulator Section                                                  */
/* ------------------------------------------------------------------ */

function SimulatorSection({ projectId }: { projectId: number }) {
  const [description, setDescription] = useState('')
  const [taskType, setTaskType] = useState('general')
  const [priority, setPriority] = useState('medium')
  const [result, setResult] = useState<SimulationResult | null>(null)
  const [simulating, setSimulating] = useState(false)

  const handleSimulate = async () => {
    if (!description.trim()) return
    setSimulating(true)
    try {
      const r = await api.post(`/api/projects/${projectId}/routing/simulate`, {
        description,
        task_type: taskType,
        priority,
      })
      setResult(r.data.data)
    } finally {
      setSimulating(false)
    }
  }

  return (
    <div className="p-4 space-y-4">
      <h3 className="text-sm font-semibold">Routing Simulator</h3>
      <p className="text-xs text-muted-foreground">
        Test which agent would be selected for a hypothetical task without creating any records.
      </p>

      <div className="space-y-3">
        <div>
          <label className="text-xs text-muted-foreground">Task Description</label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={3}
            className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5 resize-none"
            placeholder="Describe the task to simulate routing for..."
          />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="text-xs text-muted-foreground">Task Type</label>
            <select
              value={taskType}
              onChange={(e) => setTaskType(e.target.value)}
              className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
            >
              {TASK_TYPES.map((t) => (
                <option key={t} value={t}>{t.replace('_', ' ')}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="text-xs text-muted-foreground">Priority</label>
            <select
              value={priority}
              onChange={(e) => setPriority(e.target.value)}
              className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded mt-0.5"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>
        </div>

        <button
          onClick={handleSimulate}
          disabled={simulating || !description.trim()}
          className="flex items-center gap-1.5 text-xs px-4 py-1.5 bg-primary text-primary-foreground rounded hover:bg-primary/90 disabled:opacity-50"
        >
          <Play size={12} />
          {simulating ? 'Simulating...' : 'Simulate'}
        </button>
      </div>

      {result && (
        <div className="space-y-3 mt-4">
          {/* Selected Agent */}
          <div className="border border-border rounded-lg p-3">
            <div className="text-xs text-muted-foreground uppercase font-medium mb-2">Result</div>
            {result.selected_agent ? (
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                  <Target size={18} className="text-primary" />
                </div>
                <div>
                  <div className="text-sm font-medium">{result.selected_agent.agent_name}</div>
                  <div className="text-xs text-muted-foreground">
                    Score: {(result.selected_agent.score * 100).toFixed(1)}% | Strategy: {result.strategy}
                    {result.rule && ` | Rule: ${result.rule.name}`}
                  </div>
                </div>
              </div>
            ) : (
              <div className="text-sm text-muted-foreground">No suitable agent found.</div>
            )}
            <p className="text-xs text-muted-foreground mt-2">{result.reasoning}</p>
          </div>

          {/* Candidate Scores */}
          {result.candidates.length > 0 && (
            <div className="border border-border rounded-lg overflow-hidden">
              <div className="text-xs text-muted-foreground uppercase font-medium px-3 py-2 bg-muted/50">
                Candidate Scoring ({result.candidates.length} agents)
              </div>
              <table className="w-full text-xs">
                <thead className="bg-muted/30">
                  <tr>
                    <th className="text-left px-3 py-1.5 font-medium text-muted-foreground">Agent</th>
                    <th className="text-right px-3 py-1.5 font-medium text-muted-foreground">Score</th>
                    <th className="text-right px-3 py-1.5 font-medium text-muted-foreground">Proficiency</th>
                    <th className="text-right px-3 py-1.5 font-medium text-muted-foreground">Success</th>
                    <th className="text-right px-3 py-1.5 font-medium text-muted-foreground">Load</th>
                    <th className="text-center px-3 py-1.5 font-medium text-muted-foreground">Available</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {[...result.candidates]
                    .sort((a, b) => b.score - a.score)
                    .map((c) => (
                      <tr
                        key={c.agent_id}
                        className={`${
                          result.selected_agent?.agent_id === c.agent_id
                            ? 'bg-primary/5'
                            : 'hover:bg-muted/20'
                        }`}
                      >
                        <td className="px-3 py-1.5 font-medium">
                          {result.selected_agent?.agent_id === c.agent_id && (
                            <CheckCircle2 size={12} className="inline text-green-500 mr-1" />
                          )}
                          {c.agent_name}
                        </td>
                        <td className="px-3 py-1.5 text-right font-mono">
                          {(c.score * 100).toFixed(1)}%
                        </td>
                        <td className="px-3 py-1.5 text-right">{(c.proficiency * 100).toFixed(0)}%</td>
                        <td className="px-3 py-1.5 text-right">{(c.success_rate * 100).toFixed(0)}%</td>
                        <td className="px-3 py-1.5 text-right">{(c.load_factor * 100).toFixed(0)}%</td>
                        <td className="px-3 py-1.5 text-center">
                          {c.available ? (
                            <CheckCircle2 size={12} className="inline text-green-500" />
                          ) : (
                            <XCircle size={12} className="inline text-red-500" />
                          )}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Decisions Section                                                  */
/* ------------------------------------------------------------------ */

function DecisionsSection({ projectId }: { projectId: number }) {
  const [decisions, setDecisions] = useState<RoutingDecisionRow[]>([])
  const [loading, setLoading] = useState(true)
  const [expandedId, setExpandedId] = useState<number | null>(null)

  useEffect(() => {
    api
      .get(`/api/projects/${projectId}/routing-decisions`, { params: { per_page: 20 } })
      .then((r) => setDecisions(r.data.data))
      .finally(() => setLoading(false))
  }, [projectId])

  if (loading) {
    return <div className="p-4 text-sm text-muted-foreground animate-pulse">Loading decisions...</div>
  }

  return (
    <div className="p-4 space-y-4">
      <h3 className="text-sm font-semibold">Recent Routing Decisions</h3>

      {decisions.length === 0 ? (
        <div className="text-sm text-muted-foreground text-center py-8">
          No routing decisions recorded yet.
        </div>
      ) : (
        <div className="space-y-1">
          {decisions.map((d) => (
            <div key={d.id} className="border border-border rounded-lg overflow-hidden">
              <button
                onClick={() => setExpandedId(expandedId === d.id ? null : d.id)}
                className="w-full text-left px-3 py-2 hover:bg-muted/20 flex items-center justify-between"
              >
                <div className="flex items-center gap-2 min-w-0">
                  {expandedId === d.id ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                  <span className="text-sm font-medium truncate">
                    {d.task_title ?? `Task #${d.task_id}`}
                  </span>
                  <span className="text-xs text-muted-foreground shrink-0">
                    {d.selected_agent_name ?? 'No agent'}
                  </span>
                  {strategyBadge(d.strategy_used)}
                  {d.sla_met ? (
                    <span className="text-xs px-1.5 py-0.5 bg-green-500/10 text-green-600 rounded shrink-0">
                      SLA Met
                    </span>
                  ) : (
                    <span className="text-xs px-1.5 py-0.5 bg-red-500/10 text-red-600 rounded shrink-0">
                      SLA Breached
                    </span>
                  )}
                </div>
                <span className="text-xs text-muted-foreground shrink-0 ml-2">
                  {new Date(d.created_at).toLocaleString()}
                </span>
              </button>

              {expandedId === d.id && (
                <div className="px-3 pb-3 border-t border-border bg-muted/10">
                  {d.reasoning && (
                    <p className="text-xs text-muted-foreground mt-2 mb-2">{d.reasoning}</p>
                  )}

                  {d.candidates && d.candidates.length > 0 && (
                    <table className="w-full text-xs mt-1">
                      <thead>
                        <tr className="text-muted-foreground">
                          <th className="text-left py-1 font-medium">Agent</th>
                          <th className="text-right py-1 font-medium">Score</th>
                          <th className="text-right py-1 font-medium">Proficiency</th>
                          <th className="text-right py-1 font-medium">Load</th>
                          <th className="text-center py-1 font-medium">Available</th>
                        </tr>
                      </thead>
                      <tbody>
                        {[...d.candidates]
                          .sort((a, b) => b.score - a.score)
                          .map((c) => (
                            <tr
                              key={c.agent_id}
                              className={
                                d.selected_agent_id === c.agent_id
                                  ? 'font-medium text-primary'
                                  : 'text-muted-foreground'
                              }
                            >
                              <td className="py-1">
                                {d.selected_agent_id === c.agent_id && (
                                  <CheckCircle2 size={10} className="inline mr-1 text-green-500" />
                                )}
                                {c.agent_name}
                              </td>
                              <td className="py-1 text-right font-mono">
                                {(c.score * 100).toFixed(1)}%
                              </td>
                              <td className="py-1 text-right">{(c.proficiency * 100).toFixed(0)}%</td>
                              <td className="py-1 text-right">{(c.load_factor * 100).toFixed(0)}%</td>
                              <td className="py-1 text-center">
                                {c.available ? (
                                  <CheckCircle2 size={10} className="inline text-green-500" />
                                ) : (
                                  <XCircle size={10} className="inline text-red-500" />
                                )}
                              </td>
                            </tr>
                          ))}
                      </tbody>
                    </table>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
