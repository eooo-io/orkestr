import { useState, useEffect, useCallback } from 'react'
import {
  Store,
  Search,
  Loader2,
  Upload,
  ChevronLeft,
  ChevronRight,
  X,
  Download,
  ThumbsUp,
  ThumbsDown,
  Tag,
  User,
} from 'lucide-react'
import {
  fetchMarketplace,
  voteMarketplaceSkill,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { MarketplaceCard } from '@/components/marketplace/MarketplaceCard'
import { PublishModal } from '@/components/marketplace/PublishModal'
import { InstallModal } from '@/components/marketplace/InstallModal'
import { Button } from '@/components/ui/button'
import type { MarketplaceSkill } from '@/types'

const CATEGORIES = [
  { label: 'All', value: '' },
  { label: 'Laravel', value: 'Laravel' },
  { label: 'PHP', value: 'PHP' },
  { label: 'TypeScript', value: 'TypeScript' },
  { label: 'FinTech', value: 'FinTech' },
  { label: 'DevOps', value: 'DevOps' },
  { label: 'Writing', value: 'Writing' },
]

const SORT_OPTIONS = [
  { label: 'Newest', value: 'newest' },
  { label: 'Most Popular', value: 'popular' },
  { label: 'Top Rated', value: 'top-rated' },
]

export function Marketplace() {
  const [skills, setSkills] = useState<MarketplaceSkill[]>([])
  const [loading, setLoading] = useState(true)
  const [category, setCategory] = useState('')
  const [query, setQuery] = useState('')
  const [sort, setSort] = useState('newest')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [showPublish, setShowPublish] = useState(false)
  const [installSkill, setInstallSkill] = useState<MarketplaceSkill | null>(null)
  const [detailSkill, setDetailSkill] = useState<MarketplaceSkill | null>(null)
  const { showToast } = useAppStore()

  const loadSkills = useCallback(() => {
    setLoading(true)
    fetchMarketplace({
      category: category || undefined,
      q: query || undefined,
      sort,
      page,
    })
      .then((result) => {
        setSkills(result.data)
        setLastPage(result.last_page)
        setTotal(result.total)
      })
      .finally(() => setLoading(false))
  }, [category, query, sort, page])

  useEffect(() => {
    const timer = setTimeout(() => {
      loadSkills()
    }, query ? 300 : 0)
    return () => clearTimeout(timer)
  }, [loadSkills, query])

  // Reset page on filter changes
  useEffect(() => {
    setPage(1)
  }, [category, query, sort])

  const handleVote = async (skill: MarketplaceSkill, vote: 'up' | 'down') => {
    try {
      const updated = await voteMarketplaceSkill(skill.id, vote)
      setSkills((prev) =>
        prev.map((s) => (s.id === updated.id ? updated : s)),
      )
      if (detailSkill?.id === updated.id) {
        setDetailSkill(updated)
      }
    } catch {
      showToast('Vote failed', 'error')
    }
  }

  return (
    <div className="flex h-full">
      {/* Category sidebar */}
      <div className="w-48 shrink-0 border-r border-border p-4">
        <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">
          Categories
        </h3>
        <nav className="space-y-0.5">
          {CATEGORIES.map((cat) => (
            <button
              key={cat.value}
              onClick={() => setCategory(cat.value)}
              className={`w-full text-left px-3 py-1.5 text-sm transition-colors ${
                category === cat.value
                  ? 'bg-primary text-primary-foreground font-medium'
                  : 'text-foreground hover:bg-muted'
              }`}
            >
              {cat.label}
            </button>
          ))}
        </nav>
      </div>

      {/* Main content */}
      <div className="flex-1 overflow-auto p-6">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-2xl font-bold">Marketplace</h1>
            <p className="text-sm text-muted-foreground mt-1">
              Discover, share, and install community skills
            </p>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground mr-2">
              {total} skill{total !== 1 ? 's' : ''}
            </span>
            <Button variant="outline" size="sm" onClick={() => setShowPublish(true)}>
              <Upload className="h-4 w-4 mr-1" />
              Publish
            </Button>
          </div>
        </div>

        {/* Search + Sort */}
        <div className="flex items-center gap-4 mb-6">
          <div className="relative flex-1 max-w-md">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search marketplace..."
              className="w-full pl-9 pr-4 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>
          <select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            className="text-sm border border-input bg-background px-3 py-2 focus:outline-none focus:ring-1 focus:ring-ring"
          >
            {SORT_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
        </div>

        {loading ? (
          <div className="flex items-center justify-center py-20">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : skills.length === 0 ? (
          <div className="text-center py-20 text-muted-foreground">
            <Store className="h-12 w-12 mx-auto mb-4 opacity-30" />
            <p>No skills found{query ? ` for "${query}"` : ''}.</p>
            <p className="text-xs mt-2">
              Publish your first skill to get started.
            </p>
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
              {skills.map((skill) => (
                <MarketplaceCard
                  key={skill.id}
                  skill={skill}
                  onInstall={setInstallSkill}
                  onVote={handleVote}
                  onSelect={setDetailSkill}
                />
              ))}
            </div>

            {/* Pagination */}
            {lastPage > 1 && (
              <div className="flex items-center justify-center gap-2 mt-6">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => p - 1)}
                >
                  <ChevronLeft className="h-4 w-4" />
                </Button>
                <span className="text-sm text-muted-foreground">
                  Page {page} of {lastPage}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page >= lastPage}
                  onClick={() => setPage((p) => p + 1)}
                >
                  <ChevronRight className="h-4 w-4" />
                </Button>
              </div>
            )}
          </>
        )}
      </div>

      {/* Detail Panel */}
      {detailSkill && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-card border border-border shadow-lg w-full max-w-2xl mx-4 max-h-[80vh] flex flex-col">
            <div className="flex items-center justify-between px-4 py-3 border-b border-border shrink-0">
              <h2 className="font-semibold text-sm truncate">
                {detailSkill.name}
              </h2>
              <button
                onClick={() => setDetailSkill(null)}
                className="text-muted-foreground hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-4 space-y-4">
              {detailSkill.description && (
                <p className="text-sm text-muted-foreground">
                  {detailSkill.description}
                </p>
              )}

              <div className="flex items-center gap-4 text-xs text-muted-foreground">
                {detailSkill.author && (
                  <span className="flex items-center gap-1">
                    <User className="h-3 w-3" />
                    {detailSkill.author}
                  </span>
                )}
                <span className="flex items-center gap-1">
                  <Download className="h-3 w-3" />
                  {detailSkill.downloads} downloads
                </span>
                <span>v{detailSkill.version}</span>
              </div>

              <div className="flex items-center gap-2 flex-wrap">
                {detailSkill.category && (
                  <span className="text-[10px] px-1.5 py-0.5 rounded bg-secondary text-secondary-foreground font-medium">
                    {detailSkill.category}
                  </span>
                )}
                {detailSkill.tags?.map((tag) => (
                  <span
                    key={tag}
                    className="text-[10px] px-1.5 py-0.5 rounded bg-accent text-accent-foreground flex items-center gap-0.5"
                  >
                    <Tag className="h-2.5 w-2.5" />
                    {tag}
                  </span>
                ))}
              </div>

              <div className="flex items-center gap-3">
                <button
                  onClick={() => handleVote(detailSkill, 'up')}
                  className="flex items-center gap-1 text-sm text-muted-foreground hover:text-green-500 transition-colors px-2 py-1 rounded border border-border"
                >
                  <ThumbsUp className="h-3.5 w-3.5" />
                  <span>{detailSkill.upvotes}</span>
                </button>
                <button
                  onClick={() => handleVote(detailSkill, 'down')}
                  className="flex items-center gap-1 text-sm text-muted-foreground hover:text-red-500 transition-colors px-2 py-1 rounded border border-border"
                >
                  <ThumbsDown className="h-3.5 w-3.5" />
                  <span>{detailSkill.downvotes}</span>
                </button>
              </div>

              <div>
                <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                  Skill Body
                </h3>
                <pre className="text-xs bg-muted/50 border border-border p-3 whitespace-pre-wrap leading-relaxed max-h-80 overflow-y-auto">
                  {detailSkill.body}
                </pre>
              </div>
            </div>

            <div className="flex justify-end gap-2 px-4 py-3 border-t border-border shrink-0">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setDetailSkill(null)}
              >
                Close
              </Button>
              <Button
                size="sm"
                onClick={() => {
                  setInstallSkill(detailSkill)
                  setDetailSkill(null)
                }}
              >
                <Download className="h-3 w-3 mr-1" />
                Install
              </Button>
            </div>
          </div>
        </div>
      )}

      {/* Modals */}
      {showPublish && (
        <PublishModal
          onClose={() => setShowPublish(false)}
          onPublished={loadSkills}
        />
      )}

      {installSkill && (
        <InstallModal
          skill={installSkill}
          onClose={() => setInstallSkill(null)}
          onInstalled={loadSkills}
        />
      )}
    </div>
  )
}
