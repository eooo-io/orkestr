import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Maximize2 } from 'lucide-react'
import { fetchProjectGraph } from '@/api/client'
import type { ProjectGraphData } from '@/types'
import SkillDependencyGraph from './SkillDependencyGraph'
import AgentCompositionTree from './AgentCompositionTree'
import SyncFlowDiagram from './SyncFlowDiagram'
import FullProjectOverview from './FullProjectOverview'

type View = 'overview' | 'skills' | 'agents' | 'sync'

interface Props {
  projectId: number
}

export default function VisualizationTab({ projectId }: Props) {
  const [data, setData] = useState<ProjectGraphData | null>(null)
  const [loading, setLoading] = useState(true)
  const [view, setView] = useState<View>('overview')

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

  const views: { key: View; label: string }[] = [
    { key: 'overview', label: 'Full Overview' },
    { key: 'skills', label: 'Skill Dependencies' },
    { key: 'agents', label: 'Agent Composition' },
    { key: 'sync', label: 'Sync Flow' },
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
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
        <Link
          to={`/projects/${projectId}/visualize`}
          className="flex items-center gap-1 px-2 py-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
        >
          <Maximize2 className="h-3 w-3" />
          Full screen
        </Link>
      </div>

      {view === 'overview' && <FullProjectOverview data={data} height={450} />}
      {view === 'skills' && <SkillDependencyGraph data={data} height={450} />}
      {view === 'agents' && <AgentCompositionTree data={data} height={450} />}
      {view === 'sync' && <SyncFlowDiagram data={data} height={450} />}
    </div>
  )
}
