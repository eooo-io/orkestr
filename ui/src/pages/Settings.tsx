import { useState, useEffect, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Loader2,
  CheckCircle,
  XCircle,
  Save,
  Eye,
  EyeOff,
  ShieldOff,
  Shield,
  Download,
  Settings as SettingsIcon,
  KeyRound,
  Brain,
  BookOpen,
  Tags,
  Users,
  Building2,
  Fingerprint,
  FileCheck,
  Server,
  DatabaseBackup,
  HeartPulse,
  Plus,
  Pencil,
  Trash2,
  FlaskConical,
  X,
  Search,
  ChevronDown,
  ChevronUp,
  RotateCcw,
  AlertTriangle,
  RefreshCw,
  Circle,
  Copy,
} from 'lucide-react'
import {
  fetchSettings,
  updateSettings,
  fetchAirGapStatus,
  toggleAirGap,
  downloadTypescriptSdk,
  downloadPhpSdk,
  downloadPythonSdk,
  fetchLicenseStatus,
  activateLicense,
  fetchSsoProviders,
  createSsoProvider,
  updateSsoProvider,
  deleteSsoProvider,
  testSsoProvider,
  fetchContentPolicies,
  createContentPolicy,
  updateContentPolicy,
  deleteContentPolicy,
  fetchApiTokens,
  createApiToken,
  deleteApiToken,
  fetchCustomEndpoints,
  createCustomEndpoint,
  deleteCustomEndpoint,
  fetchModelHealth,
  fetchLocalModels,
  fetchBackups,
  createBackup,
  restoreBackup,
  downloadBackup,
  fetchDiagnostics,
} from '@/api/client'
import { Button } from '@/components/ui/button'
import type {
  AirGapStatus,
  LicenseStatus,
  SsoProvider,
  ContentPolicy,
  ApiToken,
  CustomEndpoint,
  ModelHealthResult,
  LocalModel,
  BackupEntry,
  DiagnosticCheck,
} from '@/types'

// --- Tab registry -----------------------------------------------------------

type SettingsTab =
  | 'general'
  | 'license'
  | 'agents'
  | 'library'
  | 'tags'
  | 'users'
  | 'organizations'
  | 'sso'
  | 'content-policies'
  | 'infrastructure'
  | 'backups'
  | 'diagnostics'

interface TabDef {
  id: SettingsTab
  label: string
  icon: typeof SettingsIcon
  section: string
}

const TABS: TabDef[] = [
  { id: 'general', label: 'General', icon: SettingsIcon, section: 'Settings' },
  { id: 'license', label: 'License', icon: KeyRound, section: 'Settings' },
  { id: 'agents', label: 'Agents', icon: Brain, section: 'Administration' },
  { id: 'library', label: 'Library', icon: BookOpen, section: 'Administration' },
  { id: 'tags', label: 'Tags', icon: Tags, section: 'Administration' },
  { id: 'users', label: 'Users', icon: Users, section: 'Access' },
  { id: 'organizations', label: 'Organizations', icon: Building2, section: 'Access' },
  { id: 'sso', label: 'SSO', icon: Fingerprint, section: 'Access' },
  { id: 'content-policies', label: 'Content Policies', icon: FileCheck, section: 'Access' },
  { id: 'infrastructure', label: 'Infrastructure', icon: Server, section: 'System' },
  { id: 'backups', label: 'Backups', icon: DatabaseBackup, section: 'System' },
  { id: 'diagnostics', label: 'Diagnostics', icon: HeartPulse, section: 'System' },
]

const SECTIONS = ['Settings', 'Administration', 'Access', 'System']

// --- Helper components -------------------------------------------------------

