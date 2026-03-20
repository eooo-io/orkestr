import { useEffect, useState, useCallback } from 'react'
import {
  Puzzle,
  Plus,
  Trash2,
  ChevronDown,
  ChevronRight,
  Wrench,
  Boxes,
  PanelLeft,
  Server,
  Layers,
  Upload,
  X,
  AlertCircle,
  CheckCircle,
  Settings,
  Anchor,
} from 'lucide-react'
import { useAppStore } from '@/store/useAppStore'
import { useConfirm } from '@/hooks/useConfirm'
import api from '@/api/client'

// -------------------------------------------------------------------
// Types
// -------------------------------------------------------------------

interface PluginHook {
  id: number
  hook_name: string
  handler: string
  priority: number
  enabled: boolean
}

interface ConfigSchemaProperty {
  type: string
  description?: string
  default?: unknown
  enum?: string[]
}

interface ConfigSchema {
  properties?: Record<string, ConfigSchemaProperty>
  required?: string[]
}

interface Plugin {
  id: number
  uuid: string
  name: string
  slug: string
  description: string | null
  version: string
  author: string | null
  type: 'tool' | 'node' | 'panel' | 'provider' | 'composite'
  manifest: Record<string, unknown>
  entry_point: string
  config: Record<string, unknown> | null
  enabled: boolean
  installed_at: string | null
  created_at: string
  updated_at: string
  hooks: PluginHook[]
}

// -------------------------------------------------------------------
// Constants
// -------------------------------------------------------------------

const TYPE_ICONS: Record<string, React.ReactNode> = {
  tool: <Wrench className="h-4 w-4" />,
  node: <Boxes className="h-4 w-4" />,
  panel: <PanelLeft className="h-4 w-4" />,
  provider: <Server className="h-4 w-4" />,
  composite: <Layers className="h-4 w-4" />,
}

const TYPE_COLORS: Record<string, string> = {
  tool: 'bg-blue-500/10 text-blue-600',
  node: 'bg-purple-500/10 text-purple-600',
  panel: 'bg-amber-500/10 text-amber-600',
  provider: 'bg-green-500/10 text-green-600',
  composite: 'bg-cyan-500/10 text-cyan-600',
}

const TABS = ['all', 'tool', 'node', 'panel', 'provider', 'composite'] as const

// -------------------------------------------------------------------
// API helpers
// -------------------------------------------------------------------

async function fetchPlugins(type?: string): Promise<Plugin[]> {
  const params: Record<string, string> = {}
  if (type && type !== 'all') params.type = type
  const res = await api.get('/plugins', { params })
  return res.data.data
}

async function installPlugin(manifest: Record<string, unknown>): Promise<Plugin> {
  const res = await api.post('/plugins', { manifest })
  return res.data.data
}

async function updatePluginConfig(id: number, config: Record<string, unknown>): Promise<Plugin> {
  const res = await api.put(`/plugins/${id}`, { config })
  return res.data.data
}

async function enablePlugin(id: number): Promise<Plugin> {
  const res = await api.post(`/plugins/${id}/enable`)
  return res.data.data
}

async function disablePlugin(id: number): Promise<Plugin> {
  const res = await api.post(`/plugins/${id}/disable`)
  return res.data.data
}

async function deletePlugin(id: number): Promise<void> {
  await api.delete(`/plugins/${id}`)
}

// -------------------------------------------------------------------
// Install Modal
// -------------------------------------------------------------------

