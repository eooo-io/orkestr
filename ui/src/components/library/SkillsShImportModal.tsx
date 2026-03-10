import { useState, useEffect, useMemo } from 'react'
import {
  X,
  Search,
  Loader2,
  Download,
  CheckCircle,
  Github,
  ExternalLink,
} from 'lucide-react'
import { discoverSkillsSh, importSkillSh } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import type { SkillsShDiscoveredSkill } from '@/types'

const POPULAR_REPOS = [
  { repo: 'vercel-labs/skills', label: 'Vercel Labs', desc: 'Official skills collection' },
  {
    repo: 'ComposioHQ/awesome-claude-skills',
    label: 'Composio',
    desc: '800+ community skills',
  },
]

interface Props {
  target: 'library' | 'project'
  projectId?: number
  onClose: () => void
  onImported: () => void
}

export function SkillsShImportModal({
  target,
  projectId,
  onClose,
  onImported,
}: Props) {
  const { showToast } = useAppStore()
  const [repo, setRepo] = useState('')
  const [skills, setSkills] = useState<SkillsShDiscoveredSkill[]>([])
  const [discovering, setDiscovering] = useState(false)
  const [discoveredRepo, setDiscoveredRepo] = useState<string | null>(null)
  const [importingPaths, setImportingPaths] = useState<Set<string>>(new Set())
  const [importedPaths, setImportedPaths] = useState<Set<string>>(new Set())
  const [error, setError] = useState<string | null>(null)
  const [filter, setFilter] = useState('')

  useEffect(() => {
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleEsc)
    return () => window.removeEventListener('keydown', handleEsc)
  }, [onClose])

  const filteredSkills = useMemo(() => {
    if (!filter.trim()) return skills
    const q = filter.toLowerCase()
    return skills.filter(
      (s) =>
        s.name.toLowerCase().includes(q) ||
        s.path.toLowerCase().includes(q),
    )
  }, [skills, filter])

  const handleDiscover = async (repoName?: string) => {
    const repoTarget = repoName || repo.trim()
    if (!repoTarget) return

    setRepo(repoTarget)
    setDiscovering(true)
    setError(null)
    setSkills([])
    setImportedPaths(new Set())
    setFilter('')

    try {
      const result = await discoverSkillsSh(repoTarget)
      setSkills(result.data)
      setDiscoveredRepo(result.repo)

      if (result.data.length === 0) {
        setError('No SKILL.md files found in this repository.')
      }
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response
          ?.data?.message || 'Failed to discover skills'
      setError(message)
    } finally {
      setDiscovering(false)
    }
  }

  const handleImport = async (skill: SkillsShDiscoveredSkill) => {
    if (!discoveredRepo) return

    setImportingPaths((prev) => new Set(prev).add(skill.path))

    try {
      await importSkillSh(discoveredRepo, skill.path, target, projectId)
      setImportedPaths((prev) => new Set(prev).add(skill.path))
      showToast(`Imported "${skill.name}"`)
      onImported()
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response
          ?.data?.message || 'Import failed'
      showToast(message, 'error')
    } finally {
      setImportingPaths((prev) => {
        const next = new Set(prev)
        next.delete(skill.path)
        return next
      })
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-background border border-border rounded-xl shadow-xl w-full max-w-3xl max-h-[85vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-border">
          <div className="flex items-center gap-2">
            <Github className="h-5 w-5" />
            <div>
              <h2 className="text-lg font-semibold">Import from Skills.sh</h2>
              <p className="text-xs text-muted-foreground">
                Discover and import skills from GitHub repositories — no npm
                required
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-md hover:bg-muted transition-colors"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Repo Input */}
        <div className="p-5 border-b border-border space-y-3">
          <div className="flex gap-2">
            <div className="relative flex-1">
              <Github className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <input
                type="text"
                value={repo}
                onChange={(e) => setRepo(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleDiscover()}
                placeholder="owner/repo (e.g. vercel-labs/skills)"
                className="w-full pl-9 pr-3 py-2 text-sm rounded-md border border-input bg-background font-mono focus:outline-none focus:ring-1 focus:ring-ring"
                autoFocus
              />
            </div>
            <Button
              size="sm"
              onClick={() => handleDiscover()}
              disabled={discovering || !repo.trim()}
            >
              {discovering ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                'Discover'
              )}
            </Button>
          </div>

          {/* Popular repos */}
          {!discoveredRepo && (
            <div>
              <p className="text-xs text-muted-foreground mb-2">
                Popular repositories:
              </p>
              <div className="flex flex-wrap gap-2">
                {POPULAR_REPOS.map((r) => (
                  <button
                    key={r.repo}
                    onClick={() => handleDiscover(r.repo)}
                    className="flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-md border border-border hover:border-primary/30 hover:bg-primary/5 transition-colors"
                  >
                    <Github className="h-3 w-3" />
                    <span className="font-medium">{r.label}</span>
                    <span className="text-muted-foreground hidden sm:inline">
                      — {r.desc}
                    </span>
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Filter (shown when results exist) */}
        {skills.length > 10 && (
          <div className="px-5 pt-3">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <input
                type="text"
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
                placeholder={`Filter ${skills.length} skills...`}
                className="w-full pl-9 pr-3 py-1.5 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
              />
            </div>
          </div>
        )}

        {/* Results */}
        <div className="flex-1 overflow-y-auto">
          {discovering && (
            <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
              <Loader2 className="h-6 w-6 animate-spin mb-2" />
              <p className="text-sm">Scanning repository for skills...</p>
            </div>
          )}

          {error && !discovering && (
            <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
              <p className="text-sm">{error}</p>
            </div>
          )}

          {!discovering && filteredSkills.length > 0 && (
            <div className="divide-y divide-border">
              {filteredSkills.map((skill) => {
                const isImporting = importingPaths.has(skill.path)
                const isImported = importedPaths.has(skill.path)
                const displayPath = skill.path.replace('/SKILL.md', '')

                return (
                  <div
                    key={skill.path}
                    className="flex items-center gap-3 px-5 py-2.5 hover:bg-muted/50 transition-colors"
                  >
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium capitalize">
                          {skill.name}
                        </span>
                        {displayPath !== skill.name && (
                          <span className="text-[10px] px-1.5 py-0.5 rounded bg-muted text-muted-foreground font-mono truncate max-w-48">
                            {displayPath}
                          </span>
                        )}
                      </div>
                    </div>

                    <div className="flex-shrink-0">
                      {isImported ? (
                        <span className="flex items-center gap-1 text-xs text-green-600 dark:text-green-400 px-2 py-1">
                          <CheckCircle className="h-3.5 w-3.5" />
                          Added
                        </span>
                      ) : (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleImport(skill)}
                          disabled={isImporting}
                          className="text-xs h-7"
                        >
                          {isImporting ? (
                            <Loader2 className="h-3 w-3 animate-spin" />
                          ) : (
                            <>
                              <Download className="h-3 w-3 mr-1" />
                              Import
                            </>
                          )}
                        </Button>
                      )}
                    </div>
                  </div>
                )
              })}
            </div>
          )}

          {!discovering &&
            skills.length > 0 &&
            filteredSkills.length === 0 && (
              <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                <p className="text-sm">
                  No skills matching "{filter}"
                </p>
              </div>
            )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between p-4 border-t border-border text-xs text-muted-foreground">
          <div className="flex items-center gap-3">
            {discoveredRepo && (
              <span>
                {filter
                  ? `${filteredSkills.length} of ${skills.length}`
                  : skills.length}{' '}
                skill{(filter ? filteredSkills.length : skills.length) !== 1 ? 's' : ''}
                {!filter && ' found'}
                {importedPaths.size > 0 &&
                  ` · ${importedPaths.size} imported`}
              </span>
            )}
          </div>
          <div className="flex items-center gap-2">
            <a
              href="https://skills.sh"
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1 hover:text-foreground transition-colors"
            >
              Browse skills.sh
              <ExternalLink className="h-3 w-3" />
            </a>
            <Button variant="outline" size="sm" onClick={onClose}>
              Done
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
