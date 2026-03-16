import { useState, useEffect, useCallback, useMemo } from 'react'
import { Link } from 'react-router-dom'
import {
  X,
  Bot,
  Sparkles,
  Server,
  ExternalLink,
  Save,
  Wifi,
  Trash2,
  Plus,
  Search,
  Loader2,
  Check,
  ToggleLeft,
  ToggleRight,
} from 'lucide-react'
import type { ProjectGraphData } from '@/types'
import {
  fetchAgent,
  updateAgent,
  deleteAgent,
  toggleAgent,
  assignAgentSkills,
  bindAgentMcpServers,
  bindAgentA2aAgents,
} from '@/api/client'

type NodeType = 'agent' | 'skill' | 'mcp' | 'a2a' | 'provider'

interface BaseProps {
  nodeId: string
  nodeType: NodeType
  data: ProjectGraphData
  projectId?: number
  onClose: () => void
  onAgentUpdate?: (agentId: number, updates: { custom_instructions?: string }) => void
  onRefresh?: () => void
  onNodeDeleted?: (nodeId: string) => void
}

// ─── Select component ─────────────────────────────────────────────
function FormSelect({
  label,
  value,
  onChange,
  options,
}: {
  label: string
  value: string
  onChange: (v: string) => void
  options: { value: string; label: string }[]
}) {
  return (
    <div>
      <label className="block text-[10px] font-medium text-zinc-500 mb-1">{label}</label>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full px-2 py-1.5 text-xs bg-zinc-800 border border-zinc-700 rounded-md text-zinc-200 focus:border-violet-600 focus:ring-1 focus:ring-violet-600"
      >
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </div>
  )
}

// ─── Text input component ─────────────────────────────────────────
function FormInput({
  label,
  value,
  onChange,
  readOnly,
  type = 'text',
  placeholder,
  min,
  max,
  step,
}: {
  label: string
  value: string
  onChange?: (v: string) => void
  readOnly?: boolean
  type?: string
  placeholder?: string
  min?: number
  max?: number
  step?: number
}) {
  return (
    <div>
      <label className="block text-[10px] font-medium text-zinc-500 mb-1">{label}</label>
      <input
        type={type}
        value={value}
        onChange={onChange ? (e) => onChange(e.target.value) : undefined}
        readOnly={readOnly}
        placeholder={placeholder}
        min={min}
        max={max}
        step={step}
        className={`w-full px-2 py-1.5 text-xs bg-zinc-800 border border-zinc-700 rounded-md text-zinc-200 placeholder-zinc-500 focus:border-violet-600 focus:ring-1 focus:ring-violet-600 ${
          readOnly ? 'opacity-60 cursor-not-allowed' : ''
        }`}
      />
    </div>
  )
}

// ─── Textarea component ───────────────────────────────────────────
function FormTextarea({
  label,
  value,
  onChange,
  rows = 4,
  placeholder,
}: {
  label: string
  value: string
  onChange: (v: string) => void
  rows?: number
  placeholder?: string
}) {
  return (
    <div>
      <label className="block text-[10px] font-medium text-zinc-500 mb-1">{label}</label>
      <textarea
        value={value}
        onChange={(e) => onChange(e.target.value)}
        rows={rows}
        placeholder={placeholder}
        className="w-full px-2 py-1.5 text-xs bg-zinc-800 border border-zinc-700 rounded-md text-zinc-200 placeholder-zinc-500 focus:border-violet-600 focus:ring-1 focus:ring-violet-600 resize-none"
      />
    </div>
  )
}

