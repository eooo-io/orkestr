import { useEffect, useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { fetchProjectGraph } from '@/api/client'
import type { ProjectGraphData } from '@/types'
import SkillDependencyGraph from '@/components/visualization/SkillDependencyGraph'
import AgentCompositionTree from '@/components/visualization/AgentCompositionTree'
import SyncFlowDiagram from '@/components/visualization/SyncFlowDiagram'
import FullProjectOverview from '@/components/visualization/FullProjectOverview'

type View = 'overview' | 'skills' | 'agents' | 'sync'

export function ProjectVisualize() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [data, setData] = useState<ProjectGraphData | null>(null)
  const [loading, setLoading] = useState(true)
  const [view, setView] = useState<View>('overview')

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

  const views: { key: View; label: string }[] = [
    { key: 'overview', label: 'Full Overview' },
    { key: 'skills', label: 'Skill Dependencies' },
    { key: 'agents', label: 'Agent Composition' },
    { key: 'sync', label: 'Sync Flow' },
  ]

  // Full screen height minus header
  const graphHeight = typeof window !== 'undefined' ? window.innerHeight - 64 : 600

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

        <div className="flex gap-1">
          {views.map((v) => (
            <button
              key={v.key}
              onClick={() => setView(v.key)}
              className={`px-3 py-1.5 text-xs font-medium rounded-md transition-all ${
                view === v.key
                  ? 'bg-primary/20 text-primary'
                  : 'text-muted-foreground hover:text-foreground bg-muted/30'
              }`}
            >
              {v.label}
            </button>
          ))}
        </div>
      </div>

      {/* Graph */}
      <div className="flex-1">
        {view === 'overview' && (
          <FullProjectOverview data={data} height={graphHeight} onNodeClick={handleNodeClick} />
        )}
        {view === 'skills' && <SkillDependencyGraph data={data} height={graphHeight} />}
        {view === 'agents' && <AgentCompositionTree data={data} height={graphHeight} />}
        {view === 'sync' && <SyncFlowDiagram data={data} height={graphHeight} />}
      </div>
    </div>
  )
}
