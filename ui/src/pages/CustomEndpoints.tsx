import { useState, useEffect } from 'react'
import {
  Server,
  Plus,
  Trash2,
  RefreshCw,
  Loader2,
  CheckCircle,
  XCircle,
  AlertTriangle,
  Wifi,
  WifiOff,
  Eye,
  EyeOff,
} from 'lucide-react'
import {
  fetchCustomEndpoints,
  createCustomEndpoint,
  updateCustomEndpoint,
  deleteCustomEndpoint,
  checkCustomEndpointHealth,
  discoverCustomEndpointModels,
} from '@/api/client'
import type { CustomEndpoint } from '@/types'
import { Button } from '@/components/ui/button'

function HealthDot({ status }: { status: string | null }) {
  if (!status) return <span className="h-2.5 w-2.5 rounded-full bg-muted-foreground/30 inline-block" />
  if (status === 'healthy') return <span className="h-2.5 w-2.5 rounded-full bg-green-500 inline-block" />
  if (status === 'degraded') return <span className="h-2.5 w-2.5 rounded-full bg-yellow-500 inline-block" />
  return <span className="h-2.5 w-2.5 rounded-full bg-red-500 inline-block" />
}

function HealthLabel({ status }: { status: string | null }) {
  if (!status) return <span className="text-xs text-muted-foreground">Unknown</span>
  if (status === 'healthy')
    return (
      <span className="flex items-center gap-1 text-xs text-green-500">
        <CheckCircle className="h-3.5 w-3.5" /> Healthy
      </span>
    )
  if (status === 'degraded')
    return (
      <span className="flex items-center gap-1 text-xs text-yellow-500">
        <AlertTriangle className="h-3.5 w-3.5" /> Degraded
      </span>
    )
  return (
    <span className="flex items-center gap-1 text-xs text-red-500">
      <XCircle className="h-3.5 w-3.5" /> Down
    </span>
  )
}