interface SettingsData {
  anthropic_api_key_set: boolean
  openai_api_key_set: boolean
  gemini_api_key_set: boolean
  grok_api_key_set?: boolean
  openrouter_api_key_set?: boolean
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

function CollapsibleSection({
  title,
  open,
  onToggle,
  children,
}: {
  title: string
  open: boolean
  onToggle: () => void
  children: React.ReactNode
}) {
  return (
    <div className="bg-card elevation-1">
      <button
        type="button"
        onClick={onToggle}
        className="w-full flex items-center justify-between p-4 text-left hover:bg-accent/30 transition-colors"
      >
        <span className="text-sm font-medium">{title}</span>
        {open ? (
          <ChevronUp className="h-4 w-4 text-muted-foreground" />
        ) : (
          <ChevronDown className="h-4 w-4 text-muted-foreground" />
        )}
      </button>
      {open && (
        <div className="px-4 pb-4 border-t border-border">{children}</div>
      )}
    </div>
  )
}

// --- Tab panels --------------------------------------------------------------

function GeneralPanel() {
  const [settings, setSettings] = useState<SettingsData | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [saveMessage, setSaveMessage] = useState<string | null>(null)
  const [airGap, setAirGap] = useState<AirGapStatus | null>(null)
  const [togglingAirGap, setTogglingAirGap] = useState(false)

  const [anthropicKey, setAnthropicKey] = useState('')
  const [openaiKey, setOpenaiKey] = useState('')
  const [geminiKey, setGeminiKey] = useState('')
  const [grokKey, setGrokKey] = useState('')
  const [openRouterKey, setOpenRouterKey] = useState('')
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
    if (openRouterKey) payload.openrouter_api_key = openRouterKey
    if (ollamaUrl) payload.ollama_url = ollamaUrl

    if (Object.keys(payload).length === 0) {
      setSaveMessage('No changes to save.')
      setSaving(false)
      return
    }

    try {
      await updateSettings(payload)
      const updated = await fetchSettings()
      setSettings(updated)
      setAnthropicKey('')
      setOpenaiKey('')
      setGeminiKey('')
      setGrokKey('')
      setOpenRouterKey('')
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
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-8">
      <section>
        <h2 className="text-lg font-semibold mb-3">LLM Provider Configuration</h2>
        <div className="bg-card elevation-1 p-4 space-y-5">
          <ApiKeyInput label="Anthropic API Key" configured={settings?.anthropic_api_key_set ?? false} value={anthropicKey} onChange={setAnthropicKey} placeholder="sk-ant-..." />
          <div className="border-t border-border" />
          <ApiKeyInput label="OpenAI API Key" configured={settings?.openai_api_key_set ?? false} value={openaiKey} onChange={setOpenaiKey} placeholder="sk-..." />
          <div className="border-t border-border" />
          <ApiKeyInput label="Google Gemini API Key" configured={settings?.gemini_api_key_set ?? false} value={geminiKey} onChange={setGeminiKey} placeholder="AIza..." />
          <div className="border-t border-border" />
          <ApiKeyInput label="Grok (xAI) API Key" configured={settings?.grok_api_key_set ?? false} value={grokKey} onChange={setGrokKey} placeholder="xai-..." />
          <div className="border-t border-border" />
          <ApiKeyInput label="OpenRouter API Key" configured={settings?.openrouter_api_key_set ?? false} value={openRouterKey} onChange={setOpenRouterKey} placeholder="sk-or-..." />
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
              {saving ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Save className="h-4 w-4 mr-1.5" />}
              Save Changes
            </Button>
            {saveMessage && <span className="text-sm text-muted-foreground">{saveMessage}</span>}
          </div>
        </div>
      </section>

      <section>
        <h2 className="text-lg font-semibold mb-3">Defaults</h2>
        <div className="bg-card elevation-1 p-4">
          <div className="flex items-center justify-between">
            <span className="text-sm">Default Model</span>
            <span className="text-sm font-mono text-muted-foreground">{settings?.default_model || 'claude-sonnet-4-6'}</span>
          </div>
        </div>
      </section>

      <section>
        <h2 className="text-lg font-semibold mb-3">Network Security</h2>
        <div className="bg-card elevation-1 p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              {airGap?.enabled ? <ShieldOff className="h-5 w-5 text-yellow-500" /> : <Shield className="h-5 w-5 text-green-500" />}
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
                } catch {
                  /* handled */
                } finally {
                  setTogglingAirGap(false)
                }
              }}
            >
              {togglingAirGap ? <Loader2 className="h-4 w-4 animate-spin" /> : airGap?.enabled ? 'Disable' : 'Enable'}
            </Button>
          </div>
        </div>
      </section>

      <section>
        <h2 className="text-lg font-semibold mb-3">SDK Downloads</h2>
        <div className="bg-card elevation-1 p-4 flex gap-3">
          <Button variant="outline" size="sm" onClick={async () => {
            const blob = await downloadTypescriptSdk()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = 'orkestr-client.ts'
            a.click()
            URL.revokeObjectURL(url)
          }}>
            <Download className="h-4 w-4 mr-1.5" /> TypeScript SDK
          </Button>
          <Button variant="outline" size="sm" onClick={async () => {
            const blob = await downloadPhpSdk()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = 'OrkestrClient.php'
            a.click()
            URL.revokeObjectURL(url)
          }}>
            <Download className="h-4 w-4 mr-1.5" /> PHP SDK
          </Button>
          <Button variant="outline" size="sm" onClick={async () => {
            const blob = await downloadPythonSdk()
            const url = URL.createObjectURL(blob)
            const a = document.createElement('a'); a.href = url; a.download = 'orkestr_client.py'; a.click()
            URL.revokeObjectURL(url)
          }}>
            <Download className="h-4 w-4 mr-1.5" /> Python SDK
          </Button>
        </div>
      </section>
    </div>
  )
}

