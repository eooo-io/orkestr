import { useState, useEffect } from 'react'
import { Package, Loader2, X, Check } from 'lucide-react'
import { fetchSkills, fetchProjectAgents, exportBundle } from '@/api/client'
import { Button } from '@/components/ui/button'
import type { Skill, ProjectAgent } from '@/types'

interface ExportModalProps {
  projectId: number
  projectName: string
  onClose: () => void
}

export function ExportModal({ projectId, projectName, onClose }: ExportModalProps) {
  const [skills, setSkills] = useState<Skill[]>([])
  const [agents, setAgents] = useState<ProjectAgent[]>([])
  const [selectedSkillIds, setSelectedSkillIds] = useState<Set<number>>(new Set())
  const [selectedAgentIds, setSelectedAgentIds] = useState<Set<number>>(new Set())
  const [format, setFormat] = useState<'zip' | 'json'>('zip')
  const [loading, setLoading] = useState(true)
  const [exporting, setExporting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    Promise.all([fetchSkills(projectId), fetchProjectAgents(projectId)])
      .then(([sk, ag]) => {
        setSkills(sk)
        setAgents(ag)
        setSelectedSkillIds(new Set(sk.map((s) => s.id)))
      })
      .finally(() => setLoading(false))
  }, [projectId])

  const toggleSkill = (id: number) => {
    setSelectedSkillIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const toggleAgent = (id: number) => {
    setSelectedAgentIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const selectAllSkills = () => setSelectedSkillIds(new Set(skills.map((s) => s.id)))
  const deselectAllSkills = () => setSelectedSkillIds(new Set())
  const selectAllAgents = () => setSelectedAgentIds(new Set(agents.map((a) => a.id)))
  const deselectAllAgents = () => setSelectedAgentIds(new Set())

  const handleExport = async () => {
    const skillIds = Array.from(selectedSkillIds)
    const agentIds = Array.from(selectedAgentIds)

    if (skillIds.length === 0 && agentIds.length === 0) return

    setExporting(true)
    setError(null)

    try {
      const result = await exportBundle(projectId, {
        skill_ids: skillIds,
        agent_ids: agentIds,
        format,
      })

      if (format === 'zip') {
        const blob = result as Blob
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `${projectName.toLowerCase().replace(/\s+/g, '-')}-bundle.zip`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
      } else {
        const json = JSON.stringify(result, null, 2)
        const blob = new Blob([json], { type: 'application/json' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `${projectName.toLowerCase().replace(/\s+/g, '-')}-bundle.json`
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        URL.revokeObjectURL(url)
      }

      onClose()
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message || 'Export failed. Please try again.'
      setError(message)
    } finally {
      setExporting(false)
    }
  }

  const totalSelected = selectedSkillIds.size + selectedAgentIds.size

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-background rounded-lg border border-border shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col">
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <div className="flex items-center gap-2">
            <Package className="h-4 w-4 text-primary" />
            <h2 className="font-semibold text-sm">Export Bundle</h2>
          </div>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="p-4 space-y-4 overflow-y-auto flex-1">
          {loading ? (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
            </div>
          ) : (
            <>
              {/* Skills section */}
              <div>
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                    Skills ({selectedSkillIds.size}/{skills.length})
                  </label>
                  <div className="flex gap-2">
                    <button
                      onClick={selectAllSkills}
                      className="text-xs text-primary hover:underline"
                    >
                      Select All
                    </button>
                    <button
                      onClick={deselectAllSkills}
                      className="text-xs text-muted-foreground hover:underline"
                    >
                      Deselect All
                    </button>
                  </div>
                </div>
                <div className="space-y-1 max-h-48 overflow-y-auto border border-border rounded-md p-2">
                  {skills.length === 0 ? (
                    <p className="text-xs text-muted-foreground py-2 text-center">
                      No skills in this project
                    </p>
                  ) : (
                    skills.map((skill) => (
                      <label
                        key={skill.id}
                        className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-accent cursor-pointer"
                      >
                        <div
                          className={`h-4 w-4 rounded border flex items-center justify-center transition-colors ${
                            selectedSkillIds.has(skill.id)
                              ? 'bg-primary border-primary'
                              : 'border-input'
                          }`}
                        >
                          {selectedSkillIds.has(skill.id) && (
                            <Check className="h-3 w-3 text-primary-foreground" />
                          )}
                        </div>
                        <span className="text-sm flex-1 truncate">{skill.name}</span>
                        {skill.tags.length > 0 && (
                          <span className="text-[10px] text-muted-foreground">
                            {skill.tags.length} tag{skill.tags.length !== 1 && 's'}
                          </span>
                        )}
                      </label>
                    ))
                  )}
                </div>
              </div>

              {/* Agents section */}
              {agents.length > 0 && (
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                      Agents ({selectedAgentIds.size}/{agents.length})
                    </label>
                    <div className="flex gap-2">
                      <button
                        onClick={selectAllAgents}
                        className="text-xs text-primary hover:underline"
                      >
                        Select All
                      </button>
                      <button
                        onClick={deselectAllAgents}
                        className="text-xs text-muted-foreground hover:underline"
                      >
                        Deselect All
                      </button>
                    </div>
                  </div>
                  <div className="space-y-1 max-h-36 overflow-y-auto border border-border rounded-md p-2">
                    {agents.map((agent) => (
                      <label
                        key={agent.id}
                        className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-accent cursor-pointer"
                      >
                        <div
                          className={`h-4 w-4 rounded border flex items-center justify-center transition-colors ${
                            selectedAgentIds.has(agent.id)
                              ? 'bg-primary border-primary'
                              : 'border-input'
                          }`}
                        >
                          {selectedAgentIds.has(agent.id) && (
                            <Check className="h-3 w-3 text-primary-foreground" />
                          )}
                        </div>
                        <span className="text-sm flex-1 truncate">{agent.name}</span>
                        <span className="text-[10px] text-muted-foreground">{agent.role}</span>
                      </label>
                    ))}
                  </div>
                </div>
              )}

              {/* Format toggle */}
              <div>
                <label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                  Format
                </label>
                <div className="flex gap-2 mt-1">
                  <button
                    onClick={() => setFormat('zip')}
                    className={`flex-1 px-3 py-2 text-sm rounded-md border transition-colors ${
                      format === 'zip'
                        ? 'border-primary bg-primary/10 text-foreground'
                        : 'border-input text-muted-foreground hover:text-foreground'
                    }`}
                  >
                    ZIP Archive
                  </button>
                  <button
                    onClick={() => setFormat('json')}
                    className={`flex-1 px-3 py-2 text-sm rounded-md border transition-colors ${
                      format === 'json'
                        ? 'border-primary bg-primary/10 text-foreground'
                        : 'border-input text-muted-foreground hover:text-foreground'
                    }`}
                  >
                    JSON File
                  </button>
                </div>
              </div>

              {error && (
                <div className="text-sm text-destructive bg-destructive/10 rounded-md px-3 py-2">
                  {error}
                </div>
              )}
            </>
          )}
        </div>

        <div className="flex items-center justify-end gap-2 px-4 py-3 border-t border-border">
          <Button variant="ghost" size="sm" onClick={onClose} disabled={exporting}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={handleExport}
            disabled={exporting || totalSelected === 0}
          >
            {exporting ? (
              <>
                <Loader2 className="h-4 w-4 mr-1 animate-spin" />
                Exporting...
              </>
            ) : (
              <>
                <Package className="h-4 w-4 mr-1" />
                Export {totalSelected} item{totalSelected !== 1 && 's'}
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  )
}
