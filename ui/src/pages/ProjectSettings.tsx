import { useEffect, useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import {
  ArrowLeft,
  GitBranch,
  Download,
  Globe,
  Settings2,
  Loader2,
  Save,
  Trash2,
} from 'lucide-react'
import {
  fetchProject,
  updateProject,
  deleteProject,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { RepositorySettings } from '@/components/repository/RepositorySettings'
import { WebhookSettings } from '@/components/webhooks/WebhookSettings'
import ImportTab from '@/components/integrations/ImportTab'
import { Button } from '@/components/ui/button'
import type { Project } from '@/types'

export function ProjectSettings() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { showToast, loadProjects } = useAppStore()
  const [project, setProject] = useState<Project | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [activeTab, setActiveTab] = useState<'general' | 'repository' | 'import' | 'webhooks'>('general')
  const [form, setForm] = useState({
    name: '',
    description: '',
  })

  useEffect(() => {
    if (!id) return
    fetchProject(parseInt(id))
      .then((p) => {
        setProject(p)
        setForm({
          name: p.name,
          description: p.description || '',
        })
      })
      .finally(() => setLoading(false))
  }, [id])

  const handleChange = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  const handleSave = async () => {
    if (!form.name.trim()) {
      showToast('Project name is required', 'error')
      return
    }

    setSaving(true)
    try {
      await updateProject(parseInt(id!), {
        name: form.name.trim(),
        description: form.description.trim() || null,
      })
      showToast('Project updated')
      loadProjects()
    } catch {
      showToast('Save failed', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async () => {
    if (!confirm('Delete this project and all its skills? This cannot be undone.'))
      return

    try {
      await deleteProject(parseInt(id!))
      showToast('Project deleted')
      loadProjects()
      navigate('/projects')
    } catch {
      showToast('Delete failed', 'error')
    }
  }

  if (loading) {
    return (
      <div className="p-4 md:p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 w-48 bg-muted rounded" />
          <div className="h-4 w-96 bg-muted rounded" />
        </div>
      </div>
    )
  }

  if (!project) {
    return (
      <div className="p-4 md:p-6 text-center text-muted-foreground">
        Project not found.
      </div>
    )
  }

  return (
    <div className="p-4 md:p-6">
      <div className="flex items-center gap-3 mb-6">
        <Link to={`/projects/${project.id}`}>
          <Button variant="ghost" size="sm">
            <ArrowLeft className="h-4 w-4 mr-1" />
            Back
          </Button>
        </Link>
        <div className="min-w-0">
          <h1 className="text-xl md:text-2xl font-semibold tracking-tight">
            {project.name} — Settings
          </h1>
        </div>
      </div>

      {/* Tab Switcher */}
      <div className="flex items-center gap-1 mb-4 border-b border-border">
        <button
          onClick={() => setActiveTab('general')}
          className={`flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-all duration-150 -mb-px ${
            activeTab === 'general'
              ? 'border-primary text-foreground'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <Settings2 className="h-4 w-4" />
          General
        </button>
        <button
          onClick={() => setActiveTab('repository')}
          className={`flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-all duration-150 -mb-px ${
            activeTab === 'repository'
              ? 'border-primary text-foreground'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <GitBranch className="h-4 w-4" />
          Repository
        </button>
        <button
          onClick={() => setActiveTab('import')}
          className={`flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-all duration-150 -mb-px ${
            activeTab === 'import'
              ? 'border-primary text-foreground'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <Download className="h-4 w-4" />
          Import
        </button>
        <button
          onClick={() => setActiveTab('webhooks')}
          className={`flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-all duration-150 -mb-px ${
            activeTab === 'webhooks'
              ? 'border-primary text-foreground'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <Globe className="h-4 w-4" />
          Webhooks
        </button>
      </div>

      {activeTab === 'general' && (
        <div className="max-w-2xl space-y-5">
          {/* Name */}
          <div>
            <label className="block text-sm font-medium mb-1.5">
              Name <span className="text-destructive">*</span>
            </label>
            <input
              type="text"
              value={form.name}
              onChange={(e) => handleChange('name', e.target.value)}
              placeholder="My Project"
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>

          {/* Description */}
          <div>
            <label className="block text-sm font-medium mb-1.5">
              Description
            </label>
            <textarea
              value={form.description}
              onChange={(e) => handleChange('description', e.target.value)}
              placeholder="Optional project description"
              rows={2}
              className="w-full px-3 py-2 text-sm border border-input bg-background resize-none focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>

          {/* Actions */}
          <div className="flex items-center justify-between pt-4 border-t border-border">
            <Button variant="destructive" size="sm" onClick={handleDelete}>
              <Trash2 className="h-4 w-4 mr-1" />
              Delete Project
            </Button>
            <Button size="sm" onClick={handleSave} disabled={saving}>
              {saving ? (
                <Loader2 className="h-4 w-4 mr-1 animate-spin" />
              ) : (
                <Save className="h-4 w-4 mr-1" />
              )}
              Save Changes
            </Button>
          </div>
        </div>
      )}

      {activeTab === 'repository' && (
        <RepositorySettings projectId={project.id} />
      )}

      {activeTab === 'import' && (
        <ImportTab
          projectId={project.id}
          projectPath={project.path}
          onImported={() => {}}
        />
      )}

      {activeTab === 'webhooks' && (
        <WebhookSettings projectId={project.id} />
      )}
    </div>
  )
}