// ─── Searchable dropdown ──────────────────────────────────────────
function SearchableDropdown({
  items,
  onSelect,
  placeholder = 'Search...',
}: {
  items: { id: number; name: string; badge?: string }[]
  onSelect: (id: number) => void
  placeholder?: string
}) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')

  const filtered = useMemo(() => {
    if (!query) return items
    const q = query.toLowerCase()
    return items.filter((i) => i.name.toLowerCase().includes(q))
  }, [items, query])

  if (!open) {
    return (
      <button
        onClick={() => setOpen(true)}
        className="flex items-center gap-1.5 px-2 py-1.5 text-[11px] text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800 border border-dashed border-zinc-700 rounded-md transition-colors w-full"
      >
        <Plus className="h-3 w-3" />
        Add...
      </button>
    )
  }

  return (
    <div className="border border-zinc-700 rounded-md bg-zinc-800 overflow-hidden">
      <div className="flex items-center gap-1.5 px-2 py-1.5 border-b border-zinc-700">
        <Search className="h-3 w-3 text-zinc-500" />
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={placeholder}
          autoFocus
          className="flex-1 bg-transparent text-xs text-zinc-200 placeholder-zinc-500 outline-none"
          onKeyDown={(e) => {
            if (e.key === 'Escape') {
              setOpen(false)
              setQuery('')
            }
          }}
        />
        <button
          onClick={() => {
            setOpen(false)
            setQuery('')
          }}
          className="text-zinc-500 hover:text-zinc-300"
        >
          <X className="h-3 w-3" />
        </button>
      </div>
      <div className="max-h-32 overflow-y-auto">
        {filtered.length === 0 && (
          <p className="px-2 py-2 text-[11px] text-zinc-600 italic">No items available</p>
        )}
        {filtered.map((item) => (
          <button
            key={item.id}
            onClick={() => {
              onSelect(item.id)
              setOpen(false)
              setQuery('')
            }}
            className="flex items-center justify-between w-full px-2 py-1.5 text-xs text-zinc-300 hover:bg-zinc-700/60 transition-colors"
          >
            <span className="truncate">{item.name}</span>
            {item.badge && (
              <span className="text-[10px] text-zinc-500 shrink-0 ml-2">{item.badge}</span>
            )}
          </button>
        ))}
      </div>
    </div>
  )
}

// ─── Tab button ───────────────────────────────────────────────────
function TabButton({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      onClick={onClick}
      className={`px-3 py-1.5 text-[11px] font-medium rounded-md transition-colors ${
        active
          ? 'bg-violet-700 text-white'
          : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800'
      }`}
    >
      {children}
    </button>
  )
}

// ─── Agent form state ─────────────────────────────────────────────
interface AgentFormState {
  name: string
  role: string
  icon: string
  description: string
  persona_name: string
  persona_avatar: string
  persona_aliases: string
  persona_personality: string
  persona_bio: string
  model: string
  planning_mode: string
  context_strategy: string
  loop_condition: string
  max_iterations: string
  timeout_seconds: string
  temperature: string
  autonomy_level: string
  budget_limit_usd: string
  daily_budget_limit_usd: string
  base_instructions: string
  system_prompt: string
}

