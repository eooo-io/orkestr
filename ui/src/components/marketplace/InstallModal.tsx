import { useState, useEffect } from 'react'
import { X, Loader2, Download } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { fetchProjects, installFromMarketplace } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import type { MarketplaceSkill, Project } from '@/types'

interface InstallModalProps {
  skill: MarketplaceSkill
  onClose: () => void
  onInstalled: () => void
}

export function InstallModal({ skill, onClose, onInstalled }: InstallModalProps) {
  const [target, setTarget] = useState<'library' | 'project'>('library')
  const [projects, setProjects] = useState<Project[]>([])
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null)
  const [installing, setInstalling] = useState(false)
  const { showToast, loadProjects } = useAppStore()

  useEffect(() => {
    fetchProjects().then(setProjects)
  }, [])

  const handleInstall = async () => {
    if (target === 'project' && !selectedProjectId) return
    setInstalling(true)
    try {
      await installFromMarketplace(skill.id, {
        target,
        project_id: target === 'project' ? selectedProjectId! : undefined,
      })
      showToast(`Installed "${skill.name}"`)
      if (target === 'project') {
        loadProjects()
      }
      onInstalled()
      onClose()
    } catch {
      showToast('Install failed', 'error')
    } finally {
      setInstalling(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-card border border-border shadow-lg w-full max-w-md mx-4">
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <h2 className="font-semibold text-sm">Install Skill</h2>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <p className="text-sm">
            Install <strong>{skill.name}</strong> to:
          </p>

          {/* Target Tabs */}
          <div className="flex gap-2">
            <button
              onClick={() => setTarget('library')}
              className={`flex-1 py-1.5 px-3 text-sm border transition-colors ${
                target === 'library'
                  ? 'border-primary bg-primary text-primary-foreground'
                  : 'border-border hover:bg-muted'
              }`}
            >
              Library
            </button>
            <button
              onClick={() => setTarget('project')}
              className={`flex-1 py-1.5 px-3 text-sm border transition-colors ${
                target === 'project'
                  ? 'border-primary bg-primary text-primary-foreground'
                  : 'border-border hover:bg-muted'
              }`}
            >
              Project
            </button>
          </div>

          {/* Project Picker */}
          {target === 'project' && (
            <div>
              {projects.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  No projects found. Create a project first.
                </p>
              ) : (
                <div className="space-y-1 max-h-60 overflow-y-auto border border-border p-1">
                  {projects.map((project) => (
                    <button
                      key={project.id}
                      onClick={() => setSelectedProjectId(project.id)}
                      className={`w-full text-left px-3 py-2 rounded text-sm transition-colors ${
                        selectedProjectId === project.id
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
          )}
        </div>

        <div className="flex justify-end gap-2 px-4 py-3 border-t border-border">
          <Button variant="outline" size="sm" onClick={onClose}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={handleInstall}
            disabled={(target === 'project' && !selectedProjectId) || installing}
          >
            {installing ? (
              <Loader2 className="h-3 w-3 mr-1 animate-spin" />
            ) : (
              <Download className="h-3 w-3 mr-1" />
            )}
            Install
          </Button>
        </div>
      </div>
    </div>
  )
}
