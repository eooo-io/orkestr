import { useState, useEffect } from 'react'
import {
  BookOpen,
  Search,
  Download,
  Tag,
  X,
  Loader2,
  CheckCircle,
  Github,
} from 'lucide-react'
import { fetchLibrary, importLibrarySkill, fetchProjects } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { SkillsShImportModal } from '@/components/library/SkillsShImportModal'
import { Button } from '@/components/ui/button'
import type { LibrarySkill, Project } from '@/types'

const CATEGORIES = [
  { label: 'All', value: '' },
  { label: 'Laravel', value: 'Laravel' },
  { label: 'PHP', value: 'PHP' },
  { label: 'TypeScript', value: 'TypeScript' },
  { label: 'FinTech', value: 'FinTech' },
  { label: 'DevOps', value: 'DevOps' },
  { label: 'Writing', value: 'Writing' },
]

export function Library() {
  const [skills, setSkills] = useState<LibrarySkill[]>([])
  const [loading, setLoading] = useState(true)
  const [category, setCategory] = useState('')
  const [query, setQuery] = useState('')
  const [importModal, setImportModal] = useState<LibrarySkill | null>(null)
  const [projects, setProjects] = useState<Project[]>([])
  const [selectedProject, setSelectedProject] = useState<number | null>(null)
  const [importing, setImporting] = useState(false)
  const [importedIds, setImportedIds] = useState<Set<number>>(new Set())
  const [showSkillsSh, setShowSkillsSh] = useState(false)
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

  const openImportModal = async (skill: LibrarySkill) => {
    setImportModal(skill)
    setSelectedProject(null)
    const p = await fetchProjects()
    setProjects(p)
  }

  const handleImport = async () => {
    if (!importModal || !selectedProject) return
    setImporting(true)
    try {
      await importLibrarySkill(importModal.id, selectedProject)
      showToast(`Imported "${importModal.name}"`)
      setImportedIds((prev) => new Set([...prev, importModal.id]))
      loadProjects()
      setImportModal(null)
    } catch {
      showToast('Import failed', 'error')
    } finally {
      setImporting(false)
    }
  }

  return (
    <div className="flex flex-col md:flex-row h-full">
      {/* Category sidebar — horizontal scroll on mobile, vertical on desktop */}
      <div className="shrink-0 border-b md:border-b-0 md:border-r border-border md:w-48 md:p-4">
        <h3 className="hidden md:block text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-3">
          Categories
        </h3>
        <nav className="flex md:flex-col gap-0.5 overflow-x-auto md:overflow-x-visible px-4 py-2 md:p-0">
          {CATEGORIES.map((cat) => (
            <button
              key={cat.value}
              onClick={() => setCategory(cat.value)}
              className={`whitespace-nowrap px-3 py-1.5 text-sm transition-all duration-150 ${
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
      <div className="flex-1 overflow-auto p-4 md:p-6">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">Library</h1>
            <p className="text-sm text-muted-foreground mt-1">
              Browse and import pre-built skills into your projects
            </p>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground mr-2 hidden sm:inline">
              {skills.length} skill{skills.length !== 1 ? 's' : ''}
            </span>
            <Button variant="outline" size="sm" onClick={() => setShowSkillsSh(true)}>
              <Github className="h-4 w-4 mr-1" />
              <span className="hidden sm:inline">Import from</span> Skills.sh
            </Button>
          </div>
        </div>

        {/* Search */}
        <div className="relative max-w-md mb-6">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search library skills..."
            className="w-full pl-9 pr-4 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
          />
        </div>

        {loading ? (
          <div className="flex items-center justify-center py-20">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        ) : skills.length === 0 ? (
          <div className="text-center py-20 text-muted-foreground">
            <BookOpen className="h-12 w-12 mx-auto mb-4 opacity-30" />
            <p>No skills found{query ? ` for "${query}"` : ''}.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {skills.map((skill) => (
              <div
                key={skill.id}
                className="p-4 bg-card elevation-1 hover:elevation-2 transition-all duration-150"
              >
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0 flex-1">
                    <h3 className="font-medium text-sm truncate">
                      {skill.name}
                    </h3>
                    {skill.description && (
                      <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
                        {skill.description}
                      </p>
                    )}
                  </div>
                  {importedIds.has(skill.id) ? (
                    <CheckCircle className="h-4 w-4 text-green-500 shrink-0 mt-0.5" />
                  ) : (
                    <Button
                      variant="ghost"
                      size="icon-xs"
                      onClick={() => openImportModal(skill)}
                      title="Import to project"
                    >
                      <Download className="h-3.5 w-3.5" />
                    </Button>
                  )}
                </div>

                <div className="flex items-center gap-2 mt-3 flex-wrap">
                  {skill.category && (
                    <span className="text-[10px] px-1.5 py-0.5 bg-primary/10 text-primary font-medium">
                      {skill.category}
                    </span>
                  )}
                  {skill.tags?.map((tag) => (
                    <span
                      key={tag}
                      className="text-[10px] px-1.5 py-0.5 rounded bg-accent text-accent-foreground flex items-center gap-0.5"
                    >
                      <Tag className="h-2.5 w-2.5" />
                      {tag}
                    </span>
                  ))}
                </div>

                {skill.body && (
                  <p className="text-[11px] text-muted-foreground mt-2 line-clamp-3 leading-relaxed">
                    {skill.body.slice(0, 200)}
                    {skill.body.length > 200 ? '...' : ''}
                  </p>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Import modal */}
      {importModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/30">
          <div className="bg-card elevation-3 w-full max-w-md mx-4">
            <div className="flex items-center justify-between px-4 py-3 border-b border-border">
              <h2 className="font-semibold text-sm">Import Skill</h2>
              <button
                onClick={() => setImportModal(null)}
                className="text-muted-foreground hover:text-foreground"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="p-4">
              <p className="text-sm mb-4">
                Import <strong>{importModal.name}</strong> into:
              </p>

              {projects.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No projects found. Create a project first.
                </p>
              ) : (
                <div className="space-y-1 max-h-60 overflow-y-auto">
                  {projects.map((project) => (
                    <button
                      key={project.id}
                      onClick={() => setSelectedProject(project.id)}
                      className={`w-full text-left px-3 py-2 text-sm transition-all duration-150 ${
                        selectedProject === project.id
                          ? 'bg-primary text-primary-foreground'
                          : 'hover:bg-muted'
                      }`}
                    >
                      {project.name}
                    </button>
                  ))}
                </div>
              )}
            </div>

            <div className="flex justify-end gap-2 px-4 py-3 border-t border-border">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setImportModal(null)}
              >
                Cancel
              </Button>
              <Button
                size="sm"
                onClick={handleImport}
                disabled={!selectedProject || importing}
              >
                {importing ? (
                  <Loader2 className="h-3 w-3 mr-1 animate-spin" />
                ) : (
                  <Download className="h-3 w-3 mr-1" />
                )}
                Import
              </Button>
            </div>
          </div>
        </div>
      )}

      {showSkillsSh && (
        <SkillsShImportModal
          target="library"
          onClose={() => setShowSkillsSh(false)}
          onImported={() => {
            fetchLibrary({ category: category || undefined, q: query || undefined })
              .then(setSkills)
          }}
        />
      )}
    </div>
  )
}
