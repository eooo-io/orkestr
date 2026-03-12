import { useMemo } from 'react'
import { useD3Graph, getNodeColor, getEdgeColor } from './useD3Graph'
import type { GraphNode, GraphEdge } from './useD3Graph'
import type { ProjectGraphData } from '@/types'

interface Props {
  data: ProjectGraphData
  height?: number
}

export default function SkillDependencyGraph({ data, height = 500 }: Props) {
  const { nodes, edges } = useMemo(() => {
    const circularSet = new Set(data.circular_deps)

    const nodes: GraphNode[] = data.skills.map((skill) => ({
      id: `skill-${skill.id}`,
      type: 'skill' as const,
      label: skill.name,
      sublabel: `${skill.token_estimate} tokens${skill.tags.length ? ' · ' + skill.tags.join(', ') : ''}`,
      icon: circularSet.has(skill.slug) ? '⚠' : undefined,
      color: circularSet.has(skill.slug) ? '#ef4444' : getNodeColor('skill'),
      radius: Math.max(16, Math.min(30, 12 + skill.token_estimate / 200)),
      meta: {
        slug: skill.slug,
        includes: skill.includes,
        conditions: skill.conditions,
        hasConditions: !!skill.conditions,
      },
    }))

    const edges: GraphEdge[] = data.skill_edges.map((edge) => ({
      source: `skill-${edge.source}`,
      target: `skill-${edge.target}`,
      type: edge.type,
      label: 'includes',
      color: getEdgeColor('includes'),
    }))

    return { nodes, edges }
  }, [data])

  const { svgRef } = useD3Graph(nodes, edges)

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
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full border-2" style={{ borderColor: getNodeColor('skill') }} />
          <span className="text-zinc-300">Skill ({data.skills.length})</span>
        </div>
        {data.skill_edges.length > 0 && (
          <div className="flex items-center gap-2">
            <div className="w-6 h-0 border-t-2" style={{ borderColor: getEdgeColor('includes') }} />
            <span className="text-zinc-300">Includes ({data.skill_edges.length})</span>
          </div>
        )}
        {data.circular_deps.length > 0 && (
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded-full border-2 border-red-500" />
            <span className="text-red-400">Circular dep ({data.circular_deps.length})</span>
          </div>
        )}
      </div>

      {/* Stats */}
      <div className="absolute top-3 right-3 bg-zinc-900/90 p-2 rounded text-xs border border-zinc-800 text-zinc-400">
        Max depth: {getMaxDepth(data)} · {data.skills.filter(s => s.includes.length > 0).length} composites
      </div>
    </div>
  )
}

function getMaxDepth(data: ProjectGraphData): number {
  const slugToIncludes: Record<string, string[]> = {}
  data.skills.forEach((s) => { slugToIncludes[s.slug] = s.includes })

  function depth(slug: string, visited: Set<string>): number {
    if (visited.has(slug)) return 0
    visited.add(slug)
    const includes = slugToIncludes[slug] ?? []
    if (includes.length === 0) return 0
    return 1 + Math.max(...includes.map((s) => depth(s, new Set(visited))))
  }

  return Math.max(0, ...data.skills.map((s) => depth(s.slug, new Set())))
}
