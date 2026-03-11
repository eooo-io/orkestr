import { useState, useEffect } from 'react'
import { Plus, Trash2, Save, Loader2, Network, ToggleLeft, ToggleRight } from 'lucide-react'
import api from '@/api/client'
import { Button } from '@/components/ui/button'

interface A2aAgent {
  id: number
  name: string
  url: string
  description: string | null
  skills: string[] | null
  enabled: boolean
}

interface Props {
  projectId: number
}

export function A2aAgentsTab({ projectId }: Props) {
  const [agents, setAgents] = useState<A2aAgent[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState<number | null>(null)

  const load = async () => {
    setLoading(true)
    try {
      const res = await api.get(`/projects/${projectId}/a2a-agents`)
      setAgents(res.data.a2a_agents)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [projectId])

  const addAgent = async () => {
    try {
      const res = await api.post(`/projects/${projectId}/a2a-agents`, {
        name: 'new-agent',
        url: 'http://localhost:8080/.well-known/agent.json',
        enabled: true,
      })
      setAgents([...agents, res.data.a2a_agent])
    } catch {
      // handle error
    }
  }

  const updateAgent = async (agent: A2aAgent) => {
    setSaving(agent.id)
    try {
      const res = await api.put(`/a2a-agents/${agent.id}`, agent)
      setAgents(agents.map(a => a.id === agent.id ? res.data.a2a_agent : a))
    } finally {
      setSaving(null)
    }
  }

  const deleteAgent = async (id: number) => {
    await api.delete(`/a2a-agents/${id}`)
    setAgents(agents.filter(a => a.id !== id))
  }

  const toggleEnabled = async (agent: A2aAgent) => {
    const updated = { ...agent, enabled: !agent.enabled }
    await updateAgent(updated)
  }

  const updateField = (id: number, field: string, value: unknown) => {
    setAgents(agents.map(a => a.id === id ? { ...a, [field]: value } : a))
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-medium">A2A Agents</h3>
          <p className="text-xs text-muted-foreground mt-0.5">
            Agent-to-Agent connections shared across all provider targets.
          </p>
        </div>
        <Button size="sm" onClick={addAgent}>
          <Plus className="h-4 w-4 mr-1" />
          Add Agent
        </Button>
      </div>

      {agents.length === 0 ? (
        <div className="text-center py-12 text-muted-foreground text-sm">
          <Network className="h-8 w-8 mx-auto mb-2 opacity-30" />
          No A2A agents configured.
        </div>
      ) : (
        <div className="space-y-3">
          {agents.map((agent) => (
            <div key={agent.id} className="border border-border p-4 space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <button onClick={() => toggleEnabled(agent)} className="text-muted-foreground hover:text-foreground">
                    {agent.enabled
                      ? <ToggleRight className="h-5 w-5 text-green-500" />
                      : <ToggleLeft className="h-5 w-5" />}
                  </button>
                  <input
                    type="text"
                    value={agent.name}
                    onChange={(e) => updateField(agent.id, 'name', e.target.value)}
                    className="bg-transparent border-none text-sm font-medium focus:outline-none"
                    placeholder="Agent name"
                  />
                </div>
                <div className="flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => updateAgent(agent)}
                    disabled={saving === agent.id}
                  >
                    {saving === agent.id
                      ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      : <Save className="h-3.5 w-3.5" />}
                  </Button>
                  <Button variant="ghost" size="sm" onClick={() => deleteAgent(agent.id)}>
                    <Trash2 className="h-3.5 w-3.5 text-destructive" />
                  </Button>
                </div>
              </div>

              <div>
                <label className="text-xs text-muted-foreground mb-1 block">Agent Card URL</label>
                <input
                  type="text"
                  value={agent.url}
                  onChange={(e) => updateField(agent.id, 'url', e.target.value)}
                  className="w-full border border-border bg-background px-2 py-1.5 text-sm font-mono"
                  placeholder="http://localhost:8080/.well-known/agent.json"
                />
              </div>

              <div>
                <label className="text-xs text-muted-foreground mb-1 block">Description</label>
                <textarea
                  value={agent.description || ''}
                  onChange={(e) => updateField(agent.id, 'description', e.target.value || null)}
                  rows={2}
                  className="w-full border border-border bg-background px-2 py-1.5 text-sm"
                  placeholder="What this agent does..."
                />
              </div>

              <div>
                <label className="text-xs text-muted-foreground mb-1 block">
                  Capabilities (one per line)
                </label>
                <textarea
                  value={(agent.skills || []).join('\n')}
                  onChange={(e) => updateField(agent.id, 'skills', e.target.value.split('\n').filter(Boolean))}
                  rows={2}
                  className="w-full border border-border bg-background px-2 py-1.5 text-sm font-mono"
                  placeholder="web-search&#10;code-execution"
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
