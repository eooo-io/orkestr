import { useEffect, useState, useCallback } from 'react'
import {
  BarChart3,
  Bell,
  BellOff,
  CheckCircle,
  ChevronRight,
  DollarSign,
  Eye,
  Gauge,
  Grid3X3,
  Layers,
  LineChart,
  Plus,
  RefreshCw,
  Trash2,
  TrendingDown,
  TrendingUp,
  Minus,
  X,
  AlertTriangle,
  Shield,
  Activity,
} from 'lucide-react'
import api from '@/api/client'
import { useAppStore } from '@/store/useAppStore'

// ─── Types ──────────────────────────────────────────────────────────

interface CustomMetric {
  id: number
  uuid: string
  name: string
  slug: string
  query_type: string
  query_config: Record<string, unknown> | null
  unit: string
}

interface AlertRule {
  id: number
  uuid: string
  name: string
  metric_slug: string
  condition: string
  threshold: string
  window_minutes: number
  cooldown_minutes: number
  notification_channel_id: number | null
  notification_channel?: { id: number; name: string; type: string } | null
  severity: string
  enabled: boolean
  last_triggered_at: string | null
  incidents_count?: number
}

interface AlertIncident {
  id: number
  uuid: string
  alert_rule_id: number
  alert_rule?: { id: number; name: string; severity: string; metric_slug: string; condition: string; threshold: string }
  metric_value: string
  threshold_value: string
  status: 'firing' | 'acknowledged' | 'resolved'
  acknowledged_by?: { id: number; name: string } | null
  acknowledged_at: string | null
  resolved_at: string | null
  created_at: string
}

interface DashboardLayout {
  id: number
  uuid: string
  name: string
  layout: WidgetConfig[]
  is_default: boolean
  user_id: number | null
}

interface WidgetConfig {
  metric_slug: string
  chart_type: 'number' | 'sparkline' | 'bar'
}

interface TimeSeriesPoint {
  timestamp: string
  value: number
}

interface CostForecast {
  daily_costs: { date: string; cost: number }[]
  forecast_7d: number
  forecast_30d: number
  trend: 'increasing' | 'stable' | 'decreasing'
  avg_daily: number
}

type Tab = 'dashboards' | 'metrics' | 'alerts' | 'forecast'

const QUERY_TYPES = [
  { value: 'count_runs', label: 'Run Count' },
  { value: 'sum_tokens', label: 'Total Tokens' },
  { value: 'avg_cost', label: 'Average Cost' },
  { value: 'avg_duration', label: 'Average Duration' },
  { value: 'error_rate', label: 'Error Rate' },
]

const CONDITIONS = [
  { value: 'gt', label: '>' },
  { value: 'lt', label: '<' },
  { value: 'gte', label: '>=' },
  { value: 'lte', label: '<=' },
  { value: 'eq', label: '=' },
]

const SEVERITY_COLORS: Record<string, string> = {
  info: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  warning: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
  critical: 'bg-red-500/10 text-red-400 border-red-500/20',
}

const STATUS_COLORS: Record<string, string> = {
  firing: 'bg-red-500/10 text-red-400 border-red-500/20',
  acknowledged: 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
  resolved: 'bg-green-500/10 text-green-400 border-green-500/20',
}

// ─── SVG Chart Helpers ──────────────────────────────────────────────

