import { useEffect, useState } from 'react'
import {
  Brain,
  ClipboardList,
  Boxes,
  ShieldCheck,
  Palette,
  GitPullRequest,
  Container,
  Rocket,
  Lock,
  Settings2,
  Eye,
  Loader2,
} from 'lucide-react'
import { fetchProjectAgents, toggleAgent } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { AgentConfigModal } from './AgentConfigModal'
import { AgentComposePreview } from './AgentComposePreview'
import type { ProjectAgent, Skill } from '@/types'

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

interface Props {
  projectId: number
  skills: Skill[]
}

export function AgentsTab({ projectId, skills }: Props) {
  const { showToast } = useAppStore()
  const [agents, setAgents] = useState<ProjectAgent[]>([])
  const [loading, setLoading] = useState(true)
  const [configAgent, setConfigAgent] = useState<ProjectAgent | null>(null)
  const [previewAgent, setPreviewAgent] = useState<ProjectAgent | null>(null)

  const loadAgents = () => {
    fetchProjectAgents(projectId)
      .then(setAgents)
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadAgents()
  }, [projectId])

  const handleToggle = async (agent: ProjectAgent) => {
    const newState = !agent.is_enabled
    // Optimistic update
    setAgents((prev) =>
      prev.map((a) => (a.id === agent.id ? { ...a, is_enabled: newState } : a)),
    )
    try {
      await toggleAgent(projectId, agent.id, newState)
    } catch {
      // Revert
      setAgents((prev) =>
        prev.map((a) => (a.id === agent.id ? { ...a, is_enabled: !newState } : a)),
      )
      showToast('Failed to toggle agent', 'error')
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <>
      <div className="space-y-2">
        {agents.map((agent) => {
          const Icon = ICONS[agent.icon || ''] || Brain
          const assignedCount = agent.skill_ids.length

          return (
            <div
              key={agent.id}
              className={`flex items-center gap-4 p-4 elevation-1 transition-all duration-150 ${
                agent.is_enabled
                  ? 'bg-primary/5'
                  : 'bg-background opacity-60'
              }`}
            >
              {/* Icon */}
              <div
                className={`h-10 w-10 flex items-center justify-center flex-shrink-0 ${
                  agent.is_enabled
                    ? 'bg-primary/10 text-primary'
                    : 'bg-muted text-muted-foreground'
                }`}
              >
                <Icon className="h-5 w-5" />
              </div>

              {/* Info */}
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium">{agent.name}</span>
                  <span className="text-[10px] px-1.5 py-0.5 bg-muted text-muted-foreground">
                    {agent.role}
                  </span>
                </div>
                <p className="text-xs text-muted-foreground truncate mt-0.5">
                  {agent.description}
                </p>
                {agent.is_enabled && assignedCount > 0 && (
                  <p className="text-xs text-primary mt-0.5">
                    {assignedCount} skill{assignedCount !== 1 ? 's' : ''} assigned
                  </p>
                )}
              </div>

              {/* Actions */}
              <div className="flex items-center gap-2 flex-shrink-0">
                {agent.is_enabled && (
                  <button
                    onClick={() => setPreviewAgent(agent)}
                    className="p-2 hover:bg-muted transition-all duration-150"
                    title="Preview composed output"
                  >
                    <Eye className="h-4 w-4 text-muted-foreground" />
                  </button>
                )}
                <button
                  onClick={() => setConfigAgent(agent)}
                  className="p-2 hover:bg-muted transition-all duration-150"
                  title="Configure agent"
                >
                  <Settings2 className="h-4 w-4 text-muted-foreground" />
                </button>

                {/* Toggle switch */}
                <button
                  onClick={() => handleToggle(agent)}
                  className={`relative w-10 h-5 rounded-full transition-all duration-150 ${
                    agent.is_enabled ? 'bg-primary' : 'bg-muted-foreground/30'
                  }`}
                  title={agent.is_enabled ? 'Disable agent' : 'Enable agent'}
                >
                  <span
                    className={`absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white transition-transform ${
                      agent.is_enabled ? 'translate-x-5' : 'translate-x-0'
                    }`}
                  />
                </button>
              </div>
            </div>
          )
        })}
      </div>

      {configAgent && (
        <AgentConfigModal
          projectId={projectId}
          agent={configAgent}
          skills={skills}
          onClose={() => setConfigAgent(null)}
          onSaved={loadAgents}
        />
      )}

      {previewAgent && (
        <AgentComposePreview
          projectId={projectId}
          agentId={previewAgent.id}
          agentName={previewAgent.name}
          onClose={() => setPreviewAgent(null)}
        />
      )}
    </>
  )
}
