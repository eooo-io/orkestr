import { useState, useEffect, useMemo } from 'react'
import { Cpu, RefreshCw, Loader2, Search, HardDrive, Server } from 'lucide-react'
import { fetchLocalModels, fetchOllamaModelDetail } from '@/api/client'
import { Button } from '@/components/ui/button'
import type { LocalModel, OllamaModelDetail } from '@/types'

export function LocalModels() {
  const [models, setModels] = useState<LocalModel[]>([])
  const [loading, setLoading] = useState(true)
  const [query, setQuery] = useState('')
  const [expandedModel, setExpandedModel] = useState<string | null>(null)
  const [details, setDetails] = useState<Record<string, OllamaModelDetail>>({})
  const [detailLoading, setDetailLoading] = useState<string | null>(null)

  const loadModels = () => {
    setLoading(true)
    fetchLocalModels()
      .then(setModels)
      .catch(() => setModels([]))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadModels()
  }, [])

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
        <Button variant="outline" size="sm" onClick={loadModels} disabled={loading}>
          <RefreshCw className={`w-4 h-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
          Refresh
        </Button>
      </div>

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
            No models match "{query}"
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
                  <span
                    className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium shrink-0 ${
                      model.source === 'ollama'
                        ? 'bg-blue-500/10 text-blue-500'
                        : 'bg-purple-500/10 text-purple-500'
                    }`}
                  >
                    {model.source === 'ollama' ? 'Ollama' : 'Custom'}
                  </span>
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
    </div>
  )
}
