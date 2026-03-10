import { useEffect, useState, useCallback, useRef } from 'react'
import { useParams, useSearchParams, useNavigate } from 'react-router-dom'
import Editor from '@monaco-editor/react'
import {
  fetchSkill,
  fetchSkills,
  createSkill,
  updateSkill,
  deleteSkill,
  duplicateSkill,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { FrontmatterForm } from '@/components/skills/FrontmatterForm'
import { ActionBar } from '@/components/skills/ActionBar'
import { LiveTestPanel } from '@/components/skills/LiveTestPanel'
import { VersionHistoryPanel } from '@/components/skills/VersionHistoryPanel'
import { LintPanel } from '@/components/skills/LintPanel'
import { GenerateSkillModal } from '@/components/skills/GenerateSkillModal'
import type { Skill, GeneratedSkill } from '@/types'

export function SkillEditor() {
  const { id } = useParams<{ id: string }>()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const isNew = !id || id === 'new'
  const projectId = searchParams.get('project_id')

  const [skill, setSkill] = useState<Partial<Skill>>({
    name: '',
    description: null,
    model: null,
    max_tokens: null,
    tools: [],
    includes: [],
    body: '',
    tags: [],
    project_id: projectId ? parseInt(projectId) : undefined,
  })
  const [projectSkills, setProjectSkills] = useState<Skill[]>([])
  const [loading, setLoading] = useState(!isNew)
  const [saving, setSaving] = useState(false)
  const [activeTab, setActiveTab] = useState<'test' | 'versions' | 'lint'>('test')
  const [showGenerate, setShowGenerate] = useState(false)
  const { isDirty, setDirty, showToast, loadProjects } = useAppStore()
  const initialBody = useRef<string>('')

  useEffect(() => {
    if (!isNew && id) {
      fetchSkill(parseInt(id))
        .then((s) => {
          setSkill(s)
          initialBody.current = s.body || ''
          // Load sibling skills for includes picker
          if (s.project_id) {
            fetchSkills(s.project_id).then(setProjectSkills)
          }
        })
        .finally(() => setLoading(false))
    } else if (projectId) {
      fetchSkills(parseInt(projectId)).then(setProjectSkills)
    }
  }, [id, isNew, projectId])

  // Keyboard shortcut: Ctrl+S to save
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault()
        if (isDirty) handleSave()
      }
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  })

  // Unsaved changes guard
  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => {
      if (isDirty) {
        e.preventDefault()
      }
    }
    window.addEventListener('beforeunload', handler)
    return () => window.removeEventListener('beforeunload', handler)
  }, [isDirty])

  const handleFieldChange = useCallback(
    (field: string, value: unknown) => {
      setSkill((prev) => ({ ...prev, [field]: value }))
      setDirty(true)
    },
    [setDirty],
  )

  const handleBodyChange = useCallback(
    (value: string | undefined) => {
      setSkill((prev) => ({ ...prev, body: value || '' }))
      setDirty(true)
    },
    [setDirty],
  )

  const handleSave = async () => {
    if (!skill.name?.trim()) {
      showToast('Skill name is required', 'error')
      return
    }

    setSaving(true)
    try {
      if (isNew) {
        const pid = skill.project_id
        if (!pid) {
          showToast('No project selected', 'error')
          setSaving(false)
          return
        }
        const created = await createSkill(pid, skill)
        showToast('Skill created')
        setDirty(false)
        loadProjects()
        navigate(`/skills/${created.id}`, { replace: true })
      } else {
        await updateSkill(parseInt(id!), skill)
        showToast('Skill saved')
        setDirty(false)
        loadProjects()
      }
    } catch {
      showToast('Save failed', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleDuplicate = async () => {
    if (!id) return
    try {
      const dup = await duplicateSkill(parseInt(id))
      showToast('Skill duplicated')
      loadProjects()
      navigate(`/skills/${dup.id}`)
    } catch {
      showToast('Duplicate failed', 'error')
    }
  }

  const handleGenerated = (generated: GeneratedSkill) => {
    setSkill((prev) => ({
      ...prev,
      name: generated.name,
      description: generated.description,
      model: generated.model,
      max_tokens: generated.max_tokens,
      tags: generated.tags,
      body: generated.body,
    }))
    setDirty(true)
    setShowGenerate(false)
    showToast('Skill generated — review and save')
  }

  const handleDelete = async () => {
    if (!id || !confirm('Delete this skill?')) return
    try {
      await deleteSkill(parseInt(id))
      showToast('Skill deleted')
      setDirty(false)
      loadProjects()
      if (skill.project_id) {
        navigate(`/projects/${skill.project_id}`)
      } else {
        navigate('/projects')
      }
    } catch {
      showToast('Delete failed', 'error')
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-pulse text-muted-foreground">Loading...</div>
      </div>
    )
  }

  return (
    <div className="flex flex-col h-screen">
      <ActionBar
        onSave={handleSave}
        onDuplicate={isNew ? undefined : handleDuplicate}
        onDelete={isNew ? undefined : handleDelete}
        onGenerate={() => setShowGenerate(true)}
        isSaving={saving}
        isDirty={isDirty}
        isNew={isNew}
        projectId={skill.project_id}
      />

      <div className="flex flex-1 overflow-hidden">
        {/* Left: Editor */}
        <div className="flex-1 flex flex-col min-w-0">
          <FrontmatterForm skill={skill} onChange={handleFieldChange} projectSkills={projectSkills} />
          <div className="flex-1">
            <Editor
              height="100%"
              defaultLanguage="markdown"
              value={skill.body || ''}
              onChange={handleBodyChange}
              theme="vs-dark"
              options={{
                minimap: { enabled: false },
                wordWrap: 'on',
                fontSize: 14,
                lineNumbers: 'on',
                scrollBeyondLastLine: false,
                padding: { top: 12 },
                automaticLayout: true,
              }}
            />
          </div>
        </div>

        {/* Right: Tabs */}
        <div className="w-[400px] border-l border-border flex flex-col bg-muted/20">
          <div className="flex border-b border-border">
            <button
              onClick={() => setActiveTab('test')}
              className={`flex-1 px-4 py-2 text-sm font-medium transition-colors ${
                activeTab === 'test'
                  ? 'border-b-2 border-primary text-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              Test
            </button>
            <button
              onClick={() => setActiveTab('versions')}
              className={`flex-1 px-4 py-2 text-sm font-medium transition-colors ${
                activeTab === 'versions'
                  ? 'border-b-2 border-primary text-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              Versions
            </button>
            <button
              onClick={() => setActiveTab('lint')}
              className={`flex-1 px-4 py-2 text-sm font-medium transition-colors ${
                activeTab === 'lint'
                  ? 'border-b-2 border-primary text-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              Lint
            </button>
          </div>
          <div className="flex-1 overflow-hidden">
            {activeTab === 'test' ? (
              !isNew && skill.id ? (
                <LiveTestPanel skillId={skill.id} />
              ) : (
                <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
                  Save the skill first to test it.
                </div>
              )
            ) : activeTab === 'versions' ? (
              !isNew && skill.id ? (
                <VersionHistoryPanel
                  skillId={skill.id}
                  onRestore={() => {
                    fetchSkill(parseInt(id!)).then((s) => {
                      setSkill(s)
                      setDirty(false)
                    })
                  }}
                />
              ) : (
                <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
                  Save the skill first to see versions.
                </div>
              )
            ) : !isNew && skill.id ? (
              <LintPanel skillId={skill.id} />
            ) : (
              <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
                Save the skill first to lint it.
              </div>
            )}
          </div>
        </div>
      </div>

      {showGenerate && (
        <GenerateSkillModal
          onGenerated={handleGenerated}
          onClose={() => setShowGenerate(false)}
        />
      )}
    </div>
  )
}
