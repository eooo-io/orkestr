import { useState, useEffect, useCallback } from 'react'
import {
  Bot,
  Download,
  ExternalLink,
  Filter,
  Loader2,
  Package,
  Search,
  Tag,
  ThumbsDown,
  ThumbsUp,
  Upload,
  User,
  Wrench,
  X,
  Workflow,
  Shield,
  Cpu,
  ChevronDown,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAppStore } from '@/store/useAppStore'
import { fetchProjects } from '@/api/client'
import type { Project } from '@/types'
import api from '@/api/client'

// ---- Types ----

interface MarketplaceAgentListing {
  id: number
  uuid: string
  name: string
  slug: string
  description: string | null
  category: string | null
  tags: string[]
  agent_config: Record<string, unknown>
  skills_config: Array<Record<string, unknown>>
  workflow_config: Record<string, unknown> | null
  wiring_config: Record<string, unknown> | null
  author: string
  author_url: string | null
  source: string | null
  version: string
  downloads: number
  upvotes: number
  downvotes: number
  screenshots: string[] | null
  readme: string | null
  published_by: number | null
  created_at: string
  updated_at: string
}

interface AgentPreview {
  agent: {
    name: string
    role: string | null
    description: string | null
    model: string | null
    icon: string | null
    planning_mode: string | null
    context_strategy: string | null
    max_iterations: number | null
    can_delegate: boolean
  }
  skills: Array<{
    name: string
    description: string | null
    model: string | null
    tool_count: number
  }>
  skill_count: number
  tool_count: number
  workflow: {
    name: string
    description: string | null
    step_count: number
    trigger_type: string | null
  } | null
  wiring: {
    mcp_server_count: number
    a2a_agent_count: number
  } | null
  version: string
  author: string
  author_url: string | null
  downloads: number
  upvotes: number
  readme: string | null
}

interface PaginatedResponse {
  data: MarketplaceAgentListing[]
  current_page: number
  last_page: number
  total: number
}

// ---- API helpers (local, not in shared client.ts) ----

async function fetchMarketplaceAgents(params: {
  q?: string
  category?: string
  sort?: string
  page?: number
}): Promise<PaginatedResponse> {
  const { data } = await api.get('/marketplace-agents', { params })
  return data
}

async function fetchAgentPreview(id: number): Promise<AgentPreview> {
  const { data } = await api.get(`/marketplace-agents/${id}/preview`)
  return data.data
}

async function voteMarketplaceAgent(
  id: number,
  direction: 'up' | 'down'
): Promise<MarketplaceAgentListing> {
  const { data } = await api.post(`/marketplace-agents/${id}/vote`, { direction })
  return data.data
}

async function installMarketplaceAgent(
  id: number,
  projectId: number
): Promise<void> {
  await api.post(`/marketplace-agents/${id}/install`, { project_id: projectId })
}

async function publishAgentToMarketplace(payload: {
  agent_id: number
  project_id: number
  description?: string
  category?: string
  tags?: string[]
  author: string
  author_url?: string
  version?: string
  readme?: string
}): Promise<MarketplaceAgentListing> {
  const { data } = await api.post('/marketplace-agents/publish', payload)
  return data.data
}

// ---- Categories ----

const CATEGORIES = [
  'All',
  'Coding',
  'Writing',
  'Research',
  'Data Analysis',
  'DevOps',
  'Design',
  'Customer Support',
  'Sales',
  'Other',
]

// ---- Main Component ----

