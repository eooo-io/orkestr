import { useEffect, useState } from 'react'
import { X, Trash2 } from 'lucide-react'
import type { WorkflowStep, WorkflowEdge, Agent } from '@/types'
import { fetchAgents } from '@/api/client'

interface Props {
  selectedStep: WorkflowStep | null
  selectedEdge: WorkflowEdge | null
  onUpdateStep: (step: WorkflowStep) => void
  onUpdateEdge: (edge: WorkflowEdge) => void
  onDeleteStep: (stepId: number) => void
  onDeleteEdge: (edgeId: number) => void
  onClose: () => void
}

export function WorkflowPropertiesPanel({
  selectedStep,
  selectedEdge,
  onUpdateStep,
  onUpdateEdge,
  onDeleteStep,
  onDeleteEdge,
  onClose,
}: Props) {
  const [agents, setAgents] = useState<Agent[]>([])

  useEffect(() => {
    fetchAgents().then(setAgents).catch(() => {})
  }, [])

  if (!selectedStep && !selectedEdge) return null

  return (
    <div className="w-72 bg-card border-l border-border h-full overflow-y-auto">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-border">
        <h3 className="text-sm font-medium">
          {selectedStep ? 'Step Properties' : 'Edge Properties'}
        </h3>
        <button
          onClick={onClose}
          className="p-1 hover:bg-muted transition-colors"
        >
          <X className="h-4 w-4 text-muted-foreground" />
        </button>
      </div>

      {/* Step Properties */}
      {selectedStep && (
        <div className="p-4 space-y-4">
          <div>
            <label className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">
              Name
            </label>
            <input
              type="text"
              value={selectedStep.name}
              onChange={(e) =>
                onUpdateStep({ ...selectedStep, name: e.target.value })
              }
              className="w-full mt-1 px-3 py-1.5 text-sm bg-muted border border-border focus:border-primary focus:outline-none"
            />
          </div>

          <div>
            <label className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">
              Type
            </label>
            <select
              value={selectedStep.type}
              onChange={(e) =>
                onUpdateStep({ ...selectedStep, type: e.target.value })
              }
              className="w-full mt-1 px-3 py-1.5 text-sm bg-muted border border-border focus:border-primary focus:outline-none"
            >
              <option value="start">Start</option>
              <option value="end">End</option>
              <option value="agent">Agent</option>
              <option value="checkpoint">Checkpoint</option>
              <option value="condition">Condition</option>
              <option value="parallel_split">Parallel Split</option>
              <option value="parallel_join">Parallel Join</option>
            </select>
          </div>

          {selectedStep.type === 'agent' && (
            <div>
              <label className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">
                Agent
              </label>
              <select
                value={selectedStep.agent_id ?? ''}
                onChange={(e) =>
                  onUpdateStep({
                    ...selectedStep,
                    agent_id: e.target.value ? Number(e.target.value) : null,
                  })
                }
                className="w-full mt-1 px-3 py-1.5 text-sm bg-muted border border-border focus:border-primary focus:outline-none"
              >
                <option value="">Select agent...</option>
                {agents.map((agent) => (
                  <option key={agent.id} value={agent.id}>
                    {agent.name} ({agent.role})
                  </option>
                ))}
              </select>
            </div>
          )}

          {selectedStep.type === 'checkpoint' && (
            <div className="p-3 bg-amber-500/10 border border-amber-500/20 text-xs text-amber-400">
              This step will pause execution and require human approval before
              proceeding.
            </div>
          )}

          {selectedStep.type === 'condition' && (
            <div className="p-3 bg-violet-500/10 border border-violet-500/20 text-xs text-violet-400">
              Add condition expressions on outgoing edges to control routing.
            </div>
          )}

          <div className="pt-2 border-t border-border">
            <button
              onClick={() => onDeleteStep(selectedStep.id)}
              className="flex items-center gap-1.5 text-xs text-red-400 hover:text-red-300 transition-colors"
            >
              <Trash2 className="h-3 w-3" />
              Delete Step
            </button>
          </div>
        </div>
      )}

      {/* Edge Properties */}
      {selectedEdge && (
        <div className="p-4 space-y-4">
          <div>
            <label className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">
              Label
            </label>
            <input
              type="text"
              value={selectedEdge.label ?? ''}
              onChange={(e) =>
                onUpdateEdge({
                  ...selectedEdge,
                  label: e.target.value || null,
                })
              }
              placeholder="Optional label..."
              className="w-full mt-1 px-3 py-1.5 text-sm bg-muted border border-border focus:border-primary focus:outline-none"
            />
          </div>

          <div>
            <label className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">
              Condition Expression
            </label>
            <textarea
              value={selectedEdge.condition_expression ?? ''}
              onChange={(e) =>
                onUpdateEdge({
                  ...selectedEdge,
                  condition_expression: e.target.value || null,
                })
              }
              placeholder='e.g. status == "approved"'
              rows={3}
              className="w-full mt-1 px-3 py-1.5 text-sm bg-muted border border-border focus:border-primary focus:outline-none font-mono"
            />
            <p className="text-[10px] text-muted-foreground mt-1">
              Supports: ==, !=, &gt;, &lt;, &gt;=, &lt;=, exists. References
              context values.
            </p>
          </div>

          <div>
            <label className="text-[11px] font-medium text-muted-foreground uppercase tracking-wider">
              Priority
            </label>
            <input
              type="number"
              value={selectedEdge.priority}
              onChange={(e) =>
                onUpdateEdge({
                  ...selectedEdge,
                  priority: parseInt(e.target.value) || 0,
                })
              }
              className="w-full mt-1 px-3 py-1.5 text-sm bg-muted border border-border focus:border-primary focus:outline-none"
            />
            <p className="text-[10px] text-muted-foreground mt-1">
              Higher priority edges are evaluated first.
            </p>
          </div>

          <div className="pt-2 border-t border-border">
            <button
              onClick={() => onDeleteEdge(selectedEdge.id)}
              className="flex items-center gap-1.5 text-xs text-red-400 hover:text-red-300 transition-colors"
            >
              <Trash2 className="h-3 w-3" />
              Delete Edge
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
