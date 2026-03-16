import { useMemo, useCallback, useState, useRef, useEffect } from 'react'
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  type Node,
  type Edge,
  type NodeTypes,
  type EdgeTypes,
  type OnNodesChange,
  type OnEdgesChange,
  type OnConnect,
  type Connection,
  Position,
  MarkerType,
  useReactFlow,
  ReactFlowProvider,
  applyNodeChanges,
  applyEdgeChanges,
  addEdge,
} from '@xyflow/react'
import '@xyflow/react/dist/style.css'
import {
  Bot,
  Sparkles,
  Server,
  Wifi,
  GripVertical,
  LayoutDashboard,
  Maximize,
  Minimize,
  ZoomIn,
  ZoomOut,
  Locate,
  ChevronDown,
  ChevronRight,
  Plus,
  Check,
  Search,
  X,
  Undo2,
  Redo2,
  Trash2,
  Settings2,
  Unplug,
  Pencil,
  MousePointerSquareDashed,
} from 'lucide-react'
import type { ProjectGraphData } from '@/types'
import { assignAgentSkills, bindAgentMcpServers, bindAgentA2aAgents, saveCanvasLayout } from '@/api/client'
import { AgentNode, SkillNode, ProviderNode, McpNode, ProjectNode, LaneLabel } from './FlowNodes'
import DelegationEdge, { type DelegationEdgeData } from './DelegationEdge'
import EdgeConfigPanel, { type EdgeConfigData } from './EdgeConfigPanel'
import NodeDetailPanel from './NodeDetailPanel'
import {
  detectDelegationChains,
  findChainsForNode,
  collectChainElements,
  getEdgeStepNumbers,
} from './chainUtils'

// Column X positions for the layered layout
const LANE = {
  agents: 0,
  skills: 400,
  providers: 850,
  mcp: 850,
} as const

const NODE_GAP_Y = 110
const SKILL_GAP_Y = 80

const nodeTypes: NodeTypes = {
  agentNode: AgentNode,
  skillNode: SkillNode,
  providerNode: ProviderNode,
  mcpNode: McpNode,
  projectNode: ProjectNode,
  laneLabel: LaneLabel,
}

const edgeTypes: EdgeTypes = {
  delegation: DelegationEdge,
}

interface Props {
  data: ProjectGraphData
  height?: number
  onNodeClick?: (nodeId: string, type: string) => void
  projectId?: number
  onRefresh?: () => void
}

// ─── Dagre-style auto-layout (simple LR directed graph layout) ─────
// We implement a basic layered layout without requiring the dagre package.
// Assigns layers based on node type, then spaces nodes within each layer.
function autoLayout(nodes: Node[], edges: Edge[]): Node[] {
  const layerX: Record<string, number> = {
    agentNode: 0,
    skillNode: 450,
    providerNode: 950,
    mcpNode: 950,
    projectNode: -300,
    laneLabel: -1, // skip
  }

  // Group nodes by layer
  const layers = new Map<number, Node[]>()
  const nonLayoutNodes: Node[] = []

  for (const node of nodes) {
    if (node.type === 'laneLabel') {
      nonLayoutNodes.push(node)
      continue
    }
    const x = layerX[node.type ?? ''] ?? 0
    if (!layers.has(x)) layers.set(x, [])
    layers.get(x)!.push(node)
  }

  // For agent nodes, sort by connectivity (most connected first)
  const edgeCount = new Map<string, number>()
  for (const edge of edges) {
    edgeCount.set(edge.source, (edgeCount.get(edge.source) ?? 0) + 1)
    edgeCount.set(edge.target, (edgeCount.get(edge.target) ?? 0) + 1)
  }

  const updatedNodes: Node[] = [...nonLayoutNodes]

  for (const [x, layerNodes] of layers) {
    // Sort by edge count descending for better layout
    layerNodes.sort((a, b) => (edgeCount.get(b.id) ?? 0) - (edgeCount.get(a.id) ?? 0))

    const gap = x === layerX.skillNode ? SKILL_GAP_Y : NODE_GAP_Y
    let y = 60

    for (const node of layerNodes) {
      updatedNodes.push({
        ...node,
        position: { x, y },
      })
      y += gap
    }
  }

  // Update lane labels to match new positions
  return updatedNodes.map((node) => {
    if (node.id === 'lane-agents') return { ...node, position: { x: layerX.agentNode, y: 0 } }
    if (node.id === 'lane-skills') return { ...node, position: { x: layerX.skillNode, y: 0 } }
    if (node.id === 'lane-integrations') return { ...node, position: { x: layerX.providerNode, y: 0 } }
    return node
  })
}

