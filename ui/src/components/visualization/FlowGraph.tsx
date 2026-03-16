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
  GripVertical,
  LayoutDashboard,
  Maximize,
  Minimize,
  ZoomIn,
  ZoomOut,
  Locate,
  ChevronDown,
  ChevronRight,
} from 'lucide-react'
import type { ProjectGraphData } from '@/types'
import { assignAgentSkills } from '@/api/client'
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
  defaultOpen?: boolean
}

function PaletteSection({ title, icon, items, onDragStart, defaultOpen = true }: PaletteSectionProps) {
  const [isOpen, setIsOpen] = useState(defaultOpen)

  if (items.length === 0) return null

  return (
    <div className="mb-2">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-1.5 w-full px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-400 hover:text-zinc-300 transition-colors"
      >
        {isOpen ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
        {icon}
        <span>{title}</span>
        <span className="ml-auto text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-500 font-normal">
          {items.length}
        </span>
      </button>
      {isOpen && (
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
}

function CanvasToolbar({ onAutoLayout, onFitView, onZoomIn, onZoomOut, isFullscreen, onToggleFullscreen }: ToolbarProps) {
  return (
    <div className="absolute top-3 right-3 z-10 flex items-center gap-1 bg-zinc-900/90 border border-zinc-700 rounded-lg px-1 py-1 shadow-lg backdrop-blur-sm">
      <button
        onClick={onAutoLayout}
        className="flex items-center gap-1.5 px-2 py-1 text-[11px] text-zinc-300 hover:text-white hover:bg-zinc-800 rounded transition-colors"
        title="Auto Layout"
      >
        <LayoutDashboard className="h-3.5 w-3.5" />
        <span>Auto Layout</span>
      </button>
      <div className="w-px h-4 bg-zinc-700" />
      <button
        onClick={onZoomIn}
        className="p-1 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors"
        title="Zoom In"
      >
        <ZoomIn className="h-3.5 w-3.5" />
      </button>
      <button
        onClick={onZoomOut}
        className="p-1 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors"
        title="Zoom Out"
      >
        <ZoomOut className="h-3.5 w-3.5" />
      </button>
      <button
        onClick={onFitView}
        className="p-1 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded transition-colors"
        title="Fit to View"
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
    </div>
  )
}

// ─── Main interactive graph component ──────────────────────────────
function FlowGraphInner({ data, height = 500, onNodeClick, projectId }: Props) {
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

  // Apply highlighting class to nodes in chain
  const processedNodes = useMemo(() => {
    if (!hoveredNodeId || highlightedElements.nodeIds.size === 0) return nodes
    return nodes.map((node) => {
      const isInChain = highlightedElements.nodeIds.has(node.id)
      if (!isInChain) return node
      return {
        ...node,
        className: 'ring-2 ring-cyan-400/60 ring-offset-1 ring-offset-zinc-900 rounded-lg',
      }
    })
  }, [nodes, hoveredNodeId, highlightedElements])

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

  // Re-sync when data changes
  useEffect(() => {
    const g = buildGraph(data)
    setNodes(g.nodes)
    setEdges(g.edges)
  }, [data])

  // Handle node changes (drag, select, etc.)
  const onNodesChange: OnNodesChange = useCallback(
    (changes) => setNodes((nds) => applyNodeChanges(changes, nds)),
    [],
  )

  const onEdgesChange: OnEdgesChange = useCallback(
    (changes) => setEdges((eds) => applyEdgeChanges(changes, eds)),
    [],
  )

  // Handle new edge connections (MCP -> Agent)
  const onConnect: OnConnect = useCallback(
    (connection: Connection) => {
      const { source, target } = connection
      if (!source || !target) return

      // Allow MCP -> Agent connection (uses_tool edge)
      const isMcpToAgent =
        (source.startsWith('mcp-') && target.startsWith('agent-')) ||
        (source.startsWith('agent-') && target.startsWith('mcp-'))

      if (isMcpToAgent) {
        const mcpId = source.startsWith('mcp-') ? source : target
        const agentId = source.startsWith('agent-') ? source : target

        const newEdge: Edge = {
          id: `e-agent-mcp-${agentId}-${mcpId}`,
          source: agentId,
          target: mcpId,
          type: 'smoothstep',
          animated: false,
          style: { stroke: '#ec4899', strokeWidth: 1.5, strokeDasharray: '4 3' },
          markerEnd: { type: MarkerType.ArrowClosed, color: '#ec4899', width: 12, height: 12 },
        }

        setEdges((eds) => addEdge(newEdge, eds))
        return
      }

      // Allow Agent -> Agent delegation connections (#291)
      const isAgentToAgent = source.startsWith('agent-') && target.startsWith('agent-') && source !== target
      if (isAgentToAgent) {
        const existingEdgeId = `e-delegation-${source}-${target}`
        // Don't duplicate
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
          // If reverse exists, update it to be bidirectional
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
        return
      }

      // Allow Agent -> Skill connections
      const isAgentToSkill =
        (source.startsWith('agent-') && target.startsWith('skill-')) ||
        (source.startsWith('skill-') && target.startsWith('agent-'))

      if (isAgentToSkill) {
        const agentNodeId = source.startsWith('agent-') ? source : target
        const skillNodeId = source.startsWith('skill-') ? source : target

        const newEdge: Edge = {
          id: `e-${agentNodeId}-${skillNodeId}`,
          source: agentNodeId,
          target: skillNodeId,
          type: 'smoothstep',
          animated: false,
          style: { stroke: '#8b5cf6', strokeWidth: 1.5 },
          markerEnd: { type: MarkerType.ArrowClosed, color: '#8b5cf6', width: 14, height: 14 },
        }

        setEdges((eds) => addEdge(newEdge, eds))

        // Persist: assign skill to agent via API
        if (projectId) {
          const agentNumId = parseInt(agentNodeId.replace('agent-', ''))
          const skillNumId = parseInt(skillNodeId.replace('skill-', ''))
          const agent = data.agents.find((a) => a.id === agentNumId)
          if (agent) {
            const newSkillIds = [...new Set([...agent.skill_ids, skillNumId])]
            assignAgentSkills(projectId, agentNumId, newSkillIds).catch(() => {
              // Revert edge on failure
              setEdges((eds) => eds.filter((e) => e.id !== newEdge.id))
            })
          }
        }
      }
    },
    [data.agents, projectId],
  )

  // Handle edge click — open config panel for delegation edges (#292)
  const handleEdgeClick = useCallback(
    (_: React.MouseEvent, edge: Edge) => {
      if (edge.type !== 'delegation') return
      setSelectedNodeInfo(null) // close node panel
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

  // ─── Auto Layout ───────────────────────────────────────────────
  const handleAutoLayout = useCallback(() => {
    setNodes((nds) => {
      const updated = autoLayout(nds, edges)
      setTimeout(() => fitView({ padding: 0.15 }), 50)
      return updated
    })
  }, [edges, fitView])

  // ─── Fullscreen ────────────────────────────────────────────────
  const toggleFullscreen = useCallback(() => {
    setIsFullscreen((prev) => !prev)
  }, [])

  // Handle Escape key to exit fullscreen or close panels (#294)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        if (selectedEdgeId) {
          setSelectedEdgeId(null)
        } else if (selectedNodeInfo) {
          setSelectedNodeInfo(null)
        } else if (isFullscreen) {
          setIsFullscreen(false)
        }
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [isFullscreen, selectedEdgeId, selectedNodeInfo])

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
    : { height }

  // Determine if palette has items to show
  const hasPaletteItems = paletteAgents.length > 0 || paletteSkills.length > 0 || paletteMcp.length > 0

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
            />
            <PaletteSection
              title="Skills"
              icon={<Sparkles className="h-3 w-3 text-emerald-400" />}
              items={paletteSkills}
              onDragStart={handlePaletteDragStart}
            />
            <PaletteSection
              title="MCP Servers"
              icon={<Server className="h-3 w-3 text-pink-400" />}
              items={paletteMcp}
              onDragStart={handlePaletteDragStart}
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
        />

        <ReactFlow
          nodes={processedNodes}
          edges={processedEdges}
          nodeTypes={nodeTypes}
          edgeTypes={edgeTypes}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          onEdgeClick={handleEdgeClick}
          onNodeClick={handleNodeClick}
          onPaneClick={handlePaneClick}
          onNodeMouseEnter={handleNodeMouseEnter}
          onNodeMouseLeave={handleNodeMouseLeave}
          onInit={() => setTimeout(() => fitView({ padding: 0.15 }), 50)}
          fitView
          minZoom={0.2}
          maxZoom={2}
          defaultEdgeOptions={{ type: 'smoothstep' }}
          proOptions={{ hideAttribution: true }}
          colorMode="dark"
          connectionLineStyle={{ stroke: '#8b5cf6', strokeWidth: 2 }}
          snapToGrid
          snapGrid={[20, 20]}
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

        {/* Chain tooltip (#293) */}
        {chainTooltip && hoveredNodeId && (
          <div className="absolute top-3 left-1/2 -translate-x-1/2 px-3 py-1.5 bg-zinc-800/95 border border-cyan-700/40 rounded-lg shadow-lg z-40 pointer-events-none">
            <p className="text-[11px] text-cyan-300 font-medium whitespace-nowrap">{chainTooltip}</p>
          </div>
        )}

        {/* Edge config panel (#292) */}
        {selectedEdge && selectedEdgeId && (
          <EdgeConfigPanel
            edgeId={selectedEdgeId}
            sourceAgentName={getAgentNameFromNodeId(selectedEdge.source)}
            targetAgentName={getAgentNameFromNodeId(selectedEdge.target)}
            config={selectedEdgeConfig}
            onSave={handleEdgeConfigSave}
            onClose={() => setSelectedEdgeId(null)}
          />
        )}

        {/* Node detail panel (#294) */}
        {selectedNodeInfo && (
          <NodeDetailPanel
            nodeId={selectedNodeInfo.id}
            nodeType={selectedNodeInfo.type as 'agent' | 'skill' | 'mcp' | 'a2a' | 'provider'}
            data={data}
            onClose={() => setSelectedNodeInfo(null)}
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
