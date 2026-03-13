import { memo } from 'react'
import { Handle, Position, type NodeProps } from '@xyflow/react'
import {
  Bot,
  Sparkles,
  Server,
  Wifi,
  AlertTriangle,
  FileCode,
  Zap,
} from 'lucide-react'

/* ─── Agent Node ───────────────────────────────────────────── */
type AgentNodeData = {
  label: string
  role: string
  icon: string | null
  isEnabled: boolean
  hasCustomInstructions: boolean
  skillCount: number
  model: string | null
  planningMode: string
  contextStrategy: string
  loopCondition: string
  maxIterations: number | null
  canDelegate: boolean
  hasLoopConfig: boolean
  mcpCount: number
  a2aCount: number
}

export const AgentNode = memo(({ data }: NodeProps & { data: AgentNodeData }) => {
  const d = data as AgentNodeData
  return (
    <div
      className={`px-4 py-3 rounded-lg border-2 min-w-[220px] shadow-lg transition-all ${
        d.isEnabled
          ? 'bg-violet-950/80 border-violet-500/60 hover:border-violet-400'
          : 'bg-zinc-900/80 border-zinc-600/40 opacity-60'
      }`}
    >
      <Handle type="target" position={Position.Left} className="!bg-violet-500 !w-2 !h-2" />
      <Handle type="source" position={Position.Right} className="!bg-violet-500 !w-2 !h-2" />
      <div className="flex items-center gap-2 mb-1">
        <Bot className="h-4 w-4 text-violet-400 shrink-0" />
        <span className="text-sm font-semibold text-violet-100 truncate">{d.label}</span>
        {!d.isEnabled && (
          <span className="text-[10px] px-1.5 py-0.5 rounded bg-zinc-700 text-zinc-400 shrink-0">off</span>
        )}
        {d.canDelegate && (
          <span className="text-[10px] px-1.5 py-0.5 rounded bg-violet-800 text-violet-300 shrink-0">delegate</span>
        )}
      </div>
      <div className="flex items-center gap-2 text-[11px] text-zinc-400">
        <span className="capitalize">{d.role}</span>
        <span>·</span>
        <span>{d.skillCount} skill{d.skillCount !== 1 ? 's' : ''}</span>
        {d.mcpCount > 0 && (
          <>
            <span>·</span>
            <span>{d.mcpCount} MCP</span>
          </>
        )}
        {d.a2aCount > 0 && (
          <>
            <span>·</span>
            <span>{d.a2aCount} A2A</span>
          </>
        )}
        {d.hasCustomInstructions && (
          <>
            <span>·</span>
            <Zap className="h-3 w-3 text-amber-400" />
          </>
        )}
      </div>
      {d.hasLoopConfig && (
        <div className="flex items-center gap-1.5 mt-1.5 text-[10px] text-zinc-500 flex-wrap">
          {d.model && <span className="px-1 py-0.5 rounded bg-zinc-800 text-zinc-400">{d.model}</span>}
          {d.planningMode !== 'none' && (
            <span className="px-1 py-0.5 rounded bg-violet-900/50 text-violet-300">{d.planningMode}</span>
          )}
          {d.maxIterations && (
            <span className="text-zinc-500">max:{d.maxIterations}</span>
          )}
          <span className="text-zinc-600">{d.loopCondition}</span>
        </div>
      )}
    </div>
  )
})
AgentNode.displayName = 'AgentNode'

/* ─── Skill Node ───────────────────────────────────────────── */
type SkillNodeData = {
  label: string
  slug: string
  tokenEstimate: number
  tags: string[]
  includeCount: number
  hasConditions: boolean
  isCircular: boolean
  model: string | null
  unassigned?: boolean
}

export const SkillNode = memo(({ data }: NodeProps & { data: SkillNodeData }) => {
  const d = data as SkillNodeData
  return (
    <div
      className={`px-3 py-2.5 rounded-lg border min-w-[180px] shadow-md transition-all ${
        d.isCircular
          ? 'bg-red-950/60 border-red-500/60 hover:border-red-400'
          : d.unassigned
            ? 'bg-zinc-900/60 border-zinc-600/40 hover:border-zinc-500 opacity-70'
            : 'bg-emerald-950/60 border-emerald-500/40 hover:border-emerald-400'
      }`}
    >
      <Handle type="target" position={Position.Left} className="!bg-emerald-500 !w-2 !h-2" />
      <Handle type="source" position={Position.Right} className="!bg-emerald-500 !w-2 !h-2" />
      <div className="flex items-center gap-2 mb-1">
        {d.isCircular ? (
          <AlertTriangle className="h-3.5 w-3.5 text-red-400 shrink-0" />
        ) : (
          <Sparkles className="h-3.5 w-3.5 text-emerald-400 shrink-0" />
        )}
        <span className="text-xs font-medium text-emerald-100 truncate">{d.label}</span>
      </div>
      <div className="flex items-center gap-1.5 text-[10px] text-zinc-500 flex-wrap">
        <span>{d.tokenEstimate} tok</span>
        {d.model && (
          <>
            <span>·</span>
            <span className="text-zinc-400">{d.model}</span>
          </>
        )}
        {d.includeCount > 0 && (
          <>
            <span>·</span>
            <span>{d.includeCount} include{d.includeCount !== 1 ? 's' : ''}</span>
          </>
        )}
        {d.hasConditions && (
          <>
            <span>·</span>
            <span className="text-amber-400">conditional</span>
          </>
        )}
        {d.unassigned && (
          <>
            <span>·</span>
            <span className="text-zinc-500 italic">unassigned</span>
          </>
        )}
      </div>
      {d.tags.length > 0 && (
        <div className="flex gap-1 mt-1.5 flex-wrap">
          {d.tags.slice(0, 3).map((tag) => (
            <span
              key={tag}
              className="text-[9px] px-1.5 py-0.5 rounded-full bg-emerald-900/50 text-emerald-300/70"
            >
              {tag}
            </span>
          ))}
          {d.tags.length > 3 && (
            <span className="text-[9px] text-zinc-500">+{d.tags.length - 3}</span>
          )}
        </div>
      )}
    </div>
  )
})
SkillNode.displayName = 'SkillNode'

