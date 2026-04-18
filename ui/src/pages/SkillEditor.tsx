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
import { TemplateVariablesPanel } from '@/components/skills/TemplateVariablesPanel'
import { ActionBar } from '@/components/skills/ActionBar'
import { LiveTestPanel } from '@/components/skills/LiveTestPanel'
import { VersionHistoryPanel } from '@/components/skills/VersionHistoryPanel'
import { LintPanel } from '@/components/skills/LintPanel'
import { SecurityPanel } from '@/components/skills/SecurityPanel'
import { ReviewPanel } from '@/components/skills/ReviewPanel'
import { RegressionTestPanel } from '@/components/skills/RegressionTestPanel'
import { InheritancePanel } from '@/components/skills/InheritancePanel'
import { AssetsPanel } from '@/components/skills/AssetsPanel'
import { GotchaPanel } from '@/components/skills/GotchaPanel'
import { InlineGotchaStrip } from '@/components/skills/InlineGotchaStrip'
import { EvalPanel } from '@/components/skills/EvalPanel'
import { GenerateSkillModal } from '@/components/skills/GenerateSkillModal'
import { useConfirm } from '@/hooks/useConfirm'
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
    template_variables: null,
    body: '',
    tags: [],
    project_id: projectId ? parseInt(projectId) : undefined,
  })
  const [projectSkills, setProjectSkills] = useState<Skill[]>([])
  const [loading, setLoading] = useState(!isNew)
  const [saving, setSaving] = useState(false)
  const [activeTab, setActiveTab] = useState<'test' | 'versions' | 'lint' | 'gotchas' | 'evals' | 'assets' | 'security' | 'review' | 'regression' | 'inherit'>('test')
  const [showGenerate, setShowGenerate] = useState(false)
  const [gotchaRefreshKey, setGotchaRefreshKey] = useState(0)
  const { isDirty, setDirty, showToast, loadProjects } = useAppStore()
  const confirm = useConfirm()
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
    if (!id || !(await confirm({ message: 'Delete this skill?', title: 'Confirm Delete' }))) return
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

      <div className="flex flex-col lg:flex-row flex-1 overflow-hidden">
        {/* Left: Editor */}
        <div className="flex-1 flex flex-col min-w-0 min-h-0">
          <FrontmatterForm skill={skill} onChange={handleFieldChange} projectSkills={projectSkills} />
          {!isNew && skill.id && (
            <InlineGotchaStrip
              skillId={skill.id}
              refreshKey={gotchaRefreshKey}
              onManage={() => setActiveTab('gotchas')}
            />
          )}
          {!isNew &&
            skill.id &&
            skill.project_id &&
            skill.template_variables &&
            skill.template_variables.length > 0 && (
              <TemplateVariablesPanel
                projectId={skill.project_id}
                skillId={skill.id}
                templateVariables={skill.template_variables}
              />
            )}
          <div className="flex-1 min-h-[300px]">
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
        <div className="w-full lg:w-[480px] border-t lg:border-t-0 lg:border-l border-border flex flex-col bg-muted/20 min-h-[300px] lg:min-h-0">
          <div className="flex flex-wrap border-b border-border">
            {(['test', 'versions', 'lint', 'gotchas', 'evals', 'assets', 'security', 'review', 'regression', 'inherit'] as const).map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`shrink-0 px-2.5 py-2 text-[11px] font-medium transition-all duration-150 capitalize ${
                  activeTab === tab
                    ? 'border-b-2 border-primary text-foreground'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                {tab === 'regression' ? 'Tests' : tab}
                {tab === 'assets' && skill.asset_count ? (
                  <span className="ml-1 text-[10px] bg-primary/20 text-primary rounded-full px-1.5">
                    {skill.asset_count}
                  </span>
                ) : null}
              </button>
            ))}
          </div>
          <div className="flex-1 overflow-y-auto">
            {!isNew && skill.id ? (
              activeTab === 'test' ? (
                <LiveTestPanel skillId={skill.id} />
              ) : activeTab === 'versions' ? (
                <VersionHistoryPanel
                  skillId={skill.id}
                  onRestore={() => {
                    fetchSkill(parseInt(id!)).then((s) => {
                      setSkill(s)
                      setDirty(false)
                    })
                  }}
                />
              ) : activeTab === 'lint' ? (
                <LintPanel skillId={skill.id} />
              ) : activeTab === 'gotchas' ? (
                <GotchaPanel
                  skillId={skill.id}
                  onMutate={() => setGotchaRefreshKey((k) => k + 1)}
                />
              ) : activeTab === 'evals' ? (
                <EvalPanel skillId={skill.id} />
              ) : activeTab === 'assets' ? (
                <AssetsPanel skillId={skill.id} />
              ) : activeTab === 'security' ? (
                <SecurityPanel skillId={skill.id} />
              ) : activeTab === 'review' ? (
                <ReviewPanel skillId={skill.id} />
              ) : activeTab === 'regression' ? (
                <RegressionTestPanel skillId={skill.id} />
              ) : (
                <InheritancePanel skillId={skill.id} />
              )
            ) : (
              <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
                Save the skill first.
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
