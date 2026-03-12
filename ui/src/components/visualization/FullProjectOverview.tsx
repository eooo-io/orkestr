import { useMemo } from 'react'
import { useD3Graph, getNodeColor, getEdgeColor } from './useD3Graph'
import type { GraphNode, GraphEdge } from './useD3Graph'
import type { ProjectGraphData } from '@/types'

interface Props {
  data: ProjectGraphData
  height?: number
  onNodeClick?: (nodeId: string, type: string) => void
}

export default function FullProjectOverview({ data, height = 600, onNodeClick }: Props) {
  const { nodes, edges } = useMemo(() => {
    const nodes: GraphNode[] = []
    const edges: GraphEdge[] = []

    // Project (hub)
    nodes.push({
      id: 'project',
      type: 'project',
      label: data.project.name,
      sublabel: `${data.skills.length} skills · ${data.agents.length} agents · ${data.providers.length} providers`,
      color: getNodeColor('project'),
      radius: 32,
    })

    // Agents
    data.agents.forEach((agent) => {
      nodes.push({
        id: `agent-${agent.id}`,
        type: 'agent',
        label: agent.name,
        sublabel: `${agent.role} · ${agent.skill_ids.length} skills${agent.is_enabled ? '' : ' (off)'}`,
        icon: agent.icon ?? undefined,
        color: agent.is_enabled ? getNodeColor('agent') : '#4b5563',
        radius: 22,
        meta: { entityId: agent.id, entityType: 'agent' },
      })

      // Agent → skills
      agent.skill_ids.forEach((skillId) => {
        edges.push({
          source: `agent-${agent.id}`,
          target: `skill-${skillId}`,
          type: 'assigned',
          color: getEdgeColor('assigned'),
        })
      })
    })

    // Skills
    data.skills.forEach((skill) => {
      const isCircular = data.circular_deps.includes(skill.slug)
      nodes.push({
        id: `skill-${skill.id}`,
        type: 'skill',
        label: skill.name,
        sublabel: [
          `${skill.token_estimate} tok`,
          skill.tags.length ? skill.tags.slice(0, 2).join(', ') : null,
          skill.conditions ? 'conditional' : null,
        ]
          .filter(Boolean)
          .join(' · '),
        icon: isCircular ? '⚠' : undefined,
        color: isCircular ? '#ef4444' : getNodeColor('skill'),
        radius: Math.max(14, Math.min(24, 10 + skill.token_estimate / 300)),
        meta: { entityId: skill.id, entityType: 'skill' },
      })
    })

    // Skill → includes
    data.skill_edges.forEach((edge) => {
      edges.push({
        source: `skill-${edge.source}`,
        target: `skill-${edge.target}`,
        type: 'includes',
        color: getEdgeColor('includes'),
        dashed: true,
      })
    })

    // Providers
    data.providers.forEach((provider) => {
      const outputCount = (data.sync_outputs[provider.slug] ?? []).length
      nodes.push({
        id: `provider-${provider.slug}`,
        type: 'provider',
        label: provider.name,
        sublabel: `${outputCount} output file${outputCount !== 1 ? 's' : ''}`,
        color: getNodeColor('provider'),
        radius: 20,
        meta: { entityId: provider.slug, entityType: 'provider' },
      })

      edges.push({
        source: 'project',
        target: `provider-${provider.slug}`,
        type: 'syncs_to',
        color: getEdgeColor('syncs_to'),
      })
    })

    // MCP Servers
    data.mcp_servers.forEach((mcp) => {
      nodes.push({
        id: `mcp-${mcp.id}`,
        type: 'mcp',
        label: mcp.name,
        sublabel: mcp.transport,
        color: getNodeColor('mcp'),
        radius: 16,
        meta: { entityId: mcp.id, entityType: 'mcp' },
      })

      edges.push({
        source: 'project',
        target: `mcp-${mcp.id}`,
        type: 'uses_mcp',
        color: getEdgeColor('uses_mcp'),
      })
    })

    return { nodes, edges }
  }, [data])

  const handleNodeClick = onNodeClick
    ? (node: GraphNode) => {
        const meta = node.meta as Record<string, unknown> | undefined
        if (meta?.entityType) {
          onNodeClick(String(meta.entityId), String(meta.entityType))
        }
      }
    : undefined

  const { svgRef } = useD3Graph(nodes, edges, { onNodeClick: handleNodeClick })

  return (
    <div className="relative" style={{ height }}>
      <svg
        ref={svgRef}
        width="100%"
        height="100%"
        className="bg-zinc-950 rounded-lg border border-zinc-800"
      />

      {/* Legend */}
      <div className="absolute top-3 left-3 flex flex-col gap-1 bg-zinc-900/90 p-2 rounded text-xs border border-zinc-800">
        {[
          { type: 'project', label: 'Project', count: 1 },
          { type: 'agent', label: 'Agent', count: data.agents.length },
          { type: 'skill', label: 'Skill', count: data.skills.length },
          { type: 'provider', label: 'Provider', count: data.providers.length },
          ...(data.mcp_servers.length > 0
            ? [{ type: 'mcp', label: 'MCP Server', count: data.mcp_servers.length }]
            : []),
        ].map(({ type, label, count }) => (
          <div key={type} className="flex items-center gap-2">
            <div
              className="w-3 h-3 rounded-full border-2"
              style={{ borderColor: getNodeColor(type) }}
            />
            <span className="text-zinc-300">
              {label} ({count})
            </span>
          </div>
        ))}
        <div className="border-t border-zinc-700 my-1" />
        {[
          { type: 'assigned', label: 'Assigned', dashed: false },
          { type: 'includes', label: 'Includes', dashed: true },
          { type: 'syncs_to', label: 'Syncs to', dashed: false },
        ].map(({ type, label, dashed }) => (
          <div key={type} className="flex items-center gap-2">
            <div
              className="w-6 h-0 border-t-2"
              style={{
                borderColor: getEdgeColor(type),
                borderStyle: dashed ? 'dashed' : 'solid',
              }}
            />
            <span className="text-zinc-300">{label}</span>
          </div>
        ))}
      </div>

      {/* Stats */}
      <div className="absolute top-3 right-3 bg-zinc-900/90 p-2 rounded text-xs border border-zinc-800 text-zinc-400 space-y-0.5">
        <div>Nodes: {nodes.length}</div>
        <div>Edges: {edges.length}</div>
        {data.circular_deps.length > 0 && (
          <div className="text-red-400">Circular deps: {data.circular_deps.length}</div>
        )}
      </div>
    </div>
  )
}