/* ─── Provider Node ────────────────────────────────────────── */
type ProviderNodeData = {
  label: string
  slug: string
  outputCount: number
  outputs: string[]
}

export const ProviderNode = memo(({ data }: NodeProps & { data: ProviderNodeData }) => {
  const d = data as ProviderNodeData
  return (
    <div className="px-4 py-3 rounded-lg border border-amber-500/40 bg-amber-950/50 min-w-[200px] shadow-md hover:border-amber-400 transition-all">
      <Handle type="target" position={Position.Left} className="!bg-amber-500 !w-2 !h-2" />
      <div className="flex items-center gap-2 mb-1">
        <FileCode className="h-4 w-4 text-amber-400 shrink-0" />
        <span className="text-sm font-semibold text-amber-100">{d.label}</span>
      </div>
      <div className="text-[11px] text-zinc-400">
        {d.outputCount} output file{d.outputCount !== 1 ? 's' : ''}
      </div>
      {d.outputs.length > 0 && (
        <div className="mt-1.5 space-y-0.5">
          {d.outputs.slice(0, 3).map((path) => (
            <div key={path} className="text-[9px] font-mono text-zinc-500 truncate max-w-[180px]">
              {path}
            </div>
          ))}
          {d.outputs.length > 3 && (
            <div className="text-[9px] text-zinc-600">+{d.outputs.length - 3} more</div>
          )}
        </div>
      )}
    </div>
  )
})
ProviderNode.displayName = 'ProviderNode'

/* ─── MCP Node ─────────────────────────────────────────────── */
type McpNodeData = {
  label: string
  transport: string
}

export const McpNode = memo(({ data }: NodeProps & { data: McpNodeData }) => {
  const d = data as McpNodeData
  return (
    <div className="px-4 py-3 rounded-lg border border-pink-500/40 bg-pink-950/50 min-w-[180px] shadow-md hover:border-pink-400 transition-all">
      <Handle type="target" position={Position.Left} className="!bg-pink-500 !w-2 !h-2" />
      <div className="flex items-center gap-2 mb-1">
        <Server className="h-4 w-4 text-pink-400 shrink-0" />
        <span className="text-sm font-semibold text-pink-100">{d.label}</span>
      </div>
      <div className="flex items-center gap-1 text-[11px] text-zinc-400">
        <Wifi className="h-3 w-3" />
        <span>{d.transport}</span>
      </div>
    </div>
  )
})
McpNode.displayName = 'McpNode'

/* ─── Project Node (used in full overview) ─────────────────── */
type ProjectNodeData = {
  label: string
  syncedAt: string | null
}

export const ProjectNode = memo(({ data }: NodeProps & { data: ProjectNodeData }) => {
  const d = data as ProjectNodeData
  return (
    <div className="px-5 py-4 rounded-xl border-2 border-blue-500/60 bg-blue-950/60 min-w-[220px] shadow-xl">
      <Handle type="source" position={Position.Right} className="!bg-blue-500 !w-2.5 !h-2.5" />
      <div className="text-base font-bold text-blue-100 mb-1">{d.label}</div>
      <div className="text-[11px] text-zinc-400">
        {d.syncedAt
          ? `Last synced ${new Date(d.syncedAt).toLocaleDateString()}`
          : 'Not synced yet'}
      </div>
    </div>
  )
})
ProjectNode.displayName = 'ProjectNode'

/* ─── Lane Label (column header) ───────────────────────────── */
type LaneLabelData = {
  label: string
  count: number
}

export const LaneLabel = memo(({ data }: NodeProps & { data: LaneLabelData }) => {
  const d = data as LaneLabelData
  return (
    <div className="flex items-center gap-2 px-1 pb-2">
      <span className="text-xs font-semibold uppercase tracking-wider text-zinc-500">
        {d.label}
      </span>
      <span className="text-[10px] px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-400">
        {d.count}
      </span>
    </div>
  )
})
LaneLabel.displayName = 'LaneLabel'
