import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Loader2, Save, Trash2 } from 'lucide-react'
import {
  fetchProject,
  createProject,
  updateProject,
  deleteProject,
  fetchSettings,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import type { Project } from '@/types'

const PROVIDERS = [
  { slug: 'claude', label: 'Claude', desc: '.claude/CLAUDE.md' },
  { slug: 'cursor', label: 'Cursor', desc: '.cursor/rules/' },
  { slug: 'copilot', label: 'Copilot', desc: '.github/copilot-instructions.md' },
  { slug: 'windsurf', label: 'Windsurf', desc: '.windsurf/rules/' },
  { slug: 'cline', label: 'Cline', desc: '.clinerules' },
  { slug: 'openai', label: 'OpenAI', desc: '.openai/instructions.md' },
]

export function ProjectForm() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const isEdit = !!id
  const { showToast, loadProjects } = useAppStore()

  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [allowedPaths, setAllowedPaths] = useState<string[]>([])
  const [form, setForm] = useState({
    name: '',
    description: '',
    path: '',
    providers: [] as string[],
  })

  useEffect(() => {
    fetchSettings().then((s) => {
      if (s.allowed_project_paths) setAllowedPaths(s.allowed_project_paths)
    })
  }, [])

  useEffect(() => {
    if (isEdit) {
      fetchProject(parseInt(id))
        .then((p) => {
          setForm({
            name: p.name,
            description: p.description || '',
            path: p.path,
            providers: p.providers || [],
          })
        })
        .finally(() => setLoading(false))
    }
  }, [id, isEdit])

  const handleChange = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
  }

  const toggleProvider = (slug: string) => {
    setForm((prev) => ({
      ...prev,
      providers: prev.providers.includes(slug)
        ? prev.providers.filter((p) => p !== slug)
        : [...prev.providers, slug],
    }))
  }

  const handleSave = async () => {
    if (!form.name.trim()) {
      showToast('Project name is required', 'error')
      return
    }
    if (!form.path.trim()) {
      showToast('Project path is required', 'error')
      return
    }
    if (
      allowedPaths.length > 0 &&
      !allowedPaths.some(
        (base) => form.path.trim() === base || form.path.trim().startsWith(base + '/'),
      )
    ) {
      showToast(`Path must be within: ${allowedPaths.join(' or ')}`, 'error')
      return
    }

    setSaving(true)
    try {
      const data = {
        name: form.name.trim(),
        description: form.description.trim() || null,
        path: form.path.trim(),
        providers: form.providers,
      }

      if (isEdit) {
        await updateProject(parseInt(id), data)
        showToast('Project updated')
        loadProjects()
        navigate(`/projects/${id}`)
      } else {
        const created = await createProject(data)
        showToast('Project created')
        loadProjects()
        navigate(`/projects/${created.id}`)
      }
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
      <div className="flex items-center justify-center h-full">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="p-6 max-w-2xl">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <button
          onClick={() => navigate(-1)}
          className="p-1.5 rounded-md hover:bg-muted transition-colors"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <h1 className="text-2xl font-bold">
          {isEdit ? 'Edit Project' : 'New Project'}
        </h1>
      </div>

      <div className="space-y-5">
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
            className="w-full px-3 py-2 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            autoFocus
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
            className="w-full px-3 py-2 text-sm rounded-md border border-input bg-background resize-none focus:outline-none focus:ring-1 focus:ring-ring"
          />
        </div>

        {/* Path */}
        <div>
          <label className="block text-sm font-medium mb-1.5">
            Path <span className="text-destructive">*</span>
          </label>
          <input
            type="text"
            value={form.path}
            onChange={(e) => handleChange('path', e.target.value)}
            placeholder="/Users/you/projects/my-project"
            className="w-full px-3 py-2 text-sm rounded-md border border-input bg-background font-mono text-xs focus:outline-none focus:ring-1 focus:ring-ring"
          />
          <p className="text-xs text-muted-foreground mt-1">
            {allowedPaths.length > 0
              ? `Must be within: ${allowedPaths.join(' or ')}`
              : 'Absolute path to the project directory on the host filesystem.'}
          </p>
        </div>

        {/* Providers */}
        <div>
          <label className="block text-sm font-medium mb-1.5">
            Sync Providers
          </label>
          <p className="text-xs text-muted-foreground mb-3">
            Select which AI providers to sync skills to when you run Sync.
          </p>
          <div className="grid grid-cols-2 gap-2">
            {PROVIDERS.map((p) => (
              <label
                key={p.slug}
                className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                  form.providers.includes(p.slug)
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:border-primary/30'
                }`}
              >
                <input
                  type="checkbox"
                  checked={form.providers.includes(p.slug)}
                  onChange={() => toggleProvider(p.slug)}
                  className="mt-0.5 rounded border-input accent-primary"
                />
                <div>
                  <span className="text-sm font-medium">{p.label}</span>
                  <span className="block text-[11px] text-muted-foreground font-mono mt-0.5">
                    {p.desc}
                  </span>
                </div>
              </label>
            ))}
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-between pt-4 border-t border-border">
          <div>
            {isEdit && (
              <Button variant="destructive" size="sm" onClick={handleDelete}>
                <Trash2 className="h-4 w-4 mr-1" />
                Delete Project
              </Button>
            )}
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" onClick={() => navigate(-1)}>
              Cancel
            </Button>
            <Button size="sm" onClick={handleSave} disabled={saving}>
              {saving ? (
                <Loader2 className="h-4 w-4 mr-1 animate-spin" />
              ) : (
                <Save className="h-4 w-4 mr-1" />
              )}
              {isEdit ? 'Save Changes' : 'Create Project'}
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}
