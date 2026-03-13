import { memo } from 'react'
import { Handle, Position, type NodeProps } from '@xyflow/react'
import {
  Brain,
  Play,
  Square,
  GitBranch,
  Split,
  Merge,
  ShieldCheck,
} from 'lucide-react'

interface StepNodeData {
  label: string
  type: string
  agentName?: string | null
  config?: Record<string, unknown> | null
  [key: string]: unknown
}

const TYPE_CONFIG: Record<
  string,
  { icon: React.ElementType; color: string; bg: string }
> = {
  start: { icon: Play, color: 'text-emerald-400', bg: 'bg-emerald-500/20' },
  end: { icon: Square, color: 'text-red-400', bg: 'bg-red-500/20' },
  agent: { icon: Brain, color: 'text-blue-400', bg: 'bg-blue-500/20' },
  checkpoint: {
    icon: ShieldCheck,
    color: 'text-amber-400',
    bg: 'bg-amber-500/20',
  },
  condition: {
    icon: GitBranch,
    color: 'text-violet-400',
    bg: 'bg-violet-500/20',
  },
  parallel_split: {
    icon: Split,
    color: 'text-cyan-400',
    bg: 'bg-cyan-500/20',
  },
  parallel_join: {
    icon: Merge,
    color: 'text-cyan-400',
    bg: 'bg-cyan-500/20',
  },
}

export const StepNode = memo(function StepNode({
  data,
  selected,
}: NodeProps & { data: StepNodeData }) {
  const cfg = TYPE_CONFIG[data.type] || TYPE_CONFIG.agent
  const Icon = cfg.icon

  return (
    <div
      className={`px-4 py-3 min-w-[160px] border transition-all ${
        selected
          ? 'border-primary shadow-lg shadow-primary/20'
          : 'border-border'
      } bg-card`}
    >
      {/* Handles */}
      {data.type !== 'start' && (
        <Handle
          type="target"
          position={Position.Top}
          className="!w-3 !h-3 !bg-muted-foreground !border-2 !border-card"
        />
      )}
      {data.type !== 'end' && (
        <Handle
          type="source"
          position={Position.Bottom}
          className="!w-3 !h-3 !bg-muted-foreground !border-2 !border-card"
        />
      )}

      {/* Content */}
      <div className="flex items-center gap-2">
        <div
          className={`h-7 w-7 flex items-center justify-center flex-shrink-0 ${cfg.bg}`}
        >
          <Icon className={`h-4 w-4 ${cfg.color}`} />
        </div>
        <div className="min-w-0">
          <div className="text-xs font-medium truncate">{data.label}</div>
          {data.type === 'agent' && data.agentName && (
            <div className="text-[10px] text-muted-foreground truncate">
              {data.agentName}
            </div>
          )}
          {data.type === 'checkpoint' && (
            <div className="text-[10px] text-amber-400/80">
              requires approval
            </div>
          )}
        </div>
      </div>
    </div>
  )
})

export const workflowNodeTypes = {
  step: StepNode,
}
