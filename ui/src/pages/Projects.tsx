import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FolderOpen, RefreshCw, Plus, Trash2, AlertTriangle } from 'lucide-react'
import { useAppStore } from '@/store/useAppStore'
import { syncProject, deleteProject } from '@/api/client'
import { Button } from '@/components/ui/button'

export function Projects() {
  const { projects, loadProjects, showToast } = useAppStore()
  const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    loadProjects()
  }, [loadProjects])

  const handleSync = async (id: number, name: string) => {
    try {
      await syncProject(id)
      showToast(`Synced ${name}`)
      loadProjects()
    } catch {
      showToast('Sync failed', 'error')
    }
  }

  const confirmDelete = async () => {
    if (!deleteTarget) return
    setDeleting(true)
    try {
      await deleteProject(deleteTarget.id)
      showToast(`Deleted ${deleteTarget.name}`)
      setDeleteTarget(null)
      loadProjects()
    } catch {
      showToast('Delete failed', 'error')
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div className="p-4 md:p-6 max-w-6xl mx-auto">
      <div className="flex items-center justify-between gap-4 mb-8">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Projects</h1>
          <p className="text-sm text-muted-foreground mt-1">
            {projects.length} registered project{projects.length !== 1 && 's'}
          </p>
        </div>
        <Link to="/projects/new">
          <Button size="sm">
            <Plus className="h-4 w-4 mr-1.5" />
            New Project
          </Button>
        </Link>
      </div>

      {projects.length === 0 ? (
        <div className="text-center py-24 text-muted-foreground">
          <div className="h-16 w-16 bg-muted flex items-center justify-center mx-auto mb-4">
            <FolderOpen className="h-8 w-8 opacity-40" />
          </div>
          <p className="font-medium">No projects registered yet</p>
          <Link
            to="/projects/new"
            className="text-primary text-sm mt-2 inline-block hover:underline"
          >
            Create your first project
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {projects.map((project) => (
            <div
              key={project.id}
              className="bg-card p-5 elevation-1 hover:elevation-2 transition-shadow duration-200"
            >
              <Link to={`/projects/${project.id}`}>
                <h2 className="font-semibold text-base text-foreground hover:text-primary transition-colors">
                  {project.name}
                </h2>
              </Link>
              {project.description && (
                <p className="text-sm text-muted-foreground mt-1.5 line-clamp-2">
                  {project.description}
                </p>
              )}
              <p className="text-xs text-muted-foreground mt-2 font-mono truncate opacity-60">
                {project.path}
              </p>

              <div className="flex items-center gap-1.5 mt-3 flex-wrap">
                {project.providers.map((p) => (
                  <span
                    key={p}
                    className="text-[10px] px-2 py-0.5 bg-primary/10 text-primary font-medium capitalize"
                  >
                    {p}
                  </span>
                ))}
              </div>

              <div className="flex items-center justify-between mt-4 pt-3 border-t border-border">
                <span className="text-xs text-muted-foreground tabular-nums">
                  {project.skills_count} skill
                  {project.skills_count !== 1 && 's'}
                </span>
                <div className="flex items-center gap-2">
                  <Button
                    variant="ghost"
                    size="xs"
                    onClick={() => setDeleteTarget({ id: project.id, name: project.name })}
                    className="text-muted-foreground hover:text-destructive"
                  >
                    <Trash2 className="h-3 w-3" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="xs"
                    onClick={() => handleSync(project.id, project.name)}
                  >
                    <RefreshCw className="h-3 w-3 mr-1" />
                    Sync
                  </Button>
                  <Link to={`/projects/${project.id}`}>
                    <Button variant="outline" size="xs">
                      Open
                    </Button>
                  </Link>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Delete confirmation modal */}
      {deleteTarget && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/30 backdrop-blur-sm"
          onClick={() => !deleting && setDeleteTarget(null)}
        >
          <div
            className="bg-popover elevation-5 w-full max-w-sm p-6"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center gap-3 mb-4">
              <div className="h-10 w-10 bg-destructive/10 flex items-center justify-center shrink-0">
                <AlertTriangle className="h-5 w-5 text-destructive" />
              </div>
              <div>
                <h3 className="font-semibold text-foreground">Delete Project</h3>
                <p className="text-sm text-muted-foreground mt-0.5">
                  This action cannot be undone.
                </p>
              </div>
            </div>

            <p className="text-sm text-foreground mb-6">
              Are you sure you want to delete{' '}
              <span className="font-semibold">{deleteTarget.name}</span>? All associated
              skills and versions will be permanently removed.
            </p>

            <div className="flex items-center justify-end gap-2">
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setDeleteTarget(null)}
                disabled={deleting}
              >
                Cancel
              </Button>
              <Button
                variant="destructive"
                size="sm"
                onClick={confirmDelete}
                disabled={deleting}
              >
                {deleting ? 'Deleting...' : 'Delete'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
