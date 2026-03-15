import { useState, useEffect, useCallback } from 'react'
import { Link } from 'react-router-dom'
import {
  X,
  Bot,
  Sparkles,
  Server,
  ExternalLink,
  Save,
  Wifi,
} from 'lucide-react'
import type { ProjectGraphData } from '@/types'

type NodeType = 'agent' | 'skill' | 'mcp' | 'a2a' | 'provider'

interface BaseProps {
  nodeId: string
  nodeType: NodeType
  data: ProjectGraphData
  onClose: () => void
  onAgentUpdate?: (agentId: number, updates: { custom_instructions?: string }) => void
}

// --- Agent detail ---
function AgentDetail({
  agent,
  data,
  onUpdate,
}: {
  agent: ProjectGraphData['agents'][0]
  data: ProjectGraphData
  onUpdate?: (agentId: number, updates: { custom_instructions?: string }) => void
}) {
  const [instructions, setInstructions] = useState(agent.objective_template ?? '')
  const [saving, setSaving] = useState(false)

  const assignedSkills = data.skills.filter((s) => agent.skill_ids.includes(s.id))
  const assignedMcp = data.mcp_servers.filter((m) => agent.mcp_server_ids?.includes(m.id))

  const handleSave = useCallback(() => {
    if (!onUpdate) return
    setSaving(true)
    onUpdate(agent.id, { custom_instructions: instructions })
    // Simulate async with timeout since we don't have a real promise
    setTimeout(() => setSaving(false), 500)
  }, [agent.id, instructions, onUpdate])

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center gap-2">
        <Bot className="h-5 w-5 text-violet-400" />
        <div>
          <h4 className="text-sm font-semibold text-violet-100">{agent.name}</h4>
          <p className="text-[11px] text-zinc-500">{agent.slug}</p>
        </div>
      </div>

      {/* Metadata */}
      <div className="grid grid-cols-2 gap-2">
        <InfoField label="Role" value={agent.role} />
        <InfoField label="Model" value={agent.model ?? 'default'} />
        <InfoField label="Planning" value={agent.planning_mode} />
        <InfoField label="Context" value={agent.context_strategy} />
        <InfoField label="Loop" value={agent.loop_condition} />
        <InfoField label="Max Iter" value={agent.max_iterations?.toString() ?? 'none'} />
      </div>

      {/* Enabled / Delegate badges */}
      <div className="flex gap-2">
        <span
          className={`text-[10px] px-2 py-0.5 rounded-full ${
            agent.is_enabled
              ? 'bg-emerald-900/50 text-emerald-300'
              : 'bg-zinc-800 text-zinc-500'
          }`}
        >
          {agent.is_enabled ? 'Enabled' : 'Disabled'}
        </span>
        {agent.can_delegate && (
          <span className="text-[10px] px-2 py-0.5 rounded-full bg-violet-900/50 text-violet-300">
            Can Delegate
          </span>
        )}
      </div>

      {/* System prompt / instructions */}
      <div className="space-y-1.5">
        <label className="text-xs font-medium text-zinc-400">Objective / Instructions</label>
        <textarea
          value={instructions}
          onChange={(e) => setInstructions(e.target.value)}
          className="w-full h-28 px-3 py-2 text-xs bg-zinc-800 border border-zinc-700 rounded-md text-zinc-200 placeholder-zinc-500 focus:border-violet-600 focus:ring-1 focus:ring-violet-600 resize-none"
          placeholder="Agent objective or custom instructions..."
        />
        {onUpdate && (
          <button
            onClick={handleSave}
            disabled={saving}
            className="flex items-center gap-1.5 px-3 py-1.5 bg-violet-700 hover:bg-violet-600 text-white text-xs font-medium rounded-md transition-colors disabled:opacity-50"
          >
            <Save className="h-3 w-3" />
            {saving ? 'Saving...' : 'Save'}
          </button>
        )}
      </div>

      {/* Assigned skills */}
      <div className="space-y-1.5">
        <label className="text-xs font-medium text-zinc-400">
          Assigned Skills ({assignedSkills.length})
        </label>
        <div className="space-y-1 max-h-40 overflow-y-auto">
          {assignedSkills.length === 0 && (
            <p className="text-[11px] text-zinc-600 italic">No skills assigned</p>
          )}
          {assignedSkills.map((skill) => (
            <div
              key={skill.id}
              className="flex items-center justify-between px-2 py-1.5 bg-zinc-800/60 rounded text-xs"
            >
              <span className="text-emerald-300 truncate">{skill.name}</span>
              <span className="text-zinc-500 text-[10px]">{skill.token_estimate} tok</span>
            </div>
          ))}
        </div>
      </div>

      {/* Assigned MCP servers */}
      {assignedMcp.length > 0 && (
        <div className="space-y-1.5">
          <label className="text-xs font-medium text-zinc-400">
            MCP Servers ({assignedMcp.length})
          </label>
          <div className="space-y-1">
            {assignedMcp.map((mcp) => (
              <div
                key={mcp.id}
                className="flex items-center gap-2 px-2 py-1.5 bg-zinc-800/60 rounded text-xs"
              >
                <Server className="h-3 w-3 text-pink-400 shrink-0" />
                <span className="text-pink-300 truncate">{mcp.name}</span>
                <span className="text-zinc-500 text-[10px]">{mcp.transport}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}

// --- Skill detail ---
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

// --- MCP detail ---
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

// --- A2A agent detail ---
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

// --- Provider detail ---
function ProviderDetail({ provider, outputs }: { provider: ProjectGraphData['providers'][0]; outputs: string[] }) {
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
            <div key={path} className="text-[10px] font-mono text-zinc-400 px-2 py-1 bg-zinc-800/60 rounded truncate">
              {path}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

// --- Info field helper ---
function InfoField({ label, value }: { label: string; value: string }) {
  return (
    <div className="px-2 py-1.5 bg-zinc-800/40 rounded">
      <p className="text-[10px] text-zinc-500">{label}</p>
      <p className="text-xs text-zinc-300 truncate">{value}</p>
    </div>
  )
}

// --- Main Panel ---
export default function NodeDetailPanel({ nodeId, nodeType, data, onClose, onAgentUpdate }: BaseProps) {
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
      content = <AgentDetail agent={agent} data={data} onUpdate={onAgentUpdate} />
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
      content = <ProviderDetail provider={provider} outputs={data.sync_outputs[provider.slug] ?? []} />
    }
  }

  if (!content) {
    content = (
      <div className="text-xs text-zinc-500 italic">
        No details available for this node.
      </div>
    )
  }

  return (
    <div
      className="fixed top-0 right-0 h-full w-[400px] bg-zinc-900 border-l border-zinc-700 shadow-2xl z-50 overflow-y-auto transition-transform duration-200 animate-slide-in-right"
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