function InstallModal({
  open,
  onClose,
  onInstalled,
}: {
  open: boolean
  onClose: () => void
  onInstalled: (p: Plugin) => void
}) {
  const [json, setJson] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)
  const [preview, setPreview] = useState<Record<string, unknown> | null>(null)

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    const reader = new FileReader()
    reader.onload = (ev) => {
      const text = ev.target?.result
      if (typeof text === 'string') {
        setJson(text)
        tryParse(text)
      }
    }
    reader.readAsText(file)
  }

  const tryParse = (text: string) => {
    try {
      const parsed = JSON.parse(text)
      setPreview(parsed)
      setError(null)
    } catch {
      setPreview(null)
      setError('Invalid JSON')
    }
  }

  const handleJsonChange = (text: string) => {
    setJson(text)
    if (text.trim()) {
      tryParse(text)
    } else {
      setPreview(null)
      setError(null)
    }
  }

  const handleInstall = async () => {
    if (!preview) return
    setLoading(true)
    setError(null)
    try {
      const plugin = await installPlugin(preview)
      onInstalled(plugin)
      setJson('')
      setPreview(null)
      onClose()
    } catch (err: unknown) {
      const msg =
        err && typeof err === 'object' && 'response' in err
          ? (err as { response?: { data?: { message?: string } } }).response?.data?.message
          : undefined
      setError(msg || 'Failed to install plugin')
    } finally {
      setLoading(false)
    }
  }

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-card border border-border rounded-lg shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-border">
          <h2 className="text-base font-semibold">Install Plugin</h2>
          <button onClick={onClose} className="text-muted-foreground hover:text-foreground">
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {/* File upload */}
          <div>
            <label className="flex items-center gap-2 text-sm font-medium mb-1">
              <Upload className="h-4 w-4" /> Upload manifest.json
            </label>
            <input
              type="file"
              accept=".json"
              onChange={handleFileUpload}
              className="block w-full text-sm text-muted-foreground file:mr-4 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-primary file:text-primary-foreground hover:file:bg-primary/90 cursor-pointer"
            />
          </div>

          <div className="relative text-center">
            <span className="text-xs text-muted-foreground bg-card px-2 relative z-10">or paste JSON</span>
            <div className="absolute inset-x-0 top-1/2 h-px bg-border" />
          </div>

          {/* JSON textarea */}
          <textarea
            value={json}
            onChange={(e) => handleJsonChange(e.target.value)}
            placeholder='{"name": "my-plugin", "version": "1.0.0", "type": "tool", "entry_point": "https://...", ...}'
            className="w-full h-48 text-sm font-mono bg-muted/30 border border-input rounded-md p-3 resize-none focus:outline-none focus:ring-2 focus:ring-primary/50"
          />

          {error && (
            <div className="flex items-center gap-2 text-sm text-red-500">
              <AlertCircle className="h-4 w-4 shrink-0" />
              {error}
            </div>
          )}

          {/* Preview */}
          {preview && !error && (
            <div className="rounded-md border border-border bg-muted/20 p-3 space-y-2">
              <div className="flex items-center gap-2 text-sm text-green-600">
                <CheckCircle className="h-4 w-4" /> Valid manifest
              </div>
              <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                <div>
                  <span className="text-muted-foreground">Name:</span>{' '}
                  <span className="font-medium">{preview.name as string}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">Version:</span>{' '}
                  <span className="font-medium">{preview.version as string}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">Type:</span>{' '}
                  <span className="font-medium capitalize">{preview.type as string}</span>
                </div>
                {preview.author && (
                  <div>
                    <span className="text-muted-foreground">Author:</span>{' '}
                    <span className="font-medium">{preview.author as string}</span>
                  </div>
                )}
              </div>
              {preview.description && (
                <p className="text-sm text-muted-foreground">{preview.description as string}</p>
              )}
              {Array.isArray(preview.hooks) && preview.hooks.length > 0 && (
                <div className="text-xs text-muted-foreground">
                  {preview.hooks.length} hook{preview.hooks.length !== 1 ? 's' : ''} registered
                </div>
              )}
              {Array.isArray(preview.tools) && preview.tools.length > 0 && (
                <div className="text-xs text-muted-foreground">
                  {preview.tools.length} tool{preview.tools.length !== 1 ? 's' : ''} provided
                </div>
              )}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 p-4 border-t border-border">
          <button
            onClick={onClose}
            className="px-4 py-2 text-sm rounded-md border border-input hover:bg-muted"
          >
            Cancel
          </button>
          <button
            onClick={handleInstall}
            disabled={!preview || !!error || loading}
            className="px-4 py-2 text-sm rounded-md bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {loading ? 'Installing...' : 'Install'}
          </button>
        </div>
      </div>
    </div>
  )
}

