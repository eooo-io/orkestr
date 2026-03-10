import { useState, useEffect } from 'react'
import {
  X,
  Search,
  Download,
  Loader2,
  CheckCircle,
  Tag,
  BookOpen,
} from 'lucide-react'
import { fetchLibrary, importLibrarySkill } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import type { LibrarySkill } from '@/types'

const CATEGORIES = [
  { label: 'All', value: '' },
  { label: 'Laravel', value: 'Laravel' },
  { label: 'PHP', value: 'PHP' },
  { label: 'TypeScript', value: 'TypeScript' },
  { label: 'FinTech', value: 'FinTech' },
  { label: 'DevOps', value: 'DevOps' },
  { label: 'Writing', value: 'Writing' },
]

interface ImportLibraryModalProps {
  projectId: number
  onClose: () => void
  onImported: () => void
}

export function ImportLibraryModal({
  projectId,
  onClose,
  onImported,
}: ImportLibraryModalProps) {
  const [skills, setSkills] = useState<LibrarySkill[]>([])
  const [loading, setLoading] = useState(true)
  const [category, setCategory] = useState('')
  const [query, setQuery] = useState('')
  const [importingId, setImportingId] = useState<number | null>(null)
  const [importedIds, setImportedIds] = useState<Set<number>>(new Set())
  const { showToast, loadProjects } = useAppStore()

  useEffect(() => {
    const timer = setTimeout(() => {
      setLoading(true)
      fetchLibrary({
        category: category || undefined,
        q: query || undefined,
      })
        .then(setSkills)
        .finally(() => setLoading(false))
    }, query ? 300 : 0)

    return () => clearTimeout(timer)
  }, [category, query])

  // Close on Escape
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [onClose])

  const handleImport = async (skill: LibrarySkill) => {
    setImportingId(skill.id)
    try {
      await importLibrarySkill(skill.id, projectId)
      showToast(`Imported "${skill.name}"`)
      setImportedIds((prev) => new Set([...prev, skill.id]))
      loadProjects()
      onImported()
    } catch {
      showToast('Import failed', 'error')
    } finally {
      setImportingId(null)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-card border border-border rounded-lg shadow-lg w-full max-w-3xl mx-4 max-h-[80vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-3 border-b border-border shrink-0">
          <div className="flex items-center gap-2">
            <BookOpen className="h-4 w-4 text-muted-foreground" />
            <h2 className="font-semibold">Import from Library</h2>
          </div>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Filters */}
        <div className="px-5 py-3 border-b border-border space-y-3 shrink-0">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input
              type="text"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search library skills..."
              className="w-full pl-9 pr-4 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
              autoFocus
            />
          </div>
          <div className="flex items-center gap-1.5 flex-wrap">
            {CATEGORIES.map((cat) => (
              <button
                key={cat.value}
                onClick={() => setCategory(cat.value)}
                className={`text-xs px-2.5 py-1 rounded-full border transition-colors ${
                  category === cat.value
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border hover:border-primary/40'
                }`}
              >
                {cat.label}
              </button>
            ))}
          </div>
        </div>

        {/* Skill list */}
        <div className="flex-1 overflow-y-auto">
          {loading ? (
            <div className="flex items-center justify-center py-16">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
            </div>
          ) : skills.length === 0 ? (
            <div className="text-center py-16 text-sm text-muted-foreground">
              No skills found{query ? ` for "${query}"` : ''}.
            </div>
          ) : (
            <div className="divide-y divide-border">
              {skills.map((skill) => {
                const imported = importedIds.has(skill.id)
                const importing = importingId === skill.id

                return (
                  <div
                    key={skill.id}
                    className="flex items-start gap-4 px-5 py-3 hover:bg-muted/30 transition-colors"
                  >
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <h3 className="text-sm font-medium truncate">
                          {skill.name}
                        </h3>
                        {skill.category && (
                          <span className="text-[10px] px-1.5 py-0.5 rounded bg-secondary text-secondary-foreground shrink-0">
                            {skill.category}
                          </span>
                        )}
                      </div>
                      {skill.description && (
                        <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">
                          {skill.description}
                        </p>
                      )}
                      {skill.tags && skill.tags.length > 0 && (
                        <div className="flex items-center gap-1.5 mt-1.5 flex-wrap">
                          {skill.tags.map((tag) => (
                            <span
                              key={tag}
                              className="text-[10px] px-1.5 py-0.5 rounded bg-accent text-accent-foreground flex items-center gap-0.5"
                            >
                              <Tag className="h-2 w-2" />
                              {tag}
                            </span>
                          ))}
                        </div>
                      )}
                    </div>

                    <div className="shrink-0 pt-0.5">
                      {imported ? (
                        <span className="flex items-center gap-1 text-xs text-green-500">
                          <CheckCircle className="h-3.5 w-3.5" />
                          Added
                        </span>
                      ) : (
                        <Button
                          variant="outline"
                          size="xs"
                          onClick={() => handleImport(skill)}
                          disabled={importing}
                        >
                          {importing ? (
                            <Loader2 className="h-3 w-3 mr-1 animate-spin" />
                          ) : (
                            <Download className="h-3 w-3 mr-1" />
                          )}
                          Import
                        </Button>
                      )}
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-5 py-3 border-t border-border shrink-0 flex items-center justify-between">
          <span className="text-xs text-muted-foreground">
            {skills.length} skill{skills.length !== 1 ? 's' : ''} available
            {importedIds.size > 0 && (
              <span className="ml-1 text-green-500">
                &middot; {importedIds.size} imported
              </span>
            )}
          </span>
          <Button variant="outline" size="sm" onClick={onClose}>
            Done
          </Button>
        </div>
      </div>
    </div>
  )
}
