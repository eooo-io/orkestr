import { useEffect, useState, useCallback, useRef, useMemo } from 'react'
import { useParams } from 'react-router-dom'
import {
  Database,
  Brain,
  Plus,
  Search,
  Trash2,
  Users,
  ChevronRight,
  ChevronLeft,
  ArrowLeft,
  Lock,
  Unlock,
  Shield,
  BarChart3,
  GitBranch,
  Circle,
  ZoomIn,
  ZoomOut,
  Maximize2,
  X,
} from 'lucide-react'
import api from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { useConfirm } from '@/hooks/useConfirm'

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

interface SharedMemoryPool {
  id: number
  uuid: string
  project_id: number
  name: string
  slug: string
  description: string | null
  access_policy: 'open' | 'explicit' | 'role_based'
  retention_days: number | null
  entries_count?: number
  agents_count?: number
  agents?: PoolAgent[]
  stats?: {
    entry_count: number
    agent_count: number
    avg_confidence: number
    oldest_entry: string | null
    newest_entry: string | null
  }
  created_at: string
}

interface PoolAgent {
  id: number
  name: string
  slug: string
  icon: string | null
  pivot?: { access_mode: string }
}

interface MemoryEntry {
  id: number
  uuid: string
  pool_id: number
  contributed_by_agent_id: number | null
  key: string
  content: Record<string, unknown>
  tags: string[] | null
  confidence: number
  metadata: Record<string, unknown> | null
  expires_at: string | null
  contributor?: PoolAgent | null
  created_at: string
  updated_at: string
}

interface ContributorStat {
  agent: PoolAgent | null
  entry_count: number
  last_contributed_at: string | null
}

interface GraphNode {
  id: number
  uuid: string
  entity_type: string
  entity_name: string
  properties: Record<string, unknown> | null
  pool_id: number | null
  created_at: string
}

interface GraphEdge {
  id: number
  source_node_id: number
  target_node_id: number
  relationship: string
  properties: Record<string, unknown> | null
  weight: number
  created_at: string
}

interface AvailableAgent {
  id: number
  name: string
  slug: string
  icon: string | null
}

/* ------------------------------------------------------------------ */
/*  Policy badge                                                       */
/* ------------------------------------------------------------------ */

const POLICY_STYLES: Record<string, { bg: string; icon: React.ReactNode; label: string }> = {
  open: { bg: 'bg-green-500/10 text-green-600', icon: <Unlock className="h-3 w-3" />, label: 'Open' },
  explicit: { bg: 'bg-blue-500/10 text-blue-600', icon: <Lock className="h-3 w-3" />, label: 'Explicit' },
  role_based: { bg: 'bg-purple-500/10 text-purple-600', icon: <Shield className="h-3 w-3" />, label: 'Role-Based' },
}