export function AgentMarketplace() {
  const [listings, setListings] = useState<MarketplaceAgentListing[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('All')
  const [sort, setSort] = useState('newest')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  const [selectedListing, setSelectedListing] = useState<MarketplaceAgentListing | null>(null)
  const [preview, setPreview] = useState<AgentPreview | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)

  const [showPublish, setShowPublish] = useState(false)

  const { showToast } = useAppStore()

  const loadListings = useCallback(async () => {
    setLoading(true)
    try {
      const res = await fetchMarketplaceAgents({
        q: search || undefined,
        category: category !== 'All' ? category : undefined,
        sort,
        page,
      })
      setListings(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      showToast('Failed to load agent templates', 'error')
    } finally {
      setLoading(false)
    }
  }, [search, category, sort, page, showToast])

  useEffect(() => {
    loadListings()
  }, [loadListings])

  // Debounced search
  useEffect(() => {
    setPage(1)
  }, [search, category, sort])

  const handleSelect = async (listing: MarketplaceAgentListing) => {
    setSelectedListing(listing)
    setPreview(null)
    setPreviewLoading(true)
    try {
      const p = await fetchAgentPreview(listing.id)
      setPreview(p)
    } catch {
      showToast('Failed to load preview', 'error')
    } finally {
      setPreviewLoading(false)
    }
  }

  const handleVote = async (listing: MarketplaceAgentListing, direction: 'up' | 'down') => {
    try {
      const updated = await voteMarketplaceAgent(listing.id, direction)
      setListings((prev) =>
        prev.map((l) => (l.id === listing.id ? { ...l, upvotes: updated.upvotes, downvotes: updated.downvotes } : l))
      )
      if (selectedListing?.id === listing.id) {
        setSelectedListing((prev) => prev ? { ...prev, upvotes: updated.upvotes, downvotes: updated.downvotes } : null)
      }
    } catch {
      showToast('Vote failed', 'error')
    }
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold">Agent Templates</h2>
          <p className="text-xs text-muted-foreground mt-0.5">
            Pre-built agent configurations with bundled skills and workflows
          </p>
        </div>
        <Button size="sm" onClick={() => setShowPublish(true)}>
          <Upload className="h-3.5 w-3.5 mr-1" />
          Publish Agent
        </Button>
      </div>

      {/* Search + Filters */}
      <div className="flex items-center gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search agent templates..."
            className="w-full pl-8 pr-3 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
          />
        </div>

        <div className="relative">
          <select
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            className="appearance-none pl-3 pr-7 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
          >
            {CATEGORIES.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </select>
          <Filter className="absolute right-2 top-1/2 -translate-y-1/2 h-3 w-3 text-muted-foreground pointer-events-none" />
        </div>

        <div className="relative">
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="appearance-none pl-3 pr-7 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
          >
            <option value="newest">Newest</option>
            <option value="popular">Most Downloaded</option>
            <option value="top-rated">Top Rated</option>
          </select>
          <ChevronDown className="absolute right-2 top-1/2 -translate-y-1/2 h-3 w-3 text-muted-foreground pointer-events-none" />
        </div>
      </div>

      {/* Results count */}
      {!loading && (
        <p className="text-xs text-muted-foreground">
          {total} template{total !== 1 ? 's' : ''} found
        </p>
      )}

      {/* Grid */}
      {loading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        </div>
      ) : listings.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
          <Bot className="h-10 w-10 mb-3 opacity-40" />
          <p className="text-sm font-medium">No agent templates found</p>
          <p className="text-xs mt-1">
            {search || category !== 'All'
              ? 'Try adjusting your search or filters'
              : 'Publish an agent to get started'}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          {listings.map((listing) => (
            <AgentTemplateCard
              key={listing.id}
              listing={listing}
              onSelect={handleSelect}
              onVote={handleVote}
            />
          ))}
        </div>
      )}

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2 pt-2">
          <Button
            variant="outline"
            size="sm"
            disabled={page <= 1}
            onClick={() => setPage((p) => p - 1)}
          >
            Previous
          </Button>
          <span className="text-xs text-muted-foreground">
            Page {page} of {lastPage}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={page >= lastPage}
            onClick={() => setPage((p) => p + 1)}
          >
            Next
          </Button>
        </div>
      )}

      {/* Detail Modal */}
      {selectedListing && (
        <AgentDetailModal
          listing={selectedListing}
          preview={preview}
          previewLoading={previewLoading}
          onClose={() => {
            setSelectedListing(null)
            setPreview(null)
          }}
          onVote={handleVote}
          onInstalled={() => {
            loadListings()
            setSelectedListing(null)
            setPreview(null)
          }}
        />
      )}

      {/* Publish Modal */}
      {showPublish && (
        <PublishAgentModal
          onClose={() => setShowPublish(false)}
          onPublished={() => {
            loadListings()
            setShowPublish(false)
          }}
        />
      )}
    </div>
  )
}

// ---- Card Component ----

