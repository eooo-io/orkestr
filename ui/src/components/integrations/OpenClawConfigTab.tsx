import { useState, useEffect } from 'react'
import { Save, Loader2, Plus, Trash2, Terminal } from 'lucide-react'
import api from '@/api/client'
import { Button } from '@/components/ui/button'

interface Tool {
  name: string
  description: string
  instructions: string
  enabled: boolean
  api_key_env: string
}

interface OpenClawConfig {
  soul_content: string | null
  tools: Tool[]
  a2a_agents: unknown[]
}

interface Props {
  projectId: number
}

export function OpenClawConfigTab({ projectId }: Props) {
  const [config, setConfig] = useState<OpenClawConfig>({
    soul_content: null,
    tools: [],
    a2a_agents: [],
  })
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)

  const load = async () => {
    setLoading(true)
    try {
      const res = await api.get(`/projects/${projectId}/openclaw`)
      if (res.data.config) {
        setConfig(res.data.config)
      }
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [projectId])

  const save = async () => {
    setSaving(true)
    setSaved(false)
    try {
      const res = await api.put(`/projects/${projectId}/openclaw`, config)
      setConfig(res.data.config)
      setSaved(true)
      setTimeout(() => setSaved(false), 2000)
    } finally {
      setSaving(false)
    }
  }

  const addTool = () => {
    setConfig({
      ...config,
      tools: [...config.tools, {
        name: '',
        description: '',
        instructions: '',
        enabled: true,
        api_key_env: '',
      }],
    })
  }

  const updateTool = (index: number, field: string, value: unknown) => {
    const tools = [...config.tools]
    tools[index] = { ...tools[index], [field]: value }
    setConfig({ ...config, tools })
  }

  const removeTool = (index: number) => {
    setConfig({ ...config, tools: config.tools.filter((_, i) => i !== index) })
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-medium">OpenClaw Configuration</h3>
          <p className="text-xs text-muted-foreground mt-0.5">
            Configure SOUL.md identity and tools for your OpenClaw agent.
          </p>
        </div>
        <Button size="sm" onClick={save} disabled={saving}>
          {saving ? (
            <Loader2 className="h-4 w-4 animate-spin mr-1" />
          ) : (
            <Save className="h-4 w-4 mr-1" />
          )}
          {saved ? 'Saved' : 'Save Config'}
        </Button>
      </div>

      {/* SOUL.md Editor */}
      <div className="space-y-2">
        <div className="flex items-center gap-2">
          <Terminal className="h-4 w-4 text-muted-foreground" />
          <h4 className="text-sm font-medium">SOUL.md</h4>
        </div>
        <p className="text-xs text-muted-foreground">
          Define your agent's personality, values, communication style, and behavioral guardrails.
          This is read on every startup as the foundational identity layer.
        </p>
        <textarea
          value={config.soul_content || ''}
          onChange={(e) => setConfig({ ...config, soul_content: e.target.value || null })}
          rows={16}
          className="w-full border border-border bg-background px-3 py-2 text-sm font-mono leading-relaxed"
          placeholder={`# Soul\n\nYou are a helpful, knowledgeable assistant.\n\n## Personality\n- Concise and direct\n- Technical but approachable\n\n## Values\n- Accuracy over speed\n- User privacy first\n\n## Communication Style\n- Use clear, simple language\n- Provide examples when helpful`}
        />
      </div>

      {/* Tools */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <div>
            <h4 className="text-sm font-medium">Tools Configuration</h4>
            <p className="text-xs text-muted-foreground mt-0.5">
              Configure OpenClaw skill/tool entries for openclaw.json.
            </p>
          </div>
          <Button variant="outline" size="sm" onClick={addTool}>
            <Plus className="h-4 w-4 mr-1" />
            Add Tool
          </Button>
        </div>

        {config.tools.length === 0 ? (
          <div className="text-center py-8 text-muted-foreground text-sm border border-dashed border-border">
            No tools configured.
          </div>
        ) : (
          <div className="space-y-3">
            {config.tools.map((tool, index) => (
              <div key={index} className="border border-border p-4 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <label className="flex items-center gap-1.5 text-sm">
                      <input
                        type="checkbox"
                        checked={tool.enabled}
                        onChange={(e) => updateTool(index, 'enabled', e.target.checked)}
                      />
                      Enabled
                    </label>
                  </div>
                  <Button variant="ghost" size="sm" onClick={() => removeTool(index)}>
                    <Trash2 className="h-3.5 w-3.5 text-destructive" />
                  </Button>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="text-xs text-muted-foreground mb-1 block">Name</label>
                    <input
                      type="text"
                      value={tool.name}
                      onChange={(e) => updateTool(index, 'name', e.target.value)}
                      className="w-full border border-border bg-background px-2 py-1.5 text-sm"
                      placeholder="web-search"
                    />
                  </div>
                  <div>
                    <label className="text-xs text-muted-foreground mb-1 block">API Key Env Var</label>
                    <input
                      type="text"
                      value={tool.api_key_env}
                      onChange={(e) => updateTool(index, 'api_key_env', e.target.value)}
                      className="w-full border border-border bg-background px-2 py-1.5 text-sm font-mono"
                      placeholder="TAVILY_API_KEY"
                    />
                  </div>
                </div>

                <div>
                  <label className="text-xs text-muted-foreground mb-1 block">Description</label>
                  <input
                    type="text"
                    value={tool.description}
                    onChange={(e) => updateTool(index, 'description', e.target.value)}
                    className="w-full border border-border bg-background px-2 py-1.5 text-sm"
                    placeholder="Search the web for current information"
                  />
                </div>

                <div>
                  <label className="text-xs text-muted-foreground mb-1 block">Instructions</label>
                  <textarea
                    value={tool.instructions}
                    onChange={(e) => updateTool(index, 'instructions', e.target.value)}
                    rows={3}
                    className="w-full border border-border bg-background px-2 py-1.5 text-sm font-mono"
                    placeholder="Usage instructions for this tool..."
                  />
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