function Sparkline({ data, width = 120, height = 32, color = '#6366f1' }: { data: number[]; width?: number; height?: number; color?: string }) {
  if (data.length < 2) return <div className="h-8 w-30 bg-muted/30 rounded" />

  const max = Math.max(...data, 1)
  const min = Math.min(...data, 0)
  const range = max - min || 1
  const padding = 2

  const points = data.map((v, i) => {
    const x = padding + (i / (data.length - 1)) * (width - padding * 2)
    const y = height - padding - ((v - min) / range) * (height - padding * 2)
    return `${x},${y}`
  }).join(' ')

  return (
    <svg width={width} height={height} className="overflow-visible">
      <polyline
        points={points}
        fill="none"
        stroke={color}
        strokeWidth={1.5}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

function BarChart({ data, width = 120, height = 32, color = '#6366f1' }: { data: number[]; width?: number; height?: number; color?: string }) {
  if (data.length === 0) return <div className="h-8 w-30 bg-muted/30 rounded" />

  const max = Math.max(...data, 1)
  const barWidth = Math.max(2, (width - data.length) / data.length)
  const gap = 1

  return (
    <svg width={width} height={height}>
      {data.map((v, i) => {
        const barH = (v / max) * (height - 2)
        return (
          <rect
            key={i}
            x={i * (barWidth + gap)}
            y={height - barH}
            width={barWidth}
            height={barH}
            fill={color}
            rx={1}
            opacity={0.8}
          />
        )
      })}
    </svg>
  )
}

function CostChart({ data, width = 600, height = 200 }: { data: { date: string; cost: number }[]; width?: number; height?: number }) {
  if (data.length < 2) return <div className="h-50 bg-muted/30 rounded flex items-center justify-center text-muted-foreground text-sm">No cost data</div>

  const costs = data.map(d => d.cost)
  const max = Math.max(...costs, 0.01)
  const padding = { top: 10, right: 10, bottom: 30, left: 50 }
  const chartW = width - padding.left - padding.right
  const chartH = height - padding.top - padding.bottom

  const points = costs.map((v, i) => {
    const x = padding.left + (i / (costs.length - 1)) * chartW
    const y = padding.top + chartH - (v / max) * chartH
    return `${x},${y}`
  }).join(' ')

  // Area fill
  const areaPoints = `${padding.left},${padding.top + chartH} ${points} ${padding.left + chartW},${padding.top + chartH}`

  // X-axis labels (every 5th day)
  const xLabels = data.filter((_, i) => i % 5 === 0 || i === data.length - 1)

  return (
    <svg width={width} height={height} className="w-full" viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none">
      {/* Grid lines */}
      {[0, 0.25, 0.5, 0.75, 1].map(f => {
        const y = padding.top + chartH - f * chartH
        return (
          <g key={f}>
            <line x1={padding.left} y1={y} x2={padding.left + chartW} y2={y} stroke="currentColor" className="text-muted/20" strokeDasharray="3,3" />
            <text x={padding.left - 4} y={y + 3} textAnchor="end" className="fill-muted-foreground" fontSize={9}>
              ${(max * f).toFixed(0)}
            </text>
          </g>
        )
      })}
      {/* Area */}
      <polygon points={areaPoints} fill="url(#costGradient)" />
      <defs>
        <linearGradient id="costGradient" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#6366f1" stopOpacity={0.3} />
          <stop offset="100%" stopColor="#6366f1" stopOpacity={0.02} />
        </linearGradient>
      </defs>
      {/* Line */}
      <polyline points={points} fill="none" stroke="#6366f1" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
      {/* X labels */}
      {xLabels.map((d, _i) => {
        const idx = data.indexOf(d)
        const x = padding.left + (idx / (data.length - 1)) * chartW
        return (
          <text key={d.date} x={x} y={height - 5} textAnchor="middle" className="fill-muted-foreground" fontSize={9}>
            {d.date.slice(5)}
          </text>
        )
      })}
    </svg>
  )
}

// ─── Main Component ─────────────────────────────────────────────────

export function Observability() {
  const [tab, setTab] = useState<Tab>('dashboards')
  const { showToast } = useAppStore()

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Observability</h1>
          <p className="text-sm text-muted-foreground mt-1">Custom metrics, alerts, dashboards and cost forecasting</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        {([
          { key: 'dashboards' as Tab, label: 'Dashboards', icon: Grid3X3 },
          { key: 'metrics' as Tab, label: 'Metrics', icon: BarChart3 },
          { key: 'alerts' as Tab, label: 'Alerts', icon: Bell },
          { key: 'forecast' as Tab, label: 'Forecast', icon: TrendingUp },
        ]).map(t => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
              tab === t.key
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            <t.icon className="h-4 w-4" />
            {t.label}
          </button>
        ))}
      </div>

      {/* Tab Content */}
      {tab === 'dashboards' && <DashboardsTab />}
      {tab === 'metrics' && <MetricsTab />}
      {tab === 'alerts' && <AlertsTab />}
      {tab === 'forecast' && <ForecastTab />}
    </div>
  )
}

// ─── Dashboards Tab ─────────────────────────────────────────────────

