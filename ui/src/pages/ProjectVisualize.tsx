import { useEffect, useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { fetchProjectGraph } from '@/api/client'
import type { ProjectGraphData } from '@/types'
import FlowGraph from '@/components/visualization/FlowGraph'

export function ProjectVisualize() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [data, setData] = useState<ProjectGraphData | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!id) return
    setLoading(true)
    fetchProjectGraph(parseInt(id))
      .then(setData)
      .finally(() => setLoading(false))
  }, [id])

  const handleNodeClick = (nodeId: string, type: string) => {
    if (type === 'skill') {
      navigate(`/skills/${nodeId}`)
    }
  }

  const handleRefresh = () => {
    if (!id) return
    fetchProjectGraph(parseInt(id)).then(setData)
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-pulse text-muted-foreground">Loading graph...</div>
      </div>
    )
  }

  if (!data) {
    return (
      <div className="flex items-center justify-center h-screen text-muted-foreground">
        Failed to load graph data.
      </div>
    )
  }

  const graphHeight = typeof window !== 'undefined' ? window.innerHeight - 52 : 600

  return (
    <div className="flex flex-col h-screen">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-2 border-b border-border bg-muted/30">
        <div className="flex items-center gap-3">
          <Link
            to={`/projects/${id}`}
            className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Back
          </Link>
          <span className="text-sm font-medium text-foreground">{data.project.name}</span>
          <span className="text-xs text-muted-foreground">Configuration Graph</span>
        </div>

        <div className="flex items-center gap-3 text-xs text-zinc-400">
          <span className="flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-sm bg-violet-500" />
            Agents ({data.agents.length})
          </span>
          <span className="flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-sm bg-emerald-500" />
            Skills ({data.skills.length})
          </span>
          <span className="flex items-center gap-1.5">
            <span className="w-2 h-2 rounded-sm bg-amber-500" />
            Providers ({data.providers.length})
          </span>
          {data.mcp_servers.length > 0 && (
            <span className="flex items-center gap-1.5">
              <span className="w-2 h-2 rounded-sm bg-pink-500" />
              MCP ({data.mcp_servers.length})
            </span>
          )}
        </div>
      </div>

      {/* Graph */}
      <div className="flex-1">
        <FlowGraph data={data} height={graphHeight} onNodeClick={handleNodeClick} projectId={id ? parseInt(id) : undefined} onRefresh={handleRefresh} />
      </div>
    </div>
  )
}
