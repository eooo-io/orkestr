import { useEffect, useRef, useCallback } from 'react'
import * as d3 from 'd3'

export interface GraphNode {
  id: string
  type: 'project' | 'agent' | 'skill' | 'provider' | 'mcp' | 'output'
  label: string
  sublabel?: string
  icon?: string
  color: string
  radius: number
  x?: number
  y?: number
  fx?: number | null
  fy?: number | null
  meta?: Record<string, unknown>
}

export interface GraphEdge {
  source: string | GraphNode
  target: string | GraphNode
  type: string
  label?: string
  color: string
  dashed?: boolean
}

const TYPE_COLORS: Record<string, string> = {
  project: '#3b82f6',
  agent: '#8b5cf6',
  skill: '#10b981',
  provider: '#f59e0b',
  mcp: '#ec4899',
  output: '#6b7280',
}

const EDGE_COLORS: Record<string, string> = {
  includes: '#10b981',
  assigned: '#8b5cf6',
  syncs_to: '#f59e0b',
  outputs: '#6b7280',
  uses_mcp: '#ec4899',
}

export function getNodeColor(type: string): string {
  return TYPE_COLORS[type] ?? '#6b7280'
}

export function getEdgeColor(type: string): string {
  return EDGE_COLORS[type] ?? '#4b5563'
}

