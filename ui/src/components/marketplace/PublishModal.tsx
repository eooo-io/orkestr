import { useState, useEffect } from 'react'
import { X, Loader2, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { fetchLibrary, fetchProjects, fetchSkills, publishToMarketplace } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import type { LibrarySkill, Project, Skill } from '@/types'

interface PublishModalProps {
  onClose: () => void
  onPublished: () => void
}

export function PublishModal({ onClose, onPublished }: PublishModalProps) {
  const [sourceType, setSourceType] = useState<'library' | 'skill'>('library')
  const [librarySkills, setLibrarySkills] = useState<LibrarySkill[]>([])
  const [projects, setProjects] = useState<Project[]>([])
  const [projectSkills, setProjectSkills] = useState<Skill[]>([])
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null)
  const [selectedSourceId, setSelectedSourceId] = useState<number | null>(null)
  const [author, setAuthor] = useState('')
  const [publishing, setPublishing] = useState(false)
  const [loading, setLoading] = useState(true)
  const { showToast } = useAppStore()

  useEffect(() => {
    if (sourceType === 'library') {
      setLoading(true)
      fetchLibrary()
        .then(setLibrarySkills)
        .finally(() => setLoading(false))
    } else {
      setLoading(true)
      fetchProjects()
        .then(setProjects)
        .finally(() => setLoading(false))
    }
    setSelectedSourceId(null)
    setSelectedProjectId(null)
    setProjectSkills([])
  }, [sourceType])

  useEffect(() => {
    if (sourceType === 'skill' && selectedProjectId) {
      setLoading(true)
      fetchSkills(selectedProjectId)
        .then(setProjectSkills)
        .finally(() => setLoading(false))
      setSelectedSourceId(null)
    }
  }, [sourceType, selectedProjectId])

  const handlePublish = async () => {
    if (!selectedSourceId) return
    setPublishing(true)
    try {
      await publishToMarketplace({
        source_type: sourceType,
        source_id: selectedSourceId,
        author: author || undefined,
      })
      showToast('Skill published to marketplace')
      onPublished()
      onClose()
    } catch {
      showToast('Publish failed', 'error')
    } finally {
      setPublishing(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-card border border-border shadow-lg w-full max-w-lg mx-4">
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <h2 className="font-semibold text-sm">Publish to Marketplace</h2>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          {/* Source Type Tabs */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-2">
              Source
            </label>
            <div className="flex gap-2">
              <button
                onClick={() => setSourceType('library')}
                className={`flex-1 py-1.5 px-3 text-sm border transition-colors ${
                  sourceType === 'library'
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border hover:bg-muted'
                }`}
              >
                Library Skill
              </button>
              <button
                onClick={() => setSourceType('skill')}
                className={`flex-1 py-1.5 px-3 text-sm border transition-colors ${
                  sourceType === 'skill'
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border hover:bg-muted'
                }`}
              >
                Project Skill
              </button>
            </div>
          </div>

          {/* Project Picker (for skill source) */}
          {sourceType === 'skill' && (
            <div>
              <label className="text-xs font-medium text-muted-foreground block mb-2">
                Project
              </label>
              <div className="space-y-1 max-h-32 overflow-y-auto border border-border p-1">
                {projects.map((project) => (
                  <button
                    key={project.id}
                    onClick={() => setSelectedProjectId(project.id)}
                    className={`w-full text-left px-3 py-1.5 rounded text-sm transition-colors ${
                      selectedProjectId === project.id
                        ? 'bg-primary text-primary-foreground'
                        : 'hover:bg-muted'
                    }`}
                  >
                    {project.name}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Skill Picker */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-2">
              Skill
            </label>
            {loading ? (
              <div className="flex justify-center py-4">
                <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
              </div>
            ) : (
              <div className="space-y-1 max-h-48 overflow-y-auto border border-border p-1">
                {sourceType === 'library'
                  ? librarySkills.map((skill) => (
                      <button
                        key={skill.id}
                        onClick={() => setSelectedSourceId(skill.id)}
                        className={`w-full text-left px-3 py-1.5 rounded text-sm transition-colors ${
                          selectedSourceId === skill.id
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                        }`}
                      >
                        <span className="block truncate">{skill.name}</span>
                        {skill.description && (
                          <span className="block text-xs opacity-70 truncate">
                            {skill.description}
                          </span>
                        )}
                      </button>
                    ))
                  : projectSkills.map((skill) => (
                      <button
                        key={skill.id}
                        onClick={() => setSelectedSourceId(skill.id)}
                        className={`w-full text-left px-3 py-1.5 rounded text-sm transition-colors ${
                          selectedSourceId === skill.id
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                        }`}
                      >
                        <span className="block truncate">{skill.name}</span>
                        {skill.description && (
                          <span className="block text-xs opacity-70 truncate">
                            {skill.description}
                          </span>
                        )}
                      </button>
                    ))}
                {sourceType === 'skill' && !selectedProjectId && (
                  <p className="text-xs text-muted-foreground px-3 py-2">
                    Select a project first.
                  </p>
                )}
                {((sourceType === 'library' && librarySkills.length === 0) ||
                  (sourceType === 'skill' &&
                    selectedProjectId &&
                    projectSkills.length === 0)) && (
                  <p className="text-xs text-muted-foreground px-3 py-2">
                    No skills found.
                  </p>
                )}
              </div>
            )}
          </div>

          {/* Author */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-2">
              Author (optional)
            </label>
            <input
              type="text"
              value={author}
              onChange={(e) => setAuthor(e.target.value)}
              placeholder="Your name"
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>
        </div>

        <div className="flex justify-end gap-2 px-4 py-3 border-t border-border">
          <Button variant="outline" size="sm" onClick={onClose}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={handlePublish}
            disabled={!selectedSourceId || publishing}
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
