import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { Loader2, CheckCircle, XCircle, ExternalLink, Save, Eye, EyeOff, Key, Server, Activity, Cpu, ShieldOff, Shield, ChevronRight, Download } from 'lucide-react'
import { fetchSettings, updateSettings, fetchAirGapStatus, toggleAirGap, downloadTypescriptSdk, downloadPhpSdk } from '@/api/client'
import { Button } from '@/components/ui/button'
import type { AirGapStatus } from '@/types'

interface SettingsData {
  anthropic_api_key_set: boolean
  openai_api_key_set: boolean
  gemini_api_key_set: boolean
  grok_api_key_set?: boolean
  ollama_url: string
  default_model: string
}

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
  const [airGap, setAirGap] = useState<AirGapStatus | null>(null)
  const [togglingAirGap, setTogglingAirGap] = useState(false)

  // Form state for API keys
  const [anthropicKey, setAnthropicKey] = useState('')
  const [openaiKey, setOpenaiKey] = useState('')
  const [geminiKey, setGeminiKey] = useState('')
  const [grokKey, setGrokKey] = useState('')
  const [ollamaUrl, setOllamaUrl] = useState('')

  useEffect(() => {
    Promise.all([
      fetchSettings(),
      fetchAirGapStatus().catch(() => null),
    ]).then(([data, airGapData]) => {
      setSettings(data)
      setOllamaUrl(data.ollama_url || 'http://localhost:11434')
      if (airGapData) setAirGap(airGapData)
    }).finally(() => setLoading(false))
  }, [])

  const handleSave = async () => {
    setSaving(true)
    setSaveMessage(null)

    const payload: Record<string, string> = {}
    if (anthropicKey) payload.anthropic_api_key = anthropicKey
    if (openaiKey) payload.openai_api_key = openaiKey
    if (geminiKey) payload.gemini_api_key = geminiKey
    if (grokKey) payload.grok_api_key = grokKey
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
      setGrokKey('')
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

          <ApiKeyInput
            label="Grok (xAI) API Key"
            configured={settings?.grok_api_key_set ?? false}
            value={grokKey}
            onChange={setGrokKey}
            placeholder="xai-..."
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

      {/* Infrastructure Quick Links */}
      <section className="mb-8">
        <h2 className="text-lg font-semibold mb-3">Infrastructure</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Link to="/api-tokens" className="bg-card elevation-1 p-4 flex items-center gap-3 hover:bg-accent/50 transition-colors group">
            <Key className="h-5 w-5 text-muted-foreground group-hover:text-foreground" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium">API Tokens</p>
              <p className="text-xs text-muted-foreground">Manage programmatic access</p>
            </div>
            <ChevronRight className="h-4 w-4 text-muted-foreground" />
          </Link>
          <Link to="/custom-endpoints" className="bg-card elevation-1 p-4 flex items-center gap-3 hover:bg-accent/50 transition-colors group">
            <Server className="h-5 w-5 text-muted-foreground group-hover:text-foreground" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium">Custom Endpoints</p>
              <p className="text-xs text-muted-foreground">vLLM, TGI, LM Studio</p>
            </div>
            <ChevronRight className="h-4 w-4 text-muted-foreground" />
          </Link>
          <Link to="/model-health" className="bg-card elevation-1 p-4 flex items-center gap-3 hover:bg-accent/50 transition-colors group">
            <Activity className="h-5 w-5 text-muted-foreground group-hover:text-foreground" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium">Model Health</p>
              <p className="text-xs text-muted-foreground">Status, benchmarks, comparison</p>
            </div>
            <ChevronRight className="h-4 w-4 text-muted-foreground" />
          </Link>
          <Link to="/local-models" className="bg-card elevation-1 p-4 flex items-center gap-3 hover:bg-accent/50 transition-colors group">
            <Cpu className="h-5 w-5 text-muted-foreground group-hover:text-foreground" />
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium">Local Models</p>
              <p className="text-xs text-muted-foreground">Ollama & custom model browser</p>
            </div>
            <ChevronRight className="h-4 w-4 text-muted-foreground" />
          </Link>
        </div>
      </section>

      {/* Air-Gap Mode */}
      <section className="mb-8">
        <h2 className="text-lg font-semibold mb-3">Network Security</h2>
        <div className="bg-card elevation-1 p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              {airGap?.enabled ? (
                <ShieldOff className="h-5 w-5 text-yellow-500" />
              ) : (
                <Shield className="h-5 w-5 text-green-500" />
              )}
              <div>
                <p className="text-sm font-medium">Air-Gap Mode</p>
                <p className="text-xs text-muted-foreground">
                  {airGap?.enabled
                    ? 'All external network calls are blocked. Only local models are available.'
                    : 'Cloud and local models are available. External network calls are permitted.'}
                </p>
              </div>
            </div>
            <Button
              variant={airGap?.enabled ? 'destructive' : 'outline'}
              size="sm"
              disabled={togglingAirGap}
              onClick={async () => {
                setTogglingAirGap(true)
                try {
                  const result = await toggleAirGap(!airGap?.enabled)
                  setAirGap(result)
                } catch { /* handled by interceptor */ }
                finally { setTogglingAirGap(false) }
              }}
            >
              {togglingAirGap ? <Loader2 className="h-4 w-4 animate-spin" /> : airGap?.enabled ? 'Disable' : 'Enable'}
            </Button>
          </div>
        </div>
      </section>

      {/* SDK Downloads */}
      <section className="mb-8">
        <h2 className="text-lg font-semibold mb-3">SDK Downloads</h2>
        <div className="bg-card elevation-1 p-4 flex gap-3">
          <Button variant="outline" size="sm" onClick={async () => {
            const blob = await downloadTypescriptSdk()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url; a.download = 'orkestr-client.ts'; a.click()
            URL.revokeObjectURL(url)
          }}>
            <Download className="h-4 w-4 mr-1.5" />
            TypeScript SDK
          </Button>
          <Button variant="outline" size="sm" onClick={async () => {
            const blob = await downloadPhpSdk()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url; a.download = 'OrkestrClient.php'; a.click()
            URL.revokeObjectURL(url)
          }}>
            <Download className="h-4 w-4 mr-1.5" />
            PHP SDK
          </Button>
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
