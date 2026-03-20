import { useEffect, useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowLeft,
  Loader2,
  Save,
  Trash2,
  ChevronDown,
  ChevronRight,
  Monitor,
  Server,
  Shield,
  DollarSign,
} from 'lucide-react'
import {
  fetchProject,
  createProject,
  updateProject,
  deleteProject,
  fetchModels,
  fetchSettings,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import type { Project, ModelGroup } from '@/types'

const PROVIDERS = [
  { slug: 'claude', label: 'Claude', desc: '.claude/CLAUDE.md' },
  { slug: 'cursor', label: 'Cursor', desc: '.cursor/rules/' },
  { slug: 'copilot', label: 'Copilot', desc: '.github/copilot-instructions.md' },
  { slug: 'windsurf', label: 'Windsurf', desc: '.windsurf/rules/' },
  { slug: 'cline', label: 'Cline', desc: '.clinerules' },
  { slug: 'openai', label: 'OpenAI', desc: '.openai/instructions.md' },
]

const ICON_OPTIONS = [
  '\u{1F916}', '\u{1F9E0}', '\u{1F4BC}', '\u{1F52C}', '\u{1F4CA}', '\u{1F6E0}\u{FE0F}',
  '\u{1F3AF}', '\u{1F680}', '\u{1F4DD}', '\u{1F4AC}', '\u{1F512}', '\u{26A1}',
]

const COLOR_OPTIONS = [
  { value: '#3B82F6', label: 'Blue' },
  { value: '#10B981', label: 'Green' },
  { value: '#8B5CF6', label: 'Purple' },
  { value: '#F97316', label: 'Orange' },
  { value: '#EF4444', label: 'Red' },
  { value: '#14B8A6', label: 'Teal' },
  { value: '#EC4899', label: 'Pink' },
  { value: '#6B7280', label: 'Gray' },
]

const ENVIRONMENTS = [
  {
    value: 'development' as const,
    label: 'Development',
    desc: 'Full logging, relaxed guardrails',
    icon: Monitor,
  },
  {
    value: 'staging' as const,
    label: 'Staging',
    desc: 'Production-like with detailed traces',
    icon: Server,
  },
  {
    value: 'production' as const,
    label: 'Production',
    desc: 'Strict guardrails, optimized logging',
    icon: Shield,
  },
]

interface ProjectFormData {
  name: string
  description: string
  icon: string | null
  color: string | null
  default_model: string | null
  environment: 'development' | 'staging' | 'production'
  monthly_budget_usd: string
  path: string
  providers: string[]
}

export function ProjectForm() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const isEdit = !!id
  const { showToast, loadProjects } = useAppStore()
  const confirm = useConfirm()

  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)
  const [allowedPaths, setAllowedPaths] = useState<string[]>([])
  const [modelGroups, setModelGroups] = useState<ModelGroup[]>([])
  const [advancedOpen, setAdvancedOpen] = useState(false)
  const [customIcon, setCustomIcon] = useState('')
  const [form, setForm] = useState<ProjectFormData>({
    name: '',
    description: '',
    icon: null,
    color: null,
    default_model: null,
    environment: 'development',
    monthly_budget_usd: '',
    path: '',
    providers: [],
  })

  useEffect(() => {
    fetchSettings().then((s) => {
      if (s.allowed_project_paths) setAllowedPaths(s.allowed_project_paths)
    })
    fetchModels().then(setModelGroups).catch(() => {})
  }, [])

  useEffect(() => {
    if (isEdit) {
      fetchProject(parseInt(id))
        .then((p) => {
          setForm({
            name: p.name,
            description: p.description || '',
            icon: p.icon || null,
            color: p.color || null,
            default_model: p.default_model || null,
            environment: p.environment || 'development',
            monthly_budget_usd: p.monthly_budget_usd != null ? String(p.monthly_budget_usd) : '',
            path: p.path || '',
            providers: p.providers || [],
          })
          // If the icon is custom (not in presets), put it in customIcon
          if (p.icon && !ICON_OPTIONS.includes(p.icon)) {
            setCustomIcon(p.icon)
          }
        })
        .finally(() => setLoading(false))
    }
  }, [id, isEdit])

  const update = (field: keyof ProjectFormData, value: unknown) => {
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

    // Validate path only if provided
    if (form.path.trim()) {
      if (
        allowedPaths.length > 0 &&
        !allowedPaths.some(
          (base) => form.path.trim() === base || form.path.trim().startsWith(base + '/'),
        )
      ) {
        showToast(`Path must be within: ${allowedPaths.join(' or ')}`, 'error')
        return
      }
    }

    setSaving(true)
    try {
      const data: Record<string, unknown> = {
        name: form.name.trim(),
        description: form.description.trim() || null,
        icon: form.icon || null,
        color: form.color || null,
        default_model: form.default_model || null,
        environment: form.environment,
        monthly_budget_usd: form.monthly_budget_usd ? parseFloat(form.monthly_budget_usd) : null,
        path: form.path.trim() || null,
        providers: form.providers,
      }

      if (isEdit) {
        await updateProject(parseInt(id), data as Partial<Project>)
        showToast('Project updated')
        loadProjects()
        navigate(`/projects/${id}`)
      } else {
        const created = await createProject(data as Partial<Project>)
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
    if (!(await confirm({ message: 'Delete this project and all its skills? This cannot be undone.', title: 'Confirm Delete' })))
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
    <div className="p-4 md:p-6 max-w-2xl">
      {/* Header */}
      <div className="flex items-center gap-3 mb-6">
        <button
          onClick={() => navigate(-1)}
          className="p-1.5 hover:bg-muted transition-all duration-150"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <div className="flex items-center gap-2">
          {form.icon && (
            <span className="text-2xl">{form.icon}</span>
          )}
          <h1 className="text-2xl font-semibold tracking-tight">
            {isEdit ? 'Edit Project' : 'New Project'}
          </h1>
        </div>
      </div>

      <div className="space-y-6">
        {/* ── Primary Section ── */}
        <div className="space-y-5">
          {/* Name */}
          <Field label="Name" required>
            <input
              type="text"
              value={form.name}
              onChange={(e) => update('name', e.target.value)}
              placeholder="My Agent Team"
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
              autoFocus
            />
          </Field>

          {/* Description */}
          <Field label="Description">
            <textarea
              value={form.description}
              onChange={(e) => update('description', e.target.value)}
              placeholder="What does this project's agent team do?"
              rows={2}
              className="w-full px-3 py-2 text-sm border border-input bg-background resize-none focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </Field>

          {/* Icon Picker */}
          <Field label="Icon">
            <div className="flex items-center gap-2 flex-wrap">
              {ICON_OPTIONS.map((emoji) => (
                <button
                  key={emoji}
                  type="button"
                  onClick={() => {
                    update('icon', form.icon === emoji ? null : emoji)
                    setCustomIcon('')
                  }}
                  className={`w-10 h-10 flex items-center justify-center text-lg border transition-all duration-150 ${
                    form.icon === emoji
                      ? 'border-primary bg-primary/10 ring-1 ring-primary'
                      : 'border-border hover:border-primary/30'
                  }`}
                >
                  {emoji}
                </button>
              ))}
              <div className="relative">
                <input
                  type="text"
                  value={customIcon}
                  onChange={(e) => {
                    const val = e.target.value
                    setCustomIcon(val)
                    if (val.trim()) {
                      update('icon', val.trim())
                    }
                  }}
                  placeholder="..."
                  className={`w-10 h-10 text-center text-lg border bg-background focus:outline-none focus:ring-1 focus:ring-ring transition-all duration-150 ${
                    customIcon && form.icon === customIcon
                      ? 'border-primary bg-primary/10'
                      : 'border-border'
                  }`}
                  maxLength={4}
                  title="Type a custom emoji"
                />
              </div>
            </div>
          </Field>

          {/* Color Picker */}
          <Field label="Color">
            <div className="flex items-center gap-2">
              {COLOR_OPTIONS.map((c) => (
                <button
                  key={c.value}
                  type="button"
                  onClick={() => update('color', form.color === c.value ? null : c.value)}
                  className={`w-8 h-8 transition-all duration-150 ${
                    form.color === c.value
                      ? 'ring-2 ring-offset-2 ring-offset-background ring-primary scale-110'
                      : 'hover:scale-105'
                  }`}
                  style={{ backgroundColor: c.value }}
                  title={c.label}
                />
              ))}
            </div>
          </Field>
        </div>

        {/* ── Configuration Section ── */}
        <div className="border-t border-border pt-5 space-y-5">
          <h2 className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
            Configuration
          </h2>

          {/* Default Model */}
          <Field label="Default Model" hint="Default model for agents in this project">
            <select
              value={form.default_model || ''}
              onChange={(e) => update('default_model', e.target.value || null)}
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            >
              <option value="">System default</option>
              {modelGroups.map((group) => (
                <optgroup key={group.provider} label={group.label}>
                  {group.models.map((m) => (
                    <option key={m.id} value={m.id}>
                      {m.name}
                    </option>
                  ))}
                </optgroup>
              ))}
            </select>
          </Field>

          {/* Environment */}
          <Field label="Environment">
            <div className="grid grid-cols-3 gap-2">
              {ENVIRONMENTS.map((env) => {
                const Icon = env.icon
                return (
                  <label
                    key={env.value}
                    className={`flex items-start gap-2 p-3 border cursor-pointer transition-colors ${
                      form.environment === env.value
                        ? 'border-primary bg-primary/5'
                        : 'border-border hover:border-muted-foreground/30'
                    }`}
                  >
                    <input
                      type="radio"
                      name="environment"
                      value={env.value}
                      checked={form.environment === env.value}
                      onChange={() => update('environment', env.value)}
                      className="mt-0.5"
                    />
                    <div>
                      <div className="flex items-center gap-1.5">
                        <Icon className="h-3.5 w-3.5 text-muted-foreground" />
                        <span className="text-sm font-medium">{env.label}</span>
                      </div>
                      <p className="text-xs text-muted-foreground mt-0.5">{env.desc}</p>
                    </div>
                  </label>
                )
              })}
            </div>
          </Field>

          {/* Monthly Budget */}
          <Field label="Monthly Budget" hint="Spending limit for this project's agent runs">
            <div className="relative">
              <DollarSign className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <input
                type="number"
                value={form.monthly_budget_usd}
                onChange={(e) => update('monthly_budget_usd', e.target.value)}
                placeholder="No limit"
                min="0"
                step="0.01"
                className="w-full pl-8 pr-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
              />
            </div>
          </Field>
        </div>

        {/* ── Advanced Section (Collapsible) ── */}
        <div className="border-t border-border pt-4">
          <button
            type="button"
            onClick={() => setAdvancedOpen(!advancedOpen)}
            className="flex items-center gap-2 text-xs font-medium text-muted-foreground uppercase tracking-wide hover:text-foreground transition-colors w-full text-left"
          >
            {advancedOpen ? (
              <ChevronDown className="h-3.5 w-3.5" />
            ) : (
              <ChevronRight className="h-3.5 w-3.5" />
            )}
            Advanced
          </button>

          {advancedOpen && (
            <div className="mt-4 space-y-5">
              {/* Path */}
              <Field label="Filesystem Path" hint="For skill file sync to host filesystem">
                <input
                  type="text"
                  value={form.path}
                  onChange={(e) => update('path', e.target.value)}
                  placeholder="/Users/you/projects/my-project"
                  className="w-full px-3 py-2 text-sm border border-input bg-background font-mono text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                />
                <p className="text-xs text-muted-foreground mt-1">
                  {allowedPaths.length > 0
                    ? `Must be within: ${allowedPaths.join(' or ')}`
                    : 'Absolute path to the project directory on the host filesystem.'}
                </p>
              </Field>

              {/* Providers */}
              <Field label="Sync Providers" hint="Select which AI providers to sync skills to">
                <div className="grid grid-cols-2 gap-2">
                  {PROVIDERS.map((p) => (
                    <label
                      key={p.slug}
                      className={`flex items-start gap-3 p-3 border cursor-pointer transition-all duration-150 ${
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
              </Field>
            </div>
          )}
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

/* ── Local helper components ── */

function Field({
  label,
  required,
  hint,
  children,
}: {
  label: string
  required?: boolean
  hint?: string
  children: React.ReactNode
}) {
  return (
    <div className="space-y-1.5">
      <label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
        {label}
        {required && <span className="text-red-400 ml-0.5">*</span>}
      </label>
      {hint && (
        <p className="text-xs text-muted-foreground">{hint}</p>
      )}
      {children}
    </div>
  )
}
