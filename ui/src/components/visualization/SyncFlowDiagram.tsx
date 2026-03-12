import { useMemo } from 'react'
import { useD3Graph, getNodeColor, getEdgeColor } from './useD3Graph'
import type { GraphNode, GraphEdge } from './useD3Graph'
import type { ProjectGraphData } from '@/types'

interface Props {
  data: ProjectGraphData
  height?: number
}

const PROVIDER_ICONS: Record<string, string> = {
  claude: 'C',
  cursor: '▶',
  copilot: 'G',
  windsurf: 'W',
  cline: '⌘',
  openai: 'O',
  openclaw: '🦀',
}

export default function SyncFlowDiagram({ data, height = 500 }: Props) {
  const { nodes, edges } = useMemo(() => {
    const nodes: GraphNode[] = []
    const edges: GraphEdge[] = []

    // Project node (center)
    nodes.push({
      id: 'project',
      type: 'project',
      label: data.project.name,
      sublabel: data.project.synced_at
        ? `Synced ${new Date(data.project.synced_at).toLocaleDateString()}`
        : 'Not synced',
      color: getNodeColor('project'),
      radius: 28,
    })

    // Provider nodes
    data.providers.forEach((provider) => {
      nodes.push({
        id: `provider-${provider.slug}`,
        type: 'provider',
        label: provider.name,
        sublabel: `${(data.sync_outputs[provider.slug] ?? []).length} files`,
        icon: PROVIDER_ICONS[provider.slug],
        color: getNodeColor('provider'),
        radius: 22,
      })

      edges.push({
        source: 'project',
        target: `provider-${provider.slug}`,
        type: 'syncs_to',
        label: `${data.skills.length} skills`,
        color: getEdgeColor('syncs_to'),
      })

      // Output file nodes
      const outputs = data.sync_outputs[provider.slug] ?? []
      outputs.forEach((path, i) => {
        const nodeId = `output-${provider.slug}-${i}`
        nodes.push({
          id: nodeId,
          type: 'output',
          label: path.split('/').pop() ?? path,
          sublabel: path,
          color: getNodeColor('output'),
          radius: 12,
        })

        edges.push({
          source: `provider-${provider.slug}`,
          target: nodeId,
          type: 'outputs',
          color: getEdgeColor('outputs'),
          dashed: true,
        })
      })
    })

    // MCP server nodes
    data.mcp_servers.forEach((mcp) => {
      nodes.push({
        id: `mcp-${mcp.id}`,
        type: 'mcp',
        label: mcp.name,
        sublabel: mcp.transport,
        color: getNodeColor('mcp'),
        radius: 16,
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

  const { svgRef } = useD3Graph(nodes, edges)

  const totalOutputFiles = Object.values(data.sync_outputs).flat().length

  return (
    <div className="relative" style={{ height }}>
      <svg
        ref={svgRef}
        width="100%"
        height="100%"
        className="bg-zinc-950 rounded-lg border border-zinc-800"
      />

      <div className="absolute top-3 left-3 flex flex-col gap-1 bg-zinc-900/90 p-2 rounded text-xs border border-zinc-800">
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full border-2" style={{ borderColor: getNodeColor('project') }} />
          <span className="text-zinc-300">Project</span>
        </div>
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full border-2" style={{ borderColor: getNodeColor('provider') }} />
          <span className="text-zinc-300">Provider ({data.providers.length})</span>
        </div>
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full border-2" style={{ borderColor: getNodeColor('output') }} />
          <span className="text-zinc-300">Output file ({totalOutputFiles})</span>
        </div>
        {data.mcp_servers.length > 0 && (
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded-full border-2" style={{ borderColor: getNodeColor('mcp') }} />
            <span className="text-zinc-300">MCP Server ({data.mcp_servers.length})</span>
          </div>
        )}
      </div>
    </div>
  )
}