// -------------------------------------------------------------------
// Config Form (generated from manifest.config_schema)
// -------------------------------------------------------------------

function ConfigForm({
  schema,
  config,
  onSave,
}: {
  schema: ConfigSchema | null
  config: Record<string, unknown> | null
  onSave: (config: Record<string, unknown>) => void
}) {
  const properties = schema?.properties ?? {}
  const keys = Object.keys(properties)
  const [values, setValues] = useState<Record<string, unknown>>(config ?? {})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    setValues(config ?? {})
  }, [config])

  if (keys.length === 0) {
    return <p className="text-sm text-muted-foreground">This plugin has no configurable settings.</p>
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      await onSave(values)
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="space-y-3">
      {keys.map((key) => {
        const prop = properties[key]
        const value = values[key] ?? prop.default ?? ''

        if (prop.type === 'boolean') {
          return (
            <label key={key} className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={!!value}
                onChange={(e) => setValues({ ...values, [key]: e.target.checked })}
                className="rounded border-input"
              />
              <span className="font-medium">{key}</span>
              {prop.description && (
                <span className="text-muted-foreground">-- {prop.description}</span>
              )}
            </label>
          )
        }

        if (prop.enum) {
          return (
            <div key={key}>
              <label className="block text-sm font-medium mb-1">{key}</label>
              {prop.description && (
                <p className="text-xs text-muted-foreground mb-1">{prop.description}</p>
              )}
              <select
                value={String(value)}
                onChange={(e) => setValues({ ...values, [key]: e.target.value })}
                className="w-full text-sm border border-input bg-background rounded px-2 py-1.5"
              >
                {prop.enum.map((opt) => (
                  <option key={opt} value={opt}>
                    {opt}
                  </option>
                ))}
              </select>
            </div>
          )
        }

        if (prop.type === 'number' || prop.type === 'integer') {
          return (
            <div key={key}>
              <label className="block text-sm font-medium mb-1">{key}</label>
              {prop.description && (
                <p className="text-xs text-muted-foreground mb-1">{prop.description}</p>
              )}
              <input
                type="number"
                value={value as number}
                onChange={(e) => setValues({ ...values, [key]: Number(e.target.value) })}
                className="w-full text-sm border border-input bg-background rounded px-2 py-1.5"
              />
            </div>
          )
        }

        return (
          <div key={key}>
            <label className="block text-sm font-medium mb-1">{key}</label>
            {prop.description && (
              <p className="text-xs text-muted-foreground mb-1">{prop.description}</p>
            )}
            <input
              type="text"
              value={String(value)}
              onChange={(e) => setValues({ ...values, [key]: e.target.value })}
              className="w-full text-sm border border-input bg-background rounded px-2 py-1.5"
            />
          </div>
        )
      })}

      <button
        onClick={handleSave}
        disabled={saving}
        className="px-4 py-1.5 text-sm rounded-md bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
      >
        {saving ? 'Saving...' : 'Save Config'}
      </button>
    </div>
  )
}

// -------------------------------------------------------------------
// Plugin Card
// -------------------------------------------------------------------

