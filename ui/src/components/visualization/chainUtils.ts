import type { Edge } from '@xyflow/react'

/**
 * Represents a detected delegation chain through the graph.
 */
export interface DelegationChain {
  /** Ordered list of node IDs in this chain */
  nodeIds: string[]
  /** Ordered list of edge IDs connecting the nodes */
  edgeIds: string[]
  /** Human-readable summary like "Research -> Analysis -> Writer" */
  summary: string
}

/**
 * Given the edges of the graph, find all maximal delegation chains (A->B->C).
 * A chain is a path of delegation edges where no node appears more than once.
 * Only edges whose id starts with 'e-delegation-' or whose data.isDelegation is true count.
 */
export function detectDelegationChains(edges: Edge[]): DelegationChain[] {
  // Build adjacency from delegation edges only
  const delegationEdges = edges.filter(
    (e) => e.id.startsWith('e-delegation-') || e.id.startsWith('e-agent-a2a-') || (e.data as Record<string, unknown>)?.isDelegation === true,
  )

  if (delegationEdges.length === 0) return []

  // source -> [{ target, edgeId }]
  const adj = new Map<string, Array<{ target: string; edgeId: string }>>()
  const hasIncoming = new Set<string>()

  for (const e of delegationEdges) {
    const list = adj.get(e.source) ?? []
    list.push({ target: e.target, edgeId: e.id })
    adj.set(e.source, list)
    hasIncoming.add(e.target)
  }

  // Find chain starts: nodes that have outgoing delegation but no incoming delegation
  const allSources = new Set(adj.keys())
  const starts = [...allSources].filter((s) => !hasIncoming.has(s))

  // If there are cycles (all nodes have incoming), pick any node as start
  if (starts.length === 0 && allSources.size > 0) {
    starts.push([...allSources][0])
  }

  const chains: DelegationChain[] = []

  for (const start of starts) {
    // DFS to find all maximal paths from this start
    const stack: Array<{ nodeIds: string[]; edgeIds: string[]; visited: Set<string> }> = [
      { nodeIds: [start], edgeIds: [], visited: new Set([start]) },
    ]

    while (stack.length > 0) {
      const current = stack.pop()!
      const lastNode = current.nodeIds[current.nodeIds.length - 1]
      const neighbors = adj.get(lastNode) ?? []
      const unvisited = neighbors.filter((n) => !current.visited.has(n.target))

      if (unvisited.length === 0) {
        // End of chain — only record if chain has at least 2 nodes (1 edge)
        if (current.nodeIds.length >= 2) {
          chains.push({
            nodeIds: current.nodeIds,
            edgeIds: current.edgeIds,
            summary: current.nodeIds
              .map((id) => extractNodeLabel(id))
              .join(' -> '),
          })
        }
      } else {
        for (const next of unvisited) {
          const newVisited = new Set(current.visited)
          newVisited.add(next.target)
          stack.push({
            nodeIds: [...current.nodeIds, next.target],
            edgeIds: [...current.edgeIds, next.edgeId],
            visited: newVisited,
          })
        }
      }
    }
  }

  return chains
}

/**
 * Extract a human-readable label from a node ID like "agent-5" -> "Agent 5"
 */
function extractNodeLabel(nodeId: string): string {
  const parts = nodeId.split('-')
  if (parts.length >= 2) {
    const type = parts[0]
    const id = parts.slice(1).join('-')
    return `${type?.charAt(0).toUpperCase()}${type?.slice(1)} ${id}`
  }
  return nodeId
}

/**
 * Given a node ID and the detected chains, find all chains that include that node.
 */
export function findChainsForNode(nodeId: string, chains: DelegationChain[]): DelegationChain[] {
  return chains.filter((c) => c.nodeIds.includes(nodeId))
}

/**
 * Given a set of chains, collect all unique node IDs and edge IDs involved.
 */
export function collectChainElements(chains: DelegationChain[]): {
  nodeIds: Set<string>
  edgeIds: Set<string>
} {
  const nodeIds = new Set<string>()
  const edgeIds = new Set<string>()
  for (const chain of chains) {
    for (const n of chain.nodeIds) nodeIds.add(n)
    for (const e of chain.edgeIds) edgeIds.add(e)
  }
  return { nodeIds, edgeIds }
}

/**
 * Compute step numbers for edges within a chain.
 * Returns a map from edgeId -> step number (1-based).
 */
export function getEdgeStepNumbers(chains: DelegationChain[]): Map<string, number> {
  const stepMap = new Map<string, number>()
  for (const chain of chains) {
    chain.edgeIds.forEach((edgeId, idx) => {
      // If edge appears in multiple chains, use the lowest step number
      const existing = stepMap.get(edgeId)
      if (existing === undefined || idx + 1 < existing) {
        stepMap.set(edgeId, idx + 1)
      }
    })
  }
  return stepMap
}
