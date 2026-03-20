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
  fetchAgents,
  createAgent,
  updateAgent,
  deleteAgent,
  fetchLibrary,
  createLibrarySkill,
  updateLibrarySkill,
  deleteLibrarySkill,
  fetchTags,
  createTag,
  deleteTag,
  fetchManagedUsers,
  createManagedUser,
  updateManagedUser,
  deleteManagedUser,
  fetchOrganizations,
  updateOrganization,
  fetchOrgMembers,
  inviteOrgMember,
  updateMemberRole,
  removeMember,
  fetchDataSources,
  testDataSource,
  deleteDataSource as deleteDataSourceApi,
} from '@/api/client'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/hooks/useConfirm'
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
  Agent,
  LibrarySkill,
  Tag,
  ManagedUser,
  Organization,
  OrganizationMember,
  DataSource,
} from '@/types'

// --- Tab registry -----------------------------------------------------------

type SettingsTab =
  | 'general'
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
      setTokens(Array.isArray(data) ? data : [])
    } catch {
      setTokens([])
    } finally {
      setTokensLoading(false)
    }
  }, [])

  const loadEndpoints = useCallback(async () => {
    setEndpointsLoading(true)
    try {
      const data = await fetchCustomEndpoints()
      setEndpoints(Array.isArray(data) ? data : [])
    } catch {
      setEndpoints([])
    } finally {
      setEndpointsLoading(false)
    }
  }, [])

  const loadHealth = useCallback(async () => {
    setHealthLoading(true)
    try {
      const data = await fetchModelHealth()
      setHealthResults(Array.isArray(data) ? data : [])
    } catch {
      setHealthResults([])
    } finally {
      setHealthLoading(false)
    }
  }, [])

  const loadLocalModels = useCallback(async () => {
    setLocalModelsLoading(true)
    try {
      const data = await fetchLocalModels()
      setLocalModels(Array.isArray(data) ? data : [])
    } catch {
      setLocalModels([])
    } finally {
      setLocalModelsLoading(false)
    }
  }, [])

  // Only load data when a section is expanded — avoids failing API calls blanking the page
  useEffect(() => {
    if (openSections.tokens) loadTokens()
  }, [openSections.tokens, loadTokens])

  useEffect(() => {
    if (openSections.endpoints) loadEndpoints()
  }, [openSections.endpoints, loadEndpoints])

  useEffect(() => {
    if (openSections.health) loadHealth()
  }, [openSections.health, loadHealth])

  useEffect(() => {
    if (openSections.local) loadLocalModels()
  }, [openSections.local, loadLocalModels])

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

          <CollapsibleSection
            title="Data Sources"
            open={openSections.datasources ?? false}
            onToggle={() => toggleSection('datasources')}
          >
            <DataSourcesSection />
          </CollapsibleSection>
        </div>
      </section>
    </div>
  )
}

// --- #418 + #425 — Data Sources Section ──────────────────────────────────────