function PluginCard({
  plugin,
  onToggle,
  onDelete,
  onConfigSave,
}: {
  plugin: Plugin
  onToggle: (p: Plugin) => void
  onDelete: (p: Plugin) => void
  onConfigSave: (p: Plugin, config: Record<string, unknown>) => void
}) {
  const [expanded, setExpanded] = useState(false)

  const schema = (plugin.manifest.config_schema as ConfigSchema) ?? null

  return (
    <div className="border border-border rounded-lg bg-card elevation-1 overflow-hidden">
      {/* Card header */}
      <div className="p-4">
        <div className="flex items-start justify-between">
          <div className="flex items-start gap-3 min-w-0">
            <div className={`p-2 rounded-md ${TYPE_COLORS[plugin.type] ?? 'bg-muted'}`}>
              {TYPE_ICONS[plugin.type] ?? <Puzzle className="h-4 w-4" />}
            </div>
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <h3 className="text-sm font-semibold truncate">{plugin.name}</h3>
                <span className="text-[10px] text-muted-foreground">v{plugin.version}</span>
              </div>
              {plugin.author && (
                <p className="text-xs text-muted-foreground">by {plugin.author}</p>
              )}
              {plugin.description && (
                <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
                  {plugin.description}
                </p>
              )}
              <div className="flex items-center gap-2 mt-2">
                <span
                  className={`text-[10px] px-1.5 py-0.5 rounded font-medium capitalize ${TYPE_COLORS[plugin.type]}`}
                >
                  {plugin.type}
                </span>
                {plugin.hooks.length > 0 && (
                  <span className="text-[10px] text-muted-foreground flex items-center gap-0.5">
                    <Anchor className="h-3 w-3" /> {plugin.hooks.length} hook
                    {plugin.hooks.length !== 1 ? 's' : ''}
                  </span>
                )}
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2 shrink-0">
            {/* Enabled toggle */}
            <button
              onClick={() => onToggle(plugin)}
              className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${
                plugin.enabled ? 'bg-primary' : 'bg-muted'
              }`}
              title={plugin.enabled ? 'Disable' : 'Enable'}
            >
              <span
                className={`inline-block h-3.5 w-3.5 rounded-full bg-white transition-transform ${
                  plugin.enabled ? 'translate-x-4.5' : 'translate-x-0.5'
                }`}
              />
            </button>

            <button
              onClick={() => onDelete(plugin)}
              className="p-1 text-muted-foreground hover:text-destructive rounded"
              title="Uninstall"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Expand toggle */}
      <button
        onClick={() => setExpanded(!expanded)}
        className="w-full flex items-center gap-1 px-4 py-2 text-xs text-muted-foreground hover:bg-muted/30 border-t border-border transition-colors"
      >
        {expanded ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
        <Settings className="h-3 w-3" />
        Details & Configuration
      </button>

      {/* Expanded detail */}
      {expanded && (
        <div className="border-t border-border p-4 space-y-4 bg-muted/10">
          {/* Config form */}
          <div>
            <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
              Configuration
            </h4>
            <ConfigForm
              schema={schema}
              config={plugin.config}
              onSave={(cfg) => onConfigSave(plugin, cfg)}
            />
          </div>

          {/* Hooks list */}
          {plugin.hooks.length > 0 && (
            <div>
              <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">
                Registered Hooks
              </h4>
              <div className="space-y-1">
                {plugin.hooks.map((h) => (
                  <div
                    key={h.id}
                    className="flex items-center justify-between text-xs px-2 py-1.5 bg-muted/20 rounded"
                  >
                    <div className="flex items-center gap-2">
                      <Anchor className="h-3 w-3 text-muted-foreground" />
                      <span className="font-mono">{h.hook_name}</span>
                    </div>
                    <div className="flex items-center gap-2 text-muted-foreground">
                      <span>priority: {h.priority}</span>
                      <span
                        className={`w-1.5 h-1.5 rounded-full ${h.enabled ? 'bg-green-500' : 'bg-muted-foreground'}`}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Entry point */}
          <div>
            <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">
              Entry Point
            </h4>
            <p className="text-xs font-mono text-muted-foreground break-all">{plugin.entry_point}</p>
          </div>

          {/* Installed date */}
          {plugin.installed_at && (
            <p className="text-[10px] text-muted-foreground">
              Installed {new Date(plugin.installed_at).toLocaleString()}
            </p>
          )}
        </div>
      )}
    </div>
  )
}

// -------------------------------------------------------------------
// Main Page
// -------------------------------------------------------------------

export function Plugins() {
  const [plugins, setPlugins] = useState<Plugin[]>([])
  const [loading, setLoading] = useState(true)
  const [activeTab, setActiveTab] = useState<(typeof TABS)[number]>('all')
  const [installOpen, setInstallOpen] = useState(false)
  const confirm = useConfirm()
  const { showToast } = useAppStore()

  const load = useCallback(() => {
    setLoading(true)
    fetchPlugins(activeTab)
      .then(setPlugins)
      .catch(() => showToast('Failed to load plugins', 'error'))
      .finally(() => setLoading(false))
  }, [activeTab, showToast])

  useEffect(() => {
    load()
  }, [load])

  const handleToggle = async (plugin: Plugin) => {
    try {
      if (plugin.enabled) {
        await disablePlugin(plugin.id)
        showToast(`${plugin.name} disabled`)
      } else {
        await enablePlugin(plugin.id)
        showToast(`${plugin.name} enabled`)
      }
      load()
    } catch {
      showToast('Toggle failed', 'error')
    }
  }

  const handleDelete = async (plugin: Plugin) => {
    if (
      !(await confirm({
        title: 'Uninstall Plugin',
        message: `Uninstall "${plugin.name}"? This removes all hooks and configuration.`,
      }))
    )
      return
    try {
      await deletePlugin(plugin.id)
      showToast(`${plugin.name} uninstalled`)
      load()
    } catch {
      showToast('Failed to uninstall', 'error')
    }
  }

  const handleConfigSave = async (plugin: Plugin, config: Record<string, unknown>) => {
    try {
      await updatePluginConfig(plugin.id, config)
      showToast('Configuration saved')
      load()
    } catch {
      showToast('Failed to save config', 'error')
    }
  }

  const handleInstalled = (_p: Plugin) => {
    showToast('Plugin installed')
    load()
  }

  if (loading && plugins.length === 0) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-pulse text-muted-foreground">Loading plugins...</div>
      </div>
    )
  }

  return (
    <div className="flex flex-col h-screen">
      {/* Header */}
      <div className="p-6 border-b border-border">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-semibold flex items-center gap-2">
              <Puzzle className="h-5 w-5" /> Plugins
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Extend capabilities with tools, custom nodes, panels, and provider integrations.
            </p>
          </div>
          <button
            onClick={() => setInstallOpen(true)}
            className="flex items-center gap-1.5 px-4 py-2 text-sm rounded-md bg-primary text-primary-foreground hover:bg-primary/90"
          >
            <Plus className="h-4 w-4" /> Install Plugin
          </button>
        </div>

        {/* Type filter tabs */}
        <div className="flex items-center gap-1 mt-4">
          {TABS.map((tab) => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`px-3 py-1.5 text-xs rounded-md capitalize transition-colors ${
                activeTab === tab
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:bg-muted'
              }`}
            >
              {tab === 'all' ? 'All' : tab}
            </button>
          ))}
        </div>
      </div>

      {/* Plugin grid */}
      <div className="flex-1 overflow-y-auto p-6">
        {plugins.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-center">
            <Puzzle className="h-12 w-12 text-muted-foreground/30 mb-3" />
            <h3 className="text-sm font-medium">No plugins installed</h3>
            <p className="text-sm text-muted-foreground mt-1 max-w-md">
              Plugins extend your workspace with custom tools, workflow nodes, UI panels, and
              provider integrations. Click "Install Plugin" to get started.
            </p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {plugins.map((plugin) => (
              <PluginCard
                key={plugin.id}
                plugin={plugin}
                onToggle={handleToggle}
                onDelete={handleDelete}
                onConfigSave={handleConfigSave}
              />
            ))}
          </div>
        )}
      </div>

      {/* Install modal */}
      <InstallModal
        open={installOpen}
        onClose={() => setInstallOpen(false)}
        onInstalled={handleInstalled}
      />
    </div>
  )
}
