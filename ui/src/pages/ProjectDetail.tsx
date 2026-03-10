import { useEffect, useState } from 'react'
import { useParams, Link, useNavigate } from 'react-router-dom'
import {
  Plus,
  RefreshCw,
  ArrowUpFromLine,
  Eye,
  LayoutGrid,
  List,
  Pencil,
  BookOpen,
  Bot,
  Sparkles,
  Download,
  Wand2,
  Package,
  Upload,
  CheckSquare,
  Square,
  CheckCheck,
} from 'lucide-react'
import { fetchProject, fetchSkills, syncProject, scanProject, createSkill } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { SkillCard } from '@/components/skills/SkillCard'
import { ImportLibraryModal } from '@/components/library/ImportLibraryModal'
import { SkillsShImportModal } from '@/components/library/SkillsShImportModal'
import { AgentsTab } from '@/components/agents/AgentsTab'
import { GenerateSkillModal } from '@/components/skills/GenerateSkillModal'
import { ExportModal } from '@/components/bundles/ExportModal'
import { ImportBundleModal } from '@/components/bundles/ImportBundleModal'
import { SyncPreviewModal } from '@/components/sync/SyncPreviewModal'
import { BulkActionBar } from '@/components/skills/BulkActionBar'
import { Button } from '@/components/ui/button'
import type { Project, Skill, GeneratedSkill } from '@/types'