// ─── Agent detail (full editor) — #338-#342 ───────────────────────
function AgentDetail({
  agent,
  data,
  projectId,
  onRefresh,
  onNodeDeleted,
}: {
  agent: ProjectGraphData['agents'][0]
  data: ProjectGraphData
  projectId?: number
  onRefresh?: () => void
  onNodeDeleted?: (nodeId: string) => void
}) {
  const [tab, setTab] = useState<'identity' | 'reasoning' | 'autonomy'>('identity')
  const [form, setForm] = useState<AgentFormState>({
    name: agent.name ?? '',
    role: agent.role ?? '',
    icon: agent.icon ?? '',
    description: '',
    persona_name: '',
    persona_avatar: '',
    persona_aliases: '',
    persona_personality: '',
    persona_bio: '',
    model: agent.model ?? '',
    planning_mode: agent.planning_mode ?? 'none',
    context_strategy: agent.context_strategy ?? 'full',
    loop_condition: agent.loop_condition ?? 'goal_met',
    max_iterations: agent.max_iterations?.toString() ?? '',
    timeout_seconds: '',
    temperature: '',
    autonomy_level: 'supervised',
    budget_limit_usd: '',
    daily_budget_limit_usd: '',
    base_instructions: '',
    system_prompt: '',
  })
  const [savedForm, setSavedForm] = useState<AgentFormState>(form)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [saveSuccess, setSaveSuccess] = useState(false)
  const [isEnabled, setIsEnabled] = useState(agent.is_enabled)
  const [toggling, setToggling] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)
  const [skillIds, setSkillIds] = useState<number[]>(agent.skill_ids ?? [])
  const [mcpServerIds, setMcpServerIds] = useState<number[]>(agent.mcp_server_ids ?? [])
  const [a2aAgentIds, setA2aAgentIds] = useState<number[]>(agent.a2a_agent_ids ?? [])

  // Fetch full agent data to populate fields not in graph data
  useEffect(() => {
    setLoading(true)
    fetchAgent(agent.id)
      .then((full) => {
        const persona = full.persona
        const newForm: AgentFormState = {
          name: full.name ?? '',
          role: full.role ?? '',
          icon: full.icon ?? '',
          description: full.description ?? '',
          persona_name: persona?.name ?? '',
          persona_avatar: persona?.avatar ?? '',
          persona_aliases: (persona?.aliases ?? []).join(', '),
          persona_personality: persona?.personality ?? '',
          persona_bio: persona?.bio ?? '',
          model: full.model ?? '',
          planning_mode: full.planning_mode ?? 'none',
          context_strategy: full.context_strategy ?? 'full',
          loop_condition: full.loop_condition ?? 'goal_met',
          max_iterations: full.max_iterations?.toString() ?? '',
          timeout_seconds: full.timeout_seconds?.toString() ?? '',
          temperature: full.temperature?.toString() ?? '',
          autonomy_level: full.autonomy_level ?? 'supervised',
          budget_limit_usd: full.budget_limit_usd?.toString() ?? '',
          daily_budget_limit_usd: full.daily_budget_limit_usd?.toString() ?? '',
          base_instructions: full.base_instructions ?? '',
          system_prompt: full.system_prompt ?? '',
        }
        setForm(newForm)
        setSavedForm(newForm)
      })
      .finally(() => setLoading(false))
  }, [agent.id])

  const updateField = useCallback((field: keyof AgentFormState, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }, [])

  const isDirty = useMemo(
    () => JSON.stringify(form) !== JSON.stringify(savedForm),
    [form, savedForm],
  )

  const handleSave = useCallback(async () => {
    setSaving(true)
    setSaveSuccess(false)
    try {
      const aliases = form.persona_aliases
        .split(',')
        .map((a) => a.trim())
        .filter(Boolean)
      await updateAgent(agent.id, {
        name: form.name,
        role: form.role,
        icon: form.icon || null,
        description: form.description || null,
        persona: {
          name: form.persona_name || undefined,
          avatar: form.persona_avatar || undefined,
          aliases: aliases.length > 0 ? aliases : undefined,
          personality: form.persona_personality || undefined,
          bio: form.persona_bio || undefined,
        },
        model: form.model || null,
        planning_mode: form.planning_mode,
        context_strategy: form.context_strategy,
        loop_condition: form.loop_condition,
        max_iterations: form.max_iterations ? parseInt(form.max_iterations) : null,
        timeout_seconds: form.timeout_seconds ? parseInt(form.timeout_seconds) : null,
        temperature: form.temperature ? parseFloat(form.temperature) : null,
        autonomy_level:
          (form.autonomy_level as 'supervised' | 'semi_autonomous' | 'autonomous') || undefined,
        budget_limit_usd: form.budget_limit_usd ? parseFloat(form.budget_limit_usd) : null,
        daily_budget_limit_usd: form.daily_budget_limit_usd
          ? parseFloat(form.daily_budget_limit_usd)
          : null,
        base_instructions: form.base_instructions,
        system_prompt: form.system_prompt || null,
      })
      setSavedForm(form)
      setSaveSuccess(true)
      setTimeout(() => setSaveSuccess(false), 2000)
      onRefresh?.()
    } catch {
      // Keep form dirty on error
    } finally {
      setSaving(false)
    }
  }, [agent.id, form, onRefresh])

  const handleCancel = useCallback(() => {
    setForm(savedForm)
  }, [savedForm])

  // #342 — Enable/disable toggle
  const handleToggle = useCallback(async () => {
    if (!projectId) return
    setToggling(true)
    try {
      await toggleAgent(projectId, agent.id, !isEnabled)
      setIsEnabled(!isEnabled)
      onRefresh?.()
    } catch {
      // Revert on error
    } finally {
      setToggling(false)
    }
  }, [projectId, agent.id, isEnabled, onRefresh])

  // #342 — Delete with confirmation
  const handleDelete = useCallback(async () => {
    if (!confirmDelete) {
      setConfirmDelete(true)
      return
    }
    setDeleting(true)
    try {
      await deleteAgent(agent.id)
      onNodeDeleted?.(`agent-${agent.id}`)
    } catch {
      setDeleting(false)
      setConfirmDelete(false)
    }
  }, [confirmDelete, agent.id, onNodeDeleted])

  // ─── #339 — Skill management ─────────────────────────────────────
  const assignedSkills = data.skills.filter((s) => skillIds.includes(s.id))
  const unassignedSkills = data.skills.filter((s) => !skillIds.includes(s.id))
  const totalTokens = assignedSkills.reduce((sum, s) => sum + s.token_estimate, 0)

  const handleAddSkill = useCallback(
    async (skillId: number) => {
      if (!projectId) return
      const newIds = [...new Set([...skillIds, skillId])]
      setSkillIds(newIds)
      try {
        await assignAgentSkills(projectId, agent.id, newIds)
        onRefresh?.()
      } catch {
        setSkillIds(skillIds)
      }
    },
    [projectId, agent.id, skillIds, onRefresh],
  )

  const handleRemoveSkill = useCallback(
    async (skillId: number) => {
      if (!projectId) return
      const newIds = skillIds.filter((id) => id !== skillId)
      setSkillIds(newIds)
      try {
        await assignAgentSkills(projectId, agent.id, newIds)
        onRefresh?.()
      } catch {
        setSkillIds(skillIds)
      }
    },
    [projectId, agent.id, skillIds, onRefresh],
  )

  // ─── #340 — MCP server management ────────────────────────────────
  const assignedMcp = data.mcp_servers.filter((m) => mcpServerIds.includes(m.id))
  const unassignedMcp = data.mcp_servers.filter((m) => !mcpServerIds.includes(m.id))

  const handleAddMcp = useCallback(
    async (mcpId: number) => {
      if (!projectId) return
      const newIds = [...new Set([...mcpServerIds, mcpId])]
      setMcpServerIds(newIds)
      try {
        await bindAgentMcpServers(projectId, agent.id, newIds)
        onRefresh?.()
      } catch {
        setMcpServerIds(mcpServerIds)
      }
    },
    [projectId, agent.id, mcpServerIds, onRefresh],
  )

  const handleRemoveMcp = useCallback(
    async (mcpId: number) => {
      if (!projectId) return
      const newIds = mcpServerIds.filter((id) => id !== mcpId)
      setMcpServerIds(newIds)
      try {
        await bindAgentMcpServers(projectId, agent.id, newIds)
        onRefresh?.()
      } catch {
        setMcpServerIds(mcpServerIds)
      }
    },
    [projectId, agent.id, mcpServerIds, onRefresh],
  )

  // ─── #341 — A2A agent management ─────────────────────────────────
  const a2aAgents = data.a2a_agents ?? []
  const assignedA2a = a2aAgents.filter((a) => a2aAgentIds.includes(a.id))
  const unassignedA2a = a2aAgents.filter((a) => !a2aAgentIds.includes(a.id))

  const handleAddA2a = useCallback(
    async (a2aId: number) => {
      if (!projectId) return
      const newIds = [...new Set([...a2aAgentIds, a2aId])]
      setA2aAgentIds(newIds)
      try {
        await bindAgentA2aAgents(projectId, agent.id, newIds)
        onRefresh?.()
      } catch {
        setA2aAgentIds(a2aAgentIds)
      }
    },
    [projectId, agent.id, a2aAgentIds, onRefresh],
  )

  const handleRemoveA2a = useCallback(
    async (a2aId: number) => {
      if (!projectId) return
      const newIds = a2aAgentIds.filter((id) => id !== a2aId)
      setA2aAgentIds(newIds)
      try {
        await bindAgentA2aAgents(projectId, agent.id, newIds)
        onRefresh?.()
      } catch {
        setA2aAgentIds(a2aAgentIds)
      }
    },
    [projectId, agent.id, a2aAgentIds, onRefresh],
  )

  return (
    <div className="space-y-4">
      {/* #342 — Header with enable/disable toggle and delete */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Bot className="h-5 w-5 text-violet-400" />
          <div>
            <h4 className="text-sm font-semibold text-violet-100">{agent.name}</h4>
            <p className="text-[11px] text-zinc-500">{agent.slug}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={handleToggle}
            disabled={toggling || !projectId}
            className="p-1 text-zinc-400 hover:text-zinc-200 transition-colors disabled:opacity-50"
            title={isEnabled ? 'Disable agent' : 'Enable agent'}
          >
            {toggling ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : isEnabled ? (
              <ToggleRight className="h-5 w-5 text-emerald-400" />
            ) : (
              <ToggleLeft className="h-5 w-5 text-zinc-500" />
            )}
          </button>
          {confirmDelete ? (
            <div className="flex items-center gap-1">
              <button
                onClick={handleDelete}
                disabled={deleting}
                className="px-2 py-1 text-[10px] font-medium bg-red-700 hover:bg-red-600 text-white rounded transition-colors disabled:opacity-50"
              >
                {deleting ? 'Deleting...' : 'Confirm'}
              </button>
              <button
                onClick={() => setConfirmDelete(false)}
                className="px-2 py-1 text-[10px] font-medium bg-zinc-700 hover:bg-zinc-600 text-zinc-300 rounded transition-colors"
              >
                Cancel
              </button>
            </div>
          ) : (
            <button
              onClick={handleDelete}
              className="p-1 text-zinc-500 hover:text-red-400 transition-colors"
              title="Delete agent"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          )}
        </div>
      </div>

      {/* Status badges */}
      <div className="flex gap-2">
        <span
          className={`text-[10px] px-2 py-0.5 rounded-full ${
            isEnabled
              ? 'bg-emerald-900/50 text-emerald-300'
              : 'bg-zinc-800 text-zinc-500'
          }`}
        >
          {isEnabled ? 'Enabled' : 'Disabled'}
        </span>
        {agent.can_delegate && (
          <span className="text-[10px] px-2 py-0.5 rounded-full bg-violet-900/50 text-violet-300">
            Can Delegate
          </span>
        )}
      </div>

      {/* Loading indicator for full agent fetch */}
      {loading && (
        <div className="flex items-center gap-2 text-xs text-zinc-500">
          <Loader2 className="h-3 w-3 animate-spin" />
          Loading agent data...
        </div>
      )}

      {/* #338 — Tabbed editor */}
      <div className="flex gap-1 bg-zinc-800/40 rounded-lg p-1">
        <TabButton active={tab === 'identity'} onClick={() => setTab('identity')}>
          Identity
        </TabButton>
        <TabButton active={tab === 'reasoning'} onClick={() => setTab('reasoning')}>
          Reasoning
        </TabButton>
        <TabButton active={tab === 'autonomy'} onClick={() => setTab('autonomy')}>
          Autonomy
        </TabButton>
      </div>

      {/* Identity tab */}
      {tab === 'identity' && (
        <div className="space-y-3">
          <FormInput
            label="Name"
            value={form.name}
            onChange={(v) => updateField('name', v)}
          />
          <FormInput label="Slug" value={agent.slug} readOnly />
          <FormInput
            label="Role"
            value={form.role}
            onChange={(v) => updateField('role', v)}
            placeholder="e.g. planner, coder, reviewer"
          />
          <FormInput
            label="Icon"
            value={form.icon}
            onChange={(v) => updateField('icon', v)}
            placeholder="e.g. bot, brain, shield"
          />

          <div className="border-t border-zinc-800 pt-3">
            <p className="text-[10px] font-semibold text-zinc-400 uppercase tracking-wider mb-2">
              Persona
            </p>
            <div className="space-y-2">
              <FormInput
                label="Persona Name"
                value={form.persona_name}
                onChange={(v) => updateField('persona_name', v)}
                placeholder="Display name"
              />
              <FormInput
                label="Avatar"
                value={form.persona_avatar}
                onChange={(v) => updateField('persona_avatar', v)}
                placeholder="URL or emoji"
              />
              <FormInput
                label="Aliases"
                value={form.persona_aliases}
                onChange={(v) => updateField('persona_aliases', v)}
                placeholder="Comma-separated"
              />
              <FormTextarea
                label="Personality"
                value={form.persona_personality}
                onChange={(v) => updateField('persona_personality', v)}
                rows={2}
                placeholder="How this agent behaves..."
              />
              <FormTextarea
                label="Bio"
                value={form.persona_bio}
                onChange={(v) => updateField('persona_bio', v)}
                rows={2}
                placeholder="Background / context..."
              />
            </div>
          </div>

          <FormTextarea
            label="Description"
            value={form.description}
            onChange={(v) => updateField('description', v)}
            rows={3}
            placeholder="What this agent does..."
          />
        </div>
      )}

      {/* Reasoning tab */}
      {tab === 'reasoning' && (
        <div className="space-y-3">
          <FormInput
            label="Model"
            value={form.model}
            onChange={(v) => updateField('model', v)}
            placeholder="e.g. claude-sonnet-4-6"
          />
          <FormSelect
            label="Planning Mode"
            value={form.planning_mode}
            onChange={(v) => updateField('planning_mode', v)}
            options={[
              { value: 'none', label: 'None' },
              { value: 'act', label: 'Act' },
              { value: 'plan_then_act', label: 'Plan Then Act' },
              { value: 'react', label: 'ReAct' },
            ]}
          />
          <FormSelect
            label="Context Strategy"
            value={form.context_strategy}
            onChange={(v) => updateField('context_strategy', v)}
            options={[
              { value: 'full', label: 'Full' },
              { value: 'summary', label: 'Summary' },
              { value: 'sliding_window', label: 'Sliding Window' },
              { value: 'rag', label: 'RAG' },
            ]}
          />
          <FormSelect
            label="Loop Condition"
            value={form.loop_condition}
            onChange={(v) => updateField('loop_condition', v)}
            options={[
              { value: 'goal_met', label: 'Goal Met' },
              { value: 'max_iterations', label: 'Max Iterations' },
              { value: 'timeout', label: 'Timeout' },
            ]}
          />
          <div className="grid grid-cols-2 gap-2">
            <FormInput
              label="Max Iterations"
              value={form.max_iterations}
              onChange={(v) => updateField('max_iterations', v)}
              type="number"
              min={1}
              placeholder="e.g. 10"
            />
            <FormInput
              label="Timeout (sec)"
              value={form.timeout_seconds}
              onChange={(v) => updateField('timeout_seconds', v)}
              type="number"
              min={1}
              placeholder="e.g. 300"
            />
          </div>
          <FormInput
            label="Temperature"
            value={form.temperature}
            onChange={(v) => updateField('temperature', v)}
            type="number"
            min={0}
            max={2}
            step={0.1}
            placeholder="0-2"
          />
        </div>
      )}

      {/* Autonomy tab */}
      {tab === 'autonomy' && (
        <div className="space-y-3">
          <FormSelect
            label="Autonomy Level"
            value={form.autonomy_level}
            onChange={(v) => updateField('autonomy_level', v)}
            options={[
              { value: 'supervised', label: 'Supervised' },
              { value: 'semi_autonomous', label: 'Semi-Autonomous' },
              { value: 'autonomous', label: 'Autonomous' },
            ]}
          />
          <div className="grid grid-cols-2 gap-2">
            <FormInput
              label="Budget Limit ($)"
              value={form.budget_limit_usd}
              onChange={(v) => updateField('budget_limit_usd', v)}
              type="number"
              min={0}
              step={0.01}
              placeholder="e.g. 10.00"
            />
            <FormInput
              label="Daily Budget ($)"
              value={form.daily_budget_limit_usd}
              onChange={(v) => updateField('daily_budget_limit_usd', v)}
              type="number"
              min={0}
              step={0.01}
              placeholder="e.g. 5.00"
            />
          </div>
          <FormTextarea
            label="Base Instructions"
            value={form.base_instructions}
            onChange={(v) => updateField('base_instructions', v)}
            rows={6}
            placeholder="Core instructions for this agent..."
          />
          <FormTextarea
            label="System Prompt"
            value={form.system_prompt}
            onChange={(v) => updateField('system_prompt', v)}
            rows={4}
            placeholder="System-level prompt override..."
          />
        </div>
      )}

      {/* Save / Cancel buttons */}
      <div className="flex items-center gap-2">
        <button
          onClick={handleSave}
          disabled={saving || !isDirty}
          className="flex items-center gap-1.5 px-3 py-1.5 bg-violet-700 hover:bg-violet-600 text-white text-xs font-medium rounded-md transition-colors disabled:opacity-50"
        >
          {saving ? (
            <Loader2 className="h-3 w-3 animate-spin" />
          ) : saveSuccess ? (
            <Check className="h-3 w-3" />
          ) : (
            <Save className="h-3 w-3" />
          )}
          {saving ? 'Saving...' : saveSuccess ? 'Saved' : 'Save'}
        </button>
        {isDirty && (
          <button
            onClick={handleCancel}
            className="px-3 py-1.5 text-xs font-medium text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800 rounded-md transition-colors"
          >
            Cancel
          </button>
        )}
      </div>

      {/* ─── #339 — Skills section ───────────────────────────────────── */}
      <div className="border-t border-zinc-800 pt-3 space-y-2">
        <div className="flex items-center justify-between">
          <label className="text-xs font-medium text-zinc-400">
            Skills ({assignedSkills.length})
          </label>
          <span className="text-[10px] text-zinc-500">
            {totalTokens.toLocaleString()} tokens
          </span>
        </div>
        <div className="space-y-1 max-h-40 overflow-y-auto">
          {assignedSkills.length === 0 && (
            <p className="text-[11px] text-zinc-600 italic">No skills assigned</p>
          )}
          {assignedSkills.map((skill) => (
            <div
              key={skill.id}
              className="flex items-center justify-between px-2 py-1.5 bg-zinc-800/60 rounded text-xs group"
            >
              <div className="flex items-center gap-2 truncate">
                <Sparkles className="h-3 w-3 text-emerald-400 shrink-0" />
                <span className="text-emerald-300 truncate">{skill.name}</span>
              </div>
              <div className="flex items-center gap-2">
                {skill.model && (
                  <span className="text-[10px] text-zinc-500">{skill.model}</span>
                )}
                <span className="text-zinc-500 text-[10px]">{skill.token_estimate} tok</span>
                {projectId && (
                  <button
                    onClick={() => handleRemoveSkill(skill.id)}
                    className="opacity-0 group-hover:opacity-100 text-zinc-500 hover:text-red-400 transition-all"
                  >
                    <X className="h-3 w-3" />
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
        {projectId && unassignedSkills.length > 0 && (
          <SearchableDropdown
            items={unassignedSkills.map((s) => ({
              id: s.id,
              name: s.name,
              badge: s.model ?? undefined,
            }))}
            onSelect={handleAddSkill}
            placeholder="Search skills..."
          />
        )}
      </div>

      {/* ─── #340 — MCP servers section ──────────────────────────────── */}
      <div className="border-t border-zinc-800 pt-3 space-y-2">
        <label className="text-xs font-medium text-zinc-400">
          MCP Servers ({assignedMcp.length})
        </label>
        <div className="space-y-1 max-h-32 overflow-y-auto">
          {assignedMcp.length === 0 && (
            <p className="text-[11px] text-zinc-600 italic">No MCP servers bound</p>
          )}
          {assignedMcp.map((mcp) => (
            <div
              key={mcp.id}
              className="flex items-center justify-between px-2 py-1.5 bg-zinc-800/60 rounded text-xs group"
            >
              <div className="flex items-center gap-2 truncate">
                <Server className="h-3 w-3 text-pink-400 shrink-0" />
                <span className="text-pink-300 truncate">{mcp.name}</span>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-[10px] text-zinc-500">{mcp.transport}</span>
                {projectId && (
                  <button
                    onClick={() => handleRemoveMcp(mcp.id)}
                    className="opacity-0 group-hover:opacity-100 text-zinc-500 hover:text-red-400 transition-all"
                  >
                    <X className="h-3 w-3" />
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
        {projectId && unassignedMcp.length > 0 && (
          <SearchableDropdown
            items={unassignedMcp.map((m) => ({
              id: m.id,
              name: m.name,
              badge: m.transport,
            }))}
            onSelect={handleAddMcp}
            placeholder="Search MCP servers..."
          />
        )}
      </div>

      {/* ─── #341 — A2A agents section ───────────────────────────────── */}
      <div className="border-t border-zinc-800 pt-3 space-y-2">
        <label className="text-xs font-medium text-zinc-400">
          A2A Agents ({assignedA2a.length})
        </label>
        <div className="space-y-1 max-h-32 overflow-y-auto">
          {assignedA2a.length === 0 && (
            <p className="text-[11px] text-zinc-600 italic">No A2A agents bound</p>
          )}
          {assignedA2a.map((a2a) => (
            <div
              key={a2a.id}
              className="flex items-center justify-between px-2 py-1.5 bg-zinc-800/60 rounded text-xs group"
            >
              <div className="flex items-center gap-2 truncate">
                <Wifi className="h-3 w-3 text-cyan-400 shrink-0" />
                <span className="text-cyan-300 truncate">{a2a.name}</span>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-[10px] text-zinc-500 truncate max-w-[120px]">
                  {a2a.url}
                </span>
                {projectId && (
                  <button
                    onClick={() => handleRemoveA2a(a2a.id)}
                    className="opacity-0 group-hover:opacity-100 text-zinc-500 hover:text-red-400 transition-all"
                  >
                    <X className="h-3 w-3" />
                  </button>
                )}
              </div>
            </div>
          ))}
        </div>
        {projectId && unassignedA2a.length > 0 && (
          <SearchableDropdown
            items={unassignedA2a.map((a) => ({
              id: a.id,
              name: a.name,
              badge: 'A2A',
            }))}
            onSelect={handleAddA2a}
            placeholder="Search A2A agents..."
          />
        )}
      </div>
    </div>
  )
}

// ─── Skill detail ─────────────────────────────────────────────────
function SkillDetail({ skill }: { skill: ProjectGraphData['skills'][0] }) {
  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <Sparkles className="h-5 w-5 text-emerald-400" />
        <div>
          <h4 className="text-sm font-semibold text-emerald-100">{skill.name}</h4>
          <p className="text-[11px] text-zinc-500">{skill.slug}</p>
        </div>
      </div>

      {skill.description && (
        <p className="text-xs text-zinc-400 leading-relaxed">{skill.description}</p>
      )}

      <div className="grid grid-cols-2 gap-2">
        <InfoField label="Model" value={skill.model ?? 'default'} />
        <InfoField label="Tokens" value={`${skill.token_estimate}`} />
        <InfoField label="Includes" value={`${skill.includes.length}`} />
      </div>

      {skill.tags.length > 0 && (
        <div className="space-y-1.5">
          <label className="text-xs font-medium text-zinc-400">Tags</label>
          <div className="flex flex-wrap gap-1">
            {skill.tags.map((tag) => (
              <span
                key={tag}
                className="text-[10px] px-2 py-0.5 rounded-full bg-emerald-900/50 text-emerald-300/70"
              >
                {tag}
              </span>
            ))}
          </div>
        </div>
      )}

      <Link
        to={`/skills/${skill.id}`}
        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-800 hover:bg-emerald-700 text-white text-xs font-medium rounded-md transition-colors"
      >
        <ExternalLink className="h-3 w-3" />
        Open in Skill Editor
      </Link>
    </div>
  )
}

// ─── MCP detail ───────────────────────────────────────────────────
function McpDetail({ server }: { server: ProjectGraphData['mcp_servers'][0] }) {
  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <Server className="h-5 w-5 text-pink-400" />
        <div>
          <h4 className="text-sm font-semibold text-pink-100">{server.name}</h4>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-2">
        <InfoField label="Transport" value={server.transport} />
      </div>
    </div>
  )
}

// ─── A2A agent detail ─────────────────────────────────────────────
function A2ADetail({ agent }: { agent: ProjectGraphData['a2a_agents'][0] }) {
  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <Wifi className="h-5 w-5 text-cyan-400" />
        <div>
          <h4 className="text-sm font-semibold text-cyan-100">{agent.name}</h4>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-2">
        <InfoField label="URL" value={agent.url} />
        <InfoField label="Protocol" value="A2A" />
      </div>
    </div>
  )
}

// ─── Provider detail ──────────────────────────────────────────────
function ProviderDetail({
  provider,
  outputs,
}: {
  provider: ProjectGraphData['providers'][0]
  outputs: string[]
}) {
  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2">
        <div>
          <h4 className="text-sm font-semibold text-amber-100">{provider.name}</h4>
          <p className="text-[11px] text-zinc-500">{provider.slug}</p>
        </div>
      </div>

      <InfoField label="Output Files" value={`${outputs.length}`} />

      {outputs.length > 0 && (
        <div className="space-y-1">
          {outputs.map((path) => (
            <div
              key={path}
              className="text-[10px] font-mono text-zinc-400 px-2 py-1 bg-zinc-800/60 rounded truncate"
            >
              {path}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

// ─── Info field helper ────────────────────────────────────────────
function InfoField({ label, value }: { label: string; value: string }) {
  return (
    <div className="px-2 py-1.5 bg-zinc-800/40 rounded">
      <p className="text-[10px] text-zinc-500">{label}</p>
      <p className="text-xs text-zinc-300 truncate">{value}</p>
    </div>
  )
}

// ─── Main Panel ───────────────────────────────────────────────────
export default function NodeDetailPanel({
  nodeId,
  nodeType,
  data,
  projectId,
  onClose,
  onAgentUpdate,
  onRefresh,
  onNodeDeleted,
}: BaseProps) {
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [onClose])

  const entityId = parseInt(nodeId, 10)

  let content: React.ReactNode = null

  if (nodeType === 'agent') {
    const agent = data.agents.find((a) => a.id === entityId)
    if (agent) {
      content = (
        <AgentDetail
          agent={agent}
          data={data}
          projectId={projectId}
          onRefresh={onRefresh}
          onNodeDeleted={onNodeDeleted}
        />
      )
    }
  } else if (nodeType === 'skill') {
    const skill = data.skills.find((s) => s.id === entityId)
    if (skill) {
      content = <SkillDetail skill={skill} />
    }
  } else if (nodeType === 'mcp') {
    const server = data.mcp_servers.find((m) => m.id === entityId)
    if (server) {
      content = <McpDetail server={server} />
    }
  } else if (nodeType === 'a2a') {
    const a2a = (data.a2a_agents ?? []).find((a) => a.id === entityId)
    if (a2a) {
      content = <A2ADetail agent={a2a} />
    }
  } else if (nodeType === 'provider') {
    const provider = data.providers.find((p) => p.slug === nodeId)
    if (provider) {
      content = (
        <ProviderDetail
          provider={provider}
          outputs={data.sync_outputs[provider.slug] ?? []}
        />
      )
    }
  }

  if (!content) {
    content = (
      <div className="text-xs text-zinc-500 italic">
        No details available for this node.
      </div>
    )
  }

  // Preserve backward compat — onAgentUpdate kept in interface
  void onAgentUpdate

  return (
    <div
      className="fixed top-0 right-0 h-full w-[480px] bg-zinc-900 border-l border-zinc-700 shadow-2xl z-50 overflow-y-auto transition-transform duration-200 animate-slide-in-right"
      onClick={(e) => e.stopPropagation()}
    >
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-zinc-800 sticky top-0 bg-zinc-900 z-10">
        <h3 className="text-sm font-semibold text-zinc-200 capitalize">
          {nodeType === 'a2a' ? 'A2A Agent' : nodeType} Details
        </h3>
        <button
          onClick={onClose}
          className="p-1 text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800 rounded transition-colors"
        >
          <X className="h-4 w-4" />
        </button>
      </div>

      <div className="p-4">{content}</div>
    </div>
  )
}
