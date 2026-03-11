import { useState, useEffect } from 'react'
import { Loader2, CheckCircle, XCircle, ExternalLink, Save, Eye, EyeOff } from 'lucide-react'
import { fetchSettings, updateSettings } from '@/api/client'
import { Button } from '@/components/ui/button'

interface SettingsData {
  anthropic_api_key_set: boolean
  openai_api_key_set: boolean
  gemini_api_key_set: boolean
  ollama_url: string
  default_model: string
}

const SYNC_PROVIDERS = [
  {
    name: 'Claude',
    slug: 'claude',
    output: '.claude/CLAUDE.md',
    format: 'H2 headings per skill',
  },
  {
    name: 'Cursor',
    slug: 'cursor',
    output: '.cursor/rules/{slug}.mdc',
    format: 'One MDC file per skill',
  },
  {
    name: 'Copilot',
    slug: 'copilot',
    output: '.github/copilot-instructions.md',
    format: 'All skills concatenated',
  },
  {
    name: 'Windsurf',
    slug: 'windsurf',
    output: '.windsurf/rules/{slug}.md',
    format: 'One file per skill',
  },
  {
    name: 'Cline',
    slug: 'cline',
    output: '.clinerules',
    format: 'Single flat file',
  },
  {
    name: 'OpenAI',
    slug: 'openai',
    output: '.openai/instructions.md',
    format: 'All skills concatenated',
  },
]

function StatusBadge({ configured }: { configured: boolean }) {
  return configured ? (
    <span className="flex items-center gap-1.5 text-sm text-green-500">
      <CheckCircle className="h-4 w-4" />
      Configured
    </span>
  ) : (
    <span className="flex items-center gap-1.5 text-sm text-destructive">
      <XCircle className="h-4 w-4" />
      Not set
    </span>
  )
}

function ApiKeyInput({
  label,
  configured,
  value,
  onChange,
  placeholder,
}: {
  label: string
  configured: boolean
  value: string
  onChange: (v: string) => void
  placeholder?: string
}) {
  const [visible, setVisible] = useState(false)

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between">
        <label className="text-sm font-medium">{label}</label>
        <StatusBadge configured={configured || value.length > 0} />
      </div>
      <div className="relative">
        <input
          type={visible ? 'text' : 'password'}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={configured ? '********** (already set)' : placeholder}
          className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring pr-10 font-mono"
        />
        <button
          type="button"
          onClick={() => setVisible(!visible)}
          className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-all duration-150"
        >
          {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </div>
    </div>
  )
}

export function Settings() {
  const [settings, setSettings] = useState<SettingsData | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [saveMessage, setSaveMessage] = useState<string | null>(null)

  // Form state for API keys
  const [anthropicKey, setAnthropicKey] = useState('')
  const [openaiKey, setOpenaiKey] = useState('')
  const [geminiKey, setGeminiKey] = useState('')
  const [ollamaUrl, setOllamaUrl] = useState('')

  useEffect(() => {
    fetchSettings()
      .then((data) => {
        setSettings(data)
        setOllamaUrl(data.ollama_url || 'http://localhost:11434')
      })
      .finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    setSaving(true)
    setSaveMessage(null)

    const payload: Record<string, string> = {}
    if (anthropicKey) payload.anthropic_api_key = anthropicKey
    if (openaiKey) payload.openai_api_key = openaiKey
    if (geminiKey) payload.gemini_api_key = geminiKey
    if (ollamaUrl) payload.ollama_url = ollamaUrl

    if (Object.keys(payload).length === 0) {
      setSaveMessage('No changes to save.')
      setSaving(false)
      return
    }

    try {
      await updateSettings(payload)
      // Refresh settings to reflect new status
      const updated = await fetchSettings()
      setSettings(updated)
      setAnthropicKey('')
      setOpenaiKey('')
      setGeminiKey('')
      setOllamaUrl(updated.ollama_url || 'http://localhost:11434')
      setSaveMessage('Settings saved successfully.')
    } catch {
      setSaveMessage('Failed to save settings.')
    } finally {
      setSaving(false)
      setTimeout(() => setSaveMessage(null), 3000)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="p-4 md:p-6 max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-semibold tracking-tight">Settings</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Manage API keys and configuration for LLM providers.
        </p>
      </div>

      {/* LLM Provider API Keys */}
      <section className="mb-8">
        <h2 className="text-lg font-semibold mb-3">LLM Provider Configuration</h2>
        <div className="bg-card elevation-1 p-4 space-y-5">
          <ApiKeyInput
            label="Anthropic API Key"
            configured={settings?.anthropic_api_key_set ?? false}
            value={anthropicKey}
            onChange={setAnthropicKey}
            placeholder="sk-ant-..."
          />

          <div className="border-t border-border" />

          <ApiKeyInput
            label="OpenAI API Key"
            configured={settings?.openai_api_key_set ?? false}
            value={openaiKey}
            onChange={setOpenaiKey}
            placeholder="sk-..."
          />

          <div className="border-t border-border" />

          <ApiKeyInput
            label="Google Gemini API Key"
            configured={settings?.gemini_api_key_set ?? false}
            value={geminiKey}
            onChange={setGeminiKey}
            placeholder="AIza..."
          />

          <div className="border-t border-border" />

          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <label className="text-sm font-medium">Ollama URL</label>
              <span className="text-xs text-muted-foreground">Local models</span>
            </div>
            <input
              type="text"
              value={ollamaUrl}
              onChange={(e) => setOllamaUrl(e.target.value)}
              placeholder="http://localhost:11434"
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
            />
            <p className="text-[11px] text-muted-foreground">
              Ollama must be running locally. Models are auto-detected.
            </p>
          </div>

          <div className="flex items-center gap-3 pt-2">
            <Button onClick={handleSave} disabled={saving} size="sm">
              {saving ? (
                <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
              ) : (
                <Save className="h-4 w-4 mr-1.5" />
              )}
              Save Changes
            </Button>
            {saveMessage && (
              <span className="text-sm text-muted-foreground">{saveMessage}</span>
            )}
          </div>
        </div>
      </section>

      {/* Default Model */}
      <section className="mb-8">
        <h2 className="text-lg font-semibold mb-3">Defaults</h2>
        <div className="bg-card elevation-1 p-4 space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-sm">Default Model</span>
            <span className="text-sm font-mono text-muted-foreground">
              {settings?.default_model || 'claude-sonnet-4-6'}
            </span>
          </div>
        </div>
      </section>

      {/* Provider Sync Reference */}
      <section className="mb-8">
        <h2 className="text-lg font-semibold mb-3">Provider Sync Reference</h2>
        <div className="bg-card elevation-1 overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border bg-muted/30">
                <th className="text-left px-4 py-2 font-medium">Provider</th>
                <th className="text-left px-4 py-2 font-medium">Output Path</th>
                <th className="text-left px-4 py-2 font-medium">Format</th>
              </tr>
            </thead>
            <tbody>
              {SYNC_PROVIDERS.map((p) => (
                <tr key={p.slug} className="border-b border-border last:border-0">
                  <td className="px-4 py-2 font-medium">{p.name}</td>
                  <td className="px-4 py-2 font-mono text-xs text-muted-foreground">
                    {p.output}
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">{p.format}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {/* Admin Panel Link */}
      <section>
        <a href="/admin" target="_blank" rel="noopener noreferrer">
          <Button variant="outline">
            <ExternalLink className="h-4 w-4 mr-2" />
            Open Admin Panel
          </Button>
        </a>
      </section>
    </div>
  )
}
