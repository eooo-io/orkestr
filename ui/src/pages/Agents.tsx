import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  Plus,
  Loader2,
  Brain,
  ClipboardList,
  Boxes,
  ShieldCheck,
  Palette,
  GitPullRequest,
  Container,
  Rocket,
  Lock,
  Copy,
  Trash2,
  MoreVertical,
} from 'lucide-react'
import { fetchAgents, deleteAgent, duplicateAgent } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import type { Agent } from '@/types'

const ICONS: Record<string, React.ElementType> = {
  brain: Brain,
  'clipboard-list': ClipboardList,
  boxes: Boxes,
  'shield-check': ShieldCheck,
  palette: Palette,
  'git-pull-request': GitPullRequest,
  container: Container,
  rocket: Rocket,
  lock: Lock,
}

export function Agents() {
  const navigate = useNavigate()
  const { showToast } = useAppStore()
  const [agents, setAgents] = useState<Agent[]>([])
  const [loading, setLoading] = useState(true)
  const [menuOpen, setMenuOpen] = useState<number | null>(null)

  const load = () => {
    fetchAgents()
      .then(setAgents)
      .catch(() => showToast('Failed to load agents', 'error'))
      .finally(() => setLoading(false))
  }

  useEffect(() => { load() }, [])

  const handleDuplicate = async (agent: Agent) => {
    try {
      const copy = await duplicateAgent(agent.id)
      showToast(`Duplicated "${agent.name}"`, 'success')
      navigate(`/agents/${copy.id}`)
    } catch {
      showToast('Failed to duplicate agent', 'error')
    }
    setMenuOpen(null)
  }

  const handleDelete = async (agent: Agent) => {
    if (!confirm(`Delete "${agent.name}"? This cannot be undone.`)) return
    try {
      await deleteAgent(agent.id)
      showToast('Agent deleted', 'success')
      setAgents((prev) => prev.filter((a) => a.id !== agent.id))
    } catch {
      showToast('Failed to delete agent', 'error')
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
          <h1 className="text-lg font-semibold">Agents</h1>
          <p className="text-sm text-muted-foreground mt-0.5">
            Configure agent loop definitions for your AI workflows
          </p>
        </div>
        <Link
          to="/agents/new"
          className="flex items-center gap-1.5 px-4 py-1.5 text-xs bg-primary text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          <Plus className="h-3 w-3" />
          New Agent
        </Link>
      </div>

      <div className="space-y-2">
        {agents.map((agent) => {
          const Icon = ICONS[agent.icon || ''] || Brain

          return (
            <div
              key={agent.id}
              className="flex items-center gap-4 p-4 elevation-1 hover:bg-muted/30 transition-colors group relative"
            >
              {/* Icon */}
              <div className="h-10 w-10 flex items-center justify-center flex-shrink-0 bg-primary/10 text-primary">
                <Icon className="h-5 w-5" />
              </div>

              {/* Info */}
              <Link to={`/agents/${agent.id}`} className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium">{agent.name}</span>
                  <span className="text-[10px] px-1.5 py-0.5 bg-muted text-muted-foreground">
                    {agent.role}
                  </span>
                  {agent.is_template && (
                    <span className="text-[10px] px-1.5 py-0.5 bg-violet-500/20 text-violet-400">
                      template
                    </span>
                  )}
                  {agent.has_loop_config && (
                    <span className="text-[10px] px-1.5 py-0.5 bg-emerald-500/20 text-emerald-400">
                      loop
                    </span>
                  )}
                </div>
                <p className="text-xs text-muted-foreground truncate mt-0.5">
                  {agent.description}
                </p>
                {agent.planning_mode !== 'none' && (
                  <p className="text-[10px] text-muted-foreground/60 mt-0.5">
                    {agent.planning_mode} · {agent.context_strategy} · {agent.loop_condition}
                  </p>
                )}
              </Link>

              {/* Menu */}
              <div className="relative flex-shrink-0">
                <button
                  onClick={() => setMenuOpen(menuOpen === agent.id ? null : agent.id)}
                  className="p-2 hover:bg-muted transition-colors opacity-0 group-hover:opacity-100"
                >
                  <MoreVertical className="h-4 w-4 text-muted-foreground" />
                </button>
                {menuOpen === agent.id && (
                  <div className="absolute right-0 top-full mt-1 z-10 w-40 bg-popover border border-border shadow-lg">
                    <button
                      onClick={() => handleDuplicate(agent)}
                      className="w-full flex items-center gap-2 px-3 py-2 text-xs hover:bg-muted transition-colors"
                    >
                      <Copy className="h-3 w-3" />
                      Duplicate
                    </button>
                    <button
                      onClick={() => handleDelete(agent)}
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

        {agents.length === 0 && (
          <div className="text-center py-12 text-muted-foreground">
            <Brain className="h-8 w-8 mx-auto mb-3 opacity-50" />
            <p className="text-sm">No agents yet.</p>
            <Link to="/agents/new" className="text-xs text-primary hover:underline mt-1 inline-block">
              Create your first agent
            </Link>
          </div>
        )}
      </div>
    </div>
  )
}