export function useD3Graph(
  nodes: GraphNode[],
  edges: GraphEdge[],
  options?: {
    width?: number
    height?: number
    onNodeClick?: (node: GraphNode) => void
  },
) {
  const svgRef = useRef<SVGSVGElement>(null)
  const simulationRef = useRef<d3.Simulation<GraphNode, GraphEdge> | null>(null)

  const destroy = useCallback(() => {
    if (simulationRef.current) {
      simulationRef.current.stop()
      simulationRef.current = null
    }
  }, [])

  useEffect(() => {
    if (!svgRef.current || nodes.length === 0) return

    const svg = d3.select(svgRef.current)
    const width = options?.width ?? svgRef.current.clientWidth
    const height = options?.height ?? svgRef.current.clientHeight

    // Clear previous
    svg.selectAll('*').remove()

    // Defs for arrowheads
    const defs = svg.append('defs')
    const edgeTypes = [...new Set(edges.map((e) => e.type))]
    edgeTypes.forEach((type) => {
      defs
        .append('marker')
        .attr('id', `arrow-${type}`)
        .attr('viewBox', '0 -5 10 10')
        .attr('refX', 20)
        .attr('refY', 0)
        .attr('markerWidth', 6)
        .attr('markerHeight', 6)
        .attr('orient', 'auto')
        .append('path')
        .attr('d', 'M0,-5L10,0L0,5')
        .attr('fill', getEdgeColor(type))
    })

    // Container for zoom
    const container = svg.append('g')

    // Zoom behavior
    const zoom = d3.zoom<SVGSVGElement, unknown>()
      .scaleExtent([0.1, 4])
      .on('zoom', (event) => {
        container.attr('transform', event.transform)
      })
    svg.call(zoom)

    // Force simulation
    const simulation = d3
      .forceSimulation<GraphNode>(nodes)
      .force(
        'link',
        d3
          .forceLink<GraphNode, GraphEdge>(edges)
          .id((d) => d.id)
          .distance(120),
      )
      .force('charge', d3.forceManyBody().strength(-400))
      .force('center', d3.forceCenter(width / 2, height / 2))
      .force('collision', d3.forceCollide().radius((d: GraphNode) => d.radius + 10))

    simulationRef.current = simulation

    // Draw edges
    const link = container
      .append('g')
      .selectAll('line')
      .data(edges)
      .join('line')
      .attr('stroke', (d) => d.color)
      .attr('stroke-width', 1.5)
      .attr('stroke-opacity', 0.6)
      .attr('stroke-dasharray', (d) => (d.dashed ? '5,5' : ''))
      .attr('marker-end', (d) => `url(#arrow-${d.type})`)

    // Edge labels
    const edgeLabel = container
      .append('g')
      .selectAll('text')
      .data(edges.filter((e) => e.label))
      .join('text')
      .attr('font-size', 9)
      .attr('fill', '#9ca3af')
      .attr('text-anchor', 'middle')
      .text((d) => d.label ?? '')

    // Draw nodes
    const node = container
      .append('g')
      .selectAll<SVGGElement, GraphNode>('g')
      .data(nodes)
      .join('g')
      .style('cursor', 'pointer')
      .call(
        d3
          .drag<SVGGElement, GraphNode>()
          .on('start', (event, d) => {
            if (!event.active) simulation.alphaTarget(0.3).restart()
            d.fx = d.x
            d.fy = d.y
          })
          .on('drag', (event, d) => {
            d.fx = event.x
            d.fy = event.y
          })
          .on('end', (event, d) => {
            if (!event.active) simulation.alphaTarget(0)
            d.fx = null
            d.fy = null
          }),
      )

    // Node circle
    node
      .append('circle')
      .attr('r', (d) => d.radius)
      .attr('fill', (d) => d.color)
      .attr('fill-opacity', 0.15)
      .attr('stroke', (d) => d.color)
      .attr('stroke-width', 2)

    // Node icon text
    node
      .append('text')
      .attr('text-anchor', 'middle')
      .attr('dominant-baseline', 'central')
      .attr('font-size', (d) => d.radius * 0.8)
      .text((d) => d.icon ?? d.label.charAt(0).toUpperCase())

    // Node label
    node
      .append('text')
      .attr('text-anchor', 'middle')
      .attr('dy', (d) => d.radius + 14)
      .attr('font-size', 11)
      .attr('font-weight', 500)
      .attr('fill', '#e5e7eb')
      .text((d) => d.label)

    // Sublabel
    node
      .filter((d) => !!d.sublabel)
      .append('text')
      .attr('text-anchor', 'middle')
      .attr('dy', (d) => d.radius + 26)
      .attr('font-size', 9)
      .attr('fill', '#9ca3af')
      .text((d) => d.sublabel ?? '')

    // Click handler
    if (options?.onNodeClick) {
      node.on('click', (_event, d) => options.onNodeClick!(d))
    }

    // Hover effects
    node
      .on('mouseenter', function (_event, d) {
        d3.select(this).select('circle').attr('fill-opacity', 0.3).attr('stroke-width', 3)
        // Highlight connected edges
        link
          .attr('stroke-opacity', (l: GraphEdge) => {
            const s = typeof l.source === 'string' ? l.source : l.source.id
            const t = typeof l.target === 'string' ? l.target : l.target.id
            return s === d.id || t === d.id ? 1 : 0.15
          })
          .attr('stroke-width', (l: GraphEdge) => {
            const s = typeof l.source === 'string' ? l.source : l.source.id
            const t = typeof l.target === 'string' ? l.target : l.target.id
            return s === d.id || t === d.id ? 2.5 : 1.5
          })
      })
      .on('mouseleave', function () {
        d3.select(this).select('circle').attr('fill-opacity', 0.15).attr('stroke-width', 2)
        link.attr('stroke-opacity', 0.6).attr('stroke-width', 1.5)
      })

    // Tick
    simulation.on('tick', () => {
      link
        .attr('x1', (d: GraphEdge) => (d.source as GraphNode).x ?? 0)
        .attr('y1', (d: GraphEdge) => (d.source as GraphNode).y ?? 0)
        .attr('x2', (d: GraphEdge) => (d.target as GraphNode).x ?? 0)
        .attr('y2', (d: GraphEdge) => (d.target as GraphNode).y ?? 0)

      edgeLabel
        .attr('x', (d: GraphEdge) => (((d.source as GraphNode).x ?? 0) + ((d.target as GraphNode).x ?? 0)) / 2)
        .attr('y', (d: GraphEdge) => (((d.source as GraphNode).y ?? 0) + ((d.target as GraphNode).y ?? 0)) / 2)

      node.attr('transform', (d) => `translate(${d.x ?? 0},${d.y ?? 0})`)
    })

    // Initial zoom to fit
    setTimeout(() => {
      svg.call(zoom.transform, d3.zoomIdentity.translate(0, 0).scale(0.85))
    }, 500)

    return () => {
      simulation.stop()
    }
  }, [nodes, edges, options?.width, options?.height])

  return { svgRef, destroy }
}
