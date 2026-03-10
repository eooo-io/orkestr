import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import { FolderOpen, RefreshCw, Plus } from 'lucide-react'
import { useAppStore } from '@/store/useAppStore'
import { syncProject } from '@/api/client'
import { Button } from '@/components/ui/button'

export function Projects() {
  const { projects, loadProjects, showToast } = useAppStore()

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

  return (
    <div className="p-6">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Projects</h1>
          <p className="text-sm text-muted-foreground mt-1">
            {projects.length} registered project{projects.length !== 1 && 's'}
          </p>
        </div>
        <Link to="/projects/new">
          <Button size="sm">
            <Plus className="h-4 w-4 mr-1" />
            New Project
          </Button>
        </Link>
      </div>

      {projects.length === 0 ? (
        <div className="text-center py-20 text-muted-foreground">
          <FolderOpen className="h-12 w-12 mx-auto mb-4 opacity-30" />
          <p>No projects registered yet.</p>
          <Link
            to="/projects/new"
            className="text-primary underline text-sm mt-1 inline-block"
          >
            Create your first project
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {projects.map((project) => (
            <div
              key={project.id}
              className="rounded-lg border border-border bg-card p-5 hover:border-primary/40 hover:shadow-sm transition-all"
            >
              <Link to={`/projects/${project.id}`}>
                <h2 className="font-semibold text-base hover:text-primary transition-colors">
                  {project.name}
                </h2>
              </Link>
              {project.description && (
                <p className="text-sm text-muted-foreground mt-1 line-clamp-2">
                  {project.description}
                </p>
              )}
              <p className="text-xs text-muted-foreground mt-2 font-mono truncate">
                {project.path}
              </p>

              <div className="flex items-center gap-1.5 mt-3 flex-wrap">
                {project.providers.map((p) => (
                  <span
                    key={p}
                    className="text-[10px] px-1.5 py-0.5 rounded bg-secondary text-secondary-foreground capitalize"
                  >
                    {p}
                  </span>
                ))}
              </div>

              <div className="flex items-center justify-between mt-4 pt-3 border-t border-border">
                <span className="text-xs text-muted-foreground">
                  {project.skills_count} skill
                  {project.skills_count !== 1 && 's'}
                </span>
                <div className="flex items-center gap-2">
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
    </div>
  )
}