export function ProjectDetail() {
  const { id } = useParams<{ id: string }>()
  const [project, setProject] = useState<Project | null>(null)
  const [skills, setSkills] = useState<Skill[]>([])
  const [loading, setLoading] = useState(true)
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid')
  const [activeTab, setActiveTab] = useState<'skills' | 'agents'>('skills')
  const [showLibrary, setShowLibrary] = useState(false)
  const [showSkillsSh, setShowSkillsSh] = useState(false)
  const [showGenerate, setShowGenerate] = useState(false)
  const [showExport, setShowExport] = useState(false)
  const [showImportBundle, setShowImportBundle] = useState(false)
  const [showSyncPreview, setShowSyncPreview] = useState(false)
  const [selectMode, setSelectMode] = useState(false)
  const [selectedSkillIds, setSelectedSkillIds] = useState<Set<number>>(new Set())
  const navigate = useNavigate()
  const { setActiveProjectId, showToast, loadProjects } = useAppStore()

  useEffect(() => {
    if (!id) return
    const projectId = parseInt(id)
    setActiveProjectId(projectId)

    Promise.all([fetchProject(projectId), fetchSkills(projectId)])
      .then(([proj, sk]) => {
        setProject(proj)
        setSkills(sk)
      })
      .finally(() => setLoading(false))
  }, [id, setActiveProjectId])

  const handleSync = async () => {
    if (!project) return
    try {
      await syncProject(project.id)
      showToast(`Synced ${project.name}`)
      loadProjects()
    } catch {
      showToast('Sync failed', 'error')
    }
  }

  const handleScan = async () => {
    if (!project) return
    try {
      await scanProject(project.id)
      showToast('Scan queued')
      // Reload skills after a brief delay for the queue
      setTimeout(async () => {
        const sk = await fetchSkills(project.id)
        setSkills(sk)
        loadProjects()
      }, 1500)
    } catch {
      showToast('Scan failed', 'error')
    }
  }

  const handleGenerated = async (generated: GeneratedSkill) => {
    if (!project) return
    try {
      const created = await createSkill(project.id, generated)
      showToast('Skill generated and saved')
      setShowGenerate(false)
      loadProjects()
      navigate(`/skills/${created.id}`)
    } catch {
      showToast('Failed to save generated skill', 'error')
    }
  }

  const toggleSkillSelection = (id: number) => {
    setSelectedSkillIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return next
    })
  }

  const selectAll = () => {
    setSelectedSkillIds(new Set(skills.map((s) => s.id)))
  }

  const deselectAll = () => {
    setSelectedSkillIds(new Set())
  }

  const handleBulkActionComplete = async () => {
    if (!project) return
    const sk = await fetchSkills(project.id)
    setSkills(sk)
    setSelectedSkillIds(new Set())
    setSelectMode(false)
    showToast('Bulk action completed')
    loadProjects()
  }

  if (loading) {
    return (
      <div className="p-6">
        <div className="animate-pulse space-y-4">
          <div className="h-8 w-48 bg-muted rounded" />
          <div className="h-4 w-96 bg-muted rounded" />
          <div className="grid grid-cols-3 gap-4 mt-6">
            {[1, 2, 3].map((i) => (
              <div key={i} className="h-24 bg-muted rounded-lg" />
            ))}
          </div>
        </div>
      </div>
    )
  }

  if (!project) {
    return (
      <div className="p-6 text-center text-muted-foreground">
        Project not found.
      </div>
    )
  }

  return (
    <div className="p-6">
      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">{project.name}</h1>
          {project.description && (
            <p className="text-sm text-muted-foreground mt-1">
              {project.description}
            </p>
          )}
          <p className="text-xs text-muted-foreground font-mono mt-1">
            {project.path}
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Link to={`/projects/${project.id}/edit`}>
            <Button variant="ghost" size="sm">
              <Pencil className="h-4 w-4 mr-1" />
              Edit
            </Button>
          </Link>
          <Button variant="outline" size="sm" onClick={handleScan}>
            <RefreshCw className="h-4 w-4 mr-1" />
            Scan
          </Button>
          <Button variant="outline" size="sm" onClick={() => setShowSyncPreview(true)}>
            <Eye className="h-4 w-4 mr-1" />
            Preview Sync
          </Button>
          <Button variant="outline" size="sm" onClick={handleSync}>
            <ArrowUpFromLine className="h-4 w-4 mr-1" />
            Sync
          </Button>
          <Button variant="outline" size="sm" onClick={() => setShowLibrary(true)}>
            <BookOpen className="h-4 w-4 mr-1" />
            Library
          </Button>
          <Button variant="outline" size="sm" onClick={() => setShowSkillsSh(true)}>
            <Download className="h-4 w-4 mr-1" />
            Skills.sh
          </Button>
          <Button variant="outline" size="sm" onClick={() => setShowGenerate(true)}>
            <Wand2 className="h-4 w-4 mr-1" />
            Generate
          </Button>
          <Button variant="outline" size="sm" onClick={() => setShowExport(true)}>
            <Package className="h-4 w-4 mr-1" />
            Export
          </Button>
          <Button variant="outline" size="sm" onClick={() => setShowImportBundle(true)}>
            <Upload className="h-4 w-4 mr-1" />
            Import
          </Button>
          <Link to={`/skills/new?project_id=${project.id}`}>
            <Button size="sm">
              <Plus className="h-4 w-4 mr-1" />
              Add Skill
            </Button>
          </Link>
        </div>
      </div>

      {/* Tab Switcher */}
      <div className="flex items-center gap-1 mb-4 border-b border-border">
        <button
          onClick={() => setActiveTab('skills')}
          className={`flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-colors -mb-px ${
            activeTab === 'skills'
              ? 'border-primary text-foreground'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <Sparkles className="h-4 w-4" />
          Skills
          <span className="ml-1 text-xs px-1.5 py-0.5 rounded-full bg-muted">
            {skills.length}
          </span>
        </button>
        <button
          onClick={() => setActiveTab('agents')}
          className={`flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 transition-colors -mb-px ${
            activeTab === 'agents'
              ? 'border-primary text-foreground'
              : 'border-transparent text-muted-foreground hover:text-foreground'
          }`}
        >
          <Bot className="h-4 w-4" />
          Agents
        </button>
      </div>

      {activeTab === 'skills' && (
        <>
          <div className="flex items-center justify-between mb-4">
            <p className="text-sm text-muted-foreground">
              {skills.length} skill{skills.length !== 1 && 's'}
              {project.synced_at && (
                <span className="ml-2">
                  &middot; Last synced{' '}
                  {new Date(project.synced_at).toLocaleDateString()}
                </span>
              )}
            </p>
            <div className="flex items-center gap-2">
              {selectMode && skills.length > 0 && (
                <div className="flex items-center gap-1">
                  <Button variant="ghost" size="sm" onClick={selectAll}>
                    <CheckCheck className="h-4 w-4 mr-1" />
                    All
                  </Button>
                  <Button variant="ghost" size="sm" onClick={deselectAll}>
                    <Square className="h-4 w-4 mr-1" />
                    None
                  </Button>
                </div>
              )}
              <Button
                variant={selectMode ? 'default' : 'outline'}
                size="sm"
                onClick={() => {
                  setSelectMode(!selectMode)
                  if (selectMode) setSelectedSkillIds(new Set())
                }}
              >
                <CheckSquare className="h-4 w-4 mr-1" />
                {selectMode ? 'Exit Select' : 'Select'}
              </Button>
              <div className="flex items-center border border-border rounded-md">
                <button
                  onClick={() => setViewMode('grid')}
                  className={`p-1.5 ${viewMode === 'grid' ? 'bg-accent' : ''}`}
                >
                  <LayoutGrid className="h-4 w-4" />
                </button>
                <button
                  onClick={() => setViewMode('list')}
                  className={`p-1.5 ${viewMode === 'list' ? 'bg-accent' : ''}`}
                >
                  <List className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>

          {skills.length === 0 ? (
            <div className="text-center py-16 text-muted-foreground">
              <p>No skills yet.</p>
              <div className="flex items-center justify-center gap-3 mt-2">
                <Link
                  to={`/skills/new?project_id=${project.id}`}
                  className="text-primary underline text-sm"
                >
                  Create your first skill
                </Link>
                <span className="text-xs">or</span>
                <button
                  onClick={() => setShowLibrary(true)}
                  className="text-primary underline text-sm"
                >
                  import from library
                </button>
              </div>
            </div>
          ) : (
            <div
              className={
                viewMode === 'grid'
                  ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3'
                  : 'space-y-2'
              }
            >
              {skills.map((skill) => (
                <SkillCard
                  key={skill.id}
                  skill={skill}
                  selectable={selectMode}
                  selected={selectedSkillIds.has(skill.id)}
                  onToggleSelect={toggleSkillSelection}
                />
              ))}
            </div>
          )}
        </>
      )}

      {activeTab === 'agents' && (
        <AgentsTab projectId={project.id} skills={skills} />
      )}

      {showLibrary && (
        <ImportLibraryModal
          projectId={project.id}
          onClose={() => setShowLibrary(false)}
          onImported={async () => {
            const sk = await fetchSkills(project.id)
            setSkills(sk)
          }}
        />
      )}

      {showSkillsSh && (
        <SkillsShImportModal
          target="project"
          projectId={project.id}
          onClose={() => setShowSkillsSh(false)}
          onImported={async () => {
            const sk = await fetchSkills(project.id)
            setSkills(sk)
          }}
        />
      )}

      {showGenerate && (
        <GenerateSkillModal
          onGenerated={handleGenerated}
          onClose={() => setShowGenerate(false)}
        />
      )}

      {showExport && (
        <ExportModal
          projectId={project.id}
          projectName={project.name}
          onClose={() => setShowExport(false)}
        />
      )}

      {showImportBundle && (
        <ImportBundleModal
          projectId={project.id}
          onClose={() => setShowImportBundle(false)}
          onImported={async () => {
            const sk = await fetchSkills(project.id)
            setSkills(sk)
            loadProjects()
          }}
        />
      )}

      {showSyncPreview && (
        <SyncPreviewModal
          projectId={project.id}
          onClose={() => setShowSyncPreview(false)}
          onSynced={() => {
            showToast(`Synced ${project.name}`)
            loadProjects()
          }}
        />
      )}

      {selectedSkillIds.size > 0 && (
        <BulkActionBar
          selectedIds={selectedSkillIds}
          projectId={project.id}
          onClearSelection={() => {
            setSelectedSkillIds(new Set())
            setSelectMode(false)
          }}
          onActionComplete={handleBulkActionComplete}
        />
      )}
    </div>
  )
}