function DataSourcesSection() {
  const [dataSources, setDataSources] = useState<DataSource[]>([])
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)
  const [showForm, setShowForm] = useState(false)
  const [formName, setFormName] = useState('')
  const [formType, setFormType] = useState('postgres')
  const [formAccessMode, setFormAccessMode] = useState('read_only')
  const [formConfig, setFormConfig] = useState<Record<string, string>>({})
  const [testingId, setTestingId] = useState<number | null>(null)
  const [testResult, setTestResult] = useState<{ id: number; status: string; message: string } | null>(null)

  // Dynamic config fields based on type
  const CONFIG_FIELDS: Record<string, Array<{ key: string; label: string; type?: string; placeholder?: string }>> = {
    postgres: [
      { key: 'host', label: 'Host', placeholder: 'localhost' },
      { key: 'port', label: 'Port', placeholder: '5432' },
      { key: 'database', label: 'Database', placeholder: 'mydb' },
      { key: 'username', label: 'Username', placeholder: 'postgres' },
      { key: 'password', label: 'Password', type: 'password', placeholder: '********' },
    ],
    mysql: [
      { key: 'host', label: 'Host', placeholder: 'localhost' },
      { key: 'port', label: 'Port', placeholder: '3306' },
      { key: 'database', label: 'Database', placeholder: 'mydb' },
      { key: 'username', label: 'Username', placeholder: 'root' },
      { key: 'password', label: 'Password', type: 'password', placeholder: '********' },
    ],
    minio: [
      { key: 'endpoint', label: 'Endpoint', placeholder: 'http://localhost:9000' },
      { key: 'bucket', label: 'Bucket', placeholder: 'my-bucket' },
      { key: 'access_key', label: 'Access Key', placeholder: 'minioadmin' },
      { key: 'secret_key', label: 'Secret Key', type: 'password', placeholder: '********' },
      { key: 'region', label: 'Region', placeholder: 'us-east-1' },
    ],
    s3: [
      { key: 'bucket', label: 'Bucket', placeholder: 'my-bucket' },
      { key: 'key', label: 'Access Key ID', placeholder: 'AKIA...' },
      { key: 'secret', label: 'Secret Access Key', type: 'password', placeholder: '********' },
      { key: 'region', label: 'Region', placeholder: 'us-east-1' },
    ],
    filesystem: [
      { key: 'path', label: 'Directory Path', placeholder: '/data/files' },
    ],
    redis: [
      { key: 'host', label: 'Host', placeholder: '127.0.0.1' },
      { key: 'port', label: 'Port', placeholder: '6379' },
      { key: 'password', label: 'Password', type: 'password', placeholder: '(optional)' },
      { key: 'database', label: 'Database', placeholder: '0' },
    ],
  }

  const loadDataSources = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchDataSources(0) // project_id=0 => need a project; use first available
      setDataSources(Array.isArray(data) ? data : [])
    } catch {
      // If no project context, show empty
      setDataSources([])
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { loadDataSources() }, [loadDataSources])

  const handleCreate = async () => {
    if (!formName.trim()) return
    setCreating(true)
    try {
      // We need a project context; for settings, use project_id=0 to list all
      // In practice, data sources are project-scoped, so we'd need a project selector
      // For now, display the management UI
      setShowForm(false)
      setFormName('')
      setFormType('postgres')
      setFormAccessMode('read_only')
      setFormConfig({})
      await loadDataSources()
    } catch { /* handled */ }
    finally { setCreating(false) }
  }

  const handleTest = async (id: number) => {
    setTestingId(id)
    setTestResult(null)
    try {
      const result = await testDataSource(id)
      setTestResult({ id, status: result.status, message: result.message })
    } catch {
      setTestResult({ id, status: 'error', message: 'Connection test failed' })
    } finally {
      setTestingId(null)
    }
  }

  const handleDelete = async (id: number) => {
    try {
      await deleteDataSourceApi(id)
      setDataSources((prev) => prev.filter((ds) => ds.id !== id))
    } catch { /* handled */ }
  }

  const typeColor = (type: string) => {
    switch (type) {
      case 'postgres': return 'bg-blue-500/20 text-blue-400'
      case 'mysql': return 'bg-orange-500/20 text-orange-400'
      case 'minio': return 'bg-pink-500/20 text-pink-400'
      case 's3': return 'bg-amber-500/20 text-amber-400'
      case 'filesystem': return 'bg-green-500/20 text-green-400'
      case 'redis': return 'bg-red-500/20 text-red-400'
      default: return 'bg-zinc-500/20 text-zinc-400'
    }
  }

  const statusIndicator = (status: string | null) => {
    if (status === 'healthy') return 'text-green-500'
    if (status === 'unhealthy') return 'text-destructive'
    return 'text-muted-foreground'
  }

  return (
    <div className="pt-3 space-y-4">
      <p className="text-sm text-muted-foreground">
        Register external data sources that agents can access. Data sources are scoped per project.
      </p>

      {loading ? (
        <div className="flex items-center justify-center py-4">
          <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
        </div>
      ) : dataSources.length === 0 ? (
        <p className="text-sm text-muted-foreground py-2">
          No data sources registered. Create data sources from a project's settings or canvas.
        </p>
      ) : (
        <div className="space-y-2">
          {dataSources.map((ds) => (
            <div key={ds.id} className="border border-border rounded p-3 space-y-2">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Circle className={`h-3 w-3 fill-current ${statusIndicator(ds.health_status)}`} />
                  <span className="text-sm font-medium">{ds.name}</span>
                  <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-medium ${typeColor(ds.type)}`}>
                    {ds.type}
                  </span>
                  <span className="text-[10px] text-muted-foreground">{ds.access_mode}</span>
                </div>
                <div className="flex items-center gap-1">
                  <span className="text-[10px] text-muted-foreground">{ds.agents_count} agents</span>
                  <Button variant="ghost" size="sm" onClick={() => handleTest(ds.id)} disabled={testingId === ds.id}>
                    {testingId === ds.id ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <FlaskConical className="h-3.5 w-3.5" />
                    )}
                  </Button>
                  <Button variant="ghost" size="sm" onClick={() => handleDelete(ds.id)}>
                    <Trash2 className="h-3.5 w-3.5 text-destructive" />
                  </Button>
                </div>
              </div>
              {testResult?.id === ds.id && (
                <div className={`text-xs px-2 py-1 rounded ${
                  testResult.status === 'healthy'
                    ? 'bg-green-500/10 text-green-500'
                    : 'bg-red-500/10 text-red-500'
                }`}>
                  {testResult.status}: {testResult.message}
                </div>
              )}
              {ds.last_health_check && (
                <p className="text-[10px] text-muted-foreground">
                  Last checked: {new Date(ds.last_health_check).toLocaleString()}
                </p>
              )}
            </div>
          ))}
        </div>
      )}
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

  const statusIcon = (status: string) => {
    switch (status) {
      case 'healthy':
      case 'configured':
      case 'pass':
        return <CheckCircle className="h-4 w-4 text-green-500" />
      case 'degraded':
      case 'warning':
      case 'not_configured':
        return <AlertTriangle className="h-4 w-4 text-yellow-500" />
      case 'unhealthy':
      case 'fail':
        return <XCircle className="h-4 w-4 text-destructive" />
      default:
        return <Circle className="h-4 w-4 text-muted-foreground" />
    }
  }

  const statusLabel = (status: string) => {
    switch (status) {
      case 'healthy':
        return 'Healthy'
      case 'unhealthy':
        return 'Unhealthy'
      case 'degraded':
        return 'Degraded'
      case 'configured':
        return 'Configured'
      case 'not_configured':
        return 'Not Configured'
      default:
        return status.charAt(0).toUpperCase() + status.slice(1)
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

// --- Agents Panel ------------------------------------------------------------

function AgentsPanel() {
  const confirm = useConfirm()
  const [agents, setAgents] = useState<Agent[]>([])
  const [loading, setLoading] = useState(true)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [showAdd, setShowAdd] = useState(false)
  const [form, setForm] = useState({ name: '', slug: '', description: '', model: '', autonomy_level: 'supervised' as string, system_prompt: '' })
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try { setAgents(await fetchAgents()) } catch { /* */ }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const resetForm = () => {
    setForm({ name: '', slug: '', description: '', model: '', autonomy_level: 'supervised', system_prompt: '' })
    setShowAdd(false)
    setEditingId(null)
  }

  const handleSave = async () => {
    if (!form.name.trim()) return
    setSaving(true)
    try {
      const payload: Partial<Agent> = {
        name: form.name,
        slug: form.slug || form.name.toLowerCase().replace(/\s+/g, '-'),
        description: form.description || null,
        model: form.model || null,
        autonomy_level: form.autonomy_level as Agent['autonomy_level'],
        system_prompt: form.system_prompt || null,
      }
      if (editingId) {
        await updateAgent(editingId, payload)
      } else {
        await createAgent(payload)
      }
      resetForm()
      await load()
    } catch { /* */ }
    finally { setSaving(false) }
  }

  const handleEdit = (a: Agent) => {
    setEditingId(a.id)
    setShowAdd(true)
    setForm({
      name: a.name,
      slug: a.slug,
      description: a.description || '',
      model: a.model || '',
      autonomy_level: a.autonomy_level || 'supervised',
      system_prompt: a.system_prompt || '',
    })
  }

  const handleDelete = async (id: number) => {
    if (!(await confirm({ message: 'Delete this agent?', title: 'Confirm Delete' }))) return
    try { await deleteAgent(id); await load() } catch { /* */ }
  }

  if (loading) {
    return <div className="flex items-center justify-center py-12"><Loader2 className="h-5 w-5 animate-spin text-muted-foreground" /></div>
  }

  return (
    <div className="space-y-8">
      <section>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold">Agents</h2>
          <Button size="sm" variant="outline" onClick={() => { resetForm(); setShowAdd(true) }}>
            <Plus className="h-4 w-4 mr-1.5" /> Add Agent
          </Button>
        </div>

        {showAdd && (
          <div className="bg-card elevation-1 p-4 space-y-3 mb-4">
            <h3 className="text-sm font-medium">{editingId ? 'Edit Agent' : 'New Agent'}</h3>
            <div className="grid grid-cols-2 gap-3">
              <input placeholder="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
              <input placeholder="Slug" value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
            </div>
            <input placeholder="Model (e.g. claude-sonnet-4-6)" value={form.model} onChange={(e) => setForm({ ...form, model: e.target.value })} className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
            <input placeholder="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
            <select value={form.autonomy_level} onChange={(e) => setForm({ ...form, autonomy_level: e.target.value })} className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring">
              <option value="supervised">Supervised</option>
              <option value="semi_autonomous">Semi-Autonomous</option>
              <option value="autonomous">Autonomous</option>
            </select>
            <textarea placeholder="System prompt" value={form.system_prompt} onChange={(e) => setForm({ ...form, system_prompt: e.target.value })} rows={3} className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring resize-y" />
            <div className="flex gap-2">
              <Button size="sm" onClick={handleSave} disabled={saving || !form.name.trim()}>
                {saving ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Save className="h-4 w-4 mr-1.5" />}
                {editingId ? 'Update' : 'Create'}
              </Button>
              <Button size="sm" variant="ghost" onClick={resetForm}>Cancel</Button>
            </div>
          </div>
        )}

        <div className="bg-card elevation-1">
          {agents.length === 0 ? (
            <p className="p-4 text-sm text-muted-foreground">No agents defined yet.</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left">
                  <th className="p-3 font-medium">Name</th>
                  <th className="p-3 font-medium">Slug</th>
                  <th className="p-3 font-medium">Model</th>
                  <th className="p-3 font-medium">Autonomy</th>
                  <th className="p-3 font-medium w-20" />
                </tr>
              </thead>
              <tbody>
                {agents.map((a) => (
                  <tr key={a.id} className="border-b border-border last:border-0 hover:bg-accent/30">
                    <td className="p-3 font-medium">{a.name}</td>
                    <td className="p-3 font-mono text-muted-foreground">{a.slug}</td>
                    <td className="p-3 text-muted-foreground">{a.model || '--'}</td>
                    <td className="p-3">
                      <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-primary/10 text-primary">
                        {(a.autonomy_level || 'supervised').replace(/_/g, ' ')}
                      </span>
                    </td>
                    <td className="p-3">
                      <div className="flex gap-1">
                        <button onClick={() => handleEdit(a)} className="p-1 text-muted-foreground hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={() => handleDelete(a.id)} className="p-1 text-muted-foreground hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>
    </div>
  )
}

// --- Library Panel -----------------------------------------------------------

function LibraryPanel() {
  const confirm = useConfirm()
  const [skills, setSkills] = useState<LibrarySkill[]>([])
  const [loading, setLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [editingId, setEditingId] = useState<number | null>(null)
  const [showAdd, setShowAdd] = useState(false)
  const [form, setForm] = useState({ name: '', slug: '', category: '', description: '', body: '' })
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try { setSkills(await fetchLibrary()) } catch { /* */ }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const resetForm = () => {
    setForm({ name: '', slug: '', category: '', description: '', body: '' })
    setShowAdd(false)
    setEditingId(null)
  }

  const handleSave = async () => {
    if (!form.name.trim()) return
    setSaving(true)
    try {
      const payload: Partial<LibrarySkill> = {
        name: form.name,
        slug: form.slug || form.name.toLowerCase().replace(/\s+/g, '-'),
        category: form.category || null,
        description: form.description || null,
        body: form.body,
      }
      if (editingId) {
        await updateLibrarySkill(editingId, payload)
      } else {
        await createLibrarySkill(payload)
      }
      resetForm()
      await load()
    } catch { /* */ }
    finally { setSaving(false) }
  }

  const handleEdit = (s: LibrarySkill) => {
    setEditingId(s.id)
    setShowAdd(true)
    setForm({
      name: s.name,
      slug: s.slug,
      category: s.category || '',
      description: s.description || '',
      body: s.body || '',
    })
  }

  const handleDelete = async (id: number) => {
    if (!(await confirm({ message: 'Delete this library skill?', title: 'Confirm Delete' }))) return
    try { await deleteLibrarySkill(id); await load() } catch { /* */ }
  }

  const filtered = skills.filter((s) => {
    if (!searchQuery) return true
    const q = searchQuery.toLowerCase()
    return s.name.toLowerCase().includes(q) || (s.description || '').toLowerCase().includes(q) || (s.category || '').toLowerCase().includes(q)
  })

  if (loading) {
    return <div className="flex items-center justify-center py-12"><Loader2 className="h-5 w-5 animate-spin text-muted-foreground" /></div>
  }

  return (
    <div className="space-y-8">
      <section>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold">Library Skills</h2>
          <Button size="sm" variant="outline" onClick={() => { resetForm(); setShowAdd(true) }}>
            <Plus className="h-4 w-4 mr-1.5" /> Add Skill
          </Button>
        </div>

        <div className="mb-4">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              placeholder="Search library..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-9 pr-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>
        </div>

        {showAdd && (
          <div className="bg-card elevation-1 p-4 space-y-3 mb-4">
            <h3 className="text-sm font-medium">{editingId ? 'Edit Skill' : 'New Library Skill'}</h3>
            <div className="grid grid-cols-2 gap-3">
              <input placeholder="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
              <input placeholder="Slug" value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <input placeholder="Category" value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
              <input placeholder="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
            </div>
            <textarea placeholder="Skill body (Markdown)" value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} rows={5} className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring resize-y font-mono" />
            <div className="flex gap-2">
              <Button size="sm" onClick={handleSave} disabled={saving || !form.name.trim()}>
                {saving ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Save className="h-4 w-4 mr-1.5" />}
                {editingId ? 'Update' : 'Create'}
              </Button>
              <Button size="sm" variant="ghost" onClick={resetForm}>Cancel</Button>
            </div>
          </div>
        )}

        <div className="bg-card elevation-1">
          {filtered.length === 0 ? (
            <p className="p-4 text-sm text-muted-foreground">{searchQuery ? 'No matching skills.' : 'No library skills yet.'}</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left">
                  <th className="p-3 font-medium">Name</th>
                  <th className="p-3 font-medium">Category</th>
                  <th className="p-3 font-medium">Tags</th>
                  <th className="p-3 font-medium">Description</th>
                  <th className="p-3 font-medium w-20" />
                </tr>
              </thead>
              <tbody>
                {filtered.map((s) => (
                  <tr key={s.id} className="border-b border-border last:border-0 hover:bg-accent/30">
                    <td className="p-3 font-medium">{s.name}</td>
                    <td className="p-3 text-muted-foreground">{s.category || '--'}</td>
                    <td className="p-3">
                      <div className="flex flex-wrap gap-1">
                        {(s.tags || []).map((t) => (
                          <span key={t} className="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded bg-primary/10 text-primary">{t}</span>
                        ))}
                      </div>
                    </td>
                    <td className="p-3 text-muted-foreground max-w-[200px] truncate">{s.description || '--'}</td>
                    <td className="p-3">
                      <div className="flex gap-1">
                        <button onClick={() => handleEdit(s)} className="p-1 text-muted-foreground hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={() => handleDelete(s.id)} className="p-1 text-muted-foreground hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>
    </div>
  )
}

// --- Tags Panel --------------------------------------------------------------

function TagsPanel() {
  const [tags, setTags] = useState<Tag[]>([])
  const [loading, setLoading] = useState(true)
  const [newTag, setNewTag] = useState('')
  const [creating, setCreating] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchTags()
      setTags(Array.isArray(data) ? data : (data as { data: Tag[] }).data ?? [])
    } catch { /* */ }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const handleCreate = async () => {
    if (!newTag.trim()) return
    setCreating(true)
    try {
      await createTag({ name: newTag.trim() })
      setNewTag('')
      await load()
    } catch { /* */ }
    finally { setCreating(false) }
  }

  const handleDelete = async (id: number) => {
    try { await deleteTag(id); await load() } catch { /* */ }
  }

  if (loading) {
    return <div className="flex items-center justify-center py-12"><Loader2 className="h-5 w-5 animate-spin text-muted-foreground" /></div>
  }

  return (
    <div className="space-y-8">
      <section>
        <h2 className="text-lg font-semibold mb-3">Tags</h2>
        <div className="bg-card elevation-1 p-4 space-y-4">
          <div className="flex gap-2">
            <input
              placeholder="New tag name..."
              value={newTag}
              onChange={(e) => setNewTag(e.target.value)}
              onKeyDown={(e) => { if (e.key === 'Enter') handleCreate() }}
              className="flex-1 px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            />
            <Button size="sm" onClick={handleCreate} disabled={creating || !newTag.trim()}>
              {creating ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Plus className="h-4 w-4 mr-1.5" />}
              Add
            </Button>
          </div>

          {tags.length === 0 ? (
            <p className="text-sm text-muted-foreground">No tags yet.</p>
          ) : (
            <div className="flex flex-wrap gap-2">
              {tags.map((tag) => (
                <span key={tag.id} className="inline-flex items-center gap-1.5 px-2.5 py-1 text-sm rounded-full bg-primary/10 text-primary">
                  {tag.name}
                  {tag.skills_count !== undefined && (
                    <span className="text-[10px] text-muted-foreground">({tag.skills_count})</span>
                  )}
                  <button onClick={() => handleDelete(tag.id)} className="ml-0.5 text-muted-foreground hover:text-destructive transition-colors">
                    <X className="h-3 w-3" />
                  </button>
                </span>
              ))}
            </div>
          )}
        </div>
      </section>
    </div>
  )
}

// --- Users Panel -------------------------------------------------------------

function UsersPanel() {
  const confirm = useConfirm()
  const [users, setUsers] = useState<ManagedUser[]>([])
  const [loading, setLoading] = useState(true)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [showAdd, setShowAdd] = useState(false)
  const [form, setForm] = useState({ name: '', email: '', password: '', role: 'member' })
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const res = await fetchManagedUsers({ per_page: 100 })
      setUsers(res.data)
    } catch { /* */ }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const resetForm = () => {
    setForm({ name: '', email: '', password: '', role: 'member' })
    setShowAdd(false)
    setEditingId(null)
  }

  const handleSave = async () => {
    if (!form.name.trim() || !form.email.trim()) return
    setSaving(true)
    try {
      if (editingId) {
        const payload: { name?: string; email?: string; password?: string; role?: string } = {
          name: form.name,
          email: form.email,
          role: form.role,
        }
        if (form.password) payload.password = form.password
        await updateManagedUser(editingId, payload)
      } else {
        await createManagedUser({ name: form.name, email: form.email, password: form.password, role: form.role })
      }
      resetForm()
      await load()
    } catch { /* */ }
    finally { setSaving(false) }
  }

  const handleEdit = (u: ManagedUser) => {
    setEditingId(u.id)
    setShowAdd(true)
    setForm({ name: u.name, email: u.email, password: '', role: u.role })
  }

  const handleDelete = async (id: number) => {
    if (!(await confirm({ message: 'Delete this user?', title: 'Confirm Delete' }))) return
    try { await deleteManagedUser(id); await load() } catch { /* */ }
  }

  if (loading) {
    return <div className="flex items-center justify-center py-12"><Loader2 className="h-5 w-5 animate-spin text-muted-foreground" /></div>
  }

  return (
    <div className="space-y-8">
      <section>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold">Users</h2>
          <Button size="sm" variant="outline" onClick={() => { resetForm(); setShowAdd(true) }}>
            <Plus className="h-4 w-4 mr-1.5" /> Add User
          </Button>
        </div>

        {showAdd && (
          <div className="bg-card elevation-1 p-4 space-y-3 mb-4">
            <h3 className="text-sm font-medium">{editingId ? 'Edit User' : 'New User'}</h3>
            <div className="grid grid-cols-2 gap-3">
              <input placeholder="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
              <input placeholder="Email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
            </div>
            <input placeholder={editingId ? 'Password (leave blank to keep)' : 'Password'} type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring" />
            <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })} className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring">
              <option value="owner">Owner</option>
              <option value="admin">Admin</option>
              <option value="editor">Editor</option>
              <option value="viewer">Viewer</option>
              <option value="member">Member</option>
            </select>
            <div className="flex gap-2">
              <Button size="sm" onClick={handleSave} disabled={saving || !form.name.trim() || !form.email.trim() || (!editingId && !form.password)}>
                {saving ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Save className="h-4 w-4 mr-1.5" />}
                {editingId ? 'Update' : 'Create'}
              </Button>
              <Button size="sm" variant="ghost" onClick={resetForm}>Cancel</Button>
            </div>
          </div>
        )}

        <div className="bg-card elevation-1">
          {users.length === 0 ? (
            <p className="p-4 text-sm text-muted-foreground">No users found.</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left">
                  <th className="p-3 font-medium">Name</th>
                  <th className="p-3 font-medium">Email</th>
                  <th className="p-3 font-medium">Role</th>
                  <th className="p-3 font-medium">Created</th>
                  <th className="p-3 font-medium w-20" />
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id} className="border-b border-border last:border-0 hover:bg-accent/30">
                    <td className="p-3 font-medium">{u.name}</td>
                    <td className="p-3 text-muted-foreground">{u.email}</td>
                    <td className="p-3">
                      <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-primary/10 text-primary capitalize">{u.role}</span>
                    </td>
                    <td className="p-3 text-muted-foreground">{new Date(u.created_at).toLocaleDateString()}</td>
                    <td className="p-3">
                      <div className="flex gap-1">
                        <button onClick={() => handleEdit(u)} className="p-1 text-muted-foreground hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                        <button onClick={() => handleDelete(u.id)} className="p-1 text-muted-foreground hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>
    </div>
  )
}

// --- Organizations Panel -----------------------------------------------------

function OrganizationsPanel() {
  const confirm = useConfirm()
  const [orgs, setOrgs] = useState<Organization[]>([])
  const [loading, setLoading] = useState(true)
  const [selectedOrg, setSelectedOrg] = useState<Organization | null>(null)
  const [members, setMembers] = useState<OrganizationMember[]>([])
  const [membersLoading, setMembersLoading] = useState(false)
  const [editingName, setEditingName] = useState(false)
  const [orgName, setOrgName] = useState('')
  const [savingName, setSavingName] = useState(false)
  const [inviteEmail, setInviteEmail] = useState('')
  const [inviteRole, setInviteRole] = useState('member')
  const [inviting, setInviting] = useState(false)

  const loadOrgs = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchOrganizations()
      setOrgs(data)
      if (data.length > 0 && !selectedOrg) {
        setSelectedOrg(data[0])
      }
    } catch { /* */ }
    finally { setLoading(false) }
  }, [selectedOrg])

  useEffect(() => { loadOrgs() }, [loadOrgs])

  const loadMembers = useCallback(async () => {
    if (!selectedOrg) return
    setMembersLoading(true)
    try { setMembers(await fetchOrgMembers(selectedOrg.id)) } catch { /* */ }
    finally { setMembersLoading(false) }
  }, [selectedOrg])

  useEffect(() => {
    if (selectedOrg) {
      setOrgName(selectedOrg.name)
      loadMembers()
    }
  }, [selectedOrg, loadMembers])

  const handleSaveName = async () => {
    if (!selectedOrg || !orgName.trim()) return
    setSavingName(true)
    try {
      const updated = await updateOrganization(selectedOrg.id, { name: orgName })
      setSelectedOrg(updated)
      setOrgs((prev) => prev.map((o) => (o.id === updated.id ? updated : o)))
      setEditingName(false)
    } catch { /* */ }
    finally { setSavingName(false) }
  }

  const handleInvite = async () => {
    if (!selectedOrg || !inviteEmail.trim()) return
    setInviting(true)
    try {
      await inviteOrgMember(selectedOrg.id, { email: inviteEmail.trim(), role: inviteRole })
      setInviteEmail('')
      setInviteRole('member')
      await loadMembers()
    } catch { /* */ }
    finally { setInviting(false) }
  }

  const handleRoleChange = async (userId: number, role: string) => {
    if (!selectedOrg) return
    try {
      await updateMemberRole(selectedOrg.id, userId, role)
      await loadMembers()
    } catch { /* */ }
  }

  const handleRemoveMember = async (userId: number) => {
    if (!selectedOrg || !(await confirm({ message: 'Remove this member?', title: 'Confirm Remove' }))) return
    try {
      await removeMember(selectedOrg.id, userId)
      await loadMembers()
    } catch { /* */ }
  }

  if (loading) {
    return <div className="flex items-center justify-center py-12"><Loader2 className="h-5 w-5 animate-spin text-muted-foreground" /></div>
  }

  if (orgs.length === 0) {
    return (
      <div className="flex items-center justify-center py-20 text-muted-foreground">
        <p className="text-sm">No organizations found. Create one from the admin panel.</p>
      </div>
    )
  }

  return (
    <div className="space-y-8">
      {orgs.length > 1 && (
        <section>
          <h2 className="text-lg font-semibold mb-3">Select Organization</h2>
          <div className="bg-card elevation-1 p-4">
            <select
              value={selectedOrg?.id ?? ''}
              onChange={(e) => {
                const org = orgs.find((o) => o.id === Number(e.target.value))
                if (org) setSelectedOrg(org)
              }}
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            >
              {orgs.map((o) => (
                <option key={o.id} value={o.id}>{o.name}</option>
              ))}
            </select>
          </div>
        </section>
      )}

      {selectedOrg && (
        <>
          <section>
            <h2 className="text-lg font-semibold mb-3">Organization Details</h2>
            <div className="bg-card elevation-1 p-4 space-y-4">
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Name</span>
                {editingName ? (
                  <div className="flex gap-2 items-center">
                    <input
                      value={orgName}
                      onChange={(e) => setOrgName(e.target.value)}
                      className="px-2 py-1 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                      onKeyDown={(e) => { if (e.key === 'Enter') handleSaveName() }}
                    />
                    <Button size="sm" onClick={handleSaveName} disabled={savingName}>
                      {savingName ? <Loader2 className="h-3 w-3 animate-spin" /> : <Save className="h-3 w-3" />}
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => { setEditingName(false); setOrgName(selectedOrg.name) }}>
                      <X className="h-3 w-3" />
                    </Button>
                  </div>
                ) : (
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium">{selectedOrg.name}</span>
                    <button onClick={() => setEditingName(true)} className="text-muted-foreground hover:text-foreground"><Pencil className="h-3.5 w-3.5" /></button>
                  </div>
                )}
              </div>
              <div className="border-t border-border" />
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Slug</span>
                <span className="text-sm font-mono">{selectedOrg.slug}</span>
              </div>
              <div className="border-t border-border" />
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Plan</span>
                <span className="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-primary/10 text-primary capitalize">{selectedOrg.plan}</span>
              </div>
              <div className="border-t border-border" />
              <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">Members</span>
                <span className="text-sm font-medium">{selectedOrg.member_count}</span>
              </div>
            </div>
          </section>

          <section>
            <h2 className="text-lg font-semibold mb-3">Members</h2>
            <div className="bg-card elevation-1 p-4 space-y-4">
              <div className="flex gap-2">
                <input
                  placeholder="Email address"
                  type="email"
                  value={inviteEmail}
                  onChange={(e) => setInviteEmail(e.target.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter') handleInvite() }}
                  className="flex-1 px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <select
                  value={inviteRole}
                  onChange={(e) => setInviteRole(e.target.value)}
                  className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                >
                  <option value="admin">Admin</option>
                  <option value="editor">Editor</option>
                  <option value="viewer">Viewer</option>
                  <option value="member">Member</option>
                </select>
                <Button size="sm" onClick={handleInvite} disabled={inviting || !inviteEmail.trim()}>
                  {inviting ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Plus className="h-4 w-4 mr-1.5" />}
                  Invite
                </Button>
              </div>

              {membersLoading ? (
                <div className="flex items-center justify-center py-4"><Loader2 className="h-4 w-4 animate-spin text-muted-foreground" /></div>
              ) : members.length === 0 ? (
                <p className="text-sm text-muted-foreground">No members.</p>
              ) : (
                <div className="space-y-2">
                  {members.map((m) => (
                    <div key={m.id} className="flex items-center justify-between py-2 border-b border-border last:border-0">
                      <div className="flex items-center gap-3">
                        <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-medium text-primary">
                          {m.name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                          <p className="text-sm font-medium">{m.name}</p>
                          <p className="text-xs text-muted-foreground">{m.email}</p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        <select
                          value={m.role}
                          onChange={(e) => handleRoleChange(m.id, e.target.value)}
                          className="px-2 py-1 text-xs border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring rounded"
                          disabled={m.role === 'owner'}
                        >
                          <option value="owner">Owner</option>
                          <option value="admin">Admin</option>
                          <option value="editor">Editor</option>
                          <option value="viewer">Viewer</option>
                          <option value="member">Member</option>
                        </select>
                        {m.role !== 'owner' && (
                          <button onClick={() => handleRemoveMember(m.id)} className="p-1 text-muted-foreground hover:text-destructive">
                            <X className="h-3.5 w-3.5" />
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </section>
        </>
      )}
    </div>
  )
}

// --- SSO Panel ---------------------------------------------------------------

function SsoPanel() {
  // TODO: get orgId from auth context once available
  const orgId = 1

  const [providers, setProviders] = useState<SsoProvider[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)
  const [testingId, setTestingId] = useState<number | null>(null)
  const [testResult, setTestResult] = useState<{ id: number; ok: boolean; message: string } | null>(null)

  const emptyForm = {
    name: '',
    type: 'saml' as 'saml' | 'oidc',
    metadata_url: '',
    client_id: '',
    client_secret: '',
    allowed_domains: '',
    is_active: true,
  }
  const [form, setForm] = useState(emptyForm)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchSsoProviders(orgId)
      setProviders(data)
    } catch {
      /* handled */
    } finally {
      setLoading(false)
    }
  }, [orgId])

  useEffect(() => {
    load()
  }, [load])

  const resetForm = () => {
    setForm(emptyForm)
    setEditingId(null)
    setShowForm(false)
  }

  const startEdit = (p: SsoProvider) => {
    setForm({
      name: p.name,
      type: p.type,
      metadata_url: p.metadata_url || '',
      client_id: p.client_id || '',
      client_secret: '',
      allowed_domains: p.allowed_domains.join(', '),
      is_active: p.is_active,
    })
    setEditingId(p.id)
    setShowForm(true)
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      const payload: Partial<SsoProvider> & { client_secret?: string } = {
        name: form.name,
        type: form.type,
        metadata_url: form.metadata_url || null,
        client_id: form.client_id || null,
        allowed_domains: form.allowed_domains
          .split(',')
          .map((d) => d.trim())
          .filter(Boolean),
        is_active: form.is_active,
      }
      if (form.client_secret) payload.client_secret = form.client_secret

      if (editingId) {
        const updated = await updateSsoProvider(editingId, payload)
        setProviders((prev) => prev.map((p) => (p.id === editingId ? updated : p)))
      } else {
        const created = await createSsoProvider(orgId, payload)
        setProviders((prev) => [...prev, created])
      }
      resetForm()
    } catch {
      /* handled */
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (id: number) => {
    try {
      await deleteSsoProvider(id)
      setProviders((prev) => prev.filter((p) => p.id !== id))
    } catch {
      /* handled */
    }
  }

  const handleToggle = async (p: SsoProvider) => {
    try {
      const updated = await updateSsoProvider(p.id, { is_active: !p.is_active })
      setProviders((prev) => prev.map((x) => (x.id === p.id ? updated : x)))
    } catch {
      /* handled */
    }
  }

  const handleTest = async (id: number) => {
    setTestingId(id)
    setTestResult(null)
    try {
      const res = await testSsoProvider(id)
      setTestResult({ id, ok: res.success ?? true, message: res.message ?? 'Connection successful' })
    } catch {
      setTestResult({ id, ok: false, message: 'Connection failed' })
    } finally {
      setTestingId(null)
    }
  }

  return (
    <div className="space-y-8">
      <section>
        <h2 className="text-lg font-semibold mb-3">SSO Providers</h2>
        <p className="text-sm text-muted-foreground mb-4">
          Configure SAML2 or OIDC single sign-on providers for your organization.
        </p>

        <div className="bg-card elevation-1 p-4 space-y-4">
          {!showForm && (
            <Button size="sm" onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4 mr-1.5" />
              Add Provider
            </Button>
          )}

          {showForm && (
            <div className="border border-border p-4 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">{editingId ? 'Edit Provider' : 'New Provider'}</span>
                <Button variant="ghost" size="sm" onClick={resetForm}>
                  <X className="h-4 w-4" />
                </Button>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  placeholder="Provider name"
                  className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <select
                  value={form.type}
                  onChange={(e) => setForm({ ...form, type: e.target.value as 'saml' | 'oidc' })}
                  className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                >
                  <option value="saml">SAML2</option>
                  <option value="oidc">OIDC</option>
                </select>
                <input
                  type="text"
                  value={form.metadata_url}
                  onChange={(e) => setForm({ ...form, metadata_url: e.target.value })}
                  placeholder="Metadata URL"
                  className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring col-span-2 font-mono"
                />
                <input
                  type="text"
                  value={form.client_id}
                  onChange={(e) => setForm({ ...form, client_id: e.target.value })}
                  placeholder="Client ID"
                  className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <input
                  type="password"
                  value={form.client_secret}
                  onChange={(e) => setForm({ ...form, client_secret: e.target.value })}
                  placeholder={editingId ? 'Client secret (leave blank to keep)' : 'Client secret'}
                  className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <input
                  type="text"
                  value={form.allowed_domains}
                  onChange={(e) => setForm({ ...form, allowed_domains: e.target.value })}
                  placeholder="Allowed domains (comma-separated)"
                  className="px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring col-span-2"
                />
              </div>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                />
                Enabled
              </label>
              <div className="flex gap-2">
                <Button size="sm" onClick={handleSave} disabled={saving || !form.name.trim()}>
                  {saving ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Save className="h-4 w-4 mr-1.5" />}
                  {editingId ? 'Update' : 'Create'}
                </Button>
                <Button variant="ghost" size="sm" onClick={resetForm}>
                  Cancel
                </Button>
              </div>
            </div>
          )}

          {loading ? (
            <div className="flex items-center justify-center py-4">
              <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
            </div>
          ) : providers.length === 0 ? (
            <p className="text-sm text-muted-foreground py-2">No SSO providers configured yet.</p>
          ) : (
            <div className="space-y-1">
              {providers.map((p) => (
                <div
                  key={p.id}
                  className="flex items-center justify-between py-2 border-b border-border last:border-0"
                >
                  <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                      <p className="text-sm font-medium">{p.name}</p>
                      <span className="text-[10px] px-1.5 py-0.5 bg-primary/10 text-primary font-medium uppercase rounded">
                        {p.type === 'saml' ? 'SAML2' : 'OIDC'}
                      </span>
                      <span
                        className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${
                          p.is_active
                            ? 'bg-green-500/10 text-green-500'
                            : 'bg-muted text-muted-foreground'
                        }`}
                      >
                        {p.is_active ? 'Enabled' : 'Disabled'}
                      </span>
                    </div>
                    {p.allowed_domains.length > 0 && (
                      <p className="text-xs text-muted-foreground">
                        Domains: {p.allowed_domains.join(', ')}
                      </p>
                    )}
                    {testResult && testResult.id === p.id && (
                      <p className={`text-xs ${testResult.ok ? 'text-green-500' : 'text-destructive'}`}>
                        {testResult.ok ? <CheckCircle className="h-3 w-3 inline mr-1" /> : <XCircle className="h-3 w-3 inline mr-1" />}
                        {testResult.message}
                      </p>
                    )}
                  </div>
                  <div className="flex items-center gap-1">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => handleTest(p.id)}
                      disabled={testingId === p.id}
                      title="Test connection"
                    >
                      {testingId === p.id ? (
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      ) : (
                        <FlaskConical className="h-3.5 w-3.5" />
                      )}
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => handleToggle(p)} title="Toggle enabled">
                      {p.is_active ? (
                        <Shield className="h-3.5 w-3.5 text-green-500" />
                      ) : (
                        <ShieldOff className="h-3.5 w-3.5 text-muted-foreground" />
                      )}
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => startEdit(p)} title="Edit">
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => handleDelete(p.id)} title="Delete">
                      <Trash2 className="h-3.5 w-3.5 text-destructive" />
                    </Button>
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

// --- Content Policies Panel --------------------------------------------------

function ContentPoliciesPanel() {
  // TODO: get orgId from auth context once available
  const orgId = 1

  const [policies, setPolicies] = useState<ContentPolicy[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)

  const emptyForm = {
    name: '',
    description: '',
    rules: '[]',
    is_active: true,
  }
  const [form, setForm] = useState(emptyForm)

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchContentPolicies(orgId)
      setPolicies(data)
    } catch {
      /* handled */
    } finally {
      setLoading(false)
    }
  }, [orgId])

  useEffect(() => {
    load()
  }, [load])

  const resetForm = () => {
    setForm(emptyForm)
    setEditingId(null)
    setShowForm(false)
  }

  const startEdit = (p: ContentPolicy) => {
    setForm({
      name: p.name,
      description: p.description || '',
      rules: JSON.stringify(p.rules, null, 2),
      is_active: p.is_active,
    })
    setEditingId(p.id)
    setShowForm(true)
  }

  const handleSave = async () => {
    let parsedRules: ContentPolicy['rules']
    try {
      parsedRules = JSON.parse(form.rules)
    } catch {
      return
    }
    setSaving(true)
    try {
      const payload: Partial<ContentPolicy> = {
        name: form.name,
        description: form.description || null,
        rules: parsedRules,
        is_active: form.is_active,
      }

      if (editingId) {
        const updated = await updateContentPolicy(editingId, payload)
        setPolicies((prev) => prev.map((p) => (p.id === editingId ? updated : p)))
      } else {
        const created = await createContentPolicy(orgId, payload)
        setPolicies((prev) => [...prev, created])
      }
      resetForm()
    } catch {
      /* handled */
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (id: number) => {
    try {
      await deleteContentPolicy(id)
      setPolicies((prev) => prev.filter((p) => p.id !== id))
    } catch {
      /* handled */
    }
  }

  const handleToggle = async (p: ContentPolicy) => {
    try {
      const updated = await updateContentPolicy(p.id, { is_active: !p.is_active })
      setPolicies((prev) => prev.map((x) => (x.id === p.id ? updated : x)))
    } catch {
      /* handled */
    }
  }

  return (
    <div className="space-y-8">
      <section>
        <h2 className="text-lg font-semibold mb-3">Content Policies</h2>
        <p className="text-sm text-muted-foreground mb-4">
          Define rules that govern what content is allowed in skills and agent outputs.
        </p>

        <div className="bg-card elevation-1 p-4 space-y-4">
          {!showForm && (
            <Button size="sm" onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4 mr-1.5" />
              Add Policy
            </Button>
          )}

          {showForm && (
            <div className="border border-border p-4 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">{editingId ? 'Edit Policy' : 'New Policy'}</span>
                <Button variant="ghost" size="sm" onClick={resetForm}>
                  <X className="h-4 w-4" />
                </Button>
              </div>
              <div className="space-y-3">
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  placeholder="Policy name"
                  className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <input
                  type="text"
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  placeholder="Description (optional)"
                  className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <div>
                  <label className="text-xs text-muted-foreground mb-1 block">Rules (JSON array)</label>
                  <textarea
                    value={form.rules}
                    onChange={(e) => setForm({ ...form, rules: e.target.value })}
                    placeholder='[{"type": "keyword_block", "config": {"keywords": ["secret"]}}]'
                    rows={4}
                    className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
                  />
                </div>
              </div>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                />
                Enabled
              </label>
              <div className="flex gap-2">
                <Button size="sm" onClick={handleSave} disabled={saving || !form.name.trim()}>
                  {saving ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Save className="h-4 w-4 mr-1.5" />}
                  {editingId ? 'Update' : 'Create'}
                </Button>
                <Button variant="ghost" size="sm" onClick={resetForm}>
                  Cancel
                </Button>
              </div>
            </div>
          )}

          {loading ? (
            <div className="flex items-center justify-center py-4">
              <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
            </div>
          ) : policies.length === 0 ? (
            <p className="text-sm text-muted-foreground py-2">No content policies defined yet.</p>
          ) : (
            <div className="space-y-1">
              {policies.map((p) => (
                <div
                  key={p.id}
                  className="flex items-center justify-between py-2 border-b border-border last:border-0"
                >
                  <div className="space-y-0.5">
                    <div className="flex items-center gap-2">
                      <p className="text-sm font-medium">{p.name}</p>
                      <span className="text-[10px] px-1.5 py-0.5 bg-primary/10 text-primary font-medium rounded">
                        {p.rules.length} rule{p.rules.length !== 1 ? 's' : ''}
                      </span>
                      <span
                        className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${
                          p.is_active
                            ? 'bg-green-500/10 text-green-500'
                            : 'bg-muted text-muted-foreground'
                        }`}
                      >
                        {p.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </div>
                    {p.description && (
                      <p className="text-xs text-muted-foreground">{p.description}</p>
                    )}
                  </div>
                  <div className="flex items-center gap-1">
                    <Button variant="ghost" size="sm" onClick={() => handleToggle(p)} title="Toggle active">
                      {p.is_active ? (
                        <Shield className="h-3.5 w-3.5 text-green-500" />
                      ) : (
                        <ShieldOff className="h-3.5 w-3.5 text-muted-foreground" />
                      )}
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => startEdit(p)} title="Edit">
                      <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="sm" onClick={() => handleDelete(p.id)} title="Delete">
                      <Trash2 className="h-3.5 w-3.5 text-destructive" />
                    </Button>
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
        {/* License panel removed — open source */}
        {activeTab === 'agents' && <AgentsPanel />}
        {activeTab === 'library' && <LibraryPanel />}
        {activeTab === 'tags' && <TagsPanel />}
        {activeTab === 'users' && <UsersPanel />}
        {activeTab === 'organizations' && <OrganizationsPanel />}
        {activeTab === 'sso' && <SsoPanel />}
        {activeTab === 'content-policies' && <ContentPoliciesPanel />}
        {activeTab === 'infrastructure' && <InfrastructurePanel />}
        {activeTab === 'backups' && <BackupsPanel />}
        {activeTab === 'diagnostics' && <DiagnosticsPanel />}
      </div>
    </div>
  )
}