function AgentTemplateCard({
  listing,
  onSelect,
  onVote,
}: {
  listing: MarketplaceAgentListing
  onSelect: (l: MarketplaceAgentListing) => void
  onVote: (l: MarketplaceAgentListing, d: 'up' | 'down') => void
}) {
  const agentConfig = listing.agent_config as Record<string, unknown>
  const model = (agentConfig.model as string) || null
  const skillCount = listing.skills_config?.length ?? 0

  return (
    <div
      className="p-4 border border-border bg-card hover:border-primary/40 hover:shadow-sm transition-all cursor-pointer"
      onClick={() => onSelect(listing)}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1.5">
            <Bot className="h-4 w-4 text-primary shrink-0" />
            <h3 className="font-medium text-sm truncate">{listing.name}</h3>
          </div>
          {listing.description && (
            <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
              {listing.description}
            </p>
          )}
        </div>
      </div>

      {/* Meta row: model, skills, tools */}
      <div className="flex items-center gap-3 mt-2.5 text-xs text-muted-foreground">
        {model && (
          <span className="flex items-center gap-1 truncate max-w-[120px]">
            <Cpu className="h-3 w-3 shrink-0" />
            {model}
          </span>
        )}
        <span className="flex items-center gap-1">
          <Package className="h-3 w-3" />
          {skillCount} skill{skillCount !== 1 ? 's' : ''}
        </span>
        {listing.workflow_config && (
          <span className="flex items-center gap-1">
            <Workflow className="h-3 w-3" />
            Workflow
          </span>
        )}
      </div>

      {/* Author */}
      {listing.author && (
        <div className="flex items-center gap-1 mt-2 text-xs text-muted-foreground">
          <User className="h-3 w-3" />
          <span>{listing.author}</span>
        </div>
      )}

      {/* Tags + Category */}
      <div className="flex items-center gap-1.5 mt-2.5 flex-wrap">
        {listing.category && (
          <span className="text-[10px] px-1.5 py-0.5 rounded bg-secondary text-secondary-foreground font-medium">
            {listing.category}
          </span>
        )}
        {listing.tags?.slice(0, 3).map((tag) => (
          <span
            key={tag}
            className="text-[10px] px-1.5 py-0.5 rounded bg-accent text-accent-foreground flex items-center gap-0.5"
          >
            <Tag className="h-2.5 w-2.5" />
            {tag}
          </span>
        ))}
        {listing.tags?.length > 3 && (
          <span className="text-[10px] text-muted-foreground">
            +{listing.tags.length - 3} more
          </span>
        )}
      </div>

      {/* Footer: downloads + votes */}
      <div className="flex items-center justify-between mt-3 pt-2 border-t border-border/50">
        <div className="flex items-center gap-3 text-xs text-muted-foreground">
          <span className="flex items-center gap-1">
            <Download className="h-3 w-3" />
            {listing.downloads}
          </span>
          <span className="text-[10px]">v{listing.version}</span>
        </div>
        <div className="flex items-center gap-1">
          <button
            onClick={(e) => {
              e.stopPropagation()
              onVote(listing, 'up')
            }}
            className="flex items-center gap-0.5 text-xs text-muted-foreground hover:text-green-500 transition-colors p-1 rounded"
            title="Upvote"
          >
            <ThumbsUp className="h-3 w-3" />
            <span>{listing.upvotes}</span>
          </button>
          <button
            onClick={(e) => {
              e.stopPropagation()
              onVote(listing, 'down')
            }}
            className="flex items-center gap-0.5 text-xs text-muted-foreground hover:text-red-500 transition-colors p-1 rounded"
            title="Downvote"
          >
            <ThumbsDown className="h-3 w-3" />
            <span>{listing.downvotes}</span>
          </button>
        </div>
      </div>
    </div>
  )
}

// ---- Detail Modal ----

