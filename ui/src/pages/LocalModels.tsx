import { useState, useEffect, useMemo, useCallback } from 'react'
import {
  Cpu,
  RefreshCw,
  Loader2,
  Search,
  HardDrive,
  Server,
  Download,
  Trash2,
  X,
  CheckCircle,
  Lightbulb,
} from 'lucide-react'
import {
  fetchLocalModels,
  fetchOllamaModelDetail,
  pullOllamaModel,
  deleteOllamaModel,
  fetchPullingModels,
  fetchModelRecommendations,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import { Button } from '@/components/ui/button'
import type {
  LocalModel,
  OllamaModelDetail,
  ModelPullProgress,
  ModelRecommendation,
} from '@/types'

interface ActivePull {
  model: string
  status: string
  completed: number
  total: number
  error?: string
  done: boolean
}

const TASK_TYPES = [
  { value: 'chat', label: 'Chat' },
  { value: 'code', label: 'Code' },
  { value: 'summarization', label: 'Summarization' },
  { value: 'translation', label: 'Translation' },
  { value: 'analysis', label: 'Analysis' },
  { value: 'creative', label: 'Creative' },
]

export function LocalModels() {
  const confirm = useConfirm()
  const [models, setModels] = useState<LocalModel[]>([])
  const [loading, setLoading] = useState(true)
  const [query, setQuery] = useState('')
  const [expandedModel, setExpandedModel] = useState<string | null>(null)
  const [details, setDetails] = useState<Record<string, OllamaModelDetail>>({})
  const [detailLoading, setDetailLoading] = useState<string | null>(null)

  // Pull state
  const [showPullForm, setShowPullForm] = useState(false)
  const [pullModelName, setPullModelName] = useState('')
  const [activePulls, setActivePulls] = useState<Record<string, ActivePull>>({})
  const [deleting, setDeleting] = useState<string | null>(null)

  // Recommendations state
  const [selectedTaskType, setSelectedTaskType] = useState('chat')
  const [recommendations, setRecommendations] = useState<ModelRecommendation[]>([])
  const [recsLoading, setRecsLoading] = useState(false)

  const loadModels = () => {
    setLoading(true)
    fetchLocalModels()
      .then(setModels)
      .catch(() => setModels([]))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadModels()
    // Load any in-progress pulls
    fetchPullingModels()
      .then((pulling) => {
        const pulls: Record<string, ActivePull> = {}
        for (const p of pulling) {
          pulls[p.model] = {
            model: p.model,
            status: 'pulling',
            completed: 0,
            total: 0,
            done: false,
          }
        }
        setActivePulls(pulls)
      })
      .catch(() => {})
  }, [])

  // Load recommendations when task type changes
  useEffect(() => {
    setRecsLoading(true)
    fetchModelRecommendations(selectedTaskType)
      .then(setRecommendations)
      .catch(() => setRecommendations([]))
      .finally(() => setRecsLoading(false))
  }, [selectedTaskType])

  const filtered = useMemo(() => {
    if (!query) return models
    const q = query.toLowerCase()
    return models.filter(
      (m) =>
        m.name.toLowerCase().includes(q) ||
        m.provider.toLowerCase().includes(q)
    )
  }, [models, query])

  const grouped = useMemo(() => {
    const groups: Record<string, { source: 'ollama' | 'custom'; models: LocalModel[] }> = {}
    for (const model of filtered) {
      const key = model.source === 'ollama' ? 'Ollama' : model.provider
      if (!groups[key]) {
        groups[key] = { source: model.source, models: [] }
      }
      groups[key].models.push(model)
    }
    return groups
  }, [filtered])

  const handleExpand = async (model: LocalModel) => {
    if (model.source !== 'ollama') return

    const key = model.id
    if (expandedModel === key) {
      setExpandedModel(null)
      return
    }

    setExpandedModel(key)

    if (!details[key]) {
      setDetailLoading(key)
      try {
        const detail = await fetchOllamaModelDetail(model.name)
        setDetails((prev) => ({ ...prev, [key]: detail }))
      } catch {
        // Silently handle - detail section will show without extra info
      } finally {
        setDetailLoading(null)
      }
    }
  }

  const handlePull = useCallback(
    async (modelName: string) => {
      const name = modelName.trim()
      if (!name) return

      setActivePulls((prev) => ({
        ...prev,
        [name]: { model: name, status: 'starting', completed: 0, total: 0, done: false },
      }))
      setShowPullForm(false)
      setPullModelName('')

      try {
        await pullOllamaModel(name, (progress: ModelPullProgress) => {
          setActivePulls((prev) => ({
            ...prev,
            [name]: {
              model: name,
              status: progress.status,
              completed: progress.completed || 0,
              total: progress.total || 0,
              error: progress.error,
              done: progress.status === 'success' || !!progress.error,
            },
          }))
        })
        // Refresh model list after pull completes
        loadModels()
      } catch (err) {
        setActivePulls((prev) => ({
          ...prev,
          [name]: {
            ...prev[name],
            status: 'error',
            error: err instanceof Error ? err.message : 'Pull failed',
            done: true,
          },
        }))
      }
    },
    []
  )

  const handleDelete = async (modelName: string) => {
    if (!(await confirm({ message: `Delete model "${modelName}"? This cannot be undone.`, title: 'Confirm Delete' }))) return

    setDeleting(modelName)
    try {
      await deleteOllamaModel(modelName)
      loadModels()
    } catch {
      // Deletion failed silently
    } finally {
      setDeleting(null)
    }
  }

  const dismissPull = (model: string) => {
    setActivePulls((prev) => {
      const next = { ...prev }
      delete next[model]
      return next
    })
  }

  const activePullList = Object.values(activePulls)

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="w-6 h-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Cpu className="w-6 h-6 text-primary" />
          <h1 className="text-2xl font-bold">Local Models</h1>
        </div>
        <div className="flex items-center gap-2">
          <Button
            variant="default"
            size="sm"
            onClick={() => setShowPullForm(!showPullForm)}
          >
            <Download className="w-4 h-4 mr-2" />
            Pull Model
          </Button>
          <Button variant="outline" size="sm" onClick={loadModels} disabled={loading}>
            <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
            Refresh
          </Button>
        </div>
      </div>

      {/* Pull model form */}
      {showPullForm && (
        <div className="bg-card elevation-1 rounded-lg p-4 space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold">Pull Model from Ollama</h3>
            <button
              onClick={() => setShowPullForm(false)}
              className="text-muted-foreground hover:text-foreground"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
          <div className="flex gap-2">
            <input
              type="text"
              placeholder="e.g. llama3:8b, mistral:latest, codellama"
              value={pullModelName}
              onChange={(e) => setPullModelName(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') handlePull(pullModelName)
              }}
              className="flex-1 px-3 py-2 rounded-md border border-border bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <Button
              size="sm"
              onClick={() => handlePull(pullModelName)}
              disabled={!pullModelName.trim()}
            >
              <Download className="w-4 h-4 mr-2" />
              Pull
            </Button>
          </div>
          <p className="text-xs text-muted-foreground">
            Enter a model name from the{' '}
            <a
              href="https://ollama.com/library"
              target="_blank"
              rel="noopener noreferrer"
              className="text-primary underline"
            >
              Ollama library
            </a>
            . The model will be downloaded to your local Ollama instance.
          </p>
        </div>
      )}

      {/* Active pulls */}
      {activePullList.length > 0 && (
        <div className="space-y-2">
          <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide">
            Active Downloads
          </h3>
          {activePullList.map((pull) => (
            <div
              key={pull.model}
              className="bg-card elevation-1 rounded-lg p-4 space-y-2"
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  {pull.done && !pull.error ? (
                    <CheckCircle className="w-4 h-4 text-green-500" />
                  ) : pull.error ? (
                    <X className="w-4 h-4 text-destructive" />
                  ) : (
                    <Loader2 className="w-4 h-4 animate-spin text-primary" />
                  )}
                  <span className="font-mono text-sm font-medium">{pull.model}</span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-xs text-muted-foreground">{pull.status}</span>
                  {pull.done && (
                    <button
                      onClick={() => dismissPull(pull.model)}
                      className="text-muted-foreground hover:text-foreground"
                    >
                      <X className="w-3 h-3" />
                    </button>
                  )}
                </div>
              </div>
              {pull.error && (
                <p className="text-xs text-destructive">{pull.error}</p>
              )}
              {!pull.done && pull.total > 0 && (
                <div className="space-y-1">
                  <div className="w-full bg-muted rounded-full h-2">
                    <div
                      className="bg-primary h-2 rounded-full transition-all duration-300"
                      style={{
                        width: `${Math.min(100, (pull.completed / pull.total) * 100)}%`,
                      }}
                    />
                  </div>
                  <div className="flex justify-between text-xs text-muted-foreground">
                    <span>
                      {(pull.completed / 1048576).toFixed(1)} MB /{' '}
                      {(pull.total / 1048576).toFixed(1)} MB
                    </span>
                    <span>
                      {Math.round((pull.completed / pull.total) * 100)}%
                    </span>
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Search bar */}
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
        <input
          type="text"
          placeholder="Filter models by name or provider..."
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          className="w-full pl-10 pr-4 py-2 rounded-md border border-border bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
        />
      </div>

      {/* Empty state */}
      {models.length === 0 && (
        <div className="flex flex-col items-center justify-center py-16 text-center">
          <HardDrive className="w-12 h-12 text-muted-foreground mb-4" />
          <h2 className="text-lg font-semibold mb-2">No local models found</h2>
          <p className="text-sm text-muted-foreground max-w-md">
            Install{' '}
            <a
              href="https://ollama.com"
              target="_blank"
              rel="noopener noreferrer"
              className="text-primary underline"
            >
              Ollama
            </a>{' '}
            to run models locally, or add custom endpoints in Settings to connect to
            self-hosted inference servers.
          </p>
        </div>
      )}

      {/* No results from filter */}
      {models.length > 0 && filtered.length === 0 && (
        <div className="flex flex-col items-center justify-center py-12 text-center">
          <Search className="w-10 h-10 text-muted-foreground mb-3" />
          <p className="text-sm text-muted-foreground">
            No models match &quot;{query}&quot;
          </p>
        </div>
      )}

      {/* Grouped model cards */}
      {Object.entries(grouped).map(([groupName, group]) => (
        <div key={groupName} className="space-y-3">
          <div className="flex items-center gap-2">
            {group.source === 'ollama' ? (
              <HardDrive className="w-4 h-4 text-muted-foreground" />
            ) : (
              <Server className="w-4 h-4 text-muted-foreground" />
            )}
            <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
              {groupName}
            </h2>
            <span className="text-xs text-muted-foreground">
              ({group.models.length})
            </span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {group.models.map((model) => (
              <div
                key={model.id}
                className={`bg-card elevation-1 rounded-lg p-4 space-y-3 ${
                  model.source === 'ollama'
                    ? 'cursor-pointer hover:ring-2 hover:ring-ring transition-shadow'
                    : ''
                }`}
                onClick={() => handleExpand(model)}
              >
                <div className="flex items-start justify-between gap-2">
                  <p className="font-mono text-sm font-medium truncate">
                    {model.name}
                  </p>
                  <div className="flex items-center gap-1 shrink-0">
                    {model.source === 'ollama' && (
                      <button
                        onClick={(e) => {
                          e.stopPropagation()
                          handleDelete(model.name)
                        }}
                        disabled={deleting === model.name}
                        className="p-1 rounded hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors"
                        title="Delete model"
                      >
                        {deleting === model.name ? (
                          <Loader2 className="w-3.5 h-3.5 animate-spin" />
                        ) : (
                          <Trash2 className="w-3.5 h-3.5" />
                        )}
                      </button>
                    )}
                    <span
                      className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                        model.source === 'ollama'
                          ? 'bg-blue-500/10 text-blue-500'
                          : 'bg-purple-500/10 text-purple-500'
                      }`}
                    >
                      {model.source === 'ollama' ? 'Ollama' : 'Custom'}
                    </span>
                  </div>
                </div>

                <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                  <span>Provider: {model.provider}</span>
                  {model.size && <span>Size: {model.size}</span>}
                  {model.quantization && <span>Quant: {model.quantization}</span>}
                </div>

                {/* Expandable detail for Ollama models */}
                {expandedModel === model.id && model.source === 'ollama' && (
                  <div
                    className="border-t border-border pt-3 mt-2 space-y-2 text-xs text-muted-foreground"
                    onClick={(e) => e.stopPropagation()}
                  >
                    {detailLoading === model.id ? (
                      <div className="flex items-center gap-2">
                        <Loader2 className="w-3 h-3 animate-spin" />
                        <span>Loading details...</span>
                      </div>
                    ) : details[model.id] ? (
                      <>
                        {details[model.id].digest && (
                          <div>
                            <span className="font-medium text-foreground">Digest:</span>{' '}
                            <span className="font-mono">{details[model.id].digest}</span>
                          </div>
                        )}
                        {details[model.id].size != null && (
                          <div>
                            <span className="font-medium text-foreground">Size (bytes):</span>{' '}
                            <span className="font-mono">
                              {details[model.id].size!.toLocaleString()}
                            </span>
                          </div>
                        )}
                        {details[model.id].details &&
                          Object.keys(details[model.id].details).length > 0 && (
                            <div className="space-y-1">
                              <span className="font-medium text-foreground">Parameters:</span>
                              <div className="bg-muted rounded p-2 font-mono whitespace-pre-wrap break-all">
                                {Object.entries(details[model.id].details).map(
                                  ([key, value]) => (
                                    <div key={key}>
                                      {key}: {typeof value === 'object' ? JSON.stringify(value) : String(value)}
                                    </div>
                                  )
                                )}
                              </div>
                            </div>
                          )}
                      </>
                    ) : (
                      <span>No additional details available.</span>
                    )}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}

      {/* Recommendations section */}
      <div className="space-y-4 pt-4 border-t border-border">
        <div className="flex items-center gap-3">
          <Lightbulb className="w-5 h-5 text-amber-500" />
          <h2 className="text-lg font-semibold">Model Recommendations</h2>
        </div>

        <div className="flex items-center gap-2">
          <label className="text-sm text-muted-foreground">Task type:</label>
          <select
            value={selectedTaskType}
            onChange={(e) => setSelectedTaskType(e.target.value)}
            className="px-3 py-1.5 rounded-md border border-border bg-background text-sm focus:outline-none focus:ring-2 focus:ring-ring"
          >
            {TASK_TYPES.map((t) => (
              <option key={t.value} value={t.value}>
                {t.label}
              </option>
            ))}
          </select>
        </div>

        {recsLoading ? (
          <div className="flex items-center gap-2 py-4">
            <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" />
            <span className="text-sm text-muted-foreground">Loading recommendations...</span>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {recommendations.map((rec) => (
              <div
                key={rec.model}
                className="bg-card elevation-1 rounded-lg p-4 space-y-3"
              >
                <div className="flex items-start justify-between gap-2">
                  <p className="font-mono text-sm font-medium truncate">
                    {rec.model}
                  </p>
                  {rec.local_available ? (
                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-500 shrink-0">
                      <CheckCircle className="w-3 h-3" />
                      Available
                    </span>
                  ) : rec.provider === 'ollama' ? (
                    <Button
                      variant="outline"
                      size="sm"
                      className="shrink-0 h-6 text-xs"
                      onClick={() => handlePull(rec.model)}
                      disabled={!!activePulls[rec.model]}
                    >
                      <Download className="w-3 h-3 mr-1" />
                      Pull
                    </Button>
                  ) : (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground shrink-0">
                      Cloud
                    </span>
                  )}
                </div>
                <div className="text-xs text-muted-foreground space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="capitalize">{rec.provider}</span>
                    {rec.size_gb != null && <span>{rec.size_gb} GB</span>}
                  </div>
                  <p>{rec.reason}</p>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