function DashboardsTab() {
  const [dashboards, setDashboards] = useState<DashboardLayout[]>([])
  const [metrics, setMetrics] = useState<CustomMetric[]>([])
  const [selected, setSelected] = useState<DashboardLayout | null>(null)
  const [widgetData, setWidgetData] = useState<Record<string, TimeSeriesPoint[]>>({})
  const [loading, setLoading] = useState(true)
  const [showCreate, setShowCreate] = useState(false)
  const [newName, setNewName] = useState('')
  const [newWidgets, setNewWidgets] = useState<WidgetConfig[]>([{ metric_slug: '', chart_type: 'sparkline' }])
  const { showToast } = useAppStore()

  const load = useCallback(() => {
    setLoading(true)
    Promise.all([
      api.get('/observability/dashboards').then(r => r.data.data),
      api.get('/observability/metrics').then(r => r.data.data),
    ]).then(([d, m]) => {
      setDashboards(d)
      setMetrics(m)
      if (!selected && d.length > 0) {
        const def = d.find((x: DashboardLayout) => x.is_default) || d[0]
        setSelected(def)
      }
    }).finally(() => setLoading(false))
  }, [])

  useEffect(() => { load() }, [load])

  // Load widget data when a dashboard is selected
  useEffect(() => {
    if (!selected?.layout?.length) return
    const slugs = [...new Set(selected.layout.map(w => w.metric_slug))]
    const metricMap = Object.fromEntries(metrics.map(m => [m.slug, m]))

    Promise.all(
      slugs.filter(s => metricMap[s]).map(s =>
        api.get(`/observability/metrics/${metricMap[s].id}/evaluate`).then(r => ({ slug: s, data: r.data.data as TimeSeriesPoint[] }))
      )
    ).then(results => {
      const map: Record<string, TimeSeriesPoint[]> = {}
      results.forEach(r => { map[r.slug] = r.data })
      setWidgetData(map)
    })
  }, [selected, metrics])

  const handleCreate = async () => {
    if (!newName.trim()) return
    try {
      const { data } = await api.post('/observability/dashboards', {
        name: newName,
        layout: newWidgets.filter(w => w.metric_slug),
      })
      showToast('Dashboard created', 'success')
      setShowCreate(false)
      setNewName('')
      setNewWidgets([{ metric_slug: '', chart_type: 'sparkline' }])
      load()
      setSelected(data.data)
    } catch {
      showToast('Failed to create dashboard', 'error')
    }
  }

  const handleDelete = async (d: DashboardLayout) => {
    try {
      await api.delete(`/observability/dashboards/${d.id}`)
      showToast('Dashboard deleted', 'success')
      if (selected?.id === d.id) setSelected(null)
      load()
    } catch {
      showToast('Failed to delete', 'error')
    }
  }

  if (loading) return <LoadingState />

  return (
    <div className="space-y-4">
      {/* Dashboard list */}
      <div className="flex items-center gap-2 flex-wrap">
        {dashboards.map(d => (
          <button
            key={d.id}
            onClick={() => setSelected(d)}
            className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm border transition-colors ${
              selected?.id === d.id
                ? 'border-primary bg-primary/5 text-primary'
                : 'border-border text-muted-foreground hover:text-foreground hover:border-muted-foreground'
            }`}
          >
            <Grid3X3 className="h-3.5 w-3.5" />
            {d.name}
            {d.is_default && <span className="text-[10px] bg-primary/10 text-primary px-1.5 rounded">default</span>}
          </button>
        ))}
        <button onClick={() => setShowCreate(true)} className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm border border-dashed border-border text-muted-foreground hover:text-foreground hover:border-muted-foreground transition-colors">
          <Plus className="h-3.5 w-3.5" /> New
        </button>
      </div>

      {/* Create modal */}
      {showCreate && (
        <Modal title="Create Dashboard" onClose={() => setShowCreate(false)}>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">Name</label>
              <input value={newName} onChange={e => setNewName(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm" placeholder="My Dashboard" />
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">Widgets</label>
              {newWidgets.map((w, i) => (
                <div key={i} className="flex items-center gap-2 mb-2">
                  <select
                    value={w.metric_slug}
                    onChange={e => {
                      const copy = [...newWidgets]
                      copy[i] = { ...copy[i], metric_slug: e.target.value }
                      setNewWidgets(copy)
                    }}
                    className="flex-1 px-3 py-2 rounded-lg border border-border bg-background text-sm"
                  >
                    <option value="">Select metric...</option>
                    {metrics.map(m => <option key={m.slug} value={m.slug}>{m.name}</option>)}
                  </select>
                  <select
                    value={w.chart_type}
                    onChange={e => {
                      const copy = [...newWidgets]
                      copy[i] = { ...copy[i], chart_type: e.target.value as WidgetConfig['chart_type'] }
                      setNewWidgets(copy)
                    }}
                    className="px-3 py-2 rounded-lg border border-border bg-background text-sm"
                  >
                    <option value="number">Number</option>
                    <option value="sparkline">Sparkline</option>
                    <option value="bar">Bar</option>
                  </select>
                  {newWidgets.length > 1 && (
                    <button onClick={() => setNewWidgets(newWidgets.filter((_, j) => j !== i))} className="p-1.5 text-muted-foreground hover:text-red-400">
                      <X className="h-4 w-4" />
                    </button>
                  )}
                </div>
              ))}
              <button onClick={() => setNewWidgets([...newWidgets, { metric_slug: '', chart_type: 'sparkline' }])} className="text-sm text-primary hover:underline flex items-center gap-1 mt-1">
                <Plus className="h-3.5 w-3.5" /> Add widget
              </button>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setShowCreate(false)} className="px-4 py-2 text-sm rounded-lg border border-border hover:bg-muted/50">Cancel</button>
              <button onClick={handleCreate} className="px-4 py-2 text-sm rounded-lg bg-primary text-primary-foreground hover:bg-primary/90">Create</button>
            </div>
          </div>
        </Modal>
      )}

      {/* Dashboard view */}
      {selected && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold">{selected.name}</h2>
            <button onClick={() => handleDelete(selected)} className="p-2 text-muted-foreground hover:text-red-400 rounded-lg hover:bg-muted/50">
              <Trash2 className="h-4 w-4" />
            </button>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {(selected.layout || []).map((widget, i) => {
              const metric = metrics.find(m => m.slug === widget.metric_slug)
              const data = widgetData[widget.metric_slug] || []
              const values = data.map(d => d.value)
              const current = values.length > 0 ? values[values.length - 1] : 0

              return (
                <div key={i} className="rounded-xl border border-border bg-card p-4 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-muted-foreground">{metric?.name || widget.metric_slug}</span>
                    <span className="text-xs text-muted-foreground">{metric?.unit}</span>
                  </div>
                  <div className="text-2xl font-bold">{formatMetricValue(current, metric?.unit)}</div>
                  <div className="pt-1">
                    {widget.chart_type === 'sparkline' && <Sparkline data={values} width={200} height={32} />}
                    {widget.chart_type === 'bar' && <BarChart data={values} width={200} height={32} />}
                    {widget.chart_type === 'number' && (
                      <div className="text-xs text-muted-foreground">
                        {data.length > 0 ? `${data.length} data points` : 'No data'}
                      </div>
                    )}
                  </div>
                </div>
              )
            })}
          </div>
          {(!selected.layout || selected.layout.length === 0) && (
            <div className="text-center py-12 text-muted-foreground text-sm">
              No widgets configured. Edit this dashboard to add metric widgets.
            </div>
          )}
        </div>
      )}

      {!selected && dashboards.length === 0 && (
        <EmptyState
          icon={Grid3X3}
          title="No dashboards yet"
          description="Create a dashboard to visualize your custom metrics."
        />
      )}
    </div>
  )
}

// ─── Metrics Tab ────────────────────────────────────────────────────

function MetricsTab() {
  const [metrics, setMetrics] = useState<CustomMetric[]>([])
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)
  const [editing, setEditing] = useState<CustomMetric | null>(null)
  const [previewData, setPreviewData] = useState<TimeSeriesPoint[] | null>(null)
  const [previewMetricId, setPreviewMetricId] = useState<number | null>(null)
  const { showToast } = useAppStore()

  // Form state
  const [formName, setFormName] = useState('')
  const [formQueryType, setFormQueryType] = useState('count_runs')
  const [formUnit, setFormUnit] = useState('count')
  const [formProjectId, setFormProjectId] = useState('')
  const [formAgentId, setFormAgentId] = useState('')

  const load = useCallback(() => {
    setLoading(true)
    api.get('/observability/metrics').then(r => setMetrics(r.data.data)).finally(() => setLoading(false))
  }, [])

  useEffect(() => { load() }, [load])

  const openCreate = () => {
    setEditing(null)
    setFormName('')
    setFormQueryType('count_runs')
    setFormUnit('count')
    setFormProjectId('')
    setFormAgentId('')
    setShowModal(true)
  }

  const openEdit = (m: CustomMetric) => {
    setEditing(m)
    setFormName(m.name)
    setFormQueryType(m.query_type)
    setFormUnit(m.unit)
    setFormProjectId(m.query_config?.project_id?.toString() || '')
    setFormAgentId(m.query_config?.agent_id?.toString() || '')
    setShowModal(true)
  }

  const handleSave = async () => {
    const queryConfig: Record<string, unknown> = {}
    if (formProjectId) queryConfig.project_id = parseInt(formProjectId)
    if (formAgentId) queryConfig.agent_id = parseInt(formAgentId)

    const payload = {
      name: formName,
      query_type: formQueryType,
      unit: formUnit,
      query_config: Object.keys(queryConfig).length > 0 ? queryConfig : null,
    }

    try {
      if (editing) {
        await api.put(`/observability/metrics/${editing.id}`, payload)
        showToast('Metric updated', 'success')
      } else {
        await api.post('/observability/metrics', payload)
        showToast('Metric created', 'success')
      }
      setShowModal(false)
      load()
    } catch {
      showToast('Failed to save metric', 'error')
    }
  }

  const handleDelete = async (m: CustomMetric) => {
    try {
      await api.delete(`/observability/metrics/${m.id}`)
      showToast('Metric deleted', 'success')
      load()
    } catch {
      showToast('Failed to delete', 'error')
    }
  }

  const handlePreview = async (m: CustomMetric) => {
    if (previewMetricId === m.id) {
      setPreviewData(null)
      setPreviewMetricId(null)
      return
    }
    try {
      const { data } = await api.get(`/observability/metrics/${m.id}/evaluate`)
      setPreviewData(data.data)
      setPreviewMetricId(m.id)
    } catch {
      showToast('Failed to evaluate metric', 'error')
    }
  }

  if (loading) return <LoadingState />

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <button onClick={openCreate} className="flex items-center gap-2 px-4 py-2 text-sm rounded-lg bg-primary text-primary-foreground hover:bg-primary/90">
          <Plus className="h-4 w-4" /> New Metric
        </button>
      </div>

      {metrics.length === 0 ? (
        <EmptyState icon={BarChart3} title="No custom metrics" description="Define custom metrics to track specific patterns in your execution data." />
      ) : (
        <div className="space-y-2">
          {metrics.map(m => (
            <div key={m.id} className="rounded-xl border border-border bg-card">
              <div className="p-4 flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-3">
                    <Gauge className="h-4 w-4 text-muted-foreground" />
                    <span className="font-medium">{m.name}</span>
                    <span className="text-xs bg-muted px-2 py-0.5 rounded">{m.query_type.replace('_', ' ')}</span>
                    <span className="text-xs text-muted-foreground">{m.unit}</span>
                  </div>
                  {m.query_config && (Object.keys(m.query_config).length > 0) && (
                    <div className="mt-1 text-xs text-muted-foreground ml-7">
                      Filters: {Object.entries(m.query_config).map(([k, v]) => `${k}=${v}`).join(', ')}
                    </div>
                  )}
                </div>
                <div className="flex items-center gap-1">
                  <button onClick={() => handlePreview(m)} className="p-2 text-muted-foreground hover:text-foreground rounded-lg hover:bg-muted/50" title="Preview">
                    <Eye className="h-4 w-4" />
                  </button>
                  <button onClick={() => openEdit(m)} className="p-2 text-muted-foreground hover:text-foreground rounded-lg hover:bg-muted/50" title="Edit">
                    <Layers className="h-4 w-4" />
                  </button>
                  <button onClick={() => handleDelete(m)} className="p-2 text-muted-foreground hover:text-red-400 rounded-lg hover:bg-muted/50" title="Delete">
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
              {previewMetricId === m.id && previewData && (
                <div className="border-t border-border p-4 bg-muted/10">
                  {previewData.length > 0 ? (
                    <Sparkline data={previewData.map(d => d.value)} width={400} height={48} />
                  ) : (
                    <div className="text-sm text-muted-foreground">No data for the last 7 days</div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {showModal && (
        <Modal title={editing ? 'Edit Metric' : 'Create Metric'} onClose={() => setShowModal(false)}>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">Name</label>
              <input value={formName} onChange={e => setFormName(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm" placeholder="Error rate for production" />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-1">Query Type</label>
                <select value={formQueryType} onChange={e => setFormQueryType(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm">
                  {QUERY_TYPES.map(qt => <option key={qt.value} value={qt.value}>{qt.label}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Unit</label>
                <input value={formUnit} onChange={e => setFormUnit(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm" placeholder="tokens, usd, ms, count, percent" />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-1">Project ID (optional)</label>
                <input value={formProjectId} onChange={e => setFormProjectId(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm" placeholder="Filter by project" />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Agent ID (optional)</label>
                <input value={formAgentId} onChange={e => setFormAgentId(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm" placeholder="Filter by agent" />
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setShowModal(false)} className="px-4 py-2 text-sm rounded-lg border border-border hover:bg-muted/50">Cancel</button>
              <button onClick={handleSave} className="px-4 py-2 text-sm rounded-lg bg-primary text-primary-foreground hover:bg-primary/90">{editing ? 'Update' : 'Create'}</button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  )
}

// ─── Alerts Tab ─────────────────────────────────────────────────────

function AlertsTab() {
  const [rules, setRules] = useState<AlertRule[]>([])
  const [incidents, setIncidents] = useState<AlertIncident[]>([])
  const [metrics, setMetrics] = useState<CustomMetric[]>([])
  const [loading, setLoading] = useState(true)
  const [showModal, setShowModal] = useState(false)
  const [editing, setEditing] = useState<AlertRule | null>(null)
  const { showToast } = useAppStore()

  // Form state
  const [formName, setFormName] = useState('')
  const [formMetric, setFormMetric] = useState('')
  const [formCondition, setFormCondition] = useState('gt')
  const [formThreshold, setFormThreshold] = useState('')
  const [formWindow, setFormWindow] = useState('60')
  const [formCooldown, setFormCooldown] = useState('30')
  const [formSeverity, setFormSeverity] = useState('warning')

  const load = useCallback(() => {
    setLoading(true)
    Promise.all([
      api.get('/observability/alert-rules').then(r => r.data.data),
      api.get('/observability/incidents?per_page=20').then(r => r.data.data),
      api.get('/observability/metrics').then(r => r.data.data),
    ]).then(([r, i, m]) => {
      setRules(r)
      setIncidents(i)
      setMetrics(m)
    }).finally(() => setLoading(false))
  }, [])

  useEffect(() => { load() }, [load])

  const openCreate = () => {
    setEditing(null)
    setFormName('')
    setFormMetric('')
    setFormCondition('gt')
    setFormThreshold('')
    setFormWindow('60')
    setFormCooldown('30')
    setFormSeverity('warning')
    setShowModal(true)
  }

  const openEdit = (r: AlertRule) => {
    setEditing(r)
    setFormName(r.name)
    setFormMetric(r.metric_slug)
    setFormCondition(r.condition)
    setFormThreshold(r.threshold)
    setFormWindow(r.window_minutes.toString())
    setFormCooldown(r.cooldown_minutes.toString())
    setFormSeverity(r.severity)
    setShowModal(true)
  }

  const handleSave = async () => {
    const payload = {
      name: formName,
      metric_slug: formMetric,
      condition: formCondition,
      threshold: parseFloat(formThreshold),
      window_minutes: parseInt(formWindow),
      cooldown_minutes: parseInt(formCooldown),
      severity: formSeverity,
    }

    try {
      if (editing) {
        await api.put(`/observability/alert-rules/${editing.id}`, payload)
        showToast('Alert rule updated', 'success')
      } else {
        await api.post('/observability/alert-rules', payload)
        showToast('Alert rule created', 'success')
      }
      setShowModal(false)
      load()
    } catch {
      showToast('Failed to save alert rule', 'error')
    }
  }

  const handleDelete = async (r: AlertRule) => {
    try {
      await api.delete(`/observability/alert-rules/${r.id}`)
      showToast('Alert rule deleted', 'success')
      load()
    } catch {
      showToast('Failed to delete', 'error')
    }
  }

  const handleToggle = async (r: AlertRule) => {
    try {
      await api.put(`/observability/alert-rules/${r.id}`, { enabled: !r.enabled })
      load()
    } catch {
      showToast('Failed to toggle rule', 'error')
    }
  }

  const handleAcknowledge = async (inc: AlertIncident) => {
    try {
      await api.post(`/observability/incidents/${inc.id}/acknowledge`)
      showToast('Incident acknowledged', 'success')
      load()
    } catch {
      showToast('Failed to acknowledge', 'error')
    }
  }

  const handleResolve = async (inc: AlertIncident) => {
    try {
      await api.post(`/observability/incidents/${inc.id}/resolve`)
      showToast('Incident resolved', 'success')
      load()
    } catch {
      showToast('Failed to resolve', 'error')
    }
  }

  if (loading) return <LoadingState />

  return (
    <div className="space-y-6">
      {/* Alert Rules */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Alert Rules</h2>
          <button onClick={openCreate} className="flex items-center gap-2 px-4 py-2 text-sm rounded-lg bg-primary text-primary-foreground hover:bg-primary/90">
            <Plus className="h-4 w-4" /> New Rule
          </button>
        </div>

        {rules.length === 0 ? (
          <EmptyState icon={Bell} title="No alert rules" description="Create alert rules to get notified when metrics breach thresholds." />
        ) : (
          <div className="space-y-2">
            {rules.map(r => (
              <div key={r.id} className="rounded-xl border border-border bg-card p-4 flex items-center justify-between">
                <div className="flex items-center gap-4 flex-1">
                  <button onClick={() => handleToggle(r)} className="text-muted-foreground hover:text-foreground" title={r.enabled ? 'Disable' : 'Enable'}>
                    {r.enabled ? <Bell className="h-4 w-4 text-primary" /> : <BellOff className="h-4 w-4" />}
                  </button>
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <span className="font-medium">{r.name}</span>
                      <span className={`text-[10px] px-1.5 py-0.5 rounded border ${SEVERITY_COLORS[r.severity]}`}>{r.severity}</span>
                      {r.incidents_count ? (
                        <span className="text-xs text-muted-foreground">{r.incidents_count} incident{r.incidents_count !== 1 ? 's' : ''}</span>
                      ) : null}
                    </div>
                    <div className="text-xs text-muted-foreground mt-0.5">
                      {r.metric_slug} {CONDITIONS.find(c => c.value === r.condition)?.label} {r.threshold} (window: {r.window_minutes}m, cooldown: {r.cooldown_minutes}m)
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-1">
                  <button onClick={() => openEdit(r)} className="p-2 text-muted-foreground hover:text-foreground rounded-lg hover:bg-muted/50">
                    <Layers className="h-4 w-4" />
                  </button>
                  <button onClick={() => handleDelete(r)} className="p-2 text-muted-foreground hover:text-red-400 rounded-lg hover:bg-muted/50">
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Recent Incidents */}
      <div className="space-y-3">
        <h2 className="text-lg font-semibold">Recent Incidents</h2>
        {incidents.length === 0 ? (
          <div className="text-sm text-muted-foreground py-4 text-center">No recent incidents</div>
        ) : (
          <div className="rounded-xl border border-border overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border bg-muted/30">
                  <th className="text-left p-3 font-medium text-muted-foreground">Status</th>
                  <th className="text-left p-3 font-medium text-muted-foreground">Rule</th>
                  <th className="text-left p-3 font-medium text-muted-foreground">Value / Threshold</th>
                  <th className="text-left p-3 font-medium text-muted-foreground">Time</th>
                  <th className="text-right p-3 font-medium text-muted-foreground">Actions</th>
                </tr>
              </thead>
              <tbody>
                {incidents.map(inc => (
                  <tr key={inc.id} className="border-b border-border last:border-0">
                    <td className="p-3">
                      <span className={`text-xs px-2 py-0.5 rounded border ${STATUS_COLORS[inc.status]}`}>{inc.status}</span>
                    </td>
                    <td className="p-3">
                      <div className="flex items-center gap-2">
                        <span>{inc.alert_rule?.name || '-'}</span>
                        {inc.alert_rule?.severity && (
                          <span className={`text-[10px] px-1 py-0.5 rounded border ${SEVERITY_COLORS[inc.alert_rule.severity]}`}>{inc.alert_rule.severity}</span>
                        )}
                      </div>
                    </td>
                    <td className="p-3 font-mono text-xs">
                      {parseFloat(inc.metric_value).toFixed(2)} / {parseFloat(inc.threshold_value).toFixed(2)}
                    </td>
                    <td className="p-3 text-muted-foreground text-xs">
                      {inc.created_at ? new Date(inc.created_at).toLocaleString() : '-'}
                    </td>
                    <td className="p-3 text-right">
                      <div className="flex items-center justify-end gap-1">
                        {inc.status === 'firing' && (
                          <button onClick={() => handleAcknowledge(inc)} className="px-2 py-1 text-xs rounded border border-yellow-500/30 text-yellow-400 hover:bg-yellow-500/10" title="Acknowledge">
                            Ack
                          </button>
                        )}
                        {inc.status !== 'resolved' && (
                          <button onClick={() => handleResolve(inc)} className="px-2 py-1 text-xs rounded border border-green-500/30 text-green-400 hover:bg-green-500/10" title="Resolve">
                            Resolve
                          </button>
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

      {/* Modal */}
      {showModal && (
        <Modal title={editing ? 'Edit Alert Rule' : 'Create Alert Rule'} onClose={() => setShowModal(false)}>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">Name</label>
              <input value={formName} onChange={e => setFormName(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm" placeholder="High error rate alert" />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Metric</label>
              <select value={formMetric} onChange={e => setFormMetric(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm">
                <option value="">Select metric...</option>
                {metrics.map(m => <option key={m.slug} value={m.slug}>{m.name} ({m.slug})</option>)}
              </select>
            </div>
            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium mb-1">Condition</label>
                <select value={formCondition} onChange={e => setFormCondition(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm">
                  {CONDITIONS.map(c => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Threshold</label>
                <input type="number" step="any" value={formThreshold} onChange={e => setFormThreshold(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm" placeholder="10" />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Severity</label>
                <select value={formSeverity} onChange={e => setFormSeverity(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm">
                  <option value="info">Info</option>
                  <option value="warning">Warning</option>
                  <option value="critical">Critical</option>
                </select>
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-1">Window (minutes)</label>
                <input type="number" value={formWindow} onChange={e => setFormWindow(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm" />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Cooldown (minutes)</label>
                <input type="number" value={formCooldown} onChange={e => setFormCooldown(e.target.value)} className="w-full px-3 py-2 rounded-lg border border-border bg-background text-sm" />
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button onClick={() => setShowModal(false)} className="px-4 py-2 text-sm rounded-lg border border-border hover:bg-muted/50">Cancel</button>
              <button onClick={handleSave} className="px-4 py-2 text-sm rounded-lg bg-primary text-primary-foreground hover:bg-primary/90">{editing ? 'Update' : 'Create'}</button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  )
}

// ─── Forecast Tab ───────────────────────────────────────────────────

function ForecastTab() {
  const [forecast, setForecast] = useState<CostForecast | null>(null)
  const [loading, setLoading] = useState(true)
  const { showToast } = useAppStore()

  const load = useCallback(() => {
    setLoading(true)
    api.get('/observability/cost-forecast')
      .then(r => setForecast(r.data.data))
      .catch(() => showToast('Failed to load forecast', 'error'))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => { load() }, [load])

  if (loading) return <LoadingState />

  if (!forecast) {
    return <EmptyState icon={TrendingUp} title="No forecast data" description="Cost forecast will appear once you have execution run data." />
  }

  const TrendIcon = forecast.trend === 'increasing' ? TrendingUp : forecast.trend === 'decreasing' ? TrendingDown : Minus
  const trendColor = forecast.trend === 'increasing' ? 'text-red-400' : forecast.trend === 'decreasing' ? 'text-green-400' : 'text-muted-foreground'

  return (
    <div className="space-y-6">
      {/* Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <SummaryCard label="Avg Daily Cost" value={`$${forecast.avg_daily.toFixed(2)}`} icon={DollarSign} />
        <SummaryCard label="7-Day Forecast" value={`$${forecast.forecast_7d.toFixed(2)}`} icon={LineChart} />
        <SummaryCard label="30-Day Forecast" value={`$${forecast.forecast_30d.toFixed(2)}`} icon={BarChart3} />
        <div className="rounded-xl border border-border bg-card p-4">
          <div className="flex items-center gap-2 text-muted-foreground text-sm mb-2">
            <TrendIcon className={`h-4 w-4 ${trendColor}`} />
            <span>Trend</span>
          </div>
          <div className={`text-2xl font-bold capitalize ${trendColor}`}>{forecast.trend}</div>
        </div>
      </div>

      {/* Cost Chart */}
      <div className="rounded-xl border border-border bg-card p-6">
        <h3 className="text-sm font-medium text-muted-foreground mb-4">Daily Costs (Last 30 Days)</h3>
        <CostChart data={forecast.daily_costs} width={800} height={250} />
      </div>

      {/* Daily costs table */}
      <div className="rounded-xl border border-border overflow-hidden">
        <div className="p-4 border-b border-border bg-muted/30">
          <h3 className="text-sm font-medium">Daily Breakdown</h3>
        </div>
        <div className="max-h-64 overflow-y-auto">
          <table className="w-full text-sm">
            <thead className="sticky top-0 bg-card">
              <tr className="border-b border-border">
                <th className="text-left p-3 font-medium text-muted-foreground">Date</th>
                <th className="text-right p-3 font-medium text-muted-foreground">Cost</th>
              </tr>
            </thead>
            <tbody>
              {[...forecast.daily_costs].reverse().map(d => (
                <tr key={d.date} className="border-b border-border last:border-0">
                  <td className="p-3">{d.date}</td>
                  <td className="p-3 text-right font-mono">${d.cost.toFixed(2)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}

// ─── Shared Components ──────────────────────────────────────────────

function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: React.ReactNode }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div className="relative bg-card border border-border rounded-xl shadow-xl w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold">{title}</h3>
          <button onClick={onClose} className="p-1 text-muted-foreground hover:text-foreground rounded">
            <X className="h-5 w-5" />
          </button>
        </div>
        {children}
      </div>
    </div>
  )
}

function EmptyState({ icon: Icon, title, description }: { icon: React.ComponentType<{ className?: string }>; title: string; description: string }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 text-center">
      <Icon className="h-10 w-10 text-muted-foreground/50 mb-3" />
      <h3 className="font-medium text-muted-foreground">{title}</h3>
      <p className="text-sm text-muted-foreground/70 mt-1 max-w-sm">{description}</p>
    </div>
  )
}

function SummaryCard({ label, value, icon: Icon }: { label: string; value: string; icon: React.ComponentType<{ className?: string }> }) {
  return (
    <div className="rounded-xl border border-border bg-card p-4">
      <div className="flex items-center gap-2 text-muted-foreground text-sm mb-2">
        <Icon className="h-4 w-4" />
        <span>{label}</span>
      </div>
      <div className="text-2xl font-bold">{value}</div>
    </div>
  )
}

function LoadingState() {
  return (
    <div className="flex items-center justify-center py-16">
      <RefreshCw className="h-5 w-5 animate-spin text-muted-foreground" />
    </div>
  )
}

function formatMetricValue(value: number, unit?: string): string {
  if (unit === 'usd' || unit === 'USD') return `$${value.toFixed(2)}`
  if (unit === 'percent' || unit === '%') return `${value.toFixed(1)}%`
  if (unit === 'ms') return `${value.toFixed(0)}ms`
  if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`
  if (value >= 1_000) return `${(value / 1_000).toFixed(1)}K`
  return value.toFixed(value % 1 === 0 ? 0 : 2)
}
