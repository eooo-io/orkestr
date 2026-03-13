import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import {
  Plus,
  Loader2,
  GitBranch,
  Copy,
  Trash2,
  MoreVertical,
  Play,
  Pause,
  Archive,
} from 'lucide-react'
import { fetchWorkflows, deleteWorkflow, duplicateWorkflow } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import type { Workflow } from '@/types'

const STATUS_COLORS: Record<string, string> = {
  draft: 'bg-yellow-500/20 text-yellow-400',
  active: 'bg-emerald-500/20 text-emerald-400',
  archived: 'bg-zinc-500/20 text-zinc-400',
}

const STATUS_ICONS: Record<string, React.ElementType> = {
  draft: Pause,
  active: Play,
  archived: Archive,
}

export function Workflows() {
  const { id: projectId } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { showToast } = useAppStore()
  const [workflows, setWorkflows] = useState<Workflow[]>([])
  const [loading, setLoading] = useState(true)
  const [menuOpen, setMenuOpen] = useState<number | null>(null)

  const pid = Number(projectId)

  const load = () => {
    if (!pid) return
    fetchWorkflows(pid)
      .then(setWorkflows)
      .catch(() => showToast('Failed to load workflows', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
  }, [pid])

  const handleDuplicate = async (workflow: Workflow) => {
    try {
      const copy = await duplicateWorkflow(pid, workflow.id)
      showToast(`Duplicated "${workflow.name}"`, 'success')
      navigate(`/projects/${pid}/workflows/${copy.id}`)
    } catch {
      showToast('Failed to duplicate workflow', 'error')
    }
    setMenuOpen(null)
  }

  const handleDelete = async (workflow: Workflow) => {
    if (!confirm(`Delete "${workflow.name}"? This cannot be undone.`)) return
    try {
      await deleteWorkflow(pid, workflow.id)
      showToast('Workflow deleted', 'success')
      setWorkflows((prev) => prev.filter((w) => w.id !== workflow.id))
    } catch {
      showToast('Failed to delete workflow', 'error')
    }
    setMenuOpen(null)
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold">Workflows</h1>
          <p className="text-sm text-muted-foreground mt-0.5">
            Multi-agent workflow orchestration with visual DAG builder
          </p>
        </div>
        <Link
          to={`/projects/${pid}/workflows/new`}
          className="flex items-center gap-1.5 px-4 py-1.5 text-xs bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          <Plus className="h-3 w-3" />
          New Workflow
        </Link>
      </div>

      <div className="space-y-2">
        {workflows.map((workflow) => {
          const StatusIcon = STATUS_ICONS[workflow.status] || Pause

          return (
            <div
              key={workflow.id}
              className="flex items-center gap-4 p-4 elevation-1 hover:bg-muted/30 transition-colors group relative"
            >
              {/* Icon */}
              <div className="h-10 w-10 flex items-center justify-center flex-shrink-0 bg-primary/10 text-primary">
                <GitBranch className="h-5 w-5" />
              </div>

              {/* Info */}
              <Link
                to={`/projects/${pid}/workflows/${workflow.id}`}
                className="flex-1 min-w-0"
              >
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium">{workflow.name}</span>
                  <span
                    className={`text-[10px] px-1.5 py-0.5 flex items-center gap-1 ${STATUS_COLORS[workflow.status] || ''}`}
                  >
                    <StatusIcon className="h-2.5 w-2.5" />
                    {workflow.status}
                  </span>
                  <span className="text-[10px] px-1.5 py-0.5 bg-muted text-muted-foreground">
                    {workflow.trigger_type}
                  </span>
                </div>
                {workflow.description && (
                  <p className="text-xs text-muted-foreground truncate mt-0.5">
                    {workflow.description}
                  </p>
                )}
                <p className="text-[10px] text-muted-foreground/60 mt-0.5">
                  {workflow.step_count ?? 0} steps · {workflow.edge_count ?? 0} edges
                </p>
              </Link>

              {/* Menu */}
              <div className="relative flex-shrink-0">
                <button
                  onClick={() =>
                    setMenuOpen(menuOpen === workflow.id ? null : workflow.id)
                  }
                  className="p-2 hover:bg-muted transition-colors opacity-0 group-hover:opacity-100"
                >
                  <MoreVertical className="h-4 w-4 text-muted-foreground" />
                </button>
                {menuOpen === workflow.id && (
                  <div className="absolute right-0 top-full mt-1 z-10 w-40 bg-popover border border-border shadow-lg">
                    <button
                      onClick={() => handleDuplicate(workflow)}
                      className="w-full flex items-center gap-2 px-3 py-2 text-xs hover:bg-muted transition-colors"
                    >
                      <Copy className="h-3 w-3" />
                      Duplicate
                    </button>
                    <button
                      onClick={() => handleDelete(workflow)}
                      className="w-full flex items-center gap-2 px-3 py-2 text-xs text-red-400 hover:bg-muted transition-colors"
                    >
                      <Trash2 className="h-3 w-3" />
                      Delete
                    </button>
                  </div>
                )}
              </div>
            </div>
          )
        })}

        {workflows.length === 0 && (
          <div className="text-center py-12 text-muted-foreground">
            <GitBranch className="h-8 w-8 mx-auto mb-3 opacity-50" />
            <p className="text-sm">No workflows yet.</p>
            <Link
              to={`/projects/${pid}/workflows/new`}
              className="text-xs text-primary hover:underline mt-1 inline-block"
            >
              Create your first workflow
            </Link>
          </div>
        )}
      </div>
    </div>
  )
}