function PolicyBadge({ policy }: { policy: string }) {
  const s = POLICY_STYLES[policy] || POLICY_STYLES.explicit
  return (
    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${s.bg}`}>
      {s.icon}
      {s.label}
    </span>
  )
}

/* ------------------------------------------------------------------ */
/*  Entity type colors for graph nodes                                 */
/* ------------------------------------------------------------------ */

const TYPE_COLORS: Record<string, string> = {
  agent: '#6366f1',
  skill: '#10b981',
  tool: '#f59e0b',
  concept: '#ec4899',
  document: '#3b82f6',
  task: '#8b5cf6',
  default: '#6b7280',
}

function colorForType(t: string): string {
  return TYPE_COLORS[t.toLowerCase()] || TYPE_COLORS.default
}

/* ------------------------------------------------------------------ */
/*  Main component                                                     */
/* ------------------------------------------------------------------ */

export function SharedMemory() {
  const { id } = useParams<{ id: string }>()
  const projectId = parseInt(id || '0')
  const [tab, setTab] = useState<'pools' | 'graph'>('pools')

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Shared Memory</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Cross-agent memory pools and knowledge graph
          </p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 border-b border-border">
        <button
          onClick={() => setTab('pools')}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            tab === 'pools'
              ? 'border-primary text-primary'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <Database className="h-4 w-4 inline mr-1.5 -mt-0.5" />
          Memory Pools
        </button>
        <button
          onClick={() => setTab('graph')}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            tab === 'graph'
              ? 'border-primary text-primary'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <Brain className="h-4 w-4 inline mr-1.5 -mt-0.5" />
          Knowledge Graph
        </button>
      </div>

      {tab === 'pools' && <MemoryPoolsTab projectId={projectId} />}
      {tab === 'graph' && <KnowledgeGraphTab projectId={projectId} />}
    </div>
  )
}

/* ================================================================== */
/*  MEMORY POOLS TAB                                                  */
/* ================================================================== */

function MemoryPoolsTab({ projectId }: { projectId: number }) {
  const [pools, setPools] = useState<SharedMemoryPool[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreate, setShowCreate] = useState(false)
  const [selectedPool, setSelectedPool] = useState<SharedMemoryPool | null>(null)
  const { showToast } = useAppStore()
  const confirm = useConfirm()

  const loadPools = useCallback(() => {
    api.get(`/api/projects/${projectId}/shared-memory`).then((r) => {
      setPools(r.data.data)
      setLoading(false)
    })
  }, [projectId])

  useEffect(() => {
    loadPools()
  }, [loadPools])

  const handleCreate = async (data: { name: string; description: string; access_policy: string; retention_days: string }) => {
    await api.post(`/api/projects/${projectId}/shared-memory`, {
      name: data.name,
      description: data.description || null,
      access_policy: data.access_policy,
      retention_days: data.retention_days ? parseInt(data.retention_days) : null,
    })
    showToast('Pool created')
    setShowCreate(false)
    loadPools()
  }

  const handleDelete = async (pool: SharedMemoryPool) => {
    if (!(await confirm({ message: `Delete pool "${pool.name}" and all its entries?`, title: 'Delete Pool' }))) return
    await api.delete(`/api/shared-memory/${pool.id}`)
    showToast('Pool deleted')
    if (selectedPool?.id === pool.id) setSelectedPool(null)
    loadPools()
  }

  if (selectedPool) {
    return (
      <PoolDetailView
        pool={selectedPool}
        projectId={projectId}
        onBack={() => { setSelectedPool(null); loadPools() }}
      />
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">{pools.length} pool{pools.length !== 1 ? 's' : ''}</p>
        <button
          onClick={() => setShowCreate(true)}
          className="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
        >
          <Plus className="h-4 w-4" />
          Create Pool
        </button>
      </div>

      {loading && <p className="text-sm text-muted-foreground">Loading...</p>}

      {!loading && pools.length === 0 && (
        <div className="rounded-lg border border-dashed border-border p-12 text-center">
          <Database className="h-12 w-12 mx-auto text-muted-foreground/50 mb-3" />
          <p className="text-muted-foreground">No shared memory pools yet</p>
          <p className="text-xs text-muted-foreground mt-1">Create a pool to share memory between agents</p>
        </div>
      )}

      <div className="grid gap-3">
        {pools.map((pool) => (
          <div
            key={pool.id}
            className="rounded-lg border border-border bg-card p-4 hover:bg-accent/50 transition-colors cursor-pointer"
            onClick={() => setSelectedPool(pool)}
          >
            <div className="flex items-start justify-between">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <h3 className="text-sm font-semibold truncate">{pool.name}</h3>
                  <PolicyBadge policy={pool.access_policy} />
                </div>
                {pool.description && (
                  <p className="text-xs text-muted-foreground mt-1 line-clamp-1">{pool.description}</p>
                )}
                <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                  <span className="flex items-center gap-1">
                    <Database className="h-3 w-3" />
                    {pool.entries_count ?? 0} entries
                  </span>
                  <span className="flex items-center gap-1">
                    <Users className="h-3 w-3" />
                    {pool.agents_count ?? 0} agents
                  </span>
                  {pool.retention_days && (
                    <span>Retention: {pool.retention_days}d</span>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-1 ml-2">
                <button
                  onClick={(e) => { e.stopPropagation(); handleDelete(pool) }}
                  className="rounded p-1 text-muted-foreground hover:text-red-500 hover:bg-red-500/10"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
                <ChevronRight className="h-4 w-4 text-muted-foreground" />
              </div>
            </div>
          </div>
        ))}
      </div>

      {showCreate && (
        <CreatePoolModal onClose={() => setShowCreate(false)} onCreate={handleCreate} />
      )}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Create Pool Modal                                                  */
/* ------------------------------------------------------------------ */

function CreatePoolModal({ onClose, onCreate }: {
  onClose: () => void
  onCreate: (data: { name: string; description: string; access_policy: string; retention_days: string }) => void
}) {
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [accessPolicy, setAccessPolicy] = useState('explicit')
  const [retentionDays, setRetentionDays] = useState('')

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
      <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <h2 className="text-lg font-semibold mb-4">Create Memory Pool</h2>
        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium mb-1">Name</label>
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              placeholder="e.g., Project Knowledge"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Description</label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              rows={2}
              placeholder="What this pool is used for..."
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Access Policy</label>
            <select
              value={accessPolicy}
              onChange={(e) => setAccessPolicy(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
            >
              <option value="open">Open - Any agent can access</option>
              <option value="explicit">Explicit - Must be added to pool</option>
              <option value="role_based">Role-Based - Access by role</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Retention (days, optional)</label>
            <input
              type="number"
              value={retentionDays}
              onChange={(e) => setRetentionDays(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              placeholder="Leave empty for indefinite"
            />
          </div>
        </div>
        <div className="flex justify-end gap-2 mt-5">
          <button onClick={onClose} className="rounded-md px-3 py-1.5 text-sm border border-border hover:bg-accent">Cancel</button>
          <button
            onClick={() => onCreate({ name, description, access_policy: accessPolicy, retention_days: retentionDays })}
            disabled={!name.trim()}
            className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
          >
            Create
          </button>
        </div>
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Pool Detail View                                                   */
/* ------------------------------------------------------------------ */

function PoolDetailView({ pool, projectId, onBack }: {
  pool: SharedMemoryPool
  projectId: number
  onBack: () => void
}) {
  const [detail, setDetail] = useState<SharedMemoryPool | null>(null)
  const [entries, setEntries] = useState<MemoryEntry[]>([])
  const [contributors, setContributors] = useState<ContributorStat[]>([])
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(true)
  const [agents, setAgents] = useState<AvailableAgent[]>([])
  const [showAddAgent, setShowAddAgent] = useState(false)
  const [addAgentId, setAddAgentId] = useState('')
  const [addAccessMode, setAddAccessMode] = useState('write')
  const { showToast } = useAppStore()

  const loadDetail = useCallback(() => {
    Promise.all([
      api.get(`/api/shared-memory/${pool.id}`),
      api.get(`/api/shared-memory/${pool.id}/entries`, { params: { q: search || undefined } }),
      api.get(`/api/shared-memory/${pool.id}/contributors`),
      api.get('/api/agents'),
    ]).then(([detailR, entriesR, contribR, agentsR]) => {
      setDetail(detailR.data.data)
      setEntries(entriesR.data.data || [])
      setContributors(contribR.data.data)
      setAgents(agentsR.data.data || [])
      setLoading(false)
    })
  }, [pool.id, search])

  useEffect(() => {
    loadDetail()
  }, [loadDetail])

  const handleAddAgent = async () => {
    if (!addAgentId) return
    await api.post(`/api/shared-memory/${pool.id}/agents`, {
      agent_id: parseInt(addAgentId),
      access_mode: addAccessMode,
    })
    showToast('Agent added to pool')
    setShowAddAgent(false)
    setAddAgentId('')
    loadDetail()
  }

  const handleRemoveAgent = async (agentId: number) => {
    await api.delete(`/api/shared-memory/${pool.id}/agents/${agentId}`)
    showToast('Agent removed from pool')
    loadDetail()
  }

  const maxContribCount = Math.max(1, ...contributors.map((c) => c.entry_count))

  return (
    <div className="space-y-5">
      <button onClick={onBack} className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="h-4 w-4" />
        Back to pools
      </button>

      {loading ? (
        <p className="text-sm text-muted-foreground">Loading...</p>
      ) : (
        <>
          {/* Header */}
          <div className="flex items-start justify-between">
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-xl font-bold">{detail?.name || pool.name}</h2>
                <PolicyBadge policy={detail?.access_policy || pool.access_policy} />
              </div>
              {detail?.description && (
                <p className="text-sm text-muted-foreground mt-1">{detail.description}</p>
              )}
            </div>
          </div>

          {/* Stats row */}
          {detail?.stats && (
            <div className="grid grid-cols-3 gap-3">
              <div className="rounded-lg border border-border p-3 text-center">
                <p className="text-2xl font-bold">{detail.stats.entry_count}</p>
                <p className="text-xs text-muted-foreground">Entries</p>
              </div>
              <div className="rounded-lg border border-border p-3 text-center">
                <p className="text-2xl font-bold">{detail.stats.agent_count}</p>
                <p className="text-xs text-muted-foreground">Agents</p>
              </div>
              <div className="rounded-lg border border-border p-3 text-center">
                <p className="text-2xl font-bold">{detail.stats.avg_confidence || 0}</p>
                <p className="text-xs text-muted-foreground">Avg Confidence</p>
              </div>
            </div>
          )}

          {/* Agent assignment */}
          <div className="rounded-lg border border-border p-4">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-sm font-semibold flex items-center gap-1.5">
                <Users className="h-4 w-4" />
                Assigned Agents
              </h3>
              <button
                onClick={() => setShowAddAgent(!showAddAgent)}
                className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
              >
                <Plus className="h-3 w-3" />
                Add Agent
              </button>
            </div>

            {showAddAgent && (
              <div className="flex items-center gap-2 mb-3 p-2 rounded bg-muted/50">
                <select
                  value={addAgentId}
                  onChange={(e) => setAddAgentId(e.target.value)}
                  className="flex-1 rounded border border-border bg-background px-2 py-1 text-sm"
                >
                  <option value="">Select agent...</option>
                  {agents.map((a) => (
                    <option key={a.id} value={a.id}>{a.name}</option>
                  ))}
                </select>
                <select
                  value={addAccessMode}
                  onChange={(e) => setAddAccessMode(e.target.value)}
                  className="rounded border border-border bg-background px-2 py-1 text-sm"
                >
                  <option value="read">Read</option>
                  <option value="write">Write</option>
                  <option value="admin">Admin</option>
                </select>
                <button
                  onClick={handleAddAgent}
                  disabled={!addAgentId}
                  className="rounded bg-primary px-2 py-1 text-xs font-medium text-primary-foreground disabled:opacity-50"
                >
                  Add
                </button>
              </div>
            )}

            {detail?.agents && detail.agents.length > 0 ? (
              <div className="space-y-1">
                {detail.agents.map((agent) => (
                  <div key={agent.id} className="flex items-center justify-between py-1.5 px-2 rounded hover:bg-accent/50">
                    <div className="flex items-center gap-2">
                      <span className="text-sm">{agent.icon || '🤖'}</span>
                      <span className="text-sm font-medium">{agent.name}</span>
                      <span className="text-xs text-muted-foreground capitalize">
                        ({agent.pivot?.access_mode || 'write'})
                      </span>
                    </div>
                    <button
                      onClick={() => handleRemoveAgent(agent.id)}
                      className="text-muted-foreground hover:text-red-500"
                    >
                      <X className="h-3.5 w-3.5" />
                    </button>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-muted-foreground">No agents assigned</p>
            )}
          </div>

          {/* Contributor stats bar chart */}
          {contributors.length > 0 && (
            <div className="rounded-lg border border-border p-4">
              <h3 className="text-sm font-semibold flex items-center gap-1.5 mb-3">
                <BarChart3 className="h-4 w-4" />
                Contributions
              </h3>
              <div className="space-y-2">
                {contributors.map((c, idx) => (
                  <div key={idx} className="flex items-center gap-3">
                    <span className="text-xs w-24 truncate text-right text-muted-foreground">
                      {c.agent?.name || 'Unknown'}
                    </span>
                    <div className="flex-1 h-5 bg-muted rounded-sm overflow-hidden">
                      <div
                        className="h-full bg-primary/70 rounded-sm transition-all"
                        style={{ width: `${(c.entry_count / maxContribCount) * 100}%` }}
                      />
                    </div>
                    <span className="text-xs text-muted-foreground w-8">{c.entry_count}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Entry browser */}
          <div>
            <div className="flex items-center gap-2 mb-3">
              <div className="relative flex-1">
                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="w-full rounded-md border border-border bg-background pl-9 pr-3 py-1.5 text-sm"
                  placeholder="Search entries..."
                />
              </div>
            </div>

            {entries.length === 0 && (
              <p className="text-sm text-muted-foreground text-center py-8">No entries found</p>
            )}

            <div className="space-y-2">
              {entries.map((entry) => (
                <div key={entry.id} className="rounded-lg border border-border p-3">
                  <div className="flex items-start justify-between">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-mono font-medium truncate">{entry.key}</span>
                        <span className="text-xs text-muted-foreground">
                          conf: {entry.confidence}
                        </span>
                      </div>
                      <pre className="text-xs text-muted-foreground mt-1 whitespace-pre-wrap max-h-20 overflow-y-auto">
                        {JSON.stringify(entry.content, null, 2)}
                      </pre>
                      <div className="flex items-center gap-3 mt-1.5">
                        {entry.contributor && (
                          <span className="text-xs text-muted-foreground">
                            by {entry.contributor.name}
                          </span>
                        )}
                        {entry.tags && entry.tags.length > 0 && (
                          <div className="flex gap-1">
                            {entry.tags.map((t) => (
                              <span key={t} className="rounded bg-muted px-1.5 py-0.5 text-[10px]">{t}</span>
                            ))}
                          </div>
                        )}
                        {entry.expires_at && (
                          <span className="text-[10px] text-yellow-600">
                            Expires: {new Date(entry.expires_at).toLocaleDateString()}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </>
      )}
    </div>
  )
}

/* ================================================================== */
/*  KNOWLEDGE GRAPH TAB                                               */
/* ================================================================== */

interface SimNode extends GraphNode {
  x: number
  y: number
  vx: number
  vy: number
}

function KnowledgeGraphTab({ projectId }: { projectId: number }) {
  const [nodes, setNodes] = useState<GraphNode[]>([])
  const [edges, setEdges] = useState<GraphEdge[]>([])
  const [loading, setLoading] = useState(true)
  const [selectedNode, setSelectedNode] = useState<GraphNode | null>(null)
  const [showAddNode, setShowAddNode] = useState(false)
  const [showAddEdge, setShowAddEdge] = useState(false)
  const { showToast } = useAppStore()
  const confirm = useConfirm()

  const loadGraph = useCallback(() => {
    api.get(`/api/projects/${projectId}/knowledge-graph`).then((r) => {
      setNodes(r.data.data.nodes || [])
      setEdges(r.data.data.edges || [])
      setLoading(false)
    })
  }, [projectId])

  useEffect(() => {
    loadGraph()
  }, [loadGraph])

  const handleAddNode = async (data: { entity_type: string; entity_name: string; properties: string }) => {
    let props = null
    if (data.properties.trim()) {
      try {
        props = JSON.parse(data.properties)
      } catch {
        showToast('Invalid JSON for properties')
        return
      }
    }
    await api.post(`/api/projects/${projectId}/knowledge-graph/nodes`, {
      entity_type: data.entity_type,
      entity_name: data.entity_name,
      properties: props,
    })
    showToast('Node added')
    setShowAddNode(false)
    loadGraph()
  }

  const handleAddEdge = async (data: { source_node_id: string; target_node_id: string; relationship: string; weight: string }) => {
    await api.post(`/api/projects/${projectId}/knowledge-graph/edges`, {
      source_node_id: parseInt(data.source_node_id),
      target_node_id: parseInt(data.target_node_id),
      relationship: data.relationship,
      weight: parseFloat(data.weight) || 1.0,
    })
    showToast('Edge added')
    setShowAddEdge(false)
    loadGraph()
  }

  const handleDeleteNode = async (node: GraphNode) => {
    if (!(await confirm({ message: `Delete node "${node.entity_name}"?`, title: 'Delete Node' }))) return
    await api.delete(`/api/knowledge-graph/nodes/${node.id}`)
    showToast('Node deleted')
    if (selectedNode?.id === node.id) setSelectedNode(null)
    loadGraph()
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">
          {nodes.length} node{nodes.length !== 1 ? 's' : ''}, {edges.length} edge{edges.length !== 1 ? 's' : ''}
        </p>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setShowAddNode(true)}
            className="inline-flex items-center gap-1 rounded-md border border-border px-3 py-1.5 text-sm hover:bg-accent"
          >
            <Plus className="h-4 w-4" />
            Node
          </button>
          <button
            onClick={() => setShowAddEdge(true)}
            disabled={nodes.length < 2}
            className="inline-flex items-center gap-1 rounded-md border border-border px-3 py-1.5 text-sm hover:bg-accent disabled:opacity-50"
          >
            <GitBranch className="h-4 w-4" />
            Edge
          </button>
        </div>
      </div>

      {loading && <p className="text-sm text-muted-foreground">Loading...</p>}

      {!loading && nodes.length === 0 && (
        <div className="rounded-lg border border-dashed border-border p-12 text-center">
          <Brain className="h-12 w-12 mx-auto text-muted-foreground/50 mb-3" />
          <p className="text-muted-foreground">No knowledge graph nodes yet</p>
          <p className="text-xs text-muted-foreground mt-1">Add nodes and edges to build the graph</p>
        </div>
      )}

      {!loading && nodes.length > 0 && (
        <div className="flex gap-4">
          <div className="flex-1">
            <ForceGraph
              nodes={nodes}
              edges={edges}
              selectedNodeId={selectedNode?.id ?? null}
              onSelectNode={(n) => setSelectedNode(n)}
            />
          </div>
          {selectedNode && (
            <div className="w-72 shrink-0 rounded-lg border border-border p-4 bg-card">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-semibold">Node Details</h3>
                <button onClick={() => setSelectedNode(null)} className="text-muted-foreground hover:text-foreground">
                  <X className="h-4 w-4" />
                </button>
              </div>
              <div className="space-y-2 text-sm">
                <div>
                  <span className="text-xs text-muted-foreground">Name</span>
                  <p className="font-medium">{selectedNode.entity_name}</p>
                </div>
                <div>
                  <span className="text-xs text-muted-foreground">Type</span>
                  <p className="flex items-center gap-1.5">
                    <Circle className="h-3 w-3" style={{ color: colorForType(selectedNode.entity_type), fill: colorForType(selectedNode.entity_type) }} />
                    {selectedNode.entity_type}
                  </p>
                </div>
                {selectedNode.properties && Object.keys(selectedNode.properties).length > 0 && (
                  <div>
                    <span className="text-xs text-muted-foreground">Properties</span>
                    <pre className="text-xs mt-1 p-2 rounded bg-muted overflow-x-auto">
                      {JSON.stringify(selectedNode.properties, null, 2)}
                    </pre>
                  </div>
                )}
                <div className="pt-2">
                  <button
                    onClick={() => handleDeleteNode(selectedNode)}
                    className="inline-flex items-center gap-1 text-xs text-red-500 hover:underline"
                  >
                    <Trash2 className="h-3 w-3" />
                    Delete node
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {showAddNode && (
        <AddNodeModal onClose={() => setShowAddNode(false)} onCreate={handleAddNode} />
      )}
      {showAddEdge && (
        <AddEdgeModal nodes={nodes} onClose={() => setShowAddEdge(false)} onCreate={handleAddEdge} />
      )}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Add Node Modal                                                     */
/* ------------------------------------------------------------------ */

function AddNodeModal({ onClose, onCreate }: {
  onClose: () => void
  onCreate: (data: { entity_type: string; entity_name: string; properties: string }) => void
}) {
  const [entityType, setEntityType] = useState('concept')
  const [entityName, setEntityName] = useState('')
  const [properties, setProperties] = useState('')

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
      <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <h2 className="text-lg font-semibold mb-4">Add Node</h2>
        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium mb-1">Entity Type</label>
            <select
              value={entityType}
              onChange={(e) => setEntityType(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
            >
              <option value="concept">Concept</option>
              <option value="agent">Agent</option>
              <option value="skill">Skill</option>
              <option value="tool">Tool</option>
              <option value="document">Document</option>
              <option value="task">Task</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Entity Name</label>
            <input
              value={entityName}
              onChange={(e) => setEntityName(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              placeholder="e.g., Code Review"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Properties (JSON, optional)</label>
            <textarea
              value={properties}
              onChange={(e) => setProperties(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm font-mono"
              rows={3}
              placeholder='{"key": "value"}'
            />
          </div>
        </div>
        <div className="flex justify-end gap-2 mt-5">
          <button onClick={onClose} className="rounded-md px-3 py-1.5 text-sm border border-border hover:bg-accent">Cancel</button>
          <button
            onClick={() => onCreate({ entity_type: entityType, entity_name: entityName, properties })}
            disabled={!entityName.trim()}
            className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
          >
            Add Node
          </button>
        </div>
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Add Edge Modal                                                     */
/* ------------------------------------------------------------------ */

function AddEdgeModal({ nodes, onClose, onCreate }: {
  nodes: GraphNode[]
  onClose: () => void
  onCreate: (data: { source_node_id: string; target_node_id: string; relationship: string; weight: string }) => void
}) {
  const [sourceId, setSourceId] = useState('')
  const [targetId, setTargetId] = useState('')
  const [relationship, setRelationship] = useState('')
  const [weight, setWeight] = useState('1.0')

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={onClose}>
      <div className="w-full max-w-md rounded-lg border border-border bg-background p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
        <h2 className="text-lg font-semibold mb-4">Add Edge</h2>
        <div className="space-y-3">
          <div>
            <label className="block text-sm font-medium mb-1">Source Node</label>
            <select
              value={sourceId}
              onChange={(e) => setSourceId(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
            >
              <option value="">Select...</option>
              {nodes.map((n) => (
                <option key={n.id} value={n.id}>{n.entity_name} ({n.entity_type})</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Target Node</label>
            <select
              value={targetId}
              onChange={(e) => setTargetId(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
            >
              <option value="">Select...</option>
              {nodes.map((n) => (
                <option key={n.id} value={n.id}>{n.entity_name} ({n.entity_type})</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Relationship</label>
            <input
              value={relationship}
              onChange={(e) => setRelationship(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              placeholder="e.g., depends_on, uses, related_to"
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Weight (0-1)</label>
            <input
              type="number"
              step="0.1"
              min="0"
              max="1"
              value={weight}
              onChange={(e) => setWeight(e.target.value)}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
            />
          </div>
        </div>
        <div className="flex justify-end gap-2 mt-5">
          <button onClick={onClose} className="rounded-md px-3 py-1.5 text-sm border border-border hover:bg-accent">Cancel</button>
          <button
            onClick={() => onCreate({ source_node_id: sourceId, target_node_id: targetId, relationship, weight })}
            disabled={!sourceId || !targetId || !relationship.trim()}
            className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
          >
            Add Edge
          </button>
        </div>
      </div>
    </div>
  )
}

/* ================================================================== */
/*  Force-directed graph SVG                                           */
/* ================================================================== */

function ForceGraph({ nodes, edges, selectedNodeId, onSelectNode }: {
  nodes: GraphNode[]
  edges: GraphEdge[]
  selectedNodeId: number | null
  onSelectNode: (n: GraphNode | null) => void
}) {
  const svgRef = useRef<SVGSVGElement>(null)
  const [zoom, setZoom] = useState(1)
  const [pan, setPan] = useState({ x: 0, y: 0 })
  const [dragging, setDragging] = useState<{ type: 'pan' | 'node'; nodeId?: number; startX: number; startY: number } | null>(null)
  const [simNodes, setSimNodes] = useState<SimNode[]>([])

  // Initialize node positions
  useEffect(() => {
    const width = 600
    const height = 400
    const initialized: SimNode[] = nodes.map((n, i) => ({
      ...n,
      x: width / 2 + (Math.cos((i / nodes.length) * Math.PI * 2) * 150),
      y: height / 2 + (Math.sin((i / nodes.length) * Math.PI * 2) * 150),
      vx: 0,
      vy: 0,
    }))
    setSimNodes(initialized)
  }, [nodes])

  // Simple force simulation
  useEffect(() => {
    if (simNodes.length === 0) return

    const nodeMap = new Map(simNodes.map((n) => [n.id, n]))
    let running = true

    function tick() {
      if (!running) return

      setSimNodes((prev) => {
        const next = prev.map((n) => ({ ...n }))
        const map = new Map(next.map((n) => [n.id, n]))

        // Repulsion between all nodes
        for (let i = 0; i < next.length; i++) {
          for (let j = i + 1; j < next.length; j++) {
            const a = next[i]
            const b = next[j]
            let dx = b.x - a.x
            let dy = b.y - a.y
            const dist = Math.sqrt(dx * dx + dy * dy) || 1
            const force = 2000 / (dist * dist)
            dx = (dx / dist) * force
            dy = (dy / dist) * force
            a.vx -= dx
            a.vy -= dy
            b.vx += dx
            b.vy += dy
          }
        }

        // Attraction along edges
        for (const edge of edges) {
          const source = map.get(edge.source_node_id)
          const target = map.get(edge.target_node_id)
          if (!source || !target) continue
          let dx = target.x - source.x
          let dy = target.y - source.y
          const dist = Math.sqrt(dx * dx + dy * dy) || 1
          const force = (dist - 120) * 0.01
          dx = (dx / dist) * force
          dy = (dy / dist) * force
          source.vx += dx
          source.vy += dy
          target.vx -= dx
          target.vy -= dy
        }

        // Center gravity
        const cx = 300
        const cy = 200
        for (const n of next) {
          n.vx += (cx - n.x) * 0.001
          n.vy += (cy - n.y) * 0.001
        }

        // Apply velocity with damping
        for (const n of next) {
          n.vx *= 0.85
          n.vy *= 0.85
          n.x += n.vx
          n.y += n.vy
        }

        return next
      })

      requestAnimationFrame(tick)
    }

    // Run for limited time then stop
    const handle = requestAnimationFrame(tick)
    const timeout = setTimeout(() => { running = false }, 3000)

    return () => {
      running = false
      cancelAnimationFrame(handle)
      clearTimeout(timeout)
    }
  }, [simNodes.length, edges])

  const nodeMap = useMemo(() => new Map(simNodes.map((n) => [n.id, n])), [simNodes])

  const handleMouseDown = (e: React.MouseEvent) => {
    if (e.button !== 0) return
    setDragging({ type: 'pan', startX: e.clientX - pan.x, startY: e.clientY - pan.y })
  }

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!dragging) return
    if (dragging.type === 'pan') {
      setPan({ x: e.clientX - dragging.startX, y: e.clientY - dragging.startY })
    }
  }

  const handleMouseUp = () => {
    setDragging(null)
  }

  const handleZoomIn = () => setZoom((z) => Math.min(z * 1.2, 3))
  const handleZoomOut = () => setZoom((z) => Math.max(z / 1.2, 0.3))
  const handleReset = () => { setZoom(1); setPan({ x: 0, y: 0 }) }

  return (
    <div className="relative rounded-lg border border-border bg-card overflow-hidden" style={{ height: 450 }}>
      {/* Controls */}
      <div className="absolute top-2 right-2 z-10 flex gap-1">
        <button onClick={handleZoomIn} className="rounded p-1.5 bg-background border border-border hover:bg-accent" title="Zoom in">
          <ZoomIn className="h-4 w-4" />
        </button>
        <button onClick={handleZoomOut} className="rounded p-1.5 bg-background border border-border hover:bg-accent" title="Zoom out">
          <ZoomOut className="h-4 w-4" />
        </button>
        <button onClick={handleReset} className="rounded p-1.5 bg-background border border-border hover:bg-accent" title="Reset view">
          <Maximize2 className="h-4 w-4" />
        </button>
      </div>

      <svg
        ref={svgRef}
        width="100%"
        height="100%"
        viewBox="0 0 600 400"
        onMouseDown={handleMouseDown}
        onMouseMove={handleMouseMove}
        onMouseUp={handleMouseUp}
        onMouseLeave={handleMouseUp}
        style={{ cursor: dragging ? 'grabbing' : 'grab' }}
      >
        <g transform={`translate(${pan.x}, ${pan.y}) scale(${zoom})`}>
          {/* Edges */}
          {edges.map((edge) => {
            const source = nodeMap.get(edge.source_node_id)
            const target = nodeMap.get(edge.target_node_id)
            if (!source || !target) return null
            return (
              <g key={edge.id}>
                <line
                  x1={source.x}
                  y1={source.y}
                  x2={target.x}
                  y2={target.y}
                  stroke="var(--border)"
                  strokeWidth={Math.max(1, edge.weight * 2)}
                  strokeOpacity={0.6}
                />
                {/* Edge label */}
                <text
                  x={(source.x + target.x) / 2}
                  y={(source.y + target.y) / 2 - 4}
                  textAnchor="middle"
                  fill="var(--muted-foreground)"
                  fontSize={8}
                  opacity={0.7}
                >
                  {edge.relationship}
                </text>
              </g>
            )
          })}

          {/* Nodes */}
          {simNodes.map((node) => {
            const isSelected = node.id === selectedNodeId
            const color = colorForType(node.entity_type)
            return (
              <g
                key={node.id}
                onClick={(e) => { e.stopPropagation(); onSelectNode(isSelected ? null : node) }}
                style={{ cursor: 'pointer' }}
              >
                <circle
                  cx={node.x}
                  cy={node.y}
                  r={isSelected ? 16 : 12}
                  fill={color}
                  fillOpacity={isSelected ? 1 : 0.8}
                  stroke={isSelected ? 'var(--primary)' : 'none'}
                  strokeWidth={isSelected ? 2 : 0}
                />
                <text
                  x={node.x}
                  y={node.y + 22}
                  textAnchor="middle"
                  fill="var(--foreground)"
                  fontSize={10}
                  fontWeight={isSelected ? 600 : 400}
                >
                  {node.entity_name}
                </text>
              </g>
            )
          })}
        </g>
      </svg>

      {/* Legend */}
      <div className="absolute bottom-2 left-2 flex flex-wrap gap-2">
        {Object.entries(TYPE_COLORS).filter(([k]) => k !== 'default').map(([type, color]) => (
          <span key={type} className="inline-flex items-center gap-1 text-[10px] text-muted-foreground">
            <Circle className="h-2.5 w-2.5" style={{ color, fill: color }} />
            {type}
          </span>
        ))}
      </div>
    </div>
  )
}
