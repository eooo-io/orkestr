import { useEffect, useState } from 'react'
import {
  Activity,
  Zap,
  BarChart3,
  RefreshCw,
  Loader2,
  CheckCircle,
  XCircle,
  AlertTriangle,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  fetchModelHealth,
  checkModelProviderHealth,
  benchmarkModel,
  compareModels,
  fetchModels,
} from '@/api/client'
import type {
  ModelHealthResult,
  ModelBenchmarkResult,
  ModelComparisonResult,
  ModelGroup,
} from '@/types'

function statusColor(status: ModelHealthResult['status']): string {
  switch (status) {
    case 'healthy':
      return 'bg-green-500'
    case 'degraded':
      return 'bg-yellow-500'
    case 'down':
      return 'bg-red-500'
    case 'unconfigured':
    default:
      return 'bg-gray-400'
  }
}

function statusIcon(status: ModelHealthResult['status']) {
  switch (status) {
    case 'healthy':
      return <CheckCircle className="h-4 w-4 text-green-500" />
    case 'degraded':
      return <AlertTriangle className="h-4 w-4 text-yellow-500" />
    case 'down':
      return <XCircle className="h-4 w-4 text-red-500" />
    case 'unconfigured':
    default:
      return <XCircle className="h-4 w-4 text-gray-400" />
  }
}

function statusLabel(status: ModelHealthResult['status']): string {
  return status.charAt(0).toUpperCase() + status.slice(1)
}

