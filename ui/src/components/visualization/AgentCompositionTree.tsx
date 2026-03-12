import { useMemo } from 'react'
import { useD3Graph, getNodeColor, getEdgeColor } from './useD3Graph'
import type { GraphNode, GraphEdge } from './useD3Graph'
import type { ProjectGraphData } from '@/types'

interface Props {
  data: ProjectGraphData
  height?: number
}

export default function AgentCompositionTree({ data, height = 500 }: Props) {
  const { nodes, edges } = useMemo(() => {
    const nodes: GraphNode[] = []
    const edges: GraphEdge[] = []
    const skillIdMap = new Map(data.skills.map((s) => [s.id, s]))
    const addedSkills = new Set<number>()

    // Agent nodes
    data.agents.forEach((agent) => {
      nodes.push({
        id: `agent-${agent.id}`,
        type: 'agent',
        label: agent.name,
        sublabel: `${agent.role}${agent.is_enabled ? '' : ' (disabled)'}`,
        icon: agent.icon ?? undefined,
        color: agent.is_enabled ? getNodeColor('agent') : '#4b5563',
        radius: 24,
        meta: {
          is_enabled: agent.is_enabled,
          has_custom_instructions: agent.has_custom_instructions,
          skill_count: agent.skill_ids.length,
        },
      })

      // Assigned skills
      agent.skill_ids.forEach((skillId) => {
        const skill = skillIdMap.get(skillId)
        if (!skill) return

        if (!addedSkills.has(skillId)) {
          nodes.push({
            id: `skill-${skillId}`,
            type: 'skill',
            label: skill.name,
            sublabel: `${skill.token_estimate} tok${skill.includes.length ? ' · ' + skill.includes.length + ' includes' : ''}`,
            color: getNodeColor('skill'),
            radius: 18,
            meta: { slug: skill.slug },
          })
          addedSkills.add(skillId)
        }

        edges.push({
          source: `agent-${agent.id}`,
          target: `skill-${skillId}`,
          type: 'assigned',
          label: 'uses',
          color: getEdgeColor('assigned'),
        })
      })
    })

    // Include chains for assigned skills
    addedSkills.forEach((skillId) => {
      const skill = skillIdMap.get(skillId)
      if (!skill) return

      skill.includes.forEach((includeSlug) => {
        const included = data.skills.find((s) => s.slug === includeSlug)
        if (!included) return

        if (!addedSkills.has(included.id)) {
          nodes.push({
            id: `skill-${included.id}`,
            type: 'skill',
            label: included.name,
            sublabel: `${included.token_estimate} tok (included)`,
            color: '#065f46',
            radius: 14,
            meta: { slug: included.slug },
          })
          addedSkills.add(included.id)
        }

        edges.push({
          source: `skill-${skillId}`,
          target: `skill-${included.id}`,
          type: 'includes',
          color: getEdgeColor('includes'),
          dashed: true,
        })
      })
    })

    // Skills not assigned to any agent
    data.skills
      .filter((s) => !addedSkills.has(s.id))
      .forEach((skill) => {
        nodes.push({
          id: `skill-${skill.id}`,
          type: 'skill',
          label: skill.name,
          sublabel: 'unassigned',
          color: '#374151',
          radius: 14,
          meta: { slug: skill.slug, unassigned: true },
        })
      })

    return { nodes, edges }
  }, [data])

  const { svgRef } = useD3Graph(nodes, edges)

  const enabledAgents = data.agents.filter((a) => a.is_enabled).length
  const totalAssigned = new Set(data.agents.flatMap((a) => a.skill_ids)).size
  const unassigned = data.skills.length - totalAssigned

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
          <div className="w-3 h-3 rounded-full border-2" style={{ borderColor: getNodeColor('agent') }} />
          <span className="text-zinc-300">Agent ({enabledAgents} enabled)</span>
        </div>
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full border-2" style={{ borderColor: getNodeColor('skill') }} />
          <span className="text-zinc-300">Assigned skill ({totalAssigned})</span>
        </div>
        {unassigned > 0 && (
          <div className="flex items-center gap-2">
            <div className="w-3 h-3 rounded-full border-2 border-zinc-600" />
            <span className="text-zinc-500">Unassigned ({unassigned})</span>
          </div>
        )}
        <div className="flex items-center gap-2">
          <div className="w-6 h-0 border-t-2 border-dashed" style={{ borderColor: getEdgeColor('includes') }} />
          <span className="text-zinc-300">Includes</span>
        </div>
      </div>
    </div>
  )
}
