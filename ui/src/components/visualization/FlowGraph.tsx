import { useMemo, useCallback } from 'react'
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  type Node,
  type Edge,
  type NodeTypes,
  Position,
  MarkerType,
  useReactFlow,
  ReactFlowProvider,
} from '@xyflow/react'
import '@xyflow/react/dist/style.css'
import type { ProjectGraphData } from '@/types'
import { AgentNode, SkillNode, ProviderNode, McpNode, ProjectNode, LaneLabel } from './FlowNodes'

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

interface Props {
  data: ProjectGraphData
  height?: number
  onNodeClick?: (nodeId: string, type: string) => void
}

function buildGraph(data: ProjectGraphData) {
  const nodes: Node[] = []
  const edges: Edge[] = []

  const skillIdMap = new Map(data.skills.map((s) => [s.id, s]))
  const assignedSkillIds = new Set(data.agents.flatMap((a) => a.skill_ids))
  const unassignedSkills = data.skills.filter((s) => !assignedSkillIds.has(s.id))

  // Track Y offsets per lane
  let agentY = 60
  let skillY = 60
  let providerY = 60

  // Lane labels
  nodes.push({
    id: 'lane-agents',
    type: 'laneLabel',
    position: { x: LANE.agents, y: 0 },
    data: { label: 'Agents', count: data.agents.length },
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
      count: data.providers.length + data.mcp_servers.length,
    },
    draggable: false,
    selectable: false,
  })

  // Build a map of which skills are placed (and their Y position) so we can
  // place agent-assigned skills close to their agent.
  const skillPositions = new Map<number, { x: number; y: number }>()

  // Place agents and their assigned skills
  data.agents.forEach((agent) => {
    const agentNodeY = agentY

    nodes.push({
      id: `agent-${agent.id}`,
      type: 'agentNode',
      position: { x: LANE.agents, y: agentNodeY },
      data: {
        label: agent.name,
        role: agent.role,
        icon: agent.icon,
        isEnabled: agent.is_enabled,
        hasCustomInstructions: agent.has_custom_instructions,
        skillCount: agent.skill_ids.length,
      },
      sourcePosition: Position.Right,
    })

    // Place this agent's skills in the skills lane
    agent.skill_ids.forEach((skillId) => {
      if (skillPositions.has(skillId)) {
        // Skill already placed by another agent — just add edge
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
      data: {
        label: mcp.name,
        transport: mcp.transport,
      },
      targetPosition: Position.Left,
    })

    providerY += NODE_GAP_Y
  })

  // Edges from skills to providers (all assigned skills fan out to each provider)
  // This would be too noisy — instead connect at the project level concept
  // We show a summary edge from the skill lane to each provider
  if (data.providers.length > 0 && data.skills.length > 0) {
    // Pick the middle skill as the source anchor
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

function FlowGraphInner({ data, height = 500, onNodeClick }: Props) {
  const { nodes, edges } = useMemo(() => buildGraph(data), [data])
  const { fitView } = useReactFlow()

  const handleNodeClick = useCallback(
    (_: React.MouseEvent, node: Node) => {
      if (!onNodeClick) return
      const [type] = node.id.split('-')
      if (type === 'lane') return
      const entityId = node.id.replace(/^[^-]+-/, '')
      onNodeClick(entityId, type ?? '')
    },
    [onNodeClick],
  )

  return (
    <div style={{ height }} className="rounded-lg border border-zinc-800 overflow-hidden">
      <ReactFlow
        nodes={nodes}
        edges={edges}
        nodeTypes={nodeTypes}
        onNodeClick={handleNodeClick}
        onInit={() => setTimeout(() => fitView({ padding: 0.15 }), 50)}
        fitView
        minZoom={0.2}
        maxZoom={2}
        defaultEdgeOptions={{ type: 'smoothstep' }}
        proOptions={{ hideAttribution: true }}
        colorMode="dark"
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