export function ModelHealth() {
  const [health, setHealth] = useState<ModelHealthResult[]>([])
  const [healthLoading, setHealthLoading] = useState(true)
  const [checkingProvider, setCheckingProvider] = useState<string | null>(null)

  const [models, setModels] = useState<ModelGroup[]>([])
  const [benchmarkModel_, setBenchmarkModel] = useState('')
  const [benchmarkResult, setBenchmarkResult] = useState<ModelBenchmarkResult | null>(null)
  const [benchmarkLoading, setBenchmarkLoading] = useState(false)

  const [selectedModels, setSelectedModels] = useState<string[]>([])
  const [comparePrompt, setComparePrompt] = useState('')
  const [comparisonResult, setComparisonResult] = useState<ModelComparisonResult | null>(null)
  const [compareLoading, setCompareLoading] = useState(false)

  useEffect(() => {
    loadHealth()
    fetchModels().then(setModels).catch(() => setModels([]))
  }, [])

  function loadHealth() {
    setHealthLoading(true)
    fetchModelHealth()
      .then(setHealth)
      .catch(() => setHealth([]))
      .finally(() => setHealthLoading(false))
  }

  function handleCheckProvider(provider: string) {
    setCheckingProvider(provider)
    checkModelProviderHealth(provider)
      .then((result) => {
        setHealth((prev) =>
          prev.map((h) => (h.provider === provider ? result : h))
        )
      })
      .catch(() => {})
      .finally(() => setCheckingProvider(null))
  }

  function handleBenchmark() {
    if (!benchmarkModel_) return
    setBenchmarkLoading(true)
    setBenchmarkResult(null)
    benchmarkModel({ model: benchmarkModel_ })
      .then(setBenchmarkResult)
      .catch(() => setBenchmarkResult(null))
      .finally(() => setBenchmarkLoading(false))
  }

  function handleCompare() {
    if (selectedModels.length < 2 || !comparePrompt.trim()) return
    setCompareLoading(true)
    setComparisonResult(null)
    compareModels({ models: selectedModels, prompt: comparePrompt })
      .then(setComparisonResult)
      .catch(() => setComparisonResult(null))
      .finally(() => setCompareLoading(false))
  }

  function toggleModelSelection(modelId: string) {
    setSelectedModels((prev) =>
      prev.includes(modelId)
        ? prev.filter((m) => m !== modelId)
        : [...prev, modelId]
    )
  }

  const allModels = models.flatMap((g) =>
    g.models.map((m) => ({ id: m.id, name: m.name, provider: g.provider }))
  )

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <Activity className="h-6 w-6 text-blue-400" />
          <h1 className="text-2xl font-bold">Model Health</h1>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={loadHealth}
          disabled={healthLoading}
        >
          {healthLoading ? (
            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
          ) : (
            <RefreshCw className="mr-2 h-4 w-4" />
          )}
          Refresh All
        </Button>
      </div>

      {/* Section 1: Provider Health Overview */}
      <section className="space-y-4">
        <h2 className="flex items-center gap-2 text-lg font-semibold">
          <Activity className="h-5 w-5" />
          Provider Health Overview
        </h2>

        {healthLoading && health.length === 0 ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : health.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            No provider health data available.
          </p>
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {health.map((provider) => (
              <div
                key={provider.provider}
                className="bg-card elevation-1 rounded-lg border p-4 space-y-3"
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span
                      className={`inline-block h-2.5 w-2.5 rounded-full ${statusColor(provider.status)}`}
                    />
                    <span className="font-mono text-sm font-medium">
                      {provider.provider}
                    </span>
                  </div>
                  {statusIcon(provider.status)}
                </div>

                <div className="space-y-1 text-sm text-muted-foreground">
                  <div className="flex justify-between">
                    <span>Status</span>
                    <span className="font-medium text-foreground">
                      {statusLabel(provider.status)}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span>Latency</span>
                    <span className="font-mono text-foreground">
                      {provider.latency_ms !== null
                        ? `${provider.latency_ms}ms`
                        : '--'}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span>Models</span>
                    <span className="text-foreground">
                      {provider.models.length}
                    </span>
                  </div>
                </div>

                {provider.error && (
                  <p className="text-xs text-red-400 truncate" title={provider.error}>
                    {provider.error}
                  </p>
                )}

                <Button
                  variant="outline"
                  size="sm"
                  className="w-full"
                  disabled={checkingProvider === provider.provider}
                  onClick={() => handleCheckProvider(provider.provider)}
                >
                  {checkingProvider === provider.provider ? (
                    <Loader2 className="mr-2 h-3 w-3 animate-spin" />
                  ) : (
                    <RefreshCw className="mr-2 h-3 w-3" />
                  )}
                  Check
                </Button>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Section 2: Benchmark */}
      <section className="space-y-4">
        <h2 className="flex items-center gap-2 text-lg font-semibold">
          <Zap className="h-5 w-5" />
          Benchmark
        </h2>

        <div className="bg-card elevation-1 rounded-lg border p-4 space-y-4">
          <div className="flex items-end gap-3">
            <div className="flex-1">
              <label className="mb-1 block text-sm font-medium">Model</label>
              <select
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                value={benchmarkModel_}
                onChange={(e) => setBenchmarkModel(e.target.value)}
              >
                <option value="">Select a model...</option>
                {models.map((group) => (
                  <optgroup key={group.provider} label={group.provider}>
                    {group.models.map((m) => (
                      <option key={m.id} value={m.id}>
                        {m.name}
                      </option>
                    ))}
                  </optgroup>
                ))}
              </select>
            </div>
            <Button
              onClick={handleBenchmark}
              disabled={!benchmarkModel_ || benchmarkLoading}
            >
              {benchmarkLoading ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <Zap className="mr-2 h-4 w-4" />
              )}
              Run Benchmark
            </Button>
          </div>

          {benchmarkLoading && (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
              <span className="ml-2 text-sm text-muted-foreground">
                Running benchmark...
              </span>
            </div>
          )}

          {benchmarkResult && !benchmarkLoading && (
            <div className="space-y-3 rounded-md border p-4">
              <div className="grid grid-cols-3 gap-4 text-sm">
                <div>
                  <span className="text-muted-foreground">Latency</span>
                  <p className="font-mono font-medium">
                    {benchmarkResult.latency_ms}ms
                  </p>
                </div>
                <div>
                  <span className="text-muted-foreground">Tokens/sec</span>
                  <p className="font-mono font-medium">
                    {benchmarkResult.tokens_per_second !== null
                      ? benchmarkResult.tokens_per_second.toFixed(1)
                      : '--'}
                  </p>
                </div>
                <div>
                  <span className="text-muted-foreground">Provider</span>
                  <p className="font-mono font-medium">
                    {benchmarkResult.provider}
                  </p>
                </div>
              </div>

              {benchmarkResult.error && (
                <p className="text-sm text-red-400">{benchmarkResult.error}</p>
              )}

              {benchmarkResult.output && (
                <div>
                  <span className="text-sm text-muted-foreground">Output Preview</span>
                  <pre className="mt-1 max-h-40 overflow-auto rounded-md bg-muted p-3 text-sm whitespace-pre-wrap">
                    {benchmarkResult.output}
                  </pre>
                </div>
              )}
            </div>
          )}
        </div>
      </section>

      {/* Section 3: Model Comparison */}
      <section className="space-y-4">
        <h2 className="flex items-center gap-2 text-lg font-semibold">
          <BarChart3 className="h-5 w-5" />
          Model Comparison
        </h2>

        <div className="bg-card elevation-1 rounded-lg border p-4 space-y-4">
          <div>
            <label className="mb-2 block text-sm font-medium">
              Select Models ({selectedModels.length} selected)
            </label>
            <div className="max-h-48 overflow-auto rounded-md border p-3 space-y-1">
              {allModels.map((m) => (
                <label
                  key={m.id}
                  className="flex items-center gap-2 cursor-pointer rounded px-2 py-1 text-sm hover:bg-muted"
                >
                  <input
                    type="checkbox"
                    checked={selectedModels.includes(m.id)}
                    onChange={() => toggleModelSelection(m.id)}
                    className="rounded border-gray-400"
                  />
                  <span className="font-mono">{m.id}</span>
                  <span className="text-muted-foreground">({m.provider})</span>
                </label>
              ))}
              {allModels.length === 0 && (
                <p className="text-sm text-muted-foreground">No models available.</p>
              )}
            </div>
          </div>

          <div>
            <label className="mb-1 block text-sm font-medium">Prompt</label>
            <textarea
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              rows={3}
              placeholder="Enter a prompt to send to all selected models..."
              value={comparePrompt}
              onChange={(e) => setComparePrompt(e.target.value)}
            />
          </div>

          <Button
            onClick={handleCompare}
            disabled={selectedModels.length < 2 || !comparePrompt.trim() || compareLoading}
          >
            {compareLoading ? (
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
            ) : (
              <BarChart3 className="mr-2 h-4 w-4" />
            )}
            Compare
          </Button>

          {compareLoading && (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
              <span className="ml-2 text-sm text-muted-foreground">
                Comparing models...
              </span>
            </div>
          )}

          {comparisonResult && !compareLoading && (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-muted-foreground">
                    <th className="pb-2 pr-4 font-medium">Model</th>
                    <th className="pb-2 pr-4 font-medium">Latency</th>
                    <th className="pb-2 pr-4 font-medium">Tokens/sec</th>
                    <th className="pb-2 font-medium">Output</th>
                  </tr>
                </thead>
                <tbody>
                  {comparisonResult.results.map((r) => (
                    <tr key={r.model} className="border-b last:border-0">
                      <td className="py-2 pr-4 font-mono">{r.model}</td>
                      <td className="py-2 pr-4 font-mono">{r.latency_ms}ms</td>
                      <td className="py-2 pr-4 font-mono">
                        {r.tokens_per_second !== null
                          ? r.tokens_per_second.toFixed(1)
                          : '--'}
                      </td>
                      <td className="py-2 max-w-md">
                        {r.error ? (
                          <span className="text-red-400">{r.error}</span>
                        ) : (
                          <span className="truncate block max-w-md" title={r.output}>
                            {r.output.length > 120
                              ? r.output.slice(0, 120) + '...'
                              : r.output}
                          </span>
                        )}
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
