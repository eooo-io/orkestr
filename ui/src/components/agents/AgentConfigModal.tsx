import { useState, useEffect } from 'react'
import { X, Save, Loader2, Check } from 'lucide-react'
import { updateAgentInstructions, assignAgentSkills } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import type { ProjectAgent, Skill } from '@/types'

interface Props {
  projectId: number
  agent: ProjectAgent
  skills: Skill[]
  onClose: () => void
  onSaved: () => void
}

export function AgentConfigModal({ projectId, agent, skills, onClose, onSaved }: Props) {
  const { showToast } = useAppStore()
  const [instructions, setInstructions] = useState(agent.custom_instructions || '')
  const [selectedSkills, setSelectedSkills] = useState<number[]>(agent.skill_ids)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleEsc)
    return () => window.removeEventListener('keydown', handleEsc)
  }, [onClose])

  const toggleSkill = (id: number) => {
    setSelectedSkills((prev) =>
      prev.includes(id) ? prev.filter((s) => s !== id) : [...prev, id],
    )
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      await Promise.all([
        updateAgentInstructions(projectId, agent.id, instructions || null),
        assignAgentSkills(projectId, agent.id, selectedSkills),
      ])
      showToast(`${agent.name} configured`)
      onSaved()
      onClose()
    } catch {
      showToast('Failed to save agent config', 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/30">
      <div className="bg-background elevation-4 w-full max-w-2xl max-h-[85vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-border">
          <div>
            <h2 className="text-lg font-semibold">{agent.name}</h2>
            <p className="text-xs text-muted-foreground mt-0.5">{agent.description}</p>
          </div>
          <button onClick={onClose} className="p-1.5 hover:bg-muted transition-all duration-150">
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-5 space-y-5">
          {/* Custom Instructions */}
          <div>
            <label className="block text-sm font-medium mb-1.5">
              Custom Instructions
            </label>
            <p className="text-xs text-muted-foreground mb-2">
              Project-specific instructions that extend this agent's base behavior.
            </p>
            <textarea
              value={instructions}
              onChange={(e) => setInstructions(e.target.value)}
              placeholder="Add project-specific instructions for this agent..."
              rows={5}
              className="w-full px-3 py-2 text-sm border border-input bg-background resize-none focus:outline-none focus:ring-1 focus:ring-ring font-mono text-xs"
            />
          </div>

          {/* Skill Assignment */}
          <div>
            <label className="block text-sm font-medium mb-1.5">
              Assigned Skills
            </label>
            <p className="text-xs text-muted-foreground mb-2">
              Select which project skills this agent should have access to.
            </p>

            {skills.length === 0 ? (
              <p className="text-sm text-muted-foreground py-4 text-center">
                No skills in this project yet.
              </p>
            ) : (
              <div className="space-y-1 max-h-64 overflow-y-auto border border-border p-1">
                {skills.map((skill) => {
                  const isSelected = selectedSkills.includes(skill.id)
                  return (
                    <button
                      key={skill.id}
                      onClick={() => toggleSkill(skill.id)}
                      className={`w-full flex items-center gap-3 px-3 py-2 text-left transition-all duration-150 ${
                        isSelected
                          ? 'bg-primary/10 border border-primary/20'
                          : 'hover:bg-muted border border-transparent'
                      }`}
                    >
                      <div
                        className={`h-4 w-4 rounded border flex items-center justify-center flex-shrink-0 ${
                          isSelected
                            ? 'bg-primary border-primary text-primary-foreground'
                            : 'border-input'
                        }`}
                      >
                        {isSelected && <Check className="h-3 w-3" />}
                      </div>
                      <div className="min-w-0 flex-1">
                        <span className="text-sm font-medium block truncate">
                          {skill.name}
                        </span>
                        {skill.description && (
                          <span className="text-xs text-muted-foreground block truncate">
                            {skill.description}
                          </span>
                        )}
                      </div>
                      {skill.tags.length > 0 && (
                        <div className="flex gap-1 flex-shrink-0">
                          {skill.tags.slice(0, 2).map((tag) => (
                            <span
                              key={tag}
                              className="px-1.5 py-0.5 text-[10px] bg-muted text-muted-foreground"
                            >
                              {tag}
                            </span>
                          ))}
                        </div>
                      )}
                    </button>
                  )
                })}
              </div>
            )}
            <p className="text-xs text-muted-foreground mt-1.5">
              {selectedSkills.length} of {skills.length} skills selected
            </p>
          </div>

          {/* Base Instructions (read-only) */}
          <details className="group">
            <summary className="text-sm font-medium cursor-pointer select-none hover:text-foreground/80">
              View Base Instructions
            </summary>
            <pre className="mt-2 p-3 text-xs font-mono bg-muted overflow-x-auto whitespace-pre-wrap">
              {agent.base_instructions}
            </pre>
          </details>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-2 p-5 border-t border-border">
          <Button variant="outline" size="sm" onClick={onClose}>
            Cancel
          </Button>
          <Button size="sm" onClick={handleSave} disabled={saving}>
            {saving ? (
              <Loader2 className="h-4 w-4 mr-1 animate-spin" />
            ) : (
              <Save className="h-4 w-4 mr-1" />
            )}
            Save
          </Button>
        </div>
      </div>
    </div>
  )
}