// ─── Build graph from data ─────────────────────────────────────────
function buildGraph(data: ProjectGraphData) {
  const nodes: Node[] = []
  const edges: Edge[] = []

  const skillIdMap = new Map(data.skills.map((s) => [s.id, s]))
  const enabledAgents = data.agents.filter((a) => a.is_enabled)
  const assignedSkillIds = new Set(enabledAgents.flatMap((a) => a.skill_ids))
  const unassignedSkills = data.skills.filter((s) => !assignedSkillIds.has(s.id))

  // Build agent → skill names map for chip display
  const agentSkillNames = new Map<number, Array<{ id: number; name: string }>>()
  for (const agent of enabledAgents) {
    const names: Array<{ id: number; name: string }> = []
    for (const skillId of agent.skill_ids) {
      const skill = skillIdMap.get(skillId)
      if (skill) names.push({ id: skill.id, name: skill.name })
    }
    agentSkillNames.set(agent.id, names)
  }

  let agentY = 60
  let skillY = 60
  let providerY = 60

  // Lane labels
  nodes.push({
    id: 'lane-agents',
    type: 'laneLabel',
    position: { x: LANE.agents, y: 0 },
    data: { label: 'Agents', count: enabledAgents.length },
    draggable: false,
    selectable: false,
  })
  nodes.push({
    id: 'lane-skills',
    type: 'laneLabel',
    position: { x: LANE.skills, y: 0 },
    data: { label: 'Skills', count: data.skills.length },
    draggable: false,
    selectable: false,
  })
  nodes.push({
    id: 'lane-integrations',
    type: 'laneLabel',
    position: { x: LANE.providers, y: 0 },
    data: {
      label: 'Integrations',
      count: data.providers.length + data.mcp_servers.length + (data.a2a_agents?.length ?? 0),
    },
    draggable: false,
    selectable: false,
  })

  const skillPositions = new Map<number, { x: number; y: number }>()

  // Place agents and their assigned skills (skip disabled agents)
  data.agents.filter((a) => a.is_enabled).forEach((agent) => {
    const agentNodeY = agentY

    nodes.push({
      id: `agent-${agent.id}`,
      type: 'agentNode',
      position: { x: LANE.agents, y: agentNodeY },
      draggable: true,
      data: {
        label: agent.name,
        role: agent.role,
        icon: agent.icon,
        persona: agent.persona,
        displayName: agent.display_name,
        isEnabled: agent.is_enabled,
        hasCustomInstructions: agent.has_custom_instructions,
        skillCount: agent.skill_ids.length,
        model: agent.model,
        planningMode: agent.planning_mode,
        contextStrategy: agent.context_strategy,
        loopCondition: agent.loop_condition,
        maxIterations: agent.max_iterations,
        canDelegate: agent.can_delegate,
        hasLoopConfig: agent.has_loop_config,
        mcpCount: agent.mcp_server_ids?.length ?? 0,
        a2aCount: agent.a2a_agent_ids?.length ?? 0,
        assignedSkills: agentSkillNames.get(agent.id) ?? [],
      },
      sourcePosition: Position.Right,
      targetPosition: Position.Left,
    })

    agent.skill_ids.forEach((skillId) => {
      if (skillPositions.has(skillId)) {
        edges.push({
          id: `e-agent-${agent.id}-skill-${skillId}`,
          source: `agent-${agent.id}`,
          target: `skill-${skillId}`,
          type: 'smoothstep',
          animated: false,
          style: { stroke: '#8b5cf6', strokeWidth: 1.5 },
          markerEnd: { type: MarkerType.ArrowClosed, color: '#8b5cf6', width: 14, height: 14 },
        })
        return
      }

      const skill = skillIdMap.get(skillId)
      if (!skill) return

      skillPositions.set(skillId, { x: LANE.skills, y: skillY })

      nodes.push({
        id: `skill-${skillId}`,
        type: 'skillNode',
        position: { x: LANE.skills, y: skillY },
        draggable: true,
        data: {
          label: skill.name,
          slug: skill.slug,
          tokenEstimate: skill.token_estimate,
          tags: skill.tags,
          includeCount: skill.includes.length,
          hasConditions: !!skill.conditions,
          isCircular: data.circular_deps.includes(skill.slug),
          model: skill.model,
        },
        sourcePosition: Position.Right,
        targetPosition: Position.Left,
      })

      edges.push({
        id: `e-agent-${agent.id}-skill-${skillId}`,
        source: `agent-${agent.id}`,
        target: `skill-${skillId}`,
        type: 'smoothstep',
        animated: false,
        style: { stroke: '#8b5cf6', strokeWidth: 1.5 },
        markerEnd: { type: MarkerType.ArrowClosed, color: '#8b5cf6', width: 14, height: 14 },
      })

      skillY += SKILL_GAP_Y
    })

    agentY = Math.max(agentY + NODE_GAP_Y, skillY)
    skillY = Math.max(skillY, agentY)
  })

  // Unassigned skills
  unassignedSkills.forEach((skill) => {
    skillPositions.set(skill.id, { x: LANE.skills, y: skillY })

    nodes.push({
      id: `skill-${skill.id}`,
      type: 'skillNode',
      position: { x: LANE.skills, y: skillY },
      draggable: true,
      data: {
        label: skill.name,
        slug: skill.slug,
        tokenEstimate: skill.token_estimate,
        tags: skill.tags,
        includeCount: skill.includes.length,
        hasConditions: !!skill.conditions,
        isCircular: data.circular_deps.includes(skill.slug),
        model: skill.model,
        unassigned: true,
      },
      sourcePosition: Position.Right,
      targetPosition: Position.Left,
    })

    skillY += SKILL_GAP_Y
  })

  // Skill include edges
  data.skill_edges.forEach((edge) => {
    edges.push({
      id: `e-include-${edge.source}-${edge.target}`,
      source: `skill-${edge.source}`,
      target: `skill-${edge.target}`,
      type: 'smoothstep',
      animated: false,
      style: { stroke: '#10b981', strokeWidth: 1.5, strokeDasharray: '6 3' },
      markerEnd: { type: MarkerType.ArrowClosed, color: '#10b981', width: 12, height: 12 },
      label: 'includes',
      labelStyle: { fill: '#6b7280', fontSize: 10 },
      labelBgStyle: { fill: '#18181b', fillOpacity: 0.9 },
      labelBgPadding: [4, 2] as [number, number],
    })
  })

  // Providers
  data.providers.forEach((provider) => {
    const outputCount = (data.sync_outputs[provider.slug] ?? []).length
    const outputs = data.sync_outputs[provider.slug] ?? []

    nodes.push({
      id: `provider-${provider.slug}`,
      type: 'providerNode',
      position: { x: LANE.providers, y: providerY },
      draggable: true,
      data: {
        label: provider.name,
        slug: provider.slug,
        outputCount,
        outputs,
      },
      targetPosition: Position.Left,
    })

    providerY += NODE_GAP_Y
  })

  // MCP servers
  data.mcp_servers.forEach((mcp) => {
    nodes.push({
      id: `mcp-${mcp.id}`,
      type: 'mcpNode',
      position: { x: LANE.mcp, y: providerY },
      draggable: true,
      data: {
        label: mcp.name,
        transport: mcp.transport,
      },
      targetPosition: Position.Left,
    })

    providerY += NODE_GAP_Y
  })

  // A2A agents
  ;(data.a2a_agents ?? []).forEach((a2a) => {
    nodes.push({
      id: `a2a-${a2a.id}`,
      type: 'mcpNode',
      position: { x: LANE.mcp, y: providerY },
      draggable: true,
      data: {
        label: a2a.name,
        transport: 'A2A',
      },
      targetPosition: Position.Left,
    })

    providerY += NODE_GAP_Y
  })

  // Agent -> MCP server edges
  data.agent_edges
    .filter((e) => e.type === 'uses_tool' && e.target_type === 'mcp_server')
    .forEach((edge) => {
      edges.push({
        id: `e-agent-mcp-${edge.source}-${edge.target}`,
        source: `agent-${edge.source}`,
        target: `mcp-${edge.target}`,
        type: 'smoothstep',
        animated: false,
        style: { stroke: '#ec4899', strokeWidth: 1.5, strokeDasharray: '4 3' },
        markerEnd: { type: MarkerType.ArrowClosed, color: '#ec4899', width: 12, height: 12 },
      })
    })

  // Agent -> A2A agent edges (use custom delegation edge type)
  const a2aDelegationEdges = data.agent_edges.filter(
    (e) => e.type === 'delegates_to' && e.target_type === 'a2a_agent',
  )
  const a2aDelegationPairs = new Set<string>()
  a2aDelegationEdges.forEach((e) => {
    a2aDelegationPairs.add(`agent-${e.source}->a2a-${e.target}`)
  })
  a2aDelegationEdges.forEach((edge) => {
    const reverseKey = `a2a-${edge.target}->agent-${edge.source}`
    const isBidirectional = a2aDelegationPairs.has(reverseKey)
    edges.push({
      id: `e-delegation-agent-${edge.source}-a2a-${edge.target}`,
      source: `agent-${edge.source}`,
      target: `a2a-${edge.target}`,
      type: 'delegation',
      data: {
        isDelegation: true,
        label: 'delegates to',
        isBidirectional,
      } satisfies DelegationEdgeData,
    })
  })

  // Parent -> child agent edges (use custom delegation edge type)
  const parentChildEdges = data.agent_edges.filter(
    (e) => e.type === 'parent_of' && e.target_type === 'agent',
  )
  const agentDelegationPairs = new Set<string>()
  parentChildEdges.forEach((e) => {
    agentDelegationPairs.add(`${e.source}->${e.target}`)
  })
  parentChildEdges.forEach((edge) => {
    const reverseKey = `${edge.target}->${edge.source}`
    const isBidirectional = agentDelegationPairs.has(reverseKey)
    edges.push({
      id: `e-delegation-agent-${edge.source}-agent-${edge.target}`,
      source: `agent-${edge.source}`,
      target: `agent-${edge.target}`,
      type: 'delegation',
      data: {
        isDelegation: true,
        label: 'delegates',
        isBidirectional,
      } satisfies DelegationEdgeData,
    })
  })

  // Summary sync edges
  if (data.providers.length > 0 && data.skills.length > 0) {
    const midSkillIdx = Math.floor(data.skills.length / 2)
    const midSkill = data.skills[midSkillIdx]
    if (midSkill) {
      data.providers.forEach((provider) => {
        edges.push({
          id: `e-sync-${provider.slug}`,
          source: `skill-${midSkill.id}`,
          target: `provider-${provider.slug}`,
          type: 'smoothstep',
          animated: true,
          style: { stroke: '#f59e0b', strokeWidth: 1.5, opacity: 0.5 },
          markerEnd: { type: MarkerType.ArrowClosed, color: '#f59e0b', width: 12, height: 12 },
          label: `sync ${data.skills.length} skills`,
          labelStyle: { fill: '#9ca3af', fontSize: 10 },
          labelBgStyle: { fill: '#18181b', fillOpacity: 0.9 },
          labelBgPadding: [4, 2] as [number, number],
        })
      })
    }
  }

  return { nodes, edges }
}

// ─── Palette sidebar section ───────────────────────────────────────
interface PaletteSectionProps {
  title: string
  icon: React.ReactNode
  items: Array<{ id: string; name: string; type: string }>
  onDragStart: (e: React.DragEvent, item: { id: string; name: string; type: string }) => void
  onAdd?: () => void
  defaultOpen?: boolean
}

