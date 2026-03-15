import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Maximize2 } from 'lucide-react'
import { fetchProjectGraph } from '@/api/client'
import type { ProjectGraphData } from '@/types'
import FlowGraph from './FlowGraph'

interface Props {
  projectId: number
}

export default function VisualizationTab({ projectId }: Props) {
  const [data, setData] = useState<ProjectGraphData | null>(null)
  const [loading, setLoading] = useState(true)
  const navigate = useNavigate()

  useEffect(() => {
    setLoading(true)
    fetchProjectGraph(projectId)
      .then(setData)
      .finally(() => setLoading(false))
  }, [projectId])

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-pulse text-muted-foreground">Loading graph data...</div>
      </div>
    )
  }

  if (!data) {
    return (
      <div className="flex items-center justify-center h-64 text-muted-foreground">
        Failed to load graph data.
      </div>
    )
  }

  const handleNodeClick = (nodeId: string, type: string) => {
    if (type === 'skill') {
      navigate(`/skills/${nodeId}`)
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <div className="flex items-center gap-3 text-xs text-zinc-400">
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-violet-500/60 border border-violet-500" />
              Agents ({data.agents.length})
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-emerald-500/60 border border-emerald-500" />
              Skills ({data.skills.length})
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-amber-500/60 border border-amber-500" />
              Providers ({data.providers.length})
            </span>
            {data.mcp_servers.length > 0 && (
              <span className="flex items-center gap-1.5">
                <span className="w-2.5 h-2.5 rounded-sm bg-pink-500/60 border border-pink-500" />
                MCP ({data.mcp_servers.length})
              </span>
            )}
            {(data.a2a_agents?.length ?? 0) > 0 && (
              <span className="flex items-center gap-1.5">
                <span className="w-2.5 h-2.5 rounded-sm bg-cyan-500/60 border border-cyan-500" />
                A2A ({data.a2a_agents?.length ?? 0})
              </span>
            )}
            {data.circular_deps.length > 0 && (
              <span className="flex items-center gap-1.5 text-red-400">
                <span className="w-2.5 h-2.5 rounded-sm bg-red-500/60 border border-red-500" />
                Circular deps ({data.circular_deps.length})
              </span>
            )}
          </div>
        </div>
        <Link
          to={`/projects/${projectId}/visualize`}
          className="flex items-center gap-1 px-2 py-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
        >
          <Maximize2 className="h-3 w-3" />
          Full screen
        </Link>
      </div>

      <FlowGraph data={data} height={500} onNodeClick={handleNodeClick} projectId={projectId} />

      <div className="flex items-center gap-4 text-[11px] text-zinc-500 px-1">
        <span>
          <span className="inline-block w-4 h-0 border-t-2 border-violet-500 mr-1.5 align-middle" />
          assigned to
        </span>
        <span>
          <span className="inline-block w-4 h-0 border-t-2 border-dashed border-emerald-500 mr-1.5 align-middle" />
          includes
        </span>
        <span>
          <span className="inline-block w-4 h-0 border-t-2 border-amber-500 mr-1.5 align-middle opacity-50" />
          syncs to
        </span>
        <span>
          <span className="inline-block w-4 h-0 border-t-2 border-dashed border-cyan-500 mr-1.5 align-middle" />
          delegates to
        </span>
      </div>
    </div>
  )
}