function AgentDetailModal({
  listing,
  preview,
  previewLoading,
  onClose,
  onVote,
  onInstalled,
}: {
  listing: MarketplaceAgentListing
  preview: AgentPreview | null
  previewLoading: boolean
  onClose: () => void
  onVote: (l: MarketplaceAgentListing, d: 'up' | 'down') => void
  onInstalled: () => void
}) {
  const [projects, setProjects] = useState<Project[]>([])
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null)
  const [installing, setInstalling] = useState(false)
  const [showProjectPicker, setShowProjectPicker] = useState(false)
  const { showToast } = useAppStore()

  useEffect(() => {
    fetchProjects().then(setProjects)
  }, [])

  const handleInstall = async () => {
    if (!selectedProjectId) return
    setInstalling(true)
    try {
      await installMarketplaceAgent(listing.id, selectedProjectId)
      showToast(`Installed "${listing.name}" successfully`)
      onInstalled()
    } catch {
      showToast('Installation failed', 'error')
    } finally {
      setInstalling(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-card border border-border shadow-lg w-full max-w-2xl mx-4 max-h-[85vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-3 border-b border-border shrink-0">
          <div className="flex items-center gap-2">
            <Bot className="h-5 w-5 text-primary" />
            <h2 className="font-semibold text-sm">{listing.name}</h2>
            <span className="text-[10px] px-1.5 py-0.5 rounded bg-muted text-muted-foreground">
              v{listing.version}
            </span>
          </div>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto px-5 py-4 space-y-5">
          {previewLoading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
            </div>
          ) : preview ? (
            <>
              {/* Agent Summary */}
              <div>
                <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                  Agent
                </h3>
                <div className="border border-border p-3 space-y-2">
                  <div className="flex items-center gap-2">
                    <span className="font-medium text-sm">{preview.agent.name}</span>
                    {preview.agent.role && (
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-secondary text-secondary-foreground">
                        {preview.agent.role}
                      </span>
                    )}
                  </div>
                  {preview.agent.description && (
                    <p className="text-xs text-muted-foreground">{preview.agent.description}</p>
                  )}
                  <div className="flex items-center gap-4 text-xs text-muted-foreground flex-wrap">
                    {preview.agent.model && (
                      <span className="flex items-center gap-1">
                        <Cpu className="h-3 w-3" /> {preview.agent.model}
                      </span>
                    )}
                    {preview.agent.planning_mode && (
                      <span className="flex items-center gap-1">
                        <Shield className="h-3 w-3" /> {preview.agent.planning_mode}
                      </span>
                    )}
                    {preview.agent.max_iterations && (
                      <span>Max iterations: {preview.agent.max_iterations}</span>
                    )}
                    {preview.agent.can_delegate && (
                      <span className="text-primary">Can delegate</span>
                    )}
                  </div>
                </div>
              </div>

              {/* Stats Row */}
              <div className="flex items-center gap-6 text-xs text-muted-foreground">
                <span className="flex items-center gap-1">
                  <Package className="h-3.5 w-3.5" />
                  {preview.skill_count} skill{preview.skill_count !== 1 ? 's' : ''}
                </span>
                <span className="flex items-center gap-1">
                  <Wrench className="h-3.5 w-3.5" />
                  {preview.tool_count} tool{preview.tool_count !== 1 ? 's' : ''}
                </span>
                <span className="flex items-center gap-1">
                  <Download className="h-3.5 w-3.5" />
                  {preview.downloads} download{preview.downloads !== 1 ? 's' : ''}
                </span>
                <span className="flex items-center gap-1">
                  <User className="h-3.5 w-3.5" />
                  {preview.author}
                  {preview.author_url && (
                    <a
                      href={preview.author_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="hover:text-primary"
                      onClick={(e) => e.stopPropagation()}
                    >
                      <ExternalLink className="h-3 w-3" />
                    </a>
                  )}
                </span>
              </div>

              {/* Skills List */}
              {preview.skills.length > 0 && (
                <div>
                  <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                    Included Skills ({preview.skills.length})
                  </h3>
                  <div className="border border-border divide-y divide-border">
                    {preview.skills.map((skill, i) => (
                      <div key={i} className="px-3 py-2 flex items-start justify-between">
                        <div className="min-w-0">
                          <span className="text-sm font-medium">{skill.name}</span>
                          {skill.description && (
                            <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">
                              {skill.description}
                            </p>
                          )}
                        </div>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground shrink-0 ml-2">
                          {skill.model && <span>{skill.model}</span>}
                          {skill.tool_count > 0 && (
                            <span className="flex items-center gap-0.5">
                              <Wrench className="h-3 w-3" />
                              {skill.tool_count}
                            </span>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Workflow */}
              {preview.workflow && (
                <div>
                  <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                    Workflow
                  </h3>
                  <div className="border border-border p-3 space-y-1">
                    <div className="flex items-center gap-2">
                      <Workflow className="h-4 w-4 text-primary" />
                      <span className="text-sm font-medium">{preview.workflow.name}</span>
                    </div>
                    {preview.workflow.description && (
                      <p className="text-xs text-muted-foreground">{preview.workflow.description}</p>
                    )}
                    <div className="text-xs text-muted-foreground">
                      {preview.workflow.step_count} step{preview.workflow.step_count !== 1 ? 's' : ''}
                      {preview.workflow.trigger_type && ` | Trigger: ${preview.workflow.trigger_type}`}
                    </div>
                  </div>
                </div>
              )}

              {/* Wiring */}
              {preview.wiring && (preview.wiring.mcp_server_count > 0 || preview.wiring.a2a_agent_count > 0) && (
                <div>
                  <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                    Connections
                  </h3>
                  <div className="flex items-center gap-4 text-xs text-muted-foreground">
                    {preview.wiring.mcp_server_count > 0 && (
                      <span>
                        {preview.wiring.mcp_server_count} MCP server{preview.wiring.mcp_server_count !== 1 ? 's' : ''}
                      </span>
                    )}
                    {preview.wiring.a2a_agent_count > 0 && (
                      <span>
                        {preview.wiring.a2a_agent_count} A2A agent{preview.wiring.a2a_agent_count !== 1 ? 's' : ''}
                      </span>
                    )}
                  </div>
                </div>
              )}

              {/* README */}
              {preview.readme && (
                <div>
                  <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                    README
                  </h3>
                  <div className="border border-border p-3 text-sm prose prose-sm prose-invert max-w-none whitespace-pre-wrap">
                    {preview.readme}
                  </div>
                </div>
              )}

              {/* Tags */}
              {listing.tags?.length > 0 && (
                <div className="flex items-center gap-1.5 flex-wrap">
                  {listing.tags.map((tag) => (
                    <span
                      key={tag}
                      className="text-[10px] px-1.5 py-0.5 rounded bg-accent text-accent-foreground flex items-center gap-0.5"
                    >
                      <Tag className="h-2.5 w-2.5" />
                      {tag}
                    </span>
                  ))}
                </div>
              )}
            </>
          ) : (
            <p className="text-sm text-muted-foreground">
              {listing.description || 'No description available.'}
            </p>
          )}
        </div>

        {/* Footer */}
        <div className="shrink-0 border-t border-border px-5 py-3">
          <div className="flex items-center justify-between">
            {/* Votes */}
            <div className="flex items-center gap-2">
              <button
                onClick={() => onVote(listing, 'up')}
                className="flex items-center gap-1 text-xs text-muted-foreground hover:text-green-500 transition-colors p-1 rounded"
              >
                <ThumbsUp className="h-3.5 w-3.5" />
                {listing.upvotes}
              </button>
              <button
                onClick={() => onVote(listing, 'down')}
                className="flex items-center gap-1 text-xs text-muted-foreground hover:text-red-500 transition-colors p-1 rounded"
              >
                <ThumbsDown className="h-3.5 w-3.5" />
                {listing.downvotes}
              </button>
            </div>

            {/* Install */}
            <div className="flex items-center gap-2">
              {showProjectPicker ? (
                <div className="flex items-center gap-2">
                  <select
                    value={selectedProjectId ?? ''}
                    onChange={(e) => setSelectedProjectId(e.target.value ? Number(e.target.value) : null)}
                    className="text-sm border border-input bg-background px-2 py-1 focus:outline-none focus:ring-1 focus:ring-ring"
                  >
                    <option value="">Select project...</option>
                    {projects.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                  <Button
                    size="sm"
                    onClick={handleInstall}
                    disabled={!selectedProjectId || installing}
                  >
                    {installing ? (
                      <Loader2 className="h-3 w-3 mr-1 animate-spin" />
                    ) : (
                      <Download className="h-3 w-3 mr-1" />
                    )}
                    Install
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      setShowProjectPicker(false)
                      setSelectedProjectId(null)
                    }}
                  >
                    Cancel
                  </Button>
                </div>
              ) : (
                <>
                  <Button variant="outline" size="sm" onClick={onClose}>
                    Close
                  </Button>
                  <Button size="sm" onClick={() => setShowProjectPicker(true)}>
                    <Download className="h-3 w-3 mr-1" />
                    Install to Project
                  </Button>
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

// ---- Publish Modal ----

function PublishAgentModal({
  onClose,
  onPublished,
}: {
  onClose: () => void
  onPublished: () => void
}) {
  const [projects, setProjects] = useState<Project[]>([])
  const [agents, setAgents] = useState<Array<{ id: number; name: string; role: string }>>([])
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null)
  const [selectedAgentId, setSelectedAgentId] = useState<number | null>(null)
  const [author, setAuthor] = useState('')
  const [description, setDescription] = useState('')
  const [categoryVal, setCategoryVal] = useState('')
  const [tagsVal, setTagsVal] = useState('')
  const [readme, setReadme] = useState('')
  const [publishing, setPublishing] = useState(false)
  const [loadingAgents, setLoadingAgents] = useState(false)
  const { showToast } = useAppStore()

  useEffect(() => {
    fetchProjects().then(setProjects)
  }, [])

  useEffect(() => {
    if (!selectedProjectId) {
      setAgents([])
      setSelectedAgentId(null)
      return
    }
    setLoadingAgents(true)
    setSelectedAgentId(null)
    api
      .get(`/projects/${selectedProjectId}/agents`)
      .then((res) => {
        const agentList = (res.data.data || [])
          .filter((a: { is_enabled: boolean }) => a.is_enabled)
          .map((a: { id: number; name: string; role: string }) => ({
            id: a.id,
            name: a.name,
            role: a.role,
          }))
        setAgents(agentList)
      })
      .finally(() => setLoadingAgents(false))
  }, [selectedProjectId])

  const handlePublish = async () => {
    if (!selectedAgentId || !selectedProjectId || !author) return
    setPublishing(true)
    try {
      await publishAgentToMarketplace({
        agent_id: selectedAgentId,
        project_id: selectedProjectId,
        author,
        description: description || undefined,
        category: categoryVal || undefined,
        tags: tagsVal
          ? tagsVal
              .split(',')
              .map((t) => t.trim())
              .filter(Boolean)
          : undefined,
        readme: readme || undefined,
      })
      showToast('Agent published to marketplace')
      onPublished()
    } catch {
      showToast('Publish failed', 'error')
    } finally {
      setPublishing(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-card border border-border shadow-lg w-full max-w-lg mx-4 max-h-[85vh] flex flex-col">
        <div className="flex items-center justify-between px-4 py-3 border-b border-border shrink-0">
          <h2 className="font-semibold text-sm">Publish Agent to Marketplace</h2>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {/* Project */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Project
            </label>
            <select
              value={selectedProjectId ?? ''}
              onChange={(e) => setSelectedProjectId(e.target.value ? Number(e.target.value) : null)}
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            >
              <option value="">Select project...</option>
              {projects.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </div>

          {/* Agent */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Agent
            </label>
            {loadingAgents ? (
              <div className="flex justify-center py-3">
                <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
              </div>
            ) : !selectedProjectId ? (
              <p className="text-xs text-muted-foreground py-2">Select a project first.</p>
            ) : agents.length === 0 ? (
              <p className="text-xs text-muted-foreground py-2">
                No enabled agents in this project.
              </p>
            ) : (
              <div className="space-y-1 max-h-32 overflow-y-auto border border-border p-1">
                {agents.map((agent) => (
                  <button
                    key={agent.id}
                    onClick={() => setSelectedAgentId(agent.id)}
                    className={`w-full text-left px-3 py-1.5 rounded text-sm transition-colors ${
                      selectedAgentId === agent.id
                        ? 'bg-primary text-primary-foreground'
                        : 'hover:bg-muted'
                    }`}
                  >
                    <span className="block truncate">{agent.name}</span>
                    <span className="block text-xs opacity-70">{agent.role}</span>
                  </button>
                ))}
              </div>
            )}
          </div>

          {/* Author */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Author *
            </label>
            <input
              type="text"
              value={author}
              onChange={(e) => setAuthor(e.target.value)}
              placeholder="Your name"
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>

          {/* Description */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Description
            </label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Describe what this agent does..."
              rows={3}
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring resize-none"
            />
          </div>

          {/* Category */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Category
            </label>
            <select
              value={categoryVal}
              onChange={(e) => setCategoryVal(e.target.value)}
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            >
              <option value="">None</option>
              {CATEGORIES.filter((c) => c !== 'All').map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </div>

          {/* Tags */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Tags (comma-separated)
            </label>
            <input
              type="text"
              value={tagsVal}
              onChange={(e) => setTagsVal(e.target.value)}
              placeholder="automation, coding, devops"
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>

          {/* README */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              README (optional)
            </label>
            <textarea
              value={readme}
              onChange={(e) => setReadme(e.target.value)}
              placeholder="Detailed instructions, use cases, configuration notes..."
              rows={5}
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring resize-none font-mono text-xs"
            />
          </div>
        </div>

        <div className="flex justify-end gap-2 px-4 py-3 border-t border-border shrink-0">
          <Button variant="outline" size="sm" onClick={onClose}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={handlePublish}
            disabled={!selectedAgentId || !selectedProjectId || !author || publishing}
          >
            {publishing ? (
              <Loader2 className="h-3 w-3 mr-1 animate-spin" />
            ) : (
              <Upload className="h-3 w-3 mr-1" />
            )}
            Publish
          </Button>
        </div>
      </div>
    </div>
  )
}