function PaletteSection({ title, icon, items, onDragStart, onAdd, defaultOpen = true }: PaletteSectionProps) {
  const [isOpen, setIsOpen] = useState(defaultOpen)

  return (
    <div className="mb-2">
      <div className="flex items-center">
        <button
          onClick={() => setIsOpen(!isOpen)}
          className="flex items-center gap-1.5 flex-1 px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-400 hover:text-zinc-300 transition-colors"
        >
          {isOpen ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
          {icon}
          <span>{title}</span>
          <span className="ml-auto text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-500 font-normal">
            {items.length}
          </span>
        </button>
        {onAdd && (
          <button
            onClick={(e) => {
              e.stopPropagation()
              onAdd()
            }}
            className="p-1 mr-1 text-zinc-500 hover:text-violet-400 hover:bg-zinc-800 rounded transition-colors"
            title={`Create new ${title.toLowerCase().replace(/s$/, '')}`}
          >
            <Plus className="h-3 w-3" />
          </button>
        )}
      </div>
      {isOpen && items.length > 0 && (
        <div className="space-y-1 px-1 mt-1">
          {items.map((item) => (
            <div
              key={item.id}
              draggable
              onDragStart={(e) => onDragStart(e, item)}
              className="flex items-center gap-2 px-2 py-1.5 rounded-md border border-zinc-800 bg-zinc-900/50 hover:bg-zinc-800/70 hover:border-zinc-700 cursor-grab active:cursor-grabbing transition-all text-xs text-zinc-300 group"
            >
              <GripVertical className="h-3 w-3 text-zinc-600 group-hover:text-zinc-400 shrink-0" />
              <span className="truncate">{item.name}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

// ─── Canvas toolbar ────────────────────────────────────────────────
interface ToolbarProps {
  onAutoLayout: () => void
  onFitView: () => void
  onZoomIn: () => void
  onZoomOut: () => void
  isFullscreen: boolean
  onToggleFullscreen: () => void
  onUndo?: () => void
  onRedo?: () => void
  canUndo?: boolean
  canRedo?: boolean
  savedIndicator?: boolean
  // #369 — Node search & filter
  filterSearch: string
  onFilterSearchChange: (v: string) => void
  filterTypes: Set<string>
  onToggleFilterType: (t: string) => void
  onClearFilters: () => void
  hasActiveFilter: boolean
}

const FILTER_TYPE_BUTTONS: Array<{ key: string; label: string; color: string }> = [
  { key: 'agent', label: 'A', color: 'text-violet-400 border-violet-500/60 bg-violet-500/10' },
  { key: 'skill', label: 'S', color: 'text-emerald-400 border-emerald-500/60 bg-emerald-500/10' },
  { key: 'mcp', label: 'M', color: 'text-pink-400 border-pink-500/60 bg-pink-500/10' },
  { key: 'a2a', label: 'T', color: 'text-cyan-400 border-cyan-500/60 bg-cyan-500/10' },
]

function CanvasToolbar({
  onAutoLayout,
  onFitView,
  onZoomIn,
  onZoomOut,
  isFullscreen,
  onToggleFullscreen,
  onUndo,
  onRedo,
  canUndo,
  canRedo,
  savedIndicator,
  filterSearch,
  onFilterSearchChange,
  filterTypes,
  onToggleFilterType,
  onClearFilters,
  hasActiveFilter,
}: ToolbarProps) {
  return (
    <div className="absolute top-3 right-3 z-10 flex items-center gap-1 bg-zinc-900/90 border border-zinc-700 rounded-lg px-1 py-1 shadow-lg backdrop-blur-sm">
      {/* #369 — Node search */}
      <div className="relative flex items-center">
        <Search className="absolute left-2 h-3 w-3 text-zinc-500 pointer-events-none" />
        <input
          type="text"
          value={filterSearch}
          onChange={(e) => onFilterSearchChange(e.target.value)}
          placeholder="Filter nodes..."
          className="w-[140px] pl-6 pr-6 py-1 text-[11px] bg-zinc-800/80 border border-zinc-700 rounded text-zinc-300 placeholder-zinc-600 focus:outline-none focus:border-violet-500/50"
        />
        {hasActiveFilter && (
          <button
            onClick={onClearFilters}
            className="absolute right-1 p-0.5 text-zinc-500 hover:text-zinc-300 transition-colors"
            title="Clear filters"
          >
            <X className="h-3 w-3" />
          </button>
        )}
      </div>
      {/* #369 — Type filter toggles */}
      <div className="flex items-center gap-0.5 ml-0.5">
        {FILTER_TYPE_BUTTONS.map((ft) => (
          <button
            key={ft.key}
            onClick={() => onToggleFilterType(ft.key)}
            className={`w-5 h-5 flex items-center justify-center text-[10px] font-bold rounded border transition-all ${
              filterTypes.has(ft.key)
                ? ft.color
                : 'text-zinc-600 border-zinc-700 bg-zinc-800/40 opacity-50'
            }`}
            title={`Toggle ${ft.key} nodes`}
          >
            {ft.label}
          </button>
        ))}
      </div>
      <div className="w-px h-4 bg-zinc-700" />
      {/* #366 — Undo/Redo */}
      <button
        onClick={onUndo}
        disabled={!canUndo}
        className="p-1 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
        title="Undo (Cmd+Z)"
      >
        <Undo2 className="h-3.5 w-3.5" />
      </button>
      <button
        onClick={onRedo}
        disabled={!canRedo}
        className="p-1 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
        title="Redo (Cmd+Shift+Z)"
      >
        <Redo2 className="h-3.5 w-3.5" />
      </button>
      <div className="w-px h-4 bg-zinc-700" />
      <button
        onClick={onAutoLayout}
        className="flex items-center gap-1.5 px-2 py-1 text-[11px] text-zinc-300 hover:text-white hover:bg-zinc-800 rounded transition-colors"
        title="Auto Layout (L)"
      >
        <LayoutDashboard className="h-3.5 w-3.5" />
        <span>Auto Layout</span>
      </button>
      <div className="w-px h-4 bg-zinc-700" />
      <button
        onClick={onZoomIn}
        className="p-1 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors"
        title="Zoom In (+)"
      >
        <ZoomIn className="h-3.5 w-3.5" />
      </button>
      <button
        onClick={onZoomOut}
        className="p-1 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors"
        title="Zoom Out (-)"
      >
        <ZoomOut className="h-3.5 w-3.5" />
      </button>
      <button
        onClick={onFitView}
        className="p-1 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors"
        title="Fit to View (F)"
      >
        <Locate className="h-3.5 w-3.5" />
      </button>
      <div className="w-px h-4 bg-zinc-700" />
      <button
        onClick={onToggleFullscreen}
        className="p-1 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors"
        title={isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'}
      >
        {isFullscreen ? <Minimize className="h-3.5 w-3.5" /> : <Maximize className="h-3.5 w-3.5" />}
      </button>
      {/* #367 — Saved indicator */}
      {savedIndicator && (
        <div className="flex items-center gap-1 px-1.5 py-0.5 text-[10px] text-emerald-400 animate-fade-in">
          <Check className="h-3 w-3" />
          <span>Saved</span>
        </div>
      )}
    </div>
  )
}

// ─── Context Menu (#364) ─────────────────────────────────────────
interface ContextMenuState {
  x: number
  y: number
  type: 'node' | 'edge' | 'canvas'
  id?: string
  nodeType?: string
}

interface ContextMenuProps {
  menu: ContextMenuState
  onClose: () => void
  onEditNode?: () => void
  onDeleteNode?: () => void
  onConfigureEdge?: () => void
  onRemoveEdge?: () => void
  onCreateAgent?: () => void
  onCreateSkill?: () => void
  onCreateMcp?: () => void
  onCreateA2a?: () => void
  onAutoLayout?: () => void
  onFitView?: () => void
}

function CanvasContextMenu({
  menu,
  onClose,
  onEditNode,
  onDeleteNode,
  onConfigureEdge,
  onRemoveEdge,
  onCreateAgent,
  onCreateSkill,
  onCreateMcp,
  onCreateA2a,
  onAutoLayout,
  onFitView,
}: ContextMenuProps) {
  useEffect(() => {
    const handleClick = () => onClose()
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('click', handleClick)
    window.addEventListener('keydown', handleKey)
    return () => {
      window.removeEventListener('click', handleClick)
      window.removeEventListener('keydown', handleKey)
    }
  }, [onClose])

  const items: Array<{ label: string; icon: React.ReactNode; onClick?: () => void; danger?: boolean }> = []

  if (menu.type === 'node') {
    items.push({ label: 'Edit', icon: <Pencil className="h-3.5 w-3.5" />, onClick: onEditNode })
    items.push({ label: 'Delete', icon: <Trash2 className="h-3.5 w-3.5" />, onClick: onDeleteNode, danger: true })
  } else if (menu.type === 'edge') {
    items.push({ label: 'Configure', icon: <Settings2 className="h-3.5 w-3.5" />, onClick: onConfigureEdge })
    items.push({ label: 'Remove Connection', icon: <Unplug className="h-3.5 w-3.5" />, onClick: onRemoveEdge, danger: true })
  } else if (menu.type === 'canvas') {
    items.push({ label: 'Create Agent', icon: <Bot className="h-3.5 w-3.5" />, onClick: onCreateAgent })
    items.push({ label: 'Create Skill', icon: <Sparkles className="h-3.5 w-3.5" />, onClick: onCreateSkill })
    items.push({ label: 'Create MCP Server', icon: <Server className="h-3.5 w-3.5" />, onClick: onCreateMcp })
    items.push({ label: 'Create A2A Agent', icon: <Wifi className="h-3.5 w-3.5" />, onClick: onCreateA2a })
    items.push({ label: 'Auto Layout', icon: <LayoutDashboard className="h-3.5 w-3.5" />, onClick: onAutoLayout })
    items.push({ label: 'Fit to View', icon: <Locate className="h-3.5 w-3.5" />, onClick: onFitView })
  }

  return (
    <div
      className="absolute z-50 min-w-[160px] bg-zinc-800 border border-zinc-700 rounded-lg shadow-xl py-1"
      style={{ left: menu.x, top: menu.y }}
      onClick={(e) => e.stopPropagation()}
    >
      {items.map((item, i) => (
        <button
          key={i}
          onClick={(e) => {
            e.stopPropagation()
            item.onClick?.()
            onClose()
          }}
          className={`w-full flex items-center gap-2 px-3 py-1.5 text-sm transition-colors ${
            item.danger
              ? 'text-red-400 hover:bg-red-500/10 hover:text-red-300'
              : 'text-zinc-300 hover:bg-zinc-700 hover:text-white'
          }`}
        >
          {item.icon}
          <span>{item.label}</span>
        </button>
      ))}
    </div>
  )
}

// ─── Undo/Redo types (#366) ─────────────────────────────────────
interface UndoOperation {
  type: string
  undo: () => void
  redo: () => void
}

// ─── Main interactive graph component ──────────────────────────────
function FlowGraphInner({ data, height = 500, onNodeClick, projectId, onRefresh }: Props) {
  const initialGraph = useMemo(() => buildGraph(data), [data])
  const [nodes, setNodes] = useState<Node[]>(initialGraph.nodes)
  const [edges, setEdges] = useState<Edge[]>(initialGraph.edges)
  const [isFullscreen, setIsFullscreen] = useState(false)
  const [dropTargetNodeId, setDropTargetNodeId] = useState<string | null>(null)
  const containerRef = useRef<HTMLDivElement>(null)
  const reactFlowWrapper = useRef<HTMLDivElement>(null)
  const { fitView, zoomIn, zoomOut, screenToFlowPosition } = useReactFlow()

  // ─── Panel & chain state (#291-#294) ─────────────────────────────
  const [edgeConfigs, setEdgeConfigs] = useState<Map<string, EdgeConfigData>>(new Map())
  const [selectedEdgeId, setSelectedEdgeId] = useState<string | null>(null)
  const [selectedNodeInfo, setSelectedNodeInfo] = useState<{ id: string; type: string } | null>(null)
  const [hoveredNodeId, setHoveredNodeId] = useState<string | null>(null)

  // ─── Create mode (#348) ─────────────────────────────────────────
  const [createMode, setCreateMode] = useState<'agent' | 'skill' | 'mcp' | 'a2a' | null>(null)

  // ─── Context menu (#364) ──────────────────────────────────────
  const [contextMenu, setContextMenu] = useState<ContextMenuState | null>(null)

  // ─── Undo/Redo (#366) ────────────────────────────────────────
  const undoStackRef = useRef<UndoOperation[]>([])
  const redoStackRef = useRef<UndoOperation[]>([])
  const [undoRedoVersion, setUndoRedoVersion] = useState(0)

  // ─── Auto-save (#367) ────────────────────────────────────────
  const autoSaveTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const [savedIndicator, setSavedIndicator] = useState(false)
  const savedIndicatorTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const isBulkOperationRef = useRef(false)

  // ─── Node filter (#369) ──────────────────────────────────────
  const [filterSearch, setFilterSearch] = useState('')
  const [filterTypes, setFilterTypes] = useState<Set<string>>(new Set(['agent', 'skill', 'mcp', 'a2a']))

  // ─── Position tracking for undo (#366) ───────────────────────
  const positionBatchRef = useRef<Map<string, { before: { x: number; y: number }; after: { x: number; y: number } }> | null>(null)
  const positionTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  // Chain detection (#293)
  const chains = useMemo(() => detectDelegationChains(edges), [edges])
  const edgeStepNumbers = useMemo(() => getEdgeStepNumbers(chains), [chains])

  const highlightedElements = useMemo(() => {
    if (!hoveredNodeId) return { nodeIds: new Set<string>(), edgeIds: new Set<string>() }
    const matchingChains = findChainsForNode(hoveredNodeId, chains)
    return collectChainElements(matchingChains)
  }, [hoveredNodeId, chains])

  const chainTooltip = useMemo(() => {
    if (!hoveredNodeId) return null
    const matchingChains = findChainsForNode(hoveredNodeId, chains)
    if (matchingChains.length === 0) return null
    // Build summary using actual agent names from data
    const chain = matchingChains[0]
    const names = chain.nodeIds.map((nid) => {
      const numId = parseInt(nid.replace(/^[^-]+-/, ''), 10)
      if (nid.startsWith('agent-')) {
        return data.agents.find((a) => a.id === numId)?.name ?? nid
      }
      if (nid.startsWith('a2a-')) {
        return (data.a2a_agents ?? []).find((a) => a.id === numId)?.name ?? nid
      }
      return nid
    })
    return names.join(' -> ')
  }, [hoveredNodeId, chains, data])

  // Apply highlighting to edges
  const processedEdges = useMemo(() => {
    return edges.map((edge) => {
      if (edge.type !== 'delegation') return edge
      const isHighlighted = highlightedElements.edgeIds.has(edge.id)
      const stepNumber = edgeStepNumbers.get(edge.id)
      const edgeData = (edge.data ?? {}) as DelegationEdgeData
      return {
        ...edge,
        data: { ...edgeData, isHighlighted, stepNumber } satisfies DelegationEdgeData,
      }
    })
  }, [edges, highlightedElements, edgeStepNumbers])

  // Apply highlighting class to nodes in chain + filter opacity (#369)
  const processedNodes = useMemo(() => {
    const searchLower = filterSearch.toLowerCase()
    const isFiltering = filterSearch.length > 0 || filterTypes.size < 4

    return nodes.map((node) => {
      let updated = { ...node }

      // Chain highlighting
      if (hoveredNodeId && highlightedElements.nodeIds.size > 0) {
        if (highlightedElements.nodeIds.has(node.id)) {
          updated = { ...updated, className: 'ring-2 ring-cyan-400/60 ring-offset-1 ring-offset-zinc-900 rounded-lg' }
        }
      }

      // #369 — Filter opacity
      if (isFiltering && node.type !== 'laneLabel') {
        const nodePrefix = node.id.split('-')[0] ?? ''
        const typeMap: Record<string, string> = { agent: 'agent', skill: 'skill', mcp: 'mcp', a2a: 'a2a', provider: 'mcp' }
        const filterKey = typeMap[nodePrefix]
        const typeMatch = filterKey ? filterTypes.has(filterKey) : true
        const nameMatch = filterSearch.length === 0 || (node.data.label as string)?.toLowerCase().includes(searchLower)
        if (!typeMatch || !nameMatch) {
          updated = { ...updated, style: { ...updated.style, opacity: 0.1 } }
        } else {
          updated = { ...updated, style: { ...updated.style, opacity: 1 } }
        }
      }

      return updated
    })
  }, [nodes, hoveredNodeId, highlightedElements, filterSearch, filterTypes])

  // ─── Undo/Redo helpers (#366) ──────────────────────────────────
  const pushUndo = useCallback((op: UndoOperation) => {
    undoStackRef.current.push(op)
    if (undoStackRef.current.length > 50) undoStackRef.current.shift()
    redoStackRef.current = []
    setUndoRedoVersion((v) => v + 1)
  }, [])

  const handleUndo = useCallback(() => {
    const op = undoStackRef.current.pop()
    if (!op) return
    op.undo()
    redoStackRef.current.push(op)
    setUndoRedoVersion((v) => v + 1)
  }, [])

  const handleRedo = useCallback(() => {
    const op = redoStackRef.current.pop()
    if (!op) return
    op.redo()
    undoStackRef.current.push(op)
    setUndoRedoVersion((v) => v + 1)
  }, [])

  // ─── Auto-save helper (#367) ─────────────────────────────────
  const triggerAutoSave = useCallback(() => {
    if (!projectId) return
    if (autoSaveTimerRef.current) clearTimeout(autoSaveTimerRef.current)
    autoSaveTimerRef.current = setTimeout(() => {
      setNodes((currentNodes) => {
        const layout: Record<string, { x: number; y: number }> = {}
        for (const n of currentNodes) {
          if (n.type !== 'laneLabel') {
            layout[n.id] = { x: n.position.x, y: n.position.y }
          }
        }
        saveCanvasLayout(projectId, layout).then(() => {
          setSavedIndicator(true)
          if (savedIndicatorTimerRef.current) clearTimeout(savedIndicatorTimerRef.current)
          savedIndicatorTimerRef.current = setTimeout(() => setSavedIndicator(false), 2000)
        }).catch(() => { /* silent */ })
        return currentNodes
      })
    }, 500)
  }, [projectId])

  // ─── Node filter helpers (#369) ──────────────────────────────
  const handleToggleFilterType = useCallback((type: string) => {
    setFilterTypes((prev) => {
      const next = new Set(prev)
      if (next.has(type)) next.delete(type)
      else next.add(type)
      return next
    })
  }, [])

  const handleClearFilters = useCallback(() => {
    setFilterSearch('')
    setFilterTypes(new Set(['agent', 'skill', 'mcp', 'a2a']))
  }, [])

  const hasActiveFilter = filterSearch.length > 0 || filterTypes.size < 4

  // Track which items are on canvas vs palette
  const canvasNodeIds = useMemo(() => new Set(nodes.map((n) => n.id)), [nodes])

  // Palette items: items NOT yet on the canvas
  const paletteAgents = useMemo(
    () =>
      data.agents
        .filter((a) => a.is_enabled && !canvasNodeIds.has(`agent-${a.id}`))
        .map((a) => ({ id: `agent-${a.id}`, name: a.display_name || a.name, type: 'agent' })),
    [data.agents, canvasNodeIds],
  )

  const paletteSkills = useMemo(
    () =>
      data.skills.map((s) => ({ id: `skill-${s.id}`, name: s.name, type: 'skill', numericId: s.id })),
    [data.skills],
  )

  const paletteMcp = useMemo(
    () =>
      data.mcp_servers
        .filter((m) => !canvasNodeIds.has(`mcp-${m.id}`))
        .map((m) => ({ id: `mcp-${m.id}`, name: m.name, type: 'mcp' })),
    [data.mcp_servers, canvasNodeIds],
  )

  const paletteA2a = useMemo(
    () =>
      (data.a2a_agents ?? [])
        .filter((a) => !canvasNodeIds.has(`a2a-${a.id}`))
        .map((a) => ({ id: `a2a-${a.id}`, name: a.name, type: 'a2a' })),
    [data.a2a_agents, canvasNodeIds],
  )

  // Re-sync when data changes
  useEffect(() => {
    const g = buildGraph(data)
    setNodes(g.nodes)
    setEdges(g.edges)
  }, [data])

  // Handle node changes (drag, select, etc.) + position undo (#366) + auto-save (#367)
  const onNodesChange: OnNodesChange = useCallback(
    (changes) => {
      // Track position changes for undo (#366) and auto-save (#367)
      const positionChanges = changes.filter(
        (c) => c.type === 'position' && c.position && !c.dragging,
      )
      if (positionChanges.length > 0 && !isBulkOperationRef.current) {
        triggerAutoSave()
      }

      // Start tracking positions for undo when drag starts
      const dragStarts = changes.filter(
        (c) => c.type === 'position' && c.dragging === true,
      )
      if (dragStarts.length > 0 && !positionBatchRef.current) {
        positionBatchRef.current = new Map()
        setNodes((nds) => {
          for (const change of dragStarts) {
            if (change.type === 'position') {
              const node = nds.find((n) => n.id === change.id)
              if (node && positionBatchRef.current) {
                positionBatchRef.current.set(node.id, {
                  before: { ...node.position },
                  after: { ...node.position },
                })
              }
            }
          }
          return nds
        })
      }

      // Track "after" positions during drag
      const dragMoves = changes.filter(
        (c) => c.type === 'position' && c.dragging === true && c.position,
      )
      for (const change of dragMoves) {
        if (change.type === 'position' && change.position && positionBatchRef.current) {
          const entry = positionBatchRef.current.get(change.id)
          if (entry) entry.after = { ...change.position }
        }
      }

      // Commit position undo when drag ends
      const dragEnds = changes.filter(
        (c) => c.type === 'position' && c.dragging === false,
      )
      if (dragEnds.length > 0 && positionBatchRef.current) {
        const batch = new Map(positionBatchRef.current)
        positionBatchRef.current = null
        // Capture final positions
        for (const change of dragEnds) {
          if (change.type === 'position' && change.position) {
            const entry = batch.get(change.id)
            if (entry) entry.after = { ...change.position }
          }
        }
        pushUndo({
          type: 'position',
          undo: () => {
            setNodes((nds) =>
              nds.map((n) => {
                const entry = batch.get(n.id)
                return entry ? { ...n, position: entry.before } : n
              }),
            )
          },
          redo: () => {
            setNodes((nds) =>
              nds.map((n) => {
                const entry = batch.get(n.id)
                return entry ? { ...n, position: entry.after } : n
              }),
            )
          },
        })
        triggerAutoSave()
      }

      setNodes((nds) => applyNodeChanges(changes, nds))
    },
    [pushUndo, triggerAutoSave],
  )

  const onEdgesChange: OnEdgesChange = useCallback(
    (changes) => setEdges((eds) => applyEdgeChanges(changes, eds)),
    [],
  )

  // ─── Helper: get node type from node ID (#360) ─────────────────────
  const getNodeType = useCallback(
    (nodeId: string): string => {
      const node = nodes.find((n) => n.id === nodeId)
      if (node?.type) return node.type
      const prefix = nodeId.split('-')[0]
      return prefix ? `${prefix}Node` : ''
    },
    [nodes],
  )

  // ─── Connection validation (#360) ──────────────────────────────────
  const isValidConnection = useCallback(
    (connection: Connection | Edge): boolean => {
      const { source, target } = connection
      if (!source || !target) return false

      // Block self-loops
      if (source === target) return false

      // Block duplicate edges (same direction)
      const isDuplicate = edges.some(
        (e) => e.source === source && e.target === target,
      )
      if (isDuplicate) return false

      const sourceType = getNodeType(source)
      const targetType = getNodeType(target)

      // Only agent nodes can be connection sources
      if (sourceType === 'agentNode') {
        // agent -> skill: VALID (#356)
        if (targetType === 'skillNode') return true
        // agent -> mcp or a2a (both use mcpNode type): VALID (#357, #358)
        if (targetType === 'mcpNode') return true
        // agent -> agent: VALID delegation (#359)
        if (targetType === 'agentNode') return true
        // agent -> provider: BLOCKED
        return false
      }

      // All non-agent sources are BLOCKED: skill, mcp, a2a, provider
      return false
    },
    [edges, getNodeType],
  )

  // ─── Handle new edge connections (#356-#359) ───────────────────────
  const onConnect: OnConnect = useCallback(
    (connection: Connection) => {
      const { source, target } = connection
      if (!source || !target) return

      const sourceIsAgent = source.startsWith('agent-')
      const targetIsAgent = target.startsWith('agent-')
      const targetIsSkill = target.startsWith('skill-')
      const targetIsMcp = target.startsWith('mcp-')
      const targetIsA2a = target.startsWith('a2a-')

      // Normalize: always agent as source
      const agentNodeId = sourceIsAgent ? source : target
      if (!agentNodeId.startsWith('agent-')) return

      // ── #356: Agent -> Skill assignment ──────────────────────────
      if (targetIsSkill || (!sourceIsAgent && source.startsWith('skill-'))) {
        const skillNodeId = targetIsSkill ? target : source
        const edgeId = `e-${agentNodeId}-${skillNodeId}`
        if (edges.some((e) => e.id === edgeId)) return

        const newEdge: Edge = {
          id: edgeId,
          source: agentNodeId,
          target: skillNodeId,
          type: 'smoothstep',
          animated: false,
          style: { stroke: '#8b5cf6', strokeWidth: 1.5 },
          markerEnd: { type: MarkerType.ArrowClosed, color: '#8b5cf6', width: 14, height: 14 },
        }

        setEdges((eds) => addEdge(newEdge, eds))

        if (projectId) {
          const agentNumId = parseInt(agentNodeId.replace('agent-', ''))
          const skillNumId = parseInt(skillNodeId.replace('skill-', ''))
          const agent = data.agents.find((a) => a.id === agentNumId)
          if (agent) {
            const newSkillIds = [...new Set([...agent.skill_ids, skillNumId])]
            assignAgentSkills(projectId, agentNumId, newSkillIds)
              .then(() => onRefresh?.())
              .catch(() => setEdges((eds) => eds.filter((e) => e.id !== newEdge.id)))
          }
        }
        return
      }

      // ── #357: Agent -> MCP server binding ────────────────────────
      if (targetIsMcp || (!sourceIsAgent && source.startsWith('mcp-'))) {
        const mcpNodeId = targetIsMcp ? target : source
        const edgeId = `e-agent-mcp-${agentNodeId}-${mcpNodeId}`
        if (edges.some((e) => e.id === edgeId)) return

        const newEdge: Edge = {
          id: edgeId,
          source: agentNodeId,
          target: mcpNodeId,
          type: 'smoothstep',
          animated: false,
          style: { stroke: '#ec4899', strokeWidth: 1.5, strokeDasharray: '4 3' },
          markerEnd: { type: MarkerType.ArrowClosed, color: '#ec4899', width: 12, height: 12 },
        }

        setEdges((eds) => addEdge(newEdge, eds))

        if (projectId) {
          const agentNumId = parseInt(agentNodeId.replace('agent-', ''))
          const mcpNumId = parseInt(mcpNodeId.replace('mcp-', ''))
          const agent = data.agents.find((a) => a.id === agentNumId)
          if (agent) {
            const newMcpIds = [...new Set([...(agent.mcp_server_ids ?? []), mcpNumId])]
            bindAgentMcpServers(projectId, agentNumId, newMcpIds)
              .then(() => onRefresh?.())
              .catch(() => setEdges((eds) => eds.filter((e) => e.id !== newEdge.id)))
          }
        }
        return
      }

      // ── #358: Agent -> A2A delegation ────────────────────────────
      if (targetIsA2a || (!sourceIsAgent && source.startsWith('a2a-'))) {
        const a2aNodeId = targetIsA2a ? target : source
        const edgeId = `e-delegation-${agentNodeId}-${a2aNodeId}`
        if (edges.some((e) => e.id === edgeId)) return

        const reverseExists = edges.some(
          (e) => e.source === a2aNodeId && e.target === agentNodeId && e.type === 'delegation',
        )

        const newEdge: Edge = {
          id: edgeId,
          source: agentNodeId,
          target: a2aNodeId,
          type: 'delegation',
          data: {
            isDelegation: true,
            label: 'delegates to',
            isBidirectional: reverseExists,
          } satisfies DelegationEdgeData,
        }

        setEdges((eds) => {
          let updated = addEdge(newEdge, eds)
          if (reverseExists) {
            updated = updated.map((e) => {
              if (e.source === a2aNodeId && e.target === agentNodeId && e.type === 'delegation') {
                return { ...e, data: { ...(e.data as DelegationEdgeData), isBidirectional: true } }
              }
              return e
            })
          }
          return updated
        })

        if (projectId) {
          const agentNumId = parseInt(agentNodeId.replace('agent-', ''))
          const a2aNumId = parseInt(a2aNodeId.replace('a2a-', ''))
          const agent = data.agents.find((a) => a.id === agentNumId)
          if (agent) {
            const newA2aIds = [...new Set([...(agent.a2a_agent_ids ?? []), a2aNumId])]
            bindAgentA2aAgents(projectId, agentNumId, newA2aIds)
              .then(() => onRefresh?.())
              .catch(() => setEdges((eds) => eds.filter((e) => e.id !== edgeId)))
          }
        }

        // Open edge config panel after creation (#358)
        setSelectedNodeInfo(null)
        setSelectedEdgeId(edgeId)
        return
      }

      // ── #359: Agent -> Agent delegation ──────────────────────────
      if (sourceIsAgent && targetIsAgent && source !== target) {
        const existingEdgeId = `e-delegation-${source}-${target}`
        if (edges.some((e) => e.id === existingEdgeId)) return

        const reverseExists = edges.some(
          (e) => e.source === target && e.target === source && e.type === 'delegation',
        )

        const newEdge: Edge = {
          id: existingEdgeId,
          source,
          target,
          type: 'delegation',
          data: {
            isDelegation: true,
            label: 'delegates to',
            isBidirectional: reverseExists,
          } satisfies DelegationEdgeData,
        }

        setEdges((eds) => {
          let updated = addEdge(newEdge, eds)
          if (reverseExists) {
            updated = updated.map((e) => {
              if (e.source === target && e.target === source && e.type === 'delegation') {
                return { ...e, data: { ...(e.data as DelegationEdgeData), isBidirectional: true } }
              }
              return e
            })
          }
          return updated
        })

        // Open edge config panel for trigger/handoff/return config (#359)
        setSelectedNodeInfo(null)
        setSelectedEdgeId(existingEdgeId)
        return
      }
    },
    [data.agents, projectId, edges, getNodeType, onRefresh],
  )

  // Handle edge click — select edge for config panel or deletion (#292, #354)
  const handleEdgeClick = useCallback(
    (_: React.MouseEvent, edge: Edge) => {
      setSelectedNodeInfo(null) // close node panel
      setCreateMode(null) // close create panel
      setSelectedEdgeId(edge.id)
    },
    [],
  )

  const handleNodeClick = useCallback(
    (_: React.MouseEvent, node: Node) => {
      const [type] = node.id.split('-')
      if (type === 'lane') return

      // Close edge panel, open node panel (#294)
      setSelectedEdgeId(null)
      const entityId = node.id.replace(/^[^-]+-/, '')
      setSelectedNodeInfo({ id: entityId, type: type ?? '' })

      // Also call external handler
      if (onNodeClick) {
        onNodeClick(entityId, type ?? '')
      }
    },
    [onNodeClick],
  )

  // Close all panels on empty canvas click (#294)
  const handlePaneClick = useCallback(() => {
    setSelectedEdgeId(null)
    setSelectedNodeInfo(null)
    setContextMenu(null)
  }, [])

  // Chain hover highlighting (#293)
  const handleNodeMouseEnter = useCallback(
    (_: React.MouseEvent, node: Node) => {
      const [type] = node.id.split('-')
      if (type === 'lane') return
      setHoveredNodeId(node.id)
    },
    [],
  )

  const handleNodeMouseLeave = useCallback(() => {
    setHoveredNodeId(null)
  }, [])

  // Edge config save (#292)
  const handleEdgeConfigSave = useCallback((configData: EdgeConfigData) => {
    setEdgeConfigs((prev) => {
      const next = new Map(prev)
      next.set(configData.edgeId, configData)
      return next
    })
    setSelectedEdgeId(null)
  }, [])

  // Resolve agent name from node ID for edge panel
  const getAgentNameFromNodeId = useCallback(
    (nodeId: string): string => {
      const id = parseInt(nodeId.replace(/^[^-]+-/, ''), 10)
      if (nodeId.startsWith('agent-')) {
        return data.agents.find((a) => a.id === id)?.name ?? nodeId
      }
      if (nodeId.startsWith('a2a-')) {
        return (data.a2a_agents ?? []).find((a) => a.id === id)?.name ?? nodeId
      }
      return nodeId
    },
    [data],
  )

  // Selected edge for config panel
  const selectedEdge = selectedEdgeId ? edges.find((e) => e.id === selectedEdgeId) : null
  const selectedEdgeConfig = selectedEdgeId ? edgeConfigs.get(selectedEdgeId) ?? null : null

  // ─── Drag & Drop from palette ──────────────────────────────────
  const dragItemRef = useRef<{ id: string; name: string; type: string } | null>(null)

  const handlePaletteDragStart = useCallback(
    (e: React.DragEvent, item: { id: string; name: string; type: string }) => {
      dragItemRef.current = item
      e.dataTransfer.setData('application/agentis-canvas', JSON.stringify(item))
      e.dataTransfer.effectAllowed = 'move'
    },
    [],
  )

  const handleDragOver = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault()
      e.dataTransfer.dropEffect = 'move'

      // Check if dragging a skill over an agent node
      const dragData = dragItemRef.current
      if (dragData?.type === 'skill') {
        // Use the native event target to find the closest agent node
        const target = e.target as HTMLElement
        const nodeEl = target.closest('[data-id^="agent-"]')
        if (nodeEl) {
          const nodeId = nodeEl.getAttribute('data-id')
          if (nodeId && nodeId !== dropTargetNodeId) {
            setDropTargetNodeId(nodeId)
            setNodes((nds) =>
              nds.map((n) => ({
                ...n,
                data: {
                  ...n.data,
                  isDropTarget: n.id === nodeId,
                },
              })),
            )
          }
          return
        }
      }

      // Clear drop target
      if (dropTargetNodeId) {
        setDropTargetNodeId(null)
        setNodes((nds) =>
          nds.map((n) => ({
            ...n,
            data: { ...n.data, isDropTarget: false },
          })),
        )
      }
    },
    [dropTargetNodeId],
  )

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault()

      const rawData = e.dataTransfer.getData('application/agentis-canvas')
      if (!rawData) return

      const item = JSON.parse(rawData) as { id: string; name: string; type: string }

      // Clear drop target highlight
      setDropTargetNodeId(null)
      setNodes((nds) =>
        nds.map((n) => ({
          ...n,
          data: { ...n.data, isDropTarget: false },
        })),
      )

      // Check if skill is being dropped onto an agent node
      if (item.type === 'skill') {
        const target = e.target as HTMLElement
        const nodeEl = target.closest('[data-id^="agent-"]')
        if (nodeEl) {
          const agentNodeId = nodeEl.getAttribute('data-id')
          if (agentNodeId && projectId) {
            const agentNumId = parseInt(agentNodeId.replace('agent-', ''))
            const skillNumId = parseInt(item.id.replace('skill-', ''))
            const agent = data.agents.find((a) => a.id === agentNumId)
            if (agent) {
              const newSkillIds = [...new Set([...agent.skill_ids, skillNumId])]
              assignAgentSkills(projectId, agentNumId, newSkillIds).then(() => {
                // Add edge visually
                const edgeId = `e-agent-${agentNumId}-skill-${skillNumId}`
                setEdges((eds) => {
                  if (eds.some((e) => e.id === edgeId)) return eds
                  return [
                    ...eds,
                    {
                      id: edgeId,
                      source: agentNodeId,
                      target: `skill-${skillNumId}`,
                      type: 'smoothstep',
                      animated: false,
                      style: { stroke: '#8b5cf6', strokeWidth: 1.5 },
                      markerEnd: { type: MarkerType.ArrowClosed, color: '#8b5cf6', width: 14, height: 14 },
                    },
                  ]
                })

                // Update agent node skill chips
                const skill = data.skills.find((s) => s.id === skillNumId)
                if (skill) {
                  setNodes((nds) =>
                    nds.map((n) => {
                      if (n.id !== agentNodeId) return n
                      const existing = (n.data.assignedSkills as Array<{ id: number; name: string }>) || []
                      if (existing.some((s: { id: number }) => s.id === skillNumId)) return n
                      return {
                        ...n,
                        data: {
                          ...n.data,
                          assignedSkills: [...existing, { id: skill.id, name: skill.name }],
                          skillCount: (n.data.skillCount as number) + 1,
                        },
                      }
                    }),
                  )
                }
              })
            }
            return
          }
        }
      }

      // Drop as a new node on canvas at the drop position
      if (canvasNodeIds.has(item.id)) return

      const position = screenToFlowPosition({
        x: e.clientX,
        y: e.clientY,
      })

      let newNode: Node | null = null

      if (item.type === 'agent') {
        const agentData = data.agents.find((a) => `agent-${a.id}` === item.id)
        if (!agentData) return

        const skillIdMap = new Map(data.skills.map((s) => [s.id, s]))
        const assignedSkills: Array<{ id: number; name: string }> = []
        for (const skillId of agentData.skill_ids) {
          const s = skillIdMap.get(skillId)
          if (s) assignedSkills.push({ id: s.id, name: s.name })
        }

        newNode = {
          id: item.id,
          type: 'agentNode',
          position,
          draggable: true,
          data: {
            label: agentData.name,
            role: agentData.role,
            icon: agentData.icon,
            persona: agentData.persona,
            displayName: agentData.display_name,
            isEnabled: agentData.is_enabled,
            hasCustomInstructions: agentData.has_custom_instructions,
            skillCount: agentData.skill_ids.length,
            model: agentData.model,
            planningMode: agentData.planning_mode,
            contextStrategy: agentData.context_strategy,
            loopCondition: agentData.loop_condition,
            maxIterations: agentData.max_iterations,
            canDelegate: agentData.can_delegate,
            hasLoopConfig: agentData.has_loop_config,
            mcpCount: agentData.mcp_server_ids?.length ?? 0,
            a2aCount: agentData.a2a_agent_ids?.length ?? 0,
            assignedSkills,
          },
          sourcePosition: Position.Right,
          targetPosition: Position.Left,
        }
      } else if (item.type === 'mcp') {
        const mcpData = data.mcp_servers.find((m) => `mcp-${m.id}` === item.id)
        if (!mcpData) return

        newNode = {
          id: item.id,
          type: 'mcpNode',
          position,
          draggable: true,
          data: {
            label: mcpData.name,
            transport: mcpData.transport,
          },
          targetPosition: Position.Left,
        }
      }

      if (newNode) {
        setNodes((nds) => [...nds, newNode])
      }
    },
    [canvasNodeIds, data, projectId, screenToFlowPosition],
  )

  const handleDragLeave = useCallback(() => {
    if (dropTargetNodeId) {
      setDropTargetNodeId(null)
      setNodes((nds) =>
        nds.map((n) => ({
          ...n,
          data: { ...n.data, isDropTarget: false },
        })),
      )
    }
  }, [dropTargetNodeId])

  // ─── Context menu handlers (#364) ────────────────────────────
  const handleNodeContextMenu = useCallback(
    (e: React.MouseEvent, node: Node) => {
      e.preventDefault()
      const [type] = node.id.split('-')
      if (type === 'lane') return
      const bounds = reactFlowWrapper.current?.getBoundingClientRect()
      setContextMenu({
        x: e.clientX - (bounds?.left ?? 0),
        y: e.clientY - (bounds?.top ?? 0),
        type: 'node',
        id: node.id,
        nodeType: type,
      })
    },
    [],
  )

  const handleEdgeContextMenu = useCallback(
    (e: React.MouseEvent, edge: Edge) => {
      e.preventDefault()
      const bounds = reactFlowWrapper.current?.getBoundingClientRect()
      setContextMenu({
        x: e.clientX - (bounds?.left ?? 0),
        y: e.clientY - (bounds?.top ?? 0),
        type: 'edge',
        id: edge.id,
        nodeType: edge.type,
      })
    },
    [],
  )

  const handlePaneContextMenu = useCallback(
    (e: React.MouseEvent) => {
      e.preventDefault()
      const bounds = reactFlowWrapper.current?.getBoundingClientRect()
      setContextMenu({
        x: e.clientX - (bounds?.left ?? 0),
        y: e.clientY - (bounds?.top ?? 0),
        type: 'canvas',
      })
    },
    [],
  )

  // ─── Auto Layout ───────────────────────────────────────────────
  const handleAutoLayout = useCallback(() => {
    isBulkOperationRef.current = true
    setNodes((nds) => {
      const updated = autoLayout(nds, edges)
      setTimeout(() => {
        fitView({ padding: 0.15 })
        isBulkOperationRef.current = false
        triggerAutoSave()
      }, 50)
      return updated
    })
  }, [edges, fitView, triggerAutoSave])

  // ─── Fullscreen ────────────────────────────────────────────────
  const toggleFullscreen = useCallback(() => {
    setIsFullscreen((prev) => !prev)
  }, [])

  // ─── Delete edge helper (reused by keydown + context menu) ─────
  const deleteEdge = useCallback(
    (edgeId: string) => {
      const edge = edges.find((ed) => ed.id === edgeId)
      if (!edge) return

      setEdges((eds) => eds.filter((ed) => ed.id !== edgeId))
      setSelectedEdgeId(null)

      // Push to undo stack (#366)
      pushUndo({
        type: 'edge-delete',
        undo: () => setEdges((eds) => [...eds, edge]),
        redo: () => setEdges((eds) => eds.filter((ed) => ed.id !== edgeId)),
      })

      // Persist: unassign depending on edge type
      if (projectId && edge.source.startsWith('agent-') && edge.target.startsWith('skill-')) {
        const agentNumId = parseInt(edge.source.replace('agent-', ''))
        const skillNumId = parseInt(edge.target.replace('skill-', ''))
        const agent = data.agents.find((a) => a.id === agentNumId)
        if (agent) {
          const newSkillIds = agent.skill_ids.filter((id) => id !== skillNumId)
          assignAgentSkills(projectId, agentNumId, newSkillIds).catch(() => onRefresh?.())
        }
      }
      if (projectId && edge.source.startsWith('agent-') && edge.target.startsWith('mcp-')) {
        const agentNumId = parseInt(edge.source.replace('agent-', ''))
        const mcpNumId = parseInt(edge.target.replace('mcp-', ''))
        const agent = data.agents.find((a) => a.id === agentNumId)
        if (agent) {
          const newMcpIds = (agent.mcp_server_ids ?? []).filter((id) => id !== mcpNumId)
          bindAgentMcpServers(projectId, agentNumId, newMcpIds).catch(() => onRefresh?.())
        }
      }
      if (projectId && edge.source.startsWith('agent-') && edge.target.startsWith('a2a-')) {
        const agentNumId = parseInt(edge.source.replace('agent-', ''))
        const a2aNumId = parseInt(edge.target.replace('a2a-', ''))
        const agent = data.agents.find((a) => a.id === agentNumId)
        if (agent) {
          const newA2aIds = (agent.a2a_agent_ids ?? []).filter((id) => id !== a2aNumId)
          bindAgentA2aAgents(projectId, agentNumId, newA2aIds).catch(() => onRefresh?.())
        }
      }
      if (edge.type === 'delegation') {
        setEdgeConfigs((prev) => {
          const next = new Map(prev)
          next.delete(edgeId)
          return next
        })
        onRefresh?.()
      }
    },
    [edges, projectId, data.agents, onRefresh, pushUndo],
  )

  // ─── Delete selected nodes helper (#363) ─────────────────────
  const deleteSelectedNodes = useCallback(() => {
    setNodes((currentNodes) => {
      const selected = currentNodes.filter((n) => n.selected && n.type !== 'laneLabel')
      if (selected.length === 0) return currentNodes

      if (selected.length > 1 && !window.confirm(`Delete ${selected.length} selected nodes?`)) {
        return currentNodes
      }

      const deletedIds = new Set(selected.map((n) => n.id))
      const remainingNodes = currentNodes.filter((n) => !deletedIds.has(n.id))

      // Push undo for each node
      pushUndo({
        type: 'nodes-delete',
        undo: () => {
          setNodes((nds) => [...nds, ...selected])
          setEdges((eds) => {
            const removedEdges = edges.filter(
              (e) => deletedIds.has(e.source) || deletedIds.has(e.target),
            )
            return [...eds, ...removedEdges]
          })
        },
        redo: () => {
          setNodes((nds) => nds.filter((n) => !deletedIds.has(n.id)))
          setEdges((eds) => eds.filter((e) => !deletedIds.has(e.source) && !deletedIds.has(e.target)))
        },
      })

      // Remove connected edges
      setEdges((eds) => eds.filter((e) => !deletedIds.has(e.source) && !deletedIds.has(e.target)))

      return remainingNodes
    })
  }, [edges, pushUndo])

  // Handle Escape key to exit fullscreen or close panels (#294)
  // Handle Delete/Backspace to delete selected edge (#354) + selected nodes (#363)
  // Handle keyboard shortcuts (#365) + undo/redo (#366)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Don't fire shortcuts when typing in inputs (#365)
      const target = e.target as HTMLElement
      const isTyping = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable

      // #366 — Undo/Redo (works even when typing? No, only on canvas)
      if (!isTyping && (e.metaKey || e.ctrlKey) && e.key === 'z') {
        e.preventDefault()
        if (e.shiftKey) {
          handleRedo()
        } else {
          handleUndo()
        }
        return
      }

      if (e.key === 'Escape') {
        // #363 — Escape clears selection
        if (contextMenu) {
          setContextMenu(null)
        } else if (createMode) {
          setCreateMode(null)
        } else if (selectedEdgeId) {
          setSelectedEdgeId(null)
        } else if (selectedNodeInfo) {
          setSelectedNodeInfo(null)
        } else if (isFullscreen) {
          setIsFullscreen(false)
        } else {
          // Deselect all nodes (#363)
          setNodes((nds) => nds.map((n) => (n.selected ? { ...n, selected: false } : n)))
        }
        return
      }

      if (isTyping) return

      // #365 — Keyboard shortcuts
      if (e.key === 'l' || e.key === 'L') {
        handleAutoLayout()
        return
      }
      if (e.key === 'f' || e.key === 'F') {
        fitView({ padding: 0.15 })
        return
      }
      if (e.key === '+' || e.key === '=') {
        zoomIn()
        return
      }
      if (e.key === '-') {
        zoomOut()
        return
      }

      // #354 + #363 — Delete edge or selected nodes via Delete/Backspace key
      if (e.key === 'Delete' || e.key === 'Backspace') {
        // Delete selected edge
        if (selectedEdgeId && !selectedNodeInfo && !createMode) {
          e.preventDefault()
          deleteEdge(selectedEdgeId)
          return
        }

        // #363 — Delete all selected nodes
        e.preventDefault()
        deleteSelectedNodes()
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [isFullscreen, selectedEdgeId, selectedNodeInfo, createMode, contextMenu, deleteEdge, deleteSelectedNodes, handleAutoLayout, handleUndo, handleRedo, fitView, zoomIn, zoomOut])

  const containerStyle = isFullscreen
    ? {
        position: 'fixed' as const,
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        zIndex: 50,
        height: '100vh',
      }
    : { height: 'calc(100vh - 12rem)' }

  // Determine if palette should show — always show when projectId is set (for + buttons)
  const hasPaletteItems =
    projectId != null || paletteAgents.length > 0 || paletteSkills.length > 0 || paletteMcp.length > 0 || paletteA2a.length > 0

  // #348 — Create mode handlers
  const handleOpenCreate = useCallback(
    (mode: 'agent' | 'skill' | 'mcp' | 'a2a') => {
      setSelectedNodeInfo(null)
      setSelectedEdgeId(null)
      setCreateMode(mode)
    },
    [],
  )

  const handleCreateComplete = useCallback(() => {
    setCreateMode(null)
    onRefresh?.()
  }, [onRefresh])

  return (
    <div ref={containerRef} style={containerStyle} className="rounded-lg border border-zinc-800 overflow-hidden flex">
      {/* Left sidebar palette */}
      {hasPaletteItems && (
        <div className="w-[200px] border-r border-zinc-800 bg-zinc-950/80 flex flex-col shrink-0 overflow-hidden">
          <div className="px-3 py-2 border-b border-zinc-800">
            <h3 className="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider">Palette</h3>
            <p className="text-[10px] text-zinc-600 mt-0.5">Drag items onto the canvas</p>
          </div>
          <div className="flex-1 overflow-y-auto p-1.5">
            <PaletteSection
              title="Agents"
              icon={<Bot className="h-3 w-3 text-violet-400" />}
              items={paletteAgents}
              onDragStart={handlePaletteDragStart}
              onAdd={projectId ? () => handleOpenCreate('agent') : undefined}
            />
            <PaletteSection
              title="Skills"
              icon={<Sparkles className="h-3 w-3 text-emerald-400" />}
              items={paletteSkills}
              onDragStart={handlePaletteDragStart}
              onAdd={projectId ? () => handleOpenCreate('skill') : undefined}
            />
            <PaletteSection
              title="MCP Servers"
              icon={<Server className="h-3 w-3 text-pink-400" />}
              items={paletteMcp}
              onDragStart={handlePaletteDragStart}
              onAdd={projectId ? () => handleOpenCreate('mcp') : undefined}
            />
            <PaletteSection
              title="A2A Agents"
              icon={<Wifi className="h-3 w-3 text-cyan-400" />}
              items={paletteA2a}
              onDragStart={handlePaletteDragStart}
              onAdd={projectId ? () => handleOpenCreate('a2a') : undefined}
            />
          </div>
        </div>
      )}

      {/* React Flow canvas */}
      <div
        ref={reactFlowWrapper}
        className="flex-1 relative"
        onDragOver={handleDragOver}
        onDrop={handleDrop}
        onDragLeave={handleDragLeave}
      >
        <CanvasToolbar
          onAutoLayout={handleAutoLayout}
          onFitView={() => fitView({ padding: 0.15 })}
          onZoomIn={() => zoomIn()}
          onZoomOut={() => zoomOut()}
          isFullscreen={isFullscreen}
          onToggleFullscreen={toggleFullscreen}
          onUndo={handleUndo}
          onRedo={handleRedo}
          canUndo={undoStackRef.current.length > 0}
          canRedo={redoStackRef.current.length > 0}
          savedIndicator={savedIndicator}
          filterSearch={filterSearch}
          onFilterSearchChange={setFilterSearch}
          filterTypes={filterTypes}
          onToggleFilterType={handleToggleFilterType}
          onClearFilters={handleClearFilters}
          hasActiveFilter={hasActiveFilter}
        />

        <ReactFlow
          nodes={processedNodes}
          edges={processedEdges}
          nodeTypes={nodeTypes}
          edgeTypes={edgeTypes}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          isValidConnection={isValidConnection}
          onEdgeClick={handleEdgeClick}
          onNodeClick={handleNodeClick}
          onPaneClick={handlePaneClick}
          onNodeMouseEnter={handleNodeMouseEnter}
          onNodeMouseLeave={handleNodeMouseLeave}
          onNodeContextMenu={handleNodeContextMenu}
          onEdgeContextMenu={handleEdgeContextMenu}
          onPaneContextMenu={handlePaneContextMenu}
          onInit={() => setTimeout(() => fitView({ padding: 0.15 }), 50)}
          fitView
          minZoom={0.2}
          maxZoom={2}
          defaultEdgeOptions={{ type: 'smoothstep' }}
          proOptions={{ hideAttribution: true }}
          colorMode="dark"
          connectionLineStyle={{ stroke: '#8b5cf6', strokeWidth: 2, strokeDasharray: '5 5' }}
          snapToGrid
          snapGrid={[20, 20]}
          selectionOnDrag
          multiSelectionKeyCode="Shift"
        >
          <Background gap={20} size={1} color="#27272a" />
          <Controls
            showInteractive={false}
            className="!bg-zinc-900 !border-zinc-700 !shadow-lg [&>button]:!bg-zinc-800 [&>button]:!border-zinc-700 [&>button]:!text-zinc-300 [&>button:hover]:!bg-zinc-700"
          />
          <MiniMap
            nodeStrokeWidth={3}
            nodeColor={(n) => {
              if (n.type === 'agentNode') return '#8b5cf6'
              if (n.type === 'skillNode') return '#10b981'
              if (n.type === 'providerNode') return '#f59e0b'
              if (n.type === 'mcpNode') return '#ec4899'
              return '#6b7280'
            }}
            maskColor="rgba(0,0,0,0.7)"
            className="!bg-zinc-900 !border-zinc-700"
          />
        </ReactFlow>

        {/* #363 — Selected node highlight + #367 saved animation */}
        <style>{`
          .react-flow__node.selected { box-shadow: 0 0 0 2px #8b5cf6; border-radius: 0.5rem; }
          @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
          .animate-fade-in { animation: fade-in 0.2s ease-in; }
        `}</style>

        {/* #368 — Empty canvas onboarding */}
        {nodes.filter((n) => n.type !== 'laneLabel').length === 0 && (
          <div className="absolute inset-0 flex items-center justify-center z-20 pointer-events-none">
            <div className="flex flex-col items-center gap-4 p-8 border-2 border-dashed border-zinc-700 rounded-xl max-w-sm pointer-events-auto">
              <MousePointerSquareDashed className="h-10 w-10 text-zinc-600" />
              <h3 className="text-lg font-semibold text-zinc-300">Start building your agent team</h3>
              <p className="text-sm text-zinc-500 text-center">
                Add agents, connect skills and tools, and draw delegation chains
              </p>
              {projectId && (
                <button
                  onClick={() => handleOpenCreate('agent')}
                  className="flex items-center gap-2 px-4 py-2 bg-violet-600 hover:bg-violet-500 text-white text-sm font-medium rounded-lg transition-colors"
                >
                  <Bot className="h-4 w-4" />
                  Create your first agent
                </button>
              )}
            </div>
          </div>
        )}

        {/* #364 — Context menu */}
        {contextMenu && (
          <CanvasContextMenu
            menu={contextMenu}
            onClose={() => setContextMenu(null)}
            onEditNode={() => {
              if (contextMenu.id) {
                const entityId = contextMenu.id.replace(/^[^-]+-/, '')
                setSelectedNodeInfo({ id: entityId, type: contextMenu.nodeType ?? '' })
              }
            }}
            onDeleteNode={() => {
              if (contextMenu.id) {
                const node = nodes.find((n) => n.id === contextMenu.id)
                if (node) {
                  if (!window.confirm(`Delete ${(node.data.label as string) || contextMenu.id}?`)) return
                  setNodes((nds) => nds.filter((n) => n.id !== contextMenu.id))
                  setEdges((eds) => eds.filter((e) => e.source !== contextMenu.id && e.target !== contextMenu.id))
                  setSelectedNodeInfo(null)
                  onRefresh?.()
                }
              }
            }}
            onConfigureEdge={() => {
              if (contextMenu.id) {
                setSelectedNodeInfo(null)
                setSelectedEdgeId(contextMenu.id)
              }
            }}
            onRemoveEdge={() => {
              if (contextMenu.id) deleteEdge(contextMenu.id)
            }}
            onCreateAgent={() => handleOpenCreate('agent')}
            onCreateSkill={() => handleOpenCreate('skill')}
            onCreateMcp={() => handleOpenCreate('mcp')}
            onCreateA2a={() => handleOpenCreate('a2a')}
            onAutoLayout={handleAutoLayout}
            onFitView={() => fitView({ padding: 0.15 })}
          />
        )}

        {/* Chain tooltip (#293) */}
        {chainTooltip && hoveredNodeId && (
          <div className="absolute top-3 left-1/2 -translate-x-1/2 px-3 py-1.5 bg-zinc-800/95 border border-cyan-700/40 rounded-lg shadow-lg z-40 pointer-events-none">
            <p className="text-[11px] text-cyan-300 font-medium whitespace-nowrap">{chainTooltip}</p>
          </div>
        )}

        {/* Edge config panel — only for delegation edges (#292) */}
        {selectedEdge && selectedEdgeId && selectedEdge.type === 'delegation' && (
          <EdgeConfigPanel
            edgeId={selectedEdgeId}
            sourceAgentName={getAgentNameFromNodeId(selectedEdge.source)}
            targetAgentName={getAgentNameFromNodeId(selectedEdge.target)}
            sourceAgentId={selectedEdge.source.startsWith('agent-') ? parseInt(selectedEdge.source.replace('agent-', ''), 10) : undefined}
            targetAgentId={selectedEdge.target.startsWith('agent-') ? parseInt(selectedEdge.target.replace('agent-', ''), 10) : undefined}
            projectId={projectId}
            config={selectedEdgeConfig}
            onSave={handleEdgeConfigSave}
            onClose={() => setSelectedEdgeId(null)}
          />
        )}

        {/* Node detail panel (#294) */}
        {selectedNodeInfo && !createMode && (
          <NodeDetailPanel
            nodeId={selectedNodeInfo.id}
            nodeType={selectedNodeInfo.type as 'agent' | 'skill' | 'mcp' | 'a2a' | 'provider'}
            data={data}
            projectId={projectId}
            onClose={() => setSelectedNodeInfo(null)}
            onRefresh={onRefresh}
            onNodeDeleted={(nodeId) => {
              setNodes((nds) => nds.filter((n) => n.id !== nodeId))
              setEdges((eds) => eds.filter((e) => e.source !== nodeId && e.target !== nodeId))
              setSelectedNodeInfo(null)
              onRefresh?.()
            }}
          />
        )}

        {/* Create entity panel (#348-#352) */}
        {createMode && projectId && (
          <NodeDetailPanel
            nodeId=""
            nodeType={createMode}
            data={data}
            projectId={projectId}
            createMode={createMode}
            onClose={() => setCreateMode(null)}
            onRefresh={onRefresh}
            onCreateComplete={handleCreateComplete}
            onNodeDeleted={() => {}}
          />
        )}
      </div>
    </div>
  )
}

export default function FlowGraph(props: Props) {
  return (
    <ReactFlowProvider>
      <FlowGraphInner {...props} />
    </ReactFlowProvider>
  )
}
