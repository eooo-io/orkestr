import { useCallback, useEffect, useMemo, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  addEdge,
  useNodesState,
  useEdgesState,
  type Connection,
  type Node,
  type Edge,
  BackgroundVariant,
} from '@xyflow/react'
import '@xyflow/react/dist/style.css'
import {
  Save,
  CheckCircle2,
  AlertTriangle,
  Plus,
  ArrowLeft,
  Loader2,
  History,
  Download,
} from 'lucide-react'
import {
  fetchWorkflow,
  createWorkflow,
  updateWorkflow,
  updateWorkflowSteps,
  updateWorkflowEdges,
  validateWorkflow,
  createWorkflowVersion,
  fetchWorkflowVersions,
  restoreWorkflowVersion,
  exportWorkflow,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import { useAppStore } from '@/store/useAppStore'
import { workflowNodeTypes } from '@/components/workflows/WorkflowNodes'
import { WorkflowPropertiesPanel } from '@/components/workflows/WorkflowPropertiesPanel'
import type { Workflow, WorkflowStep, WorkflowEdge, WorkflowVersion, WorkflowValidation } from '@/types'

const STEP_TYPES = [
  { type: 'start', label: 'Start' },
  { type: 'end', label: 'End' },
  { type: 'agent', label: 'Agent' },
  { type: 'checkpoint', label: 'Checkpoint' },
  { type: 'condition', label: 'Condition' },
  { type: 'parallel_split', label: 'Split' },
  { type: 'parallel_join', label: 'Join' },
]

let idCounter = 0
function nextTempId() {
  return --idCounter
}

export function WorkflowBuilder() {
  const { id: projectId, workflowId } = useParams<{ id: string; workflowId: string }>()
  const navigate = useNavigate()
  const { showToast } = useAppStore()
  const confirm = useConfirm()

  const pid = Number(projectId)
  const wid = workflowId && workflowId !== 'new' ? Number(workflowId) : null

  const [workflow, setWorkflow] = useState<Workflow | null>(null)
  const [loading, setLoading] = useState(!!wid)
  const [saving, setSaving] = useState(false)
  const [validation, setValidation] = useState<WorkflowValidation | null>(null)
  const [versions, setVersions] = useState<WorkflowVersion[]>([])
  const [showVersions, setShowVersions] = useState(false)
  const [showAddMenu, setShowAddMenu] = useState(false)
  const [showExportMenu, setShowExportMenu] = useState(false)

  // Workflow metadata form
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [triggerType, setTriggerType] = useState('manual')
  const [status, setStatus] = useState('draft')

  // React Flow state
  const [nodes, setNodes, onNodesChange] = useNodesState([])
  const [edges, setEdges, onEdgesChange] = useEdgesState([])

  // Selected items for properties panel
  const [selectedStepId, setSelectedStepId] = useState<string | null>(null)
  const [selectedEdgeId, setSelectedEdgeId] = useState<string | null>(null)

  // Map steps by their flow node ID for properties panel
  const [stepMap, setStepMap] = useState<Record<string, WorkflowStep>>({})

  // Load workflow
  useEffect(() => {
    if (!wid) {
      setLoading(false)
      return
    }

    fetchWorkflow(pid, wid)
      .then((wf) => {
        setWorkflow(wf)
        setName(wf.name)
        setDescription(wf.description || '')
        setTriggerType(wf.trigger_type)
        setStatus(wf.status)

        // Convert steps/edges to React Flow format
        const flowNodes: Node[] = (wf.steps || []).map((step) => ({
          id: String(step.id),
          type: 'step',
          position: { x: step.position_x, y: step.position_y },
          data: {
            label: step.name,
            type: step.type,
            agentName: step.agent?.name || null,
          },
        }))

        const flowEdges: Edge[] = (wf.edges || []).map((edge) => ({
          id: `e-${edge.id}`,
          source: String(edge.source_step_id),
          target: String(edge.target_step_id),
          label: edge.label || undefined,
          animated: !!edge.condition_expression,
          style: edge.condition_expression
            ? { stroke: '#8b5cf6', strokeWidth: 2 }
            : { strokeWidth: 2 },
        }))

        setNodes(flowNodes)
        setEdges(flowEdges)

        // Build step map
        const map: Record<string, WorkflowStep> = {}
        for (const step of wf.steps || []) {
          map[String(step.id)] = step
        }
        setStepMap(map)
      })
      .catch(() => showToast('Failed to load workflow', 'error'))
      .finally(() => setLoading(false))
  }, [wid, pid])

  // Edge connection handler
  const onConnect = useCallback(
    (params: Connection) => {
      setEdges((eds) =>
        addEdge(
          {
            ...params,
            style: { strokeWidth: 2 },
          },
          eds,
        ),
      )
    },
    [setEdges],
  )

  // Node selection
  const onNodeClick = useCallback((_: React.MouseEvent, node: Node) => {
    setSelectedStepId(node.id)
    setSelectedEdgeId(null)
  }, [])

  const onEdgeClick = useCallback((_: React.MouseEvent, edge: Edge) => {
    setSelectedEdgeId(edge.id)
    setSelectedStepId(null)
  }, [])

  const onPaneClick = useCallback(() => {
    setSelectedStepId(null)
    setSelectedEdgeId(null)
  }, [])

  // Add step
  const addStep = (type: string) => {
    const tempId = nextTempId()
    const id = String(tempId)
    const label =
      type === 'start'
        ? 'Start'
        : type === 'end'
          ? 'End'
          : `${type.charAt(0).toUpperCase() + type.slice(1).replace('_', ' ')} ${nodes.length + 1}`

    const newNode: Node = {
      id,
      type: 'step',
      position: { x: 250, y: nodes.length * 120 + 50 },
      data: { label, type, agentName: null },
    }

    const newStep: WorkflowStep = {
      id: tempId,
      uuid: '',
      workflow_id: wid || 0,
      agent_id: null,
      type,
      name: label,
      position_x: newNode.position.x,
      position_y: newNode.position.y,
      config: null,
      sort_order: nodes.length,
      is_agent: type === 'agent',
      is_checkpoint: type === 'checkpoint',
      is_condition: type === 'condition',
      is_terminal: type === 'end',
      requires_agent: type === 'agent',
      created_at: '',
      updated_at: '',
    }

    setNodes((nds) => [...nds, newNode])
    setStepMap((map) => ({ ...map, [id]: newStep }))
    setShowAddMenu(false)
  }

  // Properties panel callbacks
  const selectedStep = selectedStepId ? stepMap[selectedStepId] || null : null

  const selectedEdge = useMemo(() => {
    if (!selectedEdgeId || !workflow?.edges) return null
    const edgeIdNum = Number(selectedEdgeId.replace('e-', ''))
    return workflow.edges.find((e) => e.id === edgeIdNum) || null
  }, [selectedEdgeId, workflow])

  const handleUpdateStep = (updated: WorkflowStep) => {
    setStepMap((map) => ({ ...map, [String(updated.id)]: updated }))
    setNodes((nds) =>
      nds.map((n) =>
        n.id === String(updated.id)
          ? {
              ...n,
              data: {
                ...n.data,
                label: updated.name,
                type: updated.type,
              },
            }
          : n,
      ),
    )
  }

  const handleUpdateEdge = (updated: WorkflowEdge) => {
    if (workflow) {
      setWorkflow({
        ...workflow,
        edges: (workflow.edges || []).map((e) =>
          e.id === updated.id ? updated : e,
        ),
      })
      setEdges((eds) =>
        eds.map((e) =>
          e.id === `e-${updated.id}`
            ? {
                ...e,
                label: updated.label || undefined,
                animated: !!updated.condition_expression,
                style: updated.condition_expression
                  ? { stroke: '#8b5cf6', strokeWidth: 2 }
                  : { strokeWidth: 2 },
              }
            : e,
        ),
      )
    }
  }

  const handleDeleteStep = (stepId: number) => {
    setNodes((nds) => nds.filter((n) => n.id !== String(stepId)))
    setEdges((eds) =>
      eds.filter(
        (e) => e.source !== String(stepId) && e.target !== String(stepId),
      ),
    )
    setStepMap((map) => {
      const copy = { ...map }
      delete copy[String(stepId)]
      return copy
    })
    setSelectedStepId(null)
  }

  const handleDeleteEdge = (edgeId: number) => {
    setEdges((eds) => eds.filter((e) => e.id !== `e-${edgeId}`))
    setSelectedEdgeId(null)
  }

  // Save workflow
  const handleSave = async () => {
    setSaving(true)
    try {
      let savedWorkflow: Workflow

      if (!wid) {
        // Create new workflow
        savedWorkflow = await createWorkflow(pid, {
          name,
          description: description || undefined,
          trigger_type: triggerType,
          status,
        } as Partial<Workflow>)
        setWorkflow(savedWorkflow)
        navigate(`/projects/${pid}/workflows/${savedWorkflow.id}`, {
          replace: true,
        })
      } else {
        // Update workflow metadata
        savedWorkflow = await updateWorkflow(pid, wid, {
          name,
          description: description || undefined,
          trigger_type: triggerType,
          status,
        } as Partial<Workflow>)
      }

      const targetId = savedWorkflow.id

      // Save steps
      const stepsPayload = nodes.map((node, idx) => {
        const step = stepMap[node.id]
        return {
          id: node.id.startsWith('-') ? undefined : Number(node.id), // temp IDs are negative
          type: step?.type || (node.data as { type: string }).type,
          name: step?.name || String((node.data as { label: string }).label),
          agent_id: step?.agent_id || null,
          position_x: node.position.x,
          position_y: node.position.y,
          config: step?.config || null,
          sort_order: idx,
        }
      })

      const updatedWorkflow = await updateWorkflowSteps(
        pid,
        targetId,
        stepsPayload,
      )

      // Build step ID mapping from the response
      const newStepMap: Record<string, WorkflowStep> = {}
      for (const step of updatedWorkflow.steps || []) {
        newStepMap[String(step.id)] = step
      }
      setStepMap(newStepMap)

      // Update nodes with real IDs
      const nameToId: Record<string, number> = {}
      for (const step of updatedWorkflow.steps || []) {
        nameToId[step.name] = step.id
      }

      // Save edges with remapped IDs
      const edgesPayload = edges.map((edge) => {
        let sourceId = Number(edge.source)
        let targetId2 = Number(edge.target)

        // If source/target were temp IDs, look up by name
        if (sourceId < 0) {
          const oldStep = stepMap[edge.source]
          if (oldStep) sourceId = nameToId[oldStep.name] || sourceId
        }
        if (targetId2 < 0) {
          const oldStep = stepMap[edge.target]
          if (oldStep) targetId2 = nameToId[oldStep.name] || targetId2
        }

        return {
          source_step_id: sourceId,
          target_step_id: targetId2,
          condition_expression: null,
          label: edge.label ? String(edge.label) : null,
          priority: 0,
        }
      })

      if (edgesPayload.length > 0) {
        await updateWorkflowEdges(pid, targetId, edgesPayload)
      }

      // Reload to get clean state
      const final = await fetchWorkflow(pid, targetId)
      setWorkflow(final)

      // Rebuild nodes/edges from server data
      const flowNodes: Node[] = (final.steps || []).map((step) => ({
        id: String(step.id),
        type: 'step',
        position: { x: step.position_x, y: step.position_y },
        data: {
          label: step.name,
          type: step.type,
          agentName: step.agent?.name || null,
        },
      }))

      const flowEdges: Edge[] = (final.edges || []).map((edge) => ({
        id: `e-${edge.id}`,
        source: String(edge.source_step_id),
        target: String(edge.target_step_id),
        label: edge.label || undefined,
        animated: !!edge.condition_expression,
        style: edge.condition_expression
          ? { stroke: '#8b5cf6', strokeWidth: 2 }
          : { strokeWidth: 2 },
      }))

      setNodes(flowNodes)
      setEdges(flowEdges)

      const map: Record<string, WorkflowStep> = {}
      for (const step of final.steps || []) {
        map[String(step.id)] = step
      }
      setStepMap(map)

      showToast('Workflow saved', 'success')
    } catch {
      showToast('Failed to save workflow', 'error')
    } finally {
      setSaving(false)
    }
  }

  // Validate
  const handleValidate = async () => {
    if (!wid) {
      showToast('Save workflow first before validating', 'error')
      return
    }
    try {
      const result = await validateWorkflow(pid, wid)
      setValidation(result)
      if (result.valid) {
        showToast('Workflow is valid', 'success')
      }
    } catch {
      showToast('Validation failed', 'error')
    }
  }

  // Version management
  const handleCreateVersion = async () => {
    if (!wid) return
    try {
      await createWorkflowVersion(pid, wid, 'Manual snapshot')
      showToast('Version created', 'success')
      loadVersions()
    } catch {
      showToast('Failed to create version', 'error')
    }
  }

  const loadVersions = async () => {
    if (!wid) return
    try {
      const v = await fetchWorkflowVersions(pid, wid)
      setVersions(v)
    } catch {
      // ignore
    }
  }

  const handleRestore = async (versionNumber: number) => {
    if (!wid) return
    if (!(await confirm({ message: `Restore version ${versionNumber}? Current state will be overwritten.`, title: 'Confirm Restore' }))) return
    try {
      await restoreWorkflowVersion(pid, wid, versionNumber)
      showToast('Version restored', 'success')
      window.location.reload()
    } catch {
      showToast('Failed to restore version', 'error')
    }
  }

  const handleExport = async (format: 'json' | 'langgraph' | 'crewai') => {
    if (!wid) {
      showToast('Save workflow first before exporting', 'error')
      return
    }
    try {
      const data = await exportWorkflow(pid, wid, format)
      const content = typeof data === 'string' ? data : JSON.stringify(data, null, 2)
      const blob = new Blob([content], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `${workflow?.slug || 'workflow'}.${format === 'langgraph' ? 'yaml' : 'json'}`
      a.click()
      URL.revokeObjectURL(url)
      showToast(`Exported as ${format}`, 'success')
    } catch {
      showToast('Export failed', 'error')
    }
    setShowExportMenu(false)
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="h-[calc(100vh-3.5rem)] flex flex-col">
      {/* Toolbar */}
      <div className="flex items-center gap-3 px-4 py-2 border-b border-border bg-card">
        <button
          onClick={() => navigate(`/projects/${pid}`)}
          className="p-1.5 hover:bg-muted transition-colors"
        >
          <ArrowLeft className="h-4 w-4 text-muted-foreground" />
        </button>

        <input
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="Workflow name..."
          className="text-sm font-medium bg-transparent border-none focus:outline-none flex-1 min-w-0"
        />

        <select
          value={triggerType}
          onChange={(e) => setTriggerType(e.target.value)}
          className="text-xs px-2 py-1 bg-muted border border-border focus:outline-none"
        >
          <option value="manual">Manual</option>
          <option value="webhook">Webhook</option>
          <option value="schedule">Schedule</option>
          <option value="event">Event</option>
        </select>

        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="text-xs px-2 py-1 bg-muted border border-border focus:outline-none"
        >
          <option value="draft">Draft</option>
          <option value="active">Active</option>
          <option value="archived">Archived</option>
        </select>

        {/* Add step dropdown */}
        <div className="relative">
          <button
            onClick={() => setShowAddMenu(!showAddMenu)}
            className="flex items-center gap-1 px-2 py-1 text-xs bg-muted hover:bg-muted/80 border border-border transition-colors"
          >
            <Plus className="h-3 w-3" />
            Add Step
          </button>
          {showAddMenu && (
            <div className="absolute right-0 top-full mt-1 z-20 w-40 bg-popover border border-border shadow-lg">
              {STEP_TYPES.map(({ type, label }) => (
                <button
                  key={type}
                  onClick={() => addStep(type)}
                  className="w-full text-left px-3 py-1.5 text-xs hover:bg-muted transition-colors"
                >
                  {label}
                </button>
              ))}
            </div>
          )}
        </div>

        <button
          onClick={handleValidate}
          className="flex items-center gap-1 px-2 py-1 text-xs bg-muted hover:bg-muted/80 border border-border transition-colors"
          title="Validate DAG"
        >
          <CheckCircle2 className="h-3 w-3" />
          Validate
        </button>

        {wid && (
          <div className="relative">
            <button
              onClick={() => setShowExportMenu(!showExportMenu)}
              className="flex items-center gap-1 px-2 py-1 text-xs bg-muted hover:bg-muted/80 border border-border transition-colors"
            >
              <Download className="h-3 w-3" />
              Export
            </button>
            {showExportMenu && (
              <div className="absolute right-0 top-full mt-1 z-20 w-40 bg-popover border border-border shadow-lg">
                <button onClick={() => handleExport('json')} className="w-full text-left px-3 py-1.5 text-xs hover:bg-muted transition-colors">JSON</button>
                <button onClick={() => handleExport('langgraph')} className="w-full text-left px-3 py-1.5 text-xs hover:bg-muted transition-colors">LangGraph YAML</button>
                <button onClick={() => handleExport('crewai')} className="w-full text-left px-3 py-1.5 text-xs hover:bg-muted transition-colors">CrewAI Config</button>
              </div>
            )}
          </div>
        )}

        {wid && (
          <button
            onClick={() => {
              setShowVersions(!showVersions)
              if (!showVersions) loadVersions()
            }}
            className="flex items-center gap-1 px-2 py-1 text-xs bg-muted hover:bg-muted/80 border border-border transition-colors"
          >
            <History className="h-3 w-3" />
            Versions
          </button>
        )}

        <button
          onClick={handleSave}
          disabled={saving || !name}
          className="flex items-center gap-1 px-3 py-1 text-xs bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
        >
          {saving ? (
            <Loader2 className="h-3 w-3 animate-spin" />
          ) : (
            <Save className="h-3 w-3" />
          )}
          Save
        </button>
      </div>

      {/* Validation results */}
      {validation && !validation.valid && (
        <div className="px-4 py-2 bg-red-500/10 border-b border-red-500/20">
          {validation.errors.map((err, i) => (
            <div key={i} className="flex items-center gap-2 text-xs text-red-400">
              <AlertTriangle className="h-3 w-3 flex-shrink-0" />
              {err}
            </div>
          ))}
          {validation.warnings.map((warn, i) => (
            <div
              key={`w-${i}`}
              className="flex items-center gap-2 text-xs text-amber-400"
            >
              <AlertTriangle className="h-3 w-3 flex-shrink-0" />
              {warn}
            </div>
          ))}
        </div>
      )}

      {/* Version history dropdown */}
      {showVersions && (
        <div className="px-4 py-2 bg-muted/30 border-b border-border space-y-1">
          <div className="flex items-center justify-between">
            <span className="text-xs font-medium text-muted-foreground">
              Version History
            </span>
            <button
              onClick={handleCreateVersion}
              className="text-xs text-primary hover:underline"
            >
              Save Snapshot
            </button>
          </div>
          {versions.length === 0 && (
            <p className="text-xs text-muted-foreground">No versions yet.</p>
          )}
          {versions.map((v) => (
            <div
              key={v.id}
              className="flex items-center justify-between text-xs py-1"
            >
              <span>
                v{v.version_number}
                {v.note ? ` — ${v.note}` : ''}{' '}
                <span className="text-muted-foreground">
                  {new Date(v.created_at).toLocaleString()}
                </span>
              </span>
              <button
                onClick={() => handleRestore(v.version_number)}
                className="text-primary hover:underline"
              >
                Restore
              </button>
            </div>
          ))}
        </div>
      )}

      {/* Canvas + Properties */}
      <div className="flex-1 flex">
        <div className="flex-1">
          <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            onNodeClick={onNodeClick}
            onEdgeClick={onEdgeClick}
            onPaneClick={onPaneClick}
            nodeTypes={workflowNodeTypes}
            fitView
            snapToGrid
            snapGrid={[20, 20]}
            className="bg-background"
          >
            <Background variant={BackgroundVariant.Dots} gap={20} size={1} />
            <Controls />
            <MiniMap
              nodeStrokeWidth={3}
              className="!bg-card !border-border"
            />
          </ReactFlow>
        </div>

        <WorkflowPropertiesPanel
          selectedStep={selectedStep}
          selectedEdge={selectedEdge}
          onUpdateStep={handleUpdateStep}
          onUpdateEdge={handleUpdateEdge}
          onDeleteStep={handleDeleteStep}
          onDeleteEdge={handleDeleteEdge}
          onClose={() => {
            setSelectedStepId(null)
            setSelectedEdgeId(null)
          }}
        />
      </div>
    </div>
  )
}