export function CustomEndpoints() {
  const [endpoints, setEndpoints] = useState<CustomEndpoint[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreate, setShowCreate] = useState(false)

  // Create form state
  const [createName, setCreateName] = useState('')
  const [createUrl, setCreateUrl] = useState('')
  const [createKey, setCreateKey] = useState('')
  const [createModels, setCreateModels] = useState('')
  const [creating, setCreating] = useState(false)
  const [showCreateKey, setShowCreateKey] = useState(false)

  // Inline edit state
  const [editId, setEditId] = useState<number | null>(null)
  const [editName, setEditName] = useState('')
  const [editUrl, setEditUrl] = useState('')
  const [editKey, setEditKey] = useState('')
  const [editModels, setEditModels] = useState('')
  const [saving, setSaving] = useState(false)
  const [showEditKey, setShowEditKey] = useState(false)

  // Per-endpoint loading states
  const [healthChecking, setHealthChecking] = useState<Set<number>>(new Set())
  const [discovering, setDiscovering] = useState<Set<number>>(new Set())
  const [deleting, setDeleting] = useState<Set<number>>(new Set())

  const loadEndpoints = () => {
    setLoading(true)
    fetchCustomEndpoints()
      .then(setEndpoints)
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadEndpoints()
  }, [])

  const handleCreate = async () => {
    if (!createName.trim() || !createUrl.trim()) return
    setCreating(true)
    try {
      const models = createModels
        .split(',')
        .map((m) => m.trim())
        .filter(Boolean)
      const created = await createCustomEndpoint({
        name: createName.trim(),
        base_url: createUrl.trim(),
        api_key: createKey || undefined,
        models: models.length > 0 ? models : undefined,
      })
      setEndpoints((prev) => [...prev, created])
      setCreateName('')
      setCreateUrl('')
      setCreateKey('')
      setCreateModels('')
      setShowCreate(false)
    } finally {
      setCreating(false)
    }
  }

  const startEdit = (ep: CustomEndpoint) => {
    setEditId(ep.id)
    setEditName(ep.name)
    setEditUrl(ep.base_url)
    setEditKey('')
    setEditModels(ep.models.join(', '))
    setShowEditKey(false)
  }

  const cancelEdit = () => {
    setEditId(null)
  }

  const handleSaveEdit = async () => {
    if (editId === null) return
    setSaving(true)
    try {
      const models = editModels
        .split(',')
        .map((m) => m.trim())
        .filter(Boolean)
      const updated = await updateCustomEndpoint(editId, {
        name: editName.trim(),
        base_url: editUrl.trim(),
        models,
        ...(editKey ? { api_key: editKey } : {}),
      })
      setEndpoints((prev) => prev.map((ep) => (ep.id === editId ? updated : ep)))
      setEditId(null)
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (id: number) => {
    setDeleting((prev) => new Set(prev).add(id))
    try {
      await deleteCustomEndpoint(id)
      setEndpoints((prev) => prev.filter((ep) => ep.id !== id))
      if (editId === id) setEditId(null)
    } finally {
      setDeleting((prev) => {
        const next = new Set(prev)
        next.delete(id)
        return next
      })
    }
  }

  const handleHealthCheck = async (id: number) => {
    setHealthChecking((prev) => new Set(prev).add(id))
    try {
      const result = await checkCustomEndpointHealth(id)
      setEndpoints((prev) =>
        prev.map((ep) =>
          ep.id === id
            ? {
                ...ep,
                health_status: result.data?.status ?? result.status ?? ep.health_status,
                avg_latency_ms: result.data?.latency_ms ?? result.latency_ms ?? ep.avg_latency_ms,
                last_health_check: new Date().toISOString(),
              }
            : ep,
        ),
      )
    } finally {
      setHealthChecking((prev) => {
        const next = new Set(prev)
        next.delete(id)
        return next
      })
    }
  }

  const handleDiscover = async (id: number) => {
    setDiscovering((prev) => new Set(prev).add(id))
    try {
      const models = await discoverCustomEndpointModels(id)
      setEndpoints((prev) => prev.map((ep) => (ep.id === id ? { ...ep, models } : ep)))
      if (editId === id) {
        setEditModels(models.join(', '))
      }
    } finally {
      setDiscovering((prev) => {
        const next = new Set(prev)
        next.delete(id)
        return next
      })
    }
  }

  const handleToggleEnabled = async (ep: CustomEndpoint) => {
    const updated = await updateCustomEndpoint(ep.id, { enabled: !ep.enabled })
    setEndpoints((prev) => prev.map((e) => (e.id === ep.id ? updated : e)))
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="p-4 md:p-6 max-w-4xl">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
            <Server className="h-6 w-6" />
            Custom Endpoints
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Manage OpenAI-compatible endpoints (vLLM, TGI, LM Studio, etc.)
          </p>
        </div>
        <Button size="sm" onClick={() => setShowCreate(!showCreate)}>
          <Plus className="h-4 w-4 mr-1.5" />
          Add Endpoint
        </Button>
      </div>

      {/* Create Form */}
      {showCreate && (
        <section className="mb-6">
          <div className="bg-card elevation-1 p-4 space-y-4">
            <h3 className="text-sm font-semibold">New Endpoint</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Name</label>
                <input
                  type="text"
                  value={createName}
                  onChange={(e) => setCreateName(e.target.value)}
                  placeholder="My vLLM Server"
                  className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Base URL</label>
                <input
                  type="text"
                  value={createUrl}
                  onChange={(e) => setCreateUrl(e.target.value)}
                  placeholder="http://localhost:8080/v1"
                  className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
                />
              </div>
            </div>
            <div className="space-y-1.5">
              <label className="text-sm font-medium">API Key (optional)</label>
              <div className="relative">
                <input
                  type={showCreateKey ? 'text' : 'password'}
                  value={createKey}
                  onChange={(e) => setCreateKey(e.target.value)}
                  placeholder="sk-..."
                  className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring pr-10 font-mono"
                />
                <button
                  type="button"
                  onClick={() => setShowCreateKey(!showCreateKey)}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-all duration-150"
                >
                  {showCreateKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Models (comma-separated)</label>
              <input
                type="text"
                value={createModels}
                onChange={(e) => setCreateModels(e.target.value)}
                placeholder="meta-llama/Llama-3-8B, mistralai/Mistral-7B"
                className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
              />
              <p className="text-[11px] text-muted-foreground">
                Leave blank and use Discover Models after creation to auto-detect available models.
              </p>
            </div>
            <div className="flex items-center gap-2 pt-1">
              <Button size="sm" onClick={handleCreate} disabled={creating || !createName.trim() || !createUrl.trim()}>
                {creating ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <Plus className="h-4 w-4 mr-1.5" />}
                Create
              </Button>
              <Button
                size="sm"
                variant="ghost"
                onClick={() => {
                  setShowCreate(false)
                  setCreateName('')
                  setCreateUrl('')
                  setCreateKey('')
                  setCreateModels('')
                }}
              >
                Cancel
              </Button>
            </div>
          </div>
        </section>
      )}

      {/* Endpoints List */}
      {endpoints.length === 0 ? (
        <div className="bg-card elevation-1 p-8 text-center">
          <WifiOff className="h-10 w-10 text-muted-foreground/40 mx-auto mb-3" />
          <p className="text-sm text-muted-foreground">No custom endpoints configured.</p>
          <p className="text-xs text-muted-foreground mt-1">
            Add an OpenAI-compatible endpoint to use self-hosted models.
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {endpoints.map((ep) => (
            <div key={ep.id} className="bg-card elevation-1 p-4">
              {editId === ep.id ? (
                /* Inline Edit Mode */
                <div className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-1.5">
                      <label className="text-sm font-medium">Name</label>
                      <input
                        type="text"
                        value={editName}
                        onChange={(e) => setEditName(e.target.value)}
                        className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                      />
                    </div>
                    <div className="space-y-1.5">
                      <label className="text-sm font-medium">Base URL</label>
                      <input
                        type="text"
                        value={editUrl}
                        onChange={(e) => setEditUrl(e.target.value)}
                        className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
                      />
                    </div>
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-sm font-medium">API Key (leave blank to keep current)</label>
                    <div className="relative">
                      <input
                        type={showEditKey ? 'text' : 'password'}
                        value={editKey}
                        onChange={(e) => setEditKey(e.target.value)}
                        placeholder="********** (unchanged)"
                        className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring pr-10 font-mono"
                      />
                      <button
                        type="button"
                        onClick={() => setShowEditKey(!showEditKey)}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-all duration-150"
                      >
                        {showEditKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                      </button>
                    </div>
                  </div>
                  <div className="space-y-1.5">
                    <label className="text-sm font-medium">Models (comma-separated)</label>
                    <input
                      type="text"
                      value={editModels}
                      onChange={(e) => setEditModels(e.target.value)}
                      className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
                    />
                  </div>
                  <div className="flex items-center gap-2">
                    <Button size="sm" onClick={handleSaveEdit} disabled={saving}>
                      {saving ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : null}
                      Save
                    </Button>
                    <Button size="sm" variant="ghost" onClick={cancelEdit}>
                      Cancel
                    </Button>
                  </div>
                </div>
              ) : (
                /* Display Mode */
                <div>
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3 min-w-0">
                      <HealthDot status={ep.health_status} />
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <h3 className="text-sm font-semibold truncate">{ep.name}</h3>
                          {ep.enabled ? (
                            <span className="flex items-center gap-1 text-[11px] text-green-500">
                              <Wifi className="h-3 w-3" /> Enabled
                            </span>
                          ) : (
                            <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
                              <WifiOff className="h-3 w-3" /> Disabled
                            </span>
                          )}
                        </div>
                        <p className="text-xs font-mono text-muted-foreground truncate mt-0.5">{ep.base_url}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-1.5 shrink-0">
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => handleToggleEnabled(ep)}
                        title={ep.enabled ? 'Disable' : 'Enable'}
                      >
                        {ep.enabled ? <WifiOff className="h-4 w-4" /> : <Wifi className="h-4 w-4" />}
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => handleHealthCheck(ep.id)}
                        disabled={healthChecking.has(ep.id)}
                        title="Health Check"
                      >
                        {healthChecking.has(ep.id) ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <RefreshCw className="h-4 w-4" />
                        )}
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => handleDiscover(ep.id)}
                        disabled={discovering.has(ep.id)}
                        title="Discover Models"
                      >
                        {discovering.has(ep.id) ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <Server className="h-4 w-4" />
                        )}
                      </Button>
                      <Button size="sm" variant="ghost" onClick={() => startEdit(ep)} title="Edit">
                        Edit
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => handleDelete(ep.id)}
                        disabled={deleting.has(ep.id)}
                        className="text-destructive hover:text-destructive"
                        title="Delete"
                      >
                        {deleting.has(ep.id) ? (
                          <Loader2 className="h-4 w-4 animate-spin" />
                        ) : (
                          <Trash2 className="h-4 w-4" />
                        )}
                      </Button>
                    </div>
                  </div>

                  {/* Stats row */}
                  <div className="flex items-center gap-4 mt-3 pt-3 border-t border-border">
                    <div className="flex items-center gap-1.5">
                      <span className="text-xs text-muted-foreground">Status:</span>
                      <HealthLabel status={ep.health_status} />
                    </div>
                    <div className="flex items-center gap-1.5">
                      <span className="text-xs text-muted-foreground">Models:</span>
                      <span className="text-xs font-mono">{ep.models.length}</span>
                    </div>
                    {ep.avg_latency_ms !== null && (
                      <div className="flex items-center gap-1.5">
                        <span className="text-xs text-muted-foreground">Avg latency:</span>
                        <span className="text-xs font-mono">{ep.avg_latency_ms}ms</span>
                      </div>
                    )}
                    {ep.last_health_check && (
                      <div className="flex items-center gap-1.5">
                        <span className="text-xs text-muted-foreground">Last checked:</span>
                        <span className="text-xs text-muted-foreground">
                          {new Date(ep.last_health_check).toLocaleString()}
                        </span>
                      </div>
                    )}
                  </div>

                  {/* Models list */}
                  {ep.models.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      {ep.models.map((model) => (
                        <span
                          key={model}
                          className="inline-block px-2 py-0.5 text-[11px] font-mono bg-muted rounded"
                        >
                          {model}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