function LicensePanel() {
  const [license, setLicense] = useState<LicenseStatus | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [key, setKey] = useState('')
  const [activating, setActivating] = useState(false)
  const [activateMsg, setActivateMsg] = useState<string | null>(null)

  useEffect(() => {
    fetchLicenseStatus()
      .then(setLicense)
      .catch(() => setError('Unable to fetch license status.'))
      .finally(() => setLoading(false))
  }, [])

  const handleActivate = async () => {
    if (!key.trim()) return
    setActivating(true)
    setActivateMsg(null)
    try {
      const updated = await activateLicense(key.trim())
      setLicense(updated)
      setKey('')
      setActivateMsg('License activated successfully.')
    } catch {
      setActivateMsg('Failed to activate license. Check your key and try again.')
    } finally {
      setActivating(false)
      setTimeout(() => setActivateMsg(null), 4000)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  if (error) {
    return (
      <div className="flex items-center justify-center py-12 text-destructive">
        <XCircle className="h-4 w-4 mr-2" />
        <span className="text-sm">{error}</span>
      </div>
    )
  }

  const tierLabel = license?.tier ?? 'Free'
  const isValid = license?.valid ?? false
  const features = license?.features ?? {}
  const featureEntries = Object.entries(features)
  const expiresAt = license?.expires_at
    ? new Date(license.expires_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
    : null

  return (
    <div className="space-y-8">
      <section>
        <h2 className="text-lg font-semibold mb-3">License Status</h2>
        <div className="bg-card elevation-1 p-4 space-y-4">
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Plan</span>
            <span className="text-sm font-medium capitalize">{tierLabel}</span>
          </div>
          <div className="border-t border-border" />
          <div className="flex items-center justify-between">
            <span className="text-sm text-muted-foreground">Status</span>
            {isValid ? (
              <span className="flex items-center gap-1.5 text-sm text-green-500">
                <CheckCircle className="h-4 w-4" />
                Active
              </span>
            ) : (
              <span className="flex items-center gap-1.5 text-sm text-destructive">
                <XCircle className="h-4 w-4" />
                {expiresAt ? 'Expired' : 'Inactive'}
              </span>
            )}
          </div>
          {expiresAt && (
            <>
              <div className="border-t border-border" />
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Expires</span>
                <span className="text-sm font-mono">{expiresAt}</span>
              </div>
            </>
          )}
        </div>
      </section>

      <section>
        <h2 className="text-lg font-semibold mb-3">Activate License</h2>
        <div className="bg-card elevation-1 p-4 space-y-3">
          <p className="text-sm text-muted-foreground">
            Enter a license key to activate or upgrade your plan.
          </p>
          <div className="flex gap-2">
            <input
              type="text"
              value={key}
              onChange={(e) => setKey(e.target.value)}
              placeholder="XXXX-XXXX-XXXX-XXXX"
              className="flex-1 px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
              onKeyDown={(e) => { if (e.key === 'Enter') handleActivate() }}
            />
            <Button onClick={handleActivate} disabled={activating || !key.trim()} size="sm">
              {activating ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <KeyRound className="h-4 w-4 mr-1.5" />}
              Activate
            </Button>
          </div>
          {activateMsg && (
            <p className={`text-sm ${activateMsg.includes('success') ? 'text-green-500' : 'text-destructive'}`}>
              {activateMsg}
            </p>
          )}
        </div>
      </section>

      {featureEntries.length > 0 && (
        <section>
          <h2 className="text-lg font-semibold mb-3">Features</h2>
          <div className="bg-card elevation-1 p-4">
            <div className="space-y-2">
              {featureEntries.map(([feature, enabled]) => (
                <div key={feature} className="flex items-center justify-between">
                  <span className="text-sm capitalize">{feature.replace(/_/g, ' ')}</span>
                  {enabled ? (
                    <CheckCircle className="h-4 w-4 text-green-500" />
                  ) : (
                    <XCircle className="h-4 w-4 text-muted-foreground" />
                  )}
                </div>
              ))}
            </div>
          </div>
        </section>
      )}
    </div>
  )
}

// --- Infrastructure Panel (#281) ---------------------------------------------

function InfrastructurePanel() {
  const [openSections, setOpenSections] = useState<Record<string, boolean>>({
    tokens: true,
    endpoints: false,
    health: false,
    local: false,
  })

  const [tokens, setTokens] = useState<ApiToken[]>([])
  const [tokensLoading, setTokensLoading] = useState(false)
  const [newTokenName, setNewTokenName] = useState('')
  const [creatingToken, setCreatingToken] = useState(false)
  const [createdToken, setCreatedToken] = useState<string | null>(null)

  const [endpoints, setEndpoints] = useState<CustomEndpoint[]>([])
  const [endpointsLoading, setEndpointsLoading] = useState(false)
  const [newEndpointName, setNewEndpointName] = useState('')
  const [newEndpointUrl, setNewEndpointUrl] = useState('')
  const [creatingEndpoint, setCreatingEndpoint] = useState(false)

  const [healthResults, setHealthResults] = useState<ModelHealthResult[]>([])
  const [healthLoading, setHealthLoading] = useState(false)

  const [localModels, setLocalModels] = useState<LocalModel[]>([])
  const [localModelsLoading, setLocalModelsLoading] = useState(false)

  const toggleSection = (key: string) => {
    setOpenSections((prev) => ({ ...prev, [key]: !prev[key] }))
  }

  const loadTokens = useCallback(async () => {
    setTokensLoading(true)
    try {
      const data = await fetchApiTokens()
      setTokens(data)
    } catch {
      /* handled */
    } finally {
      setTokensLoading(false)
    }
  }, [])

  const loadEndpoints = useCallback(async () => {
    setEndpointsLoading(true)
    try {
      const data = await fetchCustomEndpoints()
      setEndpoints(data)
    } catch {
      /* handled */
    } finally {
      setEndpointsLoading(false)
    }
  }, [])

  const loadHealth = useCallback(async () => {
    setHealthLoading(true)
    try {
      const data = await fetchModelHealth()
      setHealthResults(data)
    } catch {
      /* handled */
    } finally {
      setHealthLoading(false)
    }
  }, [])

  const loadLocalModels = useCallback(async () => {
    setLocalModelsLoading(true)
    try {
      const data = await fetchLocalModels()
      setLocalModels(data)
    } catch {
      /* handled */
    } finally {
      setLocalModelsLoading(false)
    }
  }, [])

  useEffect(() => {
    loadTokens()
    loadEndpoints()
    loadHealth()
    loadLocalModels()
  }, [loadTokens, loadEndpoints, loadHealth, loadLocalModels])

  const handleCreateToken = async () => {
    if (!newTokenName.trim()) return
    setCreatingToken(true)
    setCreatedToken(null)
    try {
      const result = await createApiToken({ name: newTokenName.trim() })
      setCreatedToken(result.data.plain_token)
      setNewTokenName('')
      await loadTokens()
    } catch {
      /* handled */
    } finally {
      setCreatingToken(false)
    }
  }

  const handleDeleteToken = async (id: number) => {
    try {
      await deleteApiToken(id)
      setTokens((prev) => prev.filter((t) => t.id !== id))
    } catch {
      /* handled */
    }
  }

  const handleCreateEndpoint = async () => {
    if (!newEndpointName.trim() || !newEndpointUrl.trim()) return
    setCreatingEndpoint(true)
    try {
      await createCustomEndpoint({
        name: newEndpointName.trim(),
        base_url: newEndpointUrl.trim(),
      })
      setNewEndpointName('')
      setNewEndpointUrl('')
      await loadEndpoints()
    } catch {
      /* handled */
    } finally {
      setCreatingEndpoint(false)
    }
  }

  const handleDeleteEndpoint = async (id: number) => {
    try {
      await deleteCustomEndpoint(id)
      setEndpoints((prev) => prev.filter((e) => e.id !== id))
    } catch {
      /* handled */
    }
  }

  const healthStatusColor = (status: ModelHealthResult['status']) => {
    switch (status) {
      case 'healthy':
        return 'text-green-500'
      case 'degraded':
        return 'text-yellow-500'
      case 'down':
        return 'text-destructive'
      case 'unconfigured':
        return 'text-muted-foreground'
      default:
        return 'text-muted-foreground'
    }
  }

  return (
    <div className="space-y-8">
      <section>
        <h2 className="text-lg font-semibold mb-3">Infrastructure</h2>
        <p className="text-sm text-muted-foreground mb-4">
          Manage API tokens, custom endpoints, model health, and local models.
        </p>

        <div className="space-y-2">
          <CollapsibleSection
            title="API Tokens"
            open={openSections.tokens}
            onToggle={() => toggleSection('tokens')}
          >
            <div className="pt-3 space-y-4">
              <div className="flex gap-2">
                <input
                  type="text"
                  value={newTokenName}
                  onChange={(e) => setNewTokenName(e.target.value)}
                  placeholder="Token name"
                  className="flex-1 px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') handleCreateToken()
                  }}
                />
                <Button
                  onClick={handleCreateToken}
                  disabled={creatingToken || !newTokenName.trim()}
                  size="sm"
                >
                  {creatingToken ? (
                    <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
                  ) : (
                    <Plus className="h-4 w-4 mr-1.5" />
                  )}
                  Create
                </Button>
              </div>

              {createdToken && (
                <div className="bg-green-500/10 border border-green-500/30 p-3 space-y-1">
                  <p className="text-sm font-medium text-green-500">
                    Token created. Copy it now -- it will not be shown again.
                  </p>
                  <div className="flex items-center gap-2">
                    <code className="text-xs font-mono bg-background px-2 py-1 flex-1 truncate">
                      {createdToken}
                    </code>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => {
                        navigator.clipboard.writeText(createdToken)
                      }}
                    >
                      <Copy className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </div>
              )}

              {tokensLoading ? (
                <div className="flex items-center justify-center py-4">
                  <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                </div>
              ) : tokens.length === 0 ? (
                <p className="text-sm text-muted-foreground py-2">No API tokens created yet.</p>
              ) : (
                <div className="space-y-1">
                  {tokens.map((token) => (
                    <div
                      key={token.id}
                      className="flex items-center justify-between py-2 border-b border-border last:border-0"
                    >
                      <div className="space-y-0.5">
                        <p className="text-sm font-medium">{token.name}</p>
                        <p className="text-xs text-muted-foreground">
                          {token.abilities.join(', ') || 'all'}
                          {token.last_used_at
                            ? ` -- last used ${new Date(token.last_used_at).toLocaleDateString()}`
                            : ' -- never used'}
                        </p>
                      </div>
                      <Button variant="ghost" size="sm" onClick={() => handleDeleteToken(token.id)}>
                        <Trash2 className="h-3.5 w-3.5 text-destructive" />
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </CollapsibleSection>

          <CollapsibleSection
            title="Custom Endpoints"
            open={openSections.endpoints}
            onToggle={() => toggleSection('endpoints')}
          >
            <div className="pt-3 space-y-4">
              <div className="flex gap-2">
                <input
                  type="text"
                  value={newEndpointName}
                  onChange={(e) => setNewEndpointName(e.target.value)}
                  placeholder="Endpoint name"
                  className="flex-1 px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <input
                  type="text"
                  value={newEndpointUrl}
                  onChange={(e) => setNewEndpointUrl(e.target.value)}
                  placeholder="https://..."
                  className="flex-1 px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') handleCreateEndpoint()
                  }}
                />
                <Button
                  onClick={handleCreateEndpoint}
                  disabled={creatingEndpoint || !newEndpointName.trim() || !newEndpointUrl.trim()}
                  size="sm"
                >
                  {creatingEndpoint ? (
                    <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
                  ) : (
                    <Plus className="h-4 w-4 mr-1.5" />
                  )}
                  Add
                </Button>
              </div>

              {endpointsLoading ? (
                <div className="flex items-center justify-center py-4">
                  <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                </div>
              ) : endpoints.length === 0 ? (
                <p className="text-sm text-muted-foreground py-2">No custom endpoints configured.</p>
              ) : (
                <div className="space-y-1">
                  {endpoints.map((ep) => (
                    <div
                      key={ep.id}
                      className="flex items-center justify-between py-2 border-b border-border last:border-0"
                    >
                      <div className="space-y-0.5">
                        <p className="text-sm font-medium">{ep.name}</p>
                        <p className="text-xs text-muted-foreground font-mono truncate max-w-md">{ep.base_url}</p>
                      </div>
                      <Button variant="ghost" size="sm" onClick={() => handleDeleteEndpoint(ep.id)}>
                        <Trash2 className="h-3.5 w-3.5 text-destructive" />
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </CollapsibleSection>

          <CollapsibleSection
            title="Model Health"
            open={openSections.health}
            onToggle={() => toggleSection('health')}
          >
            <div className="pt-3 space-y-3">
              <div className="flex justify-end">
                <Button variant="outline" size="sm" onClick={loadHealth} disabled={healthLoading}>
                  {healthLoading ? (
                    <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
                  ) : (
                    <RefreshCw className="h-4 w-4 mr-1.5" />
                  )}
                  Refresh
                </Button>
              </div>

              {healthLoading && healthResults.length === 0 ? (
                <div className="flex items-center justify-center py-4">
                  <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                </div>
              ) : healthResults.length === 0 ? (
                <p className="text-sm text-muted-foreground py-2">No provider health data available.</p>
              ) : (
                <div className="grid grid-cols-2 gap-2">
                  {healthResults.map((result) => (
                    <div
                      key={result.provider}
                      className="flex items-center gap-2 p-2 border border-border rounded"
                    >
                      <Circle className={`h-3 w-3 fill-current ${healthStatusColor(result.status)}`} />
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium capitalize">{result.provider}</p>
                        <p className="text-xs text-muted-foreground">
                          {result.status === 'healthy' && result.latency_ms != null
                            ? `${result.latency_ms}ms`
                            : result.status}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </CollapsibleSection>

          <CollapsibleSection
            title="Local Models"
            open={openSections.local}
            onToggle={() => toggleSection('local')}
          >
            <div className="pt-3 space-y-3">
              <div className="flex justify-end">
                <Button variant="outline" size="sm" onClick={loadLocalModels} disabled={localModelsLoading}>
                  {localModelsLoading ? (
                    <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
                  ) : (
                    <RefreshCw className="h-4 w-4 mr-1.5" />
                  )}
                  Refresh
                </Button>
              </div>

              {localModelsLoading && localModels.length === 0 ? (
                <div className="flex items-center justify-center py-4">
                  <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                </div>
              ) : localModels.length === 0 ? (
                <p className="text-sm text-muted-foreground py-2">No local models detected. Ensure Ollama is running.</p>
              ) : (
                <div className="space-y-1">
                  {localModels.map((model) => (
                    <div
                      key={model.id}
                      className="flex items-center justify-between py-2 border-b border-border last:border-0"
                    >
                      <div className="space-y-0.5">
                        <p className="text-sm font-medium">{model.name}</p>
                        <p className="text-xs text-muted-foreground">
                          {model.source}
                          {model.size ? ` -- ${model.size}` : ''}
                          {model.quantization ? ` -- ${model.quantization}` : ''}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </CollapsibleSection>
        </div>
      </section>
    </div>
  )
}

// --- Backups Panel (#282) ----------------------------------------------------

function BackupsPanel() {
  const [backups, setBackups] = useState<BackupEntry[]>([])
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)
  const [restoring, setRestoring] = useState<string | null>(null)
  const [confirmRestore, setConfirmRestore] = useState<string | null>(null)
  const [message, setMessage] = useState<{ text: string; type: 'success' | 'error' } | null>(null)

  const loadBackups = useCallback(async () => {
    try {
      const data = await fetchBackups()
      setBackups(data)
    } catch {
      /* handled */
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadBackups()
  }, [loadBackups])

  const handleCreate = async () => {
    setCreating(true)
    setMessage(null)
    try {
      await createBackup()
      await loadBackups()
      setMessage({ text: 'Backup created successfully.', type: 'success' })
    } catch {
      setMessage({ text: 'Failed to create backup.', type: 'error' })
    } finally {
      setCreating(false)
      setTimeout(() => setMessage(null), 4000)
    }
  }

  const handleRestore = async (filename: string) => {
    setRestoring(filename)
    setConfirmRestore(null)
    setMessage(null)
    try {
      await restoreBackup(filename)
      setMessage({ text: 'Backup restored successfully.', type: 'success' })
    } catch {
      setMessage({ text: 'Failed to restore backup.', type: 'error' })
    } finally {
      setRestoring(null)
      setTimeout(() => setMessage(null), 4000)
    }
  }

  const handleDownload = async (filename: string) => {
    try {
      const blob = await downloadBackup(filename)
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = filename
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      /* handled */
    }
  }

  const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-8">
      <section>
        <h2 className="text-lg font-semibold mb-3">Backups</h2>
        <div className="bg-card elevation-1 p-4 space-y-4">
          <div className="flex items-center justify-between">
            <p className="text-sm text-muted-foreground">
              Create and manage database backups. Restore to roll back to a previous state.
            </p>
            <Button onClick={handleCreate} disabled={creating} size="sm">
              {creating ? (
                <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
              ) : (
                <Plus className="h-4 w-4 mr-1.5" />
              )}
              Create Backup
            </Button>
          </div>

          {message && (
            <p className={`text-sm ${message.type === 'success' ? 'text-green-500' : 'text-destructive'}`}>
              {message.text}
            </p>
          )}

          {backups.length === 0 ? (
            <p className="text-sm text-muted-foreground py-4 text-center">No backups found.</p>
          ) : (
            <div className="border border-border rounded overflow-hidden">
              <table className="w-full text-sm">
                <thead>
                  <tr className="bg-muted/50 border-b border-border">
                    <th className="text-left px-3 py-2 font-medium text-muted-foreground">Filename</th>
                    <th className="text-right px-3 py-2 font-medium text-muted-foreground">Size</th>
                    <th className="text-right px-3 py-2 font-medium text-muted-foreground">Created</th>
                    <th className="text-right px-3 py-2 font-medium text-muted-foreground">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {backups.map((backup) => (
                    <tr key={backup.filename} className="border-b border-border last:border-0">
                      <td className="px-3 py-2 font-mono text-xs truncate max-w-xs">{backup.filename}</td>
                      <td className="px-3 py-2 text-right text-muted-foreground">{formatSize(backup.size)}</td>
                      <td className="px-3 py-2 text-right text-muted-foreground">
                        {new Date(backup.created_at).toLocaleDateString('en-US', {
                          month: 'short',
                          day: 'numeric',
                          year: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </td>
                      <td className="px-3 py-2 text-right">
                        <div className="flex items-center justify-end gap-1">
                          <Button variant="ghost" size="sm" onClick={() => handleDownload(backup.filename)}>
                            <Download className="h-3.5 w-3.5" />
                          </Button>
                          {confirmRestore === backup.filename ? (
                            <div className="flex items-center gap-1">
                              <Button
                                variant="destructive"
                                size="sm"
                                disabled={restoring === backup.filename}
                                onClick={() => handleRestore(backup.filename)}
                              >
                                {restoring === backup.filename ? (
                                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                ) : (
                                  'Confirm'
                                )}
                              </Button>
                              <Button variant="ghost" size="sm" onClick={() => setConfirmRestore(null)}>
                                Cancel
                              </Button>
                            </div>
                          ) : (
                            <Button variant="ghost" size="sm" onClick={() => setConfirmRestore(backup.filename)}>
                              <RotateCcw className="h-3.5 w-3.5" />
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </section>
    </div>
  )
}

// --- Diagnostics Panel (#283) ------------------------------------------------

function DiagnosticsPanel() {
  const [checks, setChecks] = useState<DiagnosticCheck[]>([])
  const [loading, setLoading] = useState(true)
  const [running, setRunning] = useState(false)

  const runDiagnostics = useCallback(async () => {
    setRunning(true)
    try {
      const data = await fetchDiagnostics()
      setChecks(data)
    } catch {
      /* handled */
    } finally {
      setRunning(false)
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    runDiagnostics()
  }, [runDiagnostics])

  const statusIcon = (status: DiagnosticCheck['status']) => {
    switch (status) {
      case 'pass':
        return <CheckCircle className="h-4 w-4 text-green-500" />
      case 'warning':
        return <AlertTriangle className="h-4 w-4 text-yellow-500" />
      case 'fail':
        return <XCircle className="h-4 w-4 text-destructive" />
      default:
        return <Circle className="h-4 w-4 text-muted-foreground" />
    }
  }

  const statusLabel = (status: DiagnosticCheck['status']) => {
    switch (status) {
      case 'pass':
        return 'Pass'
      case 'warning':
        return 'Warning'
      case 'fail':
        return 'Fail'
      default:
        return status
    }
  }

  if (loading) {
    return (
      <div className="flex flex-col items-center justify-center py-12 gap-2">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        <p className="text-sm text-muted-foreground">Running diagnostics...</p>
      </div>
    )
  }

  const passCount = checks.filter((c) => c.status === 'pass').length
  const warnCount = checks.filter((c) => c.status === 'warning').length
  const failCount = checks.filter((c) => c.status === 'fail').length

  return (
    <div className="space-y-8">
      <section>
        <h2 className="text-lg font-semibold mb-3">Diagnostics</h2>
        <div className="bg-card elevation-1 p-4 space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4 text-sm">
              <span className="text-green-500">{passCount} passed</span>
              {warnCount > 0 && (
                <span className="text-yellow-500">{warnCount} warnings</span>
              )}
              {failCount > 0 && (
                <span className="text-destructive">{failCount} failed</span>
              )}
            </div>
            <Button variant="outline" size="sm" onClick={runDiagnostics} disabled={running}>
              {running ? (
                <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
              ) : (
                <RefreshCw className="h-4 w-4 mr-1.5" />
              )}
              Run Diagnostics
            </Button>
          </div>

          {checks.length === 0 ? (
            <p className="text-sm text-muted-foreground py-4 text-center">No diagnostic results available.</p>
          ) : (
            <div className="space-y-1">
              {checks.map((check) => (
                <div
                  key={check.name}
                  className="flex items-start gap-3 py-2.5 border-b border-border last:border-0"
                >
                  <div className="mt-0.5">{statusIcon(check.status)}</div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                      <p className="text-sm font-medium">{check.name}</p>
                      <span
                        className={`text-xs ${
                          check.status === 'pass'
                            ? 'text-green-500'
                            : check.status === 'warning'
                              ? 'text-yellow-500'
                              : 'text-destructive'
                        }`}
                      >
                        {statusLabel(check.status)}
                      </span>
                    </div>
                    <p className="text-xs text-muted-foreground mt-0.5">{check.message}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </section>
    </div>
  )
}

function PlaceholderPanel({ name }: { name: string }) {
  return (
    <div className="flex items-center justify-center py-20 text-muted-foreground">
      <p className="text-sm">{name} -- coming soon</p>
    </div>
  )
}

// --- Main Settings page ------------------------------------------------------

export function Settings() {
  const [searchParams, setSearchParams] = useSearchParams()
  const activeTab = (searchParams.get('tab') as SettingsTab) || 'general'

  const setActiveTab = (tab: SettingsTab) => {
    setSearchParams({ tab })
  }

  return (
    <div className="flex h-[calc(100vh-3.5rem)]">
      <div className="w-56 shrink-0 border-r border-border overflow-y-auto bg-muted/30">
        <div className="px-4 py-4">
          <h1 className="text-lg font-semibold tracking-tight">Settings</h1>
        </div>
        <nav className="px-2 pb-4">
          {SECTIONS.map((section) => (
            <div key={section} className="mb-3">
              <p className="px-3 py-1 text-[11px] font-medium text-muted-foreground uppercase tracking-widest">
                {section}
              </p>
              {TABS.filter((t) => t.section === section).map((tab) => {
                const Icon = tab.icon
                const active = activeTab === tab.id
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`w-full flex items-center gap-2.5 px-3 py-1.5 text-sm rounded transition-colors ${
                      active
                        ? 'bg-primary/15 text-primary font-medium'
                        : 'text-muted-foreground hover:text-foreground hover:bg-accent/50'
                    }`}
                  >
                    <Icon className={`h-4 w-4 ${active ? 'text-primary' : ''}`} />
                    {tab.label}
                  </button>
                )
              })}
            </div>
          ))}
        </nav>
      </div>

      <div className="flex-1 overflow-y-auto p-6 max-w-3xl">
        {activeTab === 'general' && <GeneralPanel />}
        {activeTab === 'license' && <LicensePanel />}
        {activeTab === 'agents' && <PlaceholderPanel name="Agents" />}
        {activeTab === 'library' && <PlaceholderPanel name="Library" />}
        {activeTab === 'tags' && <PlaceholderPanel name="Tags" />}
        {activeTab === 'users' && <PlaceholderPanel name="Users" />}
        {activeTab === 'organizations' && <PlaceholderPanel name="Organizations" />}
        {activeTab === 'sso' && <PlaceholderPanel name="SSO" />}
        {activeTab === 'content-policies' && <PlaceholderPanel name="Content Policies" />}
        {activeTab === 'infrastructure' && <InfrastructurePanel />}
        {activeTab === 'backups' && <BackupsPanel />}
        {activeTab === 'diagnostics' && <DiagnosticsPanel />}
      </div>
    </div>
  )
}
