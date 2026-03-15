import { memo } from 'react'
import {
  BaseEdge,
  EdgeLabelRenderer,
  getBezierPath,
  type EdgeProps,
  type Edge,
} from '@xyflow/react'

export type DelegationEdgeData = {
  isDelegation: true
  label?: string
  stepNumber?: number
  isHighlighted?: boolean
  isBidirectional?: boolean
}

type DelegationEdgeType = Edge<DelegationEdgeData, 'delegation'>

const DelegationEdge = memo(({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  data,
  selected,
}: EdgeProps<DelegationEdgeType>) => {
  const [edgePath, labelX, labelY] = getBezierPath({
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
  })

  const isHighlighted = data?.isHighlighted ?? false
  const stepNumber = data?.stepNumber
  const label = data?.label ?? 'delegates to'
  const isBidirectional = data?.isBidirectional ?? false

  const strokeColor = isHighlighted ? '#22d3ee' : '#06b6d4'
  const strokeWidth = isHighlighted ? 2.5 : 1.5
  const opacity = isHighlighted ? 1 : 0.8

  // Unique marker IDs for this edge
  const markerId = `delegation-arrow-${id}`
  const markerStartId = isBidirectional ? `delegation-arrow-start-${id}` : undefined

  return (
    <>
      {/* Custom SVG marker definitions */}
      <svg style={{ position: 'absolute', width: 0, height: 0 }}>
        <defs>
          <marker
            id={markerId}
            markerWidth="12"
            markerHeight="12"
            refX="10"
            refY="6"
            orient="auto"
            markerUnits="userSpaceOnUse"
          >
            <path
              d="M2,2 L10,6 L2,10 L4,6 Z"
              fill={strokeColor}
            />
          </marker>
          {isBidirectional && (
            <marker
              id={markerStartId}
              markerWidth="12"
              markerHeight="12"
              refX="2"
              refY="6"
              orient="auto-start-reverse"
              markerUnits="userSpaceOnUse"
            >
              <path
                d="M10,2 L2,6 L10,10 L8,6 Z"
                fill={strokeColor}
              />
            </marker>
          )}
        </defs>
      </svg>

      <BaseEdge
        id={id}
        path={edgePath}
        style={{
          stroke: strokeColor,
          strokeWidth,
          strokeDasharray: '8 4',
          opacity,
          animation: 'delegationDash 1s linear infinite',
          cursor: 'pointer',
        }}
        markerEnd={`url(#${markerId})`}
        markerStart={isBidirectional ? `url(#${markerStartId})` : undefined}
      />

      {/* Label + step number */}
      <EdgeLabelRenderer>
        <div
          className="nodrag nopan"
          style={{
            position: 'absolute',
            transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
            pointerEvents: 'all',
            cursor: 'pointer',
          }}
        >
          <div
            className={`flex items-center gap-1.5 px-2 py-1 rounded-md text-[10px] font-medium transition-all ${
              selected
                ? 'bg-cyan-900/90 text-cyan-200 border border-cyan-500 shadow-lg shadow-cyan-500/20'
                : isHighlighted
                  ? 'bg-cyan-950/90 text-cyan-300 border border-cyan-600/60'
                  : 'bg-zinc-900/90 text-cyan-400/80 border border-zinc-700/60'
            }`}
          >
            {stepNumber !== undefined && (
              <span className="flex items-center justify-center w-4 h-4 rounded-full bg-cyan-600 text-white text-[9px] font-bold">
                {stepNumber}
              </span>
            )}
            <span>{label}</span>
          </div>
        </div>
      </EdgeLabelRenderer>

      {/* Inject keyframe animation via style tag */}
      <EdgeLabelRenderer>
        <style>{`
          @keyframes delegationDash {
            to {
              stroke-dashoffset: -24;
            }
          }
        `}</style>
      </EdgeLabelRenderer>
    </>
  )
})

DelegationEdge.displayName = 'DelegationEdge'

export default DelegationEdge
