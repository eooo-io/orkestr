import { useEffect, useState } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import {
  ArrowLeft,
  Save,
  Loader2,
  ChevronDown,
  ChevronRight,
  Copy,
  Trash2,
  Download,
  Brain,
  Plus,
  X,
  ArrowUp,
  ArrowDown,
  Shield,
  DollarSign,
  Lock,
  Eye,
} from 'lucide-react'
import { fetchAgent, createAgent, updateAgent, deleteAgent, exportAgent, fetchModels } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import type { Agent, ModelGroup } from '@/types'

const EMPTY_AGENT: Partial<Agent> = {
  name: '',
  role: '',
  description: '',
  base_instructions: '',
  persona_prompt: null,
  model: 'claude-sonnet-4-6',
  icon: 'brain',
  objective_template: null,
  success_criteria: null,
  max_iterations: null,
  timeout_seconds: null,
  input_schema: null,
  memory_sources: null,
  context_strategy: 'full',
  planning_mode: 'none',
  temperature: null,
  system_prompt: null,
  eval_criteria: null,
  output_schema: null,
  loop_condition: 'goal_met',
  parent_agent_id: null,
  delegation_rules: null,
  can_delegate: false,
  custom_tools: null,
  is_template: false,
  autonomy_level: 'semi_autonomous',
  budget_limit_usd: null,
  daily_budget_limit_usd: null,
  allowed_tools: null,
  blocked_tools: null,
  data_access_scope: null,
}

const ROUTING_STRATEGIES = [
  { value: 'default', label: 'Default', desc: 'Use configured model order as-is' },
  { value: 'cost_optimized', label: 'Cost Optimized', desc: 'Cheapest viable model first' },
  { value: 'performance', label: 'Performance', desc: 'Most capable model first' },
]

const CONTEXT_STRATEGIES = [
  { value: 'full', label: 'Full Context', desc: 'Include all available context' },
  { value: 'summary', label: 'Summary', desc: 'Summarize prior context' },
  { value: 'sliding_window', label: 'Sliding Window', desc: 'Recent context only' },
  { value: 'rag', label: 'RAG', desc: 'Retrieval-augmented generation' },
]

const PLANNING_MODES = [
  { value: 'none', label: 'None', desc: 'No planning, direct execution' },
  { value: 'act', label: 'Act', desc: 'Execute actions directly' },
  { value: 'plan_then_act', label: 'Plan then Act', desc: 'Plan first, then execute' },
  { value: 'react', label: 'ReAct', desc: 'Reason + Act loop' },
]

const LOOP_CONDITIONS = [
  { value: 'goal_met', label: 'Goal Met', desc: 'Stop when success criteria are satisfied' },
  { value: 'max_iterations', label: 'Max Iterations', desc: 'Stop after N iterations' },
  { value: 'timeout', label: 'Timeout', desc: 'Stop after timeout' },
  { value: 'manual', label: 'Manual', desc: 'Human decides when to stop' },
]

const AUTONOMY_LEVELS = [
  { value: 'supervised', label: 'Supervised', desc: 'Every tool call requires human approval', icon: Eye },
  { value: 'semi_autonomous', label: 'Semi-Autonomous', desc: 'Sensitive operations require approval', icon: Shield },
  { value: 'autonomous', label: 'Autonomous', desc: 'All operations execute automatically', icon: Lock },
]

const ICONS = [
  'brain', 'clipboard-list', 'boxes', 'shield-check', 'palette',
  'git-pull-request', 'container', 'rocket', 'lock', 'code',
  'search', 'zap', 'globe', 'database', 'terminal',
]

export function AgentBuilder() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { showToast } = useAppStore()
  const isNew = !id

  const [agent, setAgent] = useState<Partial<Agent>>(EMPTY_AGENT)
  const [loading, setLoading] = useState(!isNew)
  const [saving, setSaving] = useState(false)
  const [modelGroups, setModelGroups] = useState<ModelGroup[]>([])
  const [openSections, setOpenSections] = useState<Set<string>>(
    new Set(['identity', 'goal', 'reasoning']),
  )

  useEffect(() => {
    if (id) {
      setLoading(true)
      fetchAgent(parseInt(id))
        .then((data) => setAgent(data))
        .catch(() => showToast('Failed to load agent', 'error'))
        .finally(() => setLoading(false))
    }
  }, [id])

  useEffect(() => {
    fetchModels()
      .then(setModelGroups)
      .catch(() => {})
  }, [])

  const toggleSection = (section: string) => {
    setOpenSections((prev) => {
      const next = new Set(prev)
      if (next.has(section)) next.delete(section)
      else next.add(section)
      return next
    })
  }

  const update = (field: string, value: unknown) => {
    setAgent((prev) => ({ ...prev, [field]: value }))
  }

  const handleSave = async () => {
    if (!agent.name?.trim()) {
      showToast('Name is required', 'error')
      return
    }
    if (!agent.role?.trim()) {
      showToast('Role is required', 'error')
      return
    }

    setSaving(true)
    try {
      if (isNew) {
        const created = await createAgent(agent)
        showToast('Agent created', 'success')
        navigate(`/agents/${created.id}`, { replace: true })
      } else {
        await updateAgent(parseInt(id!), agent)
        showToast('Agent saved', 'success')
      }
    } catch {
      showToast('Failed to save agent', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!id) return
    if (!confirm('Delete this agent? This cannot be undone.')) return
    try {
      await deleteAgent(parseInt(id))
      showToast('Agent deleted', 'success')
      navigate('/agents')
    } catch {
      showToast('Failed to delete agent', 'error')
    }
  }

  const handleExport = async (format: 'json' | 'yaml') => {
    if (!id) return
    try {
      const result = await exportAgent(parseInt(id), format)
      const content = format === 'yaml' ? result.content : JSON.stringify(result.content, null, 2)
      navigator.clipboard.writeText(content)
      showToast(`${format.toUpperCase()} copied to clipboard`, 'success')
    } catch {
      showToast('Failed to export agent', 'error')
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Link
            to="/agents"
            className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Agents
          </Link>
          <span className="text-muted-foreground">/</span>
          <span className="text-sm font-medium">{isNew ? 'New Agent' : agent.name}</span>
        </div>
        <div className="flex items-center gap-2">
          {!isNew && (
            <>
              <button
                onClick={() => handleExport('json')}
                className="flex items-center gap-1.5 px-3 py-1.5 text-xs text-muted-foreground hover:text-foreground border border-border hover:bg-muted transition-colors"
                title="Export as JSON"
              >
                <Download className="h-3 w-3" />
                JSON
              </button>
              <button
                onClick={() => handleExport('yaml')}
                className="flex items-center gap-1.5 px-3 py-1.5 text-xs text-muted-foreground hover:text-foreground border border-border hover:bg-muted transition-colors"
                title="Export as YAML"
              >
                <Download className="h-3 w-3" />
                YAML
              </button>
              <button
                onClick={handleDelete}
                className="flex items-center gap-1.5 px-3 py-1.5 text-xs text-red-400 hover:text-red-300 border border-red-500/30 hover:bg-red-500/10 transition-colors"
              >
                <Trash2 className="h-3 w-3" />
                Delete
              </button>
            </>
          )}
          <button
            onClick={handleSave}
            disabled={saving}
            className="flex items-center gap-1.5 px-4 py-1.5 text-xs bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
          >
            {saving ? <Loader2 className="h-3 w-3 animate-spin" /> : <Save className="h-3 w-3" />}
            {isNew ? 'Create' : 'Save'}
          </button>
        </div>
      </div>

      {/* Sections */}
      <div className="space-y-3">
        {/* Identity */}
        <Section title="Identity" id="identity" open={openSections.has('identity')} onToggle={toggleSection}>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Name" required>
              <input
                type="text"
                value={agent.name || ''}
                onChange={(e) => update('name', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                placeholder="e.g. Code Review Agent"
              />
            </Field>
            <Field label="Role" required>
              <input
                type="text"
                value={agent.role || ''}
                onChange={(e) => update('role', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                placeholder="e.g. code-reviewer"
              />
            </Field>
          </div>
          <Field label="Description">
            <textarea
              value={agent.description || ''}
              onChange={(e) => update('description', e.target.value)}
              rows={2}
              className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary resize-none"
              placeholder="Brief description of what this agent does"
            />
          </Field>
          <div className="grid grid-cols-3 gap-4">
            <Field label="Model">
              <input
                type="text"
                value={agent.model || ''}
                onChange={(e) => update('model', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                placeholder="claude-sonnet-4-6"
              />
            </Field>
            <Field label="Icon">
              <select
                value={agent.icon || 'brain'}
                onChange={(e) => update('icon', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
              >
                {ICONS.map((icon) => (
                  <option key={icon} value={icon}>{icon}</option>
                ))}
              </select>
            </Field>
            <Field label="Sort Order">
              <input
                type="number"
                value={agent.sort_order ?? 0}
                onChange={(e) => update('sort_order', parseInt(e.target.value) || 0)}
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
              />
            </Field>
          </div>
          <FallbackModelsEditor
            primaryModel={agent.model || ''}
            fallbackModels={agent.fallback_models || []}
            modelGroups={modelGroups}
            onChange={(models) => update('fallback_models', models.length > 0 ? models : null)}
          />
          <Field label="Routing Strategy">
            <div className="grid grid-cols-3 gap-2">
              {ROUTING_STRATEGIES.map((rs) => (
                <label
                  key={rs.value}
                  className={`flex items-start gap-2 p-3 border cursor-pointer transition-colors ${
                    (agent.routing_strategy || 'default') === rs.value
                      ? 'border-primary bg-primary/5'
                      : 'border-border hover:border-muted-foreground/30'
                  }`}
                >
                  <input
                    type="radio"
                    name="routing_strategy"
                    value={rs.value}
                    checked={(agent.routing_strategy || 'default') === rs.value}
                    onChange={() => update('routing_strategy', rs.value)}
                    className="mt-0.5"
                  />
                  <div>
                    <span className="text-sm font-medium">{rs.label}</span>
                    <p className="text-xs text-muted-foreground">{rs.desc}</p>
                  </div>
                </label>
              ))}
            </div>
          </Field>
          <Field label="Base Instructions">
            <textarea
              value={agent.base_instructions || ''}
              onChange={(e) => update('base_instructions', e.target.value)}
              rows={8}
              className="w-full px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder="Core instructions for this agent (Markdown supported)"
            />
          </Field>
          <Field label="Persona Prompt">
            <textarea
              value={agent.persona_prompt || ''}
              onChange={(e) => update('persona_prompt', e.target.value || null)}
              rows={3}
              className="w-full px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder="Optional persona layer on top of base instructions"
            />
          </Field>
          <div className="flex items-center gap-4">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={agent.is_template ?? false}
                onChange={(e) => update('is_template', e.target.checked)}
                className="rounded"
              />
              Template agent
            </label>
          </div>
        </Section>

        {/* Goal */}
        <Section title="Goal" id="goal" open={openSections.has('goal')} onToggle={toggleSection}>
          <Field label="Objective Template">
            <textarea
              value={agent.objective_template || ''}
              onChange={(e) => update('objective_template', e.target.value || null)}
              rows={3}
              className="w-full px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder="What this agent is trying to achieve"
            />
          </Field>
          <Field label="Success Criteria" hint="One per line">
            <textarea
              value={(agent.success_criteria || []).join('\n')}
              onChange={(e) =>
                update('success_criteria', e.target.value ? e.target.value.split('\n').filter(Boolean) : null)
              }
              rows={3}
              className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder="e.g. all_tests_passing&#10;code_reviewed&#10;no_security_issues"
            />
          </Field>
          <div className="grid grid-cols-3 gap-4">
            <Field label="Max Iterations">
              <input
                type="number"
                value={agent.max_iterations ?? ''}
                onChange={(e) => update('max_iterations', e.target.value ? parseInt(e.target.value) : null)}
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                placeholder="e.g. 10"
                min={1}
                max={1000}
              />
            </Field>
            <Field label="Timeout (seconds)">
              <input
                type="number"
                value={agent.timeout_seconds ?? ''}
                onChange={(e) => update('timeout_seconds', e.target.value ? parseInt(e.target.value) : null)}
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                placeholder="e.g. 300"
                min={1}
                max={3600}
              />
            </Field>
            <Field label="Loop Condition">
              <select
                value={agent.loop_condition || 'goal_met'}
                onChange={(e) => update('loop_condition', e.target.value)}
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
              >
                {LOOP_CONDITIONS.map((lc) => (
                  <option key={lc.value} value={lc.value}>{lc.label}</option>
                ))}
              </select>
            </Field>
          </div>
        </Section>

        {/* Perception */}
        <Section title="Perception" id="perception" open={openSections.has('perception')} onToggle={toggleSection}>
          <Field label="Context Strategy">
            <div className="grid grid-cols-2 gap-2">
              {CONTEXT_STRATEGIES.map((cs) => (
                <label
                  key={cs.value}
                  className={`flex items-start gap-2 p-3 border cursor-pointer transition-colors ${
                    agent.context_strategy === cs.value
                      ? 'border-primary bg-primary/5'
                      : 'border-border hover:border-muted-foreground/30'
                  }`}
                >
                  <input
                    type="radio"
                    name="context_strategy"
                    value={cs.value}
                    checked={agent.context_strategy === cs.value}
                    onChange={() => update('context_strategy', cs.value)}
                    className="mt-0.5"
                  />
                  <div>
                    <span className="text-sm font-medium">{cs.label}</span>
                    <p className="text-xs text-muted-foreground">{cs.desc}</p>
                  </div>
                </label>
              ))}
            </div>
          </Field>
          <Field label="Memory Sources" hint="One per line">
            <textarea
              value={(agent.memory_sources || []).join('\n')}
              onChange={(e) =>
                update('memory_sources', e.target.value ? e.target.value.split('\n').filter(Boolean) : null)
              }
              rows={3}
              className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder="e.g. conversation_history&#10;project_files&#10;external_docs"
            />
          </Field>
          <Field label="Input Schema (JSON)">
            <textarea
              value={agent.input_schema ? JSON.stringify(agent.input_schema, null, 2) : ''}
              onChange={(e) => {
                try {
                  update('input_schema', e.target.value ? JSON.parse(e.target.value) : null)
                } catch {
                  // Allow typing invalid JSON while editing
                }
              }}
              rows={4}
              className="w-full px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder='{"type": "object", "properties": {...}}'
            />
          </Field>
        </Section>

        {/* Reasoning */}
        <Section title="Reasoning" id="reasoning" open={openSections.has('reasoning')} onToggle={toggleSection}>
          <Field label="Planning Mode">
            <div className="grid grid-cols-2 gap-2">
              {PLANNING_MODES.map((pm) => (
                <label
                  key={pm.value}
                  className={`flex items-start gap-2 p-3 border cursor-pointer transition-colors ${
                    agent.planning_mode === pm.value
                      ? 'border-primary bg-primary/5'
                      : 'border-border hover:border-muted-foreground/30'
                  }`}
                >
                  <input
                    type="radio"
                    name="planning_mode"
                    value={pm.value}
                    checked={agent.planning_mode === pm.value}
                    onChange={() => update('planning_mode', pm.value)}
                    className="mt-0.5"
                  />
                  <div>
                    <span className="text-sm font-medium">{pm.label}</span>
                    <p className="text-xs text-muted-foreground">{pm.desc}</p>
                  </div>
                </label>
              ))}
            </div>
          </Field>
          <div className="grid grid-cols-2 gap-4">
            <Field label="Temperature">
              <input
                type="number"
                value={agent.temperature ?? ''}
                onChange={(e) => update('temperature', e.target.value ? parseFloat(e.target.value) : null)}
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                placeholder="0.0 - 2.0"
                min={0}
                max={2}
                step={0.1}
              />
            </Field>
          </div>
          <Field label="System Prompt">
            <textarea
              value={agent.system_prompt || ''}
              onChange={(e) => update('system_prompt', e.target.value || null)}
              rows={4}
              className="w-full px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder="Optional explicit system prompt (overrides base_instructions + persona_prompt)"
            />
          </Field>
        </Section>

        {/* Observation */}
        <Section title="Observation" id="observation" open={openSections.has('observation')} onToggle={toggleSection}>
          <Field label="Evaluation Criteria" hint="One per line">
            <textarea
              value={(agent.eval_criteria || []).join('\n')}
              onChange={(e) =>
                update('eval_criteria', e.target.value ? e.target.value.split('\n').filter(Boolean) : null)
              }
              rows={3}
              className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder="e.g. output_matches_schema&#10;no_hallucinations&#10;follows_conventions"
            />
          </Field>
          <Field label="Output Schema (JSON)">
            <textarea
              value={agent.output_schema ? JSON.stringify(agent.output_schema, null, 2) : ''}
              onChange={(e) => {
                try {
                  update('output_schema', e.target.value ? JSON.parse(e.target.value) : null)
                } catch {
                  // Allow typing invalid JSON while editing
                }
              }}
              rows={4}
              className="w-full px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder='{"type": "object", "properties": {...}}'
            />
          </Field>
        </Section>

        {/* Orchestration */}
        <Section title="Orchestration" id="orchestration" open={openSections.has('orchestration')} onToggle={toggleSection}>
          <div className="flex items-center gap-4">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={agent.can_delegate ?? false}
                onChange={(e) => update('can_delegate', e.target.checked)}
                className="rounded"
              />
              Can delegate to other agents
            </label>
          </div>
          <Field label="Delegation Rules (JSON)">
            <textarea
              value={agent.delegation_rules ? JSON.stringify(agent.delegation_rules, null, 2) : ''}
              onChange={(e) => {
                try {
                  update('delegation_rules', e.target.value ? JSON.parse(e.target.value) : null)
                } catch {
                  // Allow typing invalid JSON while editing
                }
              }}
              rows={4}
              className="w-full px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder='{"parallel_when_independent": true, "max_delegation_depth": 3}'
            />
          </Field>
        </Section>

        {/* Autonomy & Permissions */}
        <Section title="Autonomy & Permissions" id="autonomy" open={openSections.has('autonomy')} onToggle={toggleSection}>
          <Field label="Autonomy Level">
            <div className="grid grid-cols-3 gap-2">
              {AUTONOMY_LEVELS.map((al) => {
                const AlIcon = al.icon
                return (
                  <label
                    key={al.value}
                    className={`flex items-start gap-2 p-3 border cursor-pointer transition-colors ${
                      (agent.autonomy_level || 'semi_autonomous') === al.value
                        ? 'border-primary bg-primary/5'
                        : 'border-border hover:border-muted-foreground/30'
                    }`}
                  >
                    <input
                      type="radio"
                      name="autonomy_level"
                      value={al.value}
                      checked={(agent.autonomy_level || 'semi_autonomous') === al.value}
                      onChange={() => update('autonomy_level', al.value)}
                      className="mt-0.5"
                    />
                    <div>
                      <div className="flex items-center gap-1.5">
                        <AlIcon className="h-3.5 w-3.5 text-muted-foreground" />
                        <span className="text-sm font-medium">{al.label}</span>
                      </div>
                      <p className="text-xs text-muted-foreground mt-0.5">{al.desc}</p>
                    </div>
                  </label>
                )
              })}
            </div>
          </Field>

          <div className="grid grid-cols-2 gap-4">
            <Field label="Per-Run Budget (USD)" hint="Agent pauses when reached">
              <div className="relative">
                <DollarSign className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                <input
                  type="number"
                  value={agent.budget_limit_usd ?? ''}
                  onChange={(e) => update('budget_limit_usd', e.target.value ? parseFloat(e.target.value) : null)}
                  className="w-full pl-8 pr-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                  placeholder="No limit"
                  min={0}
                  step={0.01}
                />
              </div>
            </Field>
            <Field label="Daily Budget (USD)" hint="Resets every 24h">
              <div className="relative">
                <DollarSign className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                <input
                  type="number"
                  value={agent.daily_budget_limit_usd ?? ''}
                  onChange={(e) => update('daily_budget_limit_usd', e.target.value ? parseFloat(e.target.value) : null)}
                  className="w-full pl-8 pr-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                  placeholder="No limit"
                  min={0}
                  step={0.01}
                />
              </div>
            </Field>
          </div>

          <Field label="Allowed Tools" hint="Whitelist — only these tools can be used. One per line.">
            <ToolListEditor
              tools={agent.allowed_tools || []}
              onChange={(tools) => update('allowed_tools', tools.length > 0 ? tools : null)}
              placeholder="Type a tool name and press Enter..."
            />
          </Field>

          <Field label="Blocked Tools" hint="These tools are always excluded. One per line.">
            <ToolListEditor
              tools={agent.blocked_tools || []}
              onChange={(tools) => update('blocked_tools', tools.length > 0 ? tools : null)}
              placeholder="Type a tool name and press Enter..."
            />
          </Field>

          <p className="text-xs text-muted-foreground">
            Allowed tools take precedence. Leave both empty for no restrictions.
          </p>

          <Field label="Data Access">
            <div className="grid grid-cols-3 gap-4">
              <div className="space-y-1.5">
                <label className="text-xs text-muted-foreground">External API Access</label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={(agent.data_access_scope as Record<string, unknown>)?.external_api !== false}
                    onChange={(e) =>
                      update('data_access_scope', {
                        ...(agent.data_access_scope as Record<string, unknown> || {}),
                        external_api: e.target.checked,
                      })
                    }
                    className="rounded"
                  />
                  <span className="text-sm">Enabled</span>
                </label>
              </div>
              <div className="space-y-1.5">
                <label className="text-xs text-muted-foreground">File Access</label>
                <select
                  value={String((agent.data_access_scope as Record<string, unknown>)?.file_access ?? 'read_write')}
                  onChange={(e) =>
                    update('data_access_scope', {
                      ...(agent.data_access_scope as Record<string, unknown> || {}),
                      file_access: e.target.value,
                    })
                  }
                  className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                >
                  <option value="none">None</option>
                  <option value="read">Read Only</option>
                  <option value="read_write">Read + Write</option>
                </select>
              </div>
              <div className="space-y-1.5">
                <label className="text-xs text-muted-foreground">Memory Access</label>
                <select
                  value={String((agent.data_access_scope as Record<string, unknown>)?.memory_access ?? 'own')}
                  onChange={(e) =>
                    update('data_access_scope', {
                      ...(agent.data_access_scope as Record<string, unknown> || {}),
                      memory_access: e.target.value,
                    })
                  }
                  className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                >
                  <option value="own">Own Memory</option>
                  <option value="shared">Shared Memory</option>
                  <option value="none">No Memory</option>
                </select>
              </div>
            </div>
          </Field>
        </Section>

        {/* Actions */}
        <Section title="Actions / Custom Tools" id="actions" open={openSections.has('actions')} onToggle={toggleSection}>
          <Field label="Custom Tools (JSON array)">
            <textarea
              value={agent.custom_tools ? JSON.stringify(agent.custom_tools, null, 2) : ''}
              onChange={(e) => {
                try {
                  update('custom_tools', e.target.value ? JSON.parse(e.target.value) : null)
                } catch {
                  // Allow typing invalid JSON while editing
                }
              }}
              rows={6}
              className="w-full px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary resize-y"
              placeholder='[{"name": "search_docs", "description": "Search documentation", "input_schema": {...}}]'
            />
          </Field>
          <p className="text-xs text-muted-foreground">
            MCP servers and A2A agents are bound per-project in the project agent configuration.
          </p>
        </Section>
      </div>
    </div>
  )
}

// --- Helper Components ---

function Section({
  title,
  id,
  open,
  onToggle,
  children,
}: {
  title: string
  id: string
  open: boolean
  onToggle: (id: string) => void
  children: React.ReactNode
}) {
  return (
    <div className="border border-border">
      <button
        onClick={() => onToggle(id)}
        className="w-full flex items-center gap-2 px-4 py-3 bg-muted/30 hover:bg-muted/50 transition-colors text-left"
      >
        {open ? (
          <ChevronDown className="h-4 w-4 text-muted-foreground" />
        ) : (
          <ChevronRight className="h-4 w-4 text-muted-foreground" />
        )}
        <span className="text-sm font-medium">{title}</span>
      </button>
      {open && <div className="p-4 space-y-4">{children}</div>}
    </div>
  )
}

function Field({
  label,
  required,
  hint,
  children,
}: {
  label: string
  required?: boolean
  hint?: string
  children: React.ReactNode
}) {
  return (
    <div className="space-y-1.5">
      <label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
        {label}
        {required && <span className="text-red-400 ml-0.5">*</span>}
        {hint && <span className="font-normal normal-case tracking-normal ml-2 text-muted-foreground/60">({hint})</span>}
      </label>
      {children}
    </div>
  )
}

function FallbackModelsEditor({
  primaryModel,
  fallbackModels,
  modelGroups,
  onChange,
}: {
  primaryModel: string
  fallbackModels: string[]
  modelGroups: ModelGroup[]
  onChange: (models: string[]) => void
}) {
  const allModels = modelGroups.flatMap((g) =>
    g.models.map((m) => ({ id: m.id, name: m.name, provider: g.label })),
  )
  const excluded = new Set([primaryModel, ...fallbackModels])
  const availableModels = allModels.filter((m) => !excluded.has(m.id))

  const addModel = (modelId: string) => {
    if (modelId && !fallbackModels.includes(modelId)) {
      onChange([...fallbackModels, modelId])
    }
  }

  const removeModel = (index: number) => {
    onChange(fallbackModels.filter((_, i) => i !== index))
  }

  const moveUp = (index: number) => {
    if (index === 0) return
    const next = [...fallbackModels]
    ;[next[index - 1], next[index]] = [next[index], next[index - 1]]
    onChange(next)
  }

  const moveDown = (index: number) => {
    if (index >= fallbackModels.length - 1) return
    const next = [...fallbackModels]
    ;[next[index], next[index + 1]] = [next[index + 1], next[index]]
    onChange(next)
  }

  const getModelLabel = (modelId: string) => {
    const found = allModels.find((m) => m.id === modelId)
    return found ? `${found.name} (${found.provider})` : modelId
  }

  return (
    <div className="space-y-1.5">
      <label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
        Fallback Models
      </label>
      <p className="text-xs text-muted-foreground">
        If the primary model fails, try these models in order.
      </p>

      {fallbackModels.length > 0 && (
        <div className="space-y-1">
          {fallbackModels.map((modelId, index) => (
            <div
              key={modelId}
              className="flex items-center gap-2 px-3 py-2 bg-muted/30 border border-border text-sm"
            >
              <span className="text-xs text-muted-foreground font-mono w-5 text-center">
                {index + 1}
              </span>
              <span className="flex-1 truncate">{getModelLabel(modelId)}</span>
              <button
                onClick={() => moveUp(index)}
                disabled={index === 0}
                className="p-0.5 text-muted-foreground hover:text-foreground disabled:opacity-30 transition-colors"
                title="Move up"
              >
                <ArrowUp className="h-3 w-3" />
              </button>
              <button
                onClick={() => moveDown(index)}
                disabled={index >= fallbackModels.length - 1}
                className="p-0.5 text-muted-foreground hover:text-foreground disabled:opacity-30 transition-colors"
                title="Move down"
              >
                <ArrowDown className="h-3 w-3" />
              </button>
              <button
                onClick={() => removeModel(index)}
                className="p-0.5 text-red-400 hover:text-red-300 transition-colors"
                title="Remove"
              >
                <X className="h-3 w-3" />
              </button>
            </div>
          ))}
        </div>
      )}

      {availableModels.length > 0 && (
        <div className="flex items-center gap-2">
          <select
            className="flex-1 px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
            defaultValue=""
            onChange={(e) => {
              addModel(e.target.value)
              e.target.value = ''
            }}
          >
            <option value="" disabled>
              Add fallback model...
            </option>
            {availableModels.map((m) => (
              <option key={m.id} value={m.id}>
                {m.name} ({m.provider})
              </option>
            ))}
          </select>
        </div>
      )}
    </div>
  )
}

function ToolListEditor({
  tools,
  onChange,
  placeholder,
}: {
  tools: string[]
  onChange: (tools: string[]) => void
  placeholder?: string
}) {
  const [input, setInput] = useState('')

  const addTool = () => {
    const trimmed = input.trim()
    if (trimmed && !tools.includes(trimmed)) {
      onChange([...tools, trimmed])
    }
    setInput('')
  }

  const removeTool = (index: number) => {
    onChange(tools.filter((_, i) => i !== index))
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      addTool()
    }
  }

  return (
    <div className="space-y-2">
      {tools.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {tools.map((tool, index) => (
            <span
              key={tool}
              className="inline-flex items-center gap-1 px-2 py-1 bg-muted/50 border border-border text-xs font-mono"
            >
              {tool}
              <button
                onClick={() => removeTool(index)}
                className="text-muted-foreground hover:text-red-400 transition-colors"
              >
                <X className="h-3 w-3" />
              </button>
            </span>
          ))}
        </div>
      )}
      <div className="flex items-center gap-2">
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={handleKeyDown}
          className="flex-1 px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary"
          placeholder={placeholder}
        />
        <button
          onClick={addTool}
          disabled={!input.trim()}
          className="px-3 py-2 text-xs bg-muted border border-border hover:bg-muted/80 disabled:opacity-30 transition-colors"
        >
          <Plus className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  )
}
