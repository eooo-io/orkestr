import { useEffect, useState } from 'react'
import { Variable, Save, Info } from 'lucide-react'
import { fetchSkillVariables, updateSkillVariables } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import type { TemplateVariable } from '@/types'

interface TemplateVariablesPanelProps {
  projectId: number
  skillId: number
  templateVariables: TemplateVariable[]
}

interface VariableEntry {
  name: string
  description: string
  default: string | null
  value: string | null
}

export function TemplateVariablesPanel({
  projectId,
  skillId,
  templateVariables,
}: TemplateVariablesPanelProps) {
  const [entries, setEntries] = useState<VariableEntry[]>([])
  const [saving, setSaving] = useState(false)
  const [loaded, setLoaded] = useState(false)
  const { showToast } = useAppStore()

  useEffect(() => {
    fetchSkillVariables(projectId, skillId)
      .then((data) => {
        if (data.definitions.length > 0) {
          setEntries(data.definitions)
        } else {
          // Fall back to template_variables definitions with no values
          setEntries(
            templateVariables.map((tv) => ({
              name: tv.name,
              description: tv.description || '',
              default: tv.default ?? null,
              value: null,
            })),
          )
        }
        setLoaded(true)
      })
      .catch(() => {
        // If API fails, use local definitions
        setEntries(
          templateVariables.map((tv) => ({
            name: tv.name,
            description: tv.description || '',
            default: tv.default ?? null,
            value: null,
          })),
        )
        setLoaded(true)
      })
  }, [projectId, skillId, templateVariables])

  const handleValueChange = (name: string, value: string) => {
    setEntries((prev) =>
      prev.map((e) => (e.name === name ? { ...e, value: value || null } : e)),
    )
  }

  const handleSave = async () => {
    setSaving(true)
    try {
      const variables: Record<string, string> = {}
      for (const entry of entries) {
        if (entry.value !== null && entry.value !== '') {
          variables[entry.name] = entry.value
        }
      }
      await updateSkillVariables(projectId, skillId, variables)
      showToast('Variables saved')
    } catch {
      showToast('Failed to save variables', 'error')
    } finally {
      setSaving(false)
    }
  }

  if (!loaded) {
    return (
      <div className="p-3 text-xs text-muted-foreground animate-pulse">
        Loading variables...
      </div>
    )
  }

  return (
    <div className="border-t border-border bg-muted/20 p-4 space-y-3">
      <div className="flex items-center justify-between">
        <label className="text-xs font-medium text-muted-foreground flex items-center gap-1.5">
          <Variable className="h-3.5 w-3.5" />
          Template Variables
          <span className="text-[10px] px-1.5 py-0.5 bg-primary/10 text-primary font-mono">
            {entries.length}
          </span>
        </label>
        <button
          onClick={handleSave}
          disabled={saving}
          className="flex items-center gap-1 text-xs px-2.5 py-1 bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-all duration-150"
        >
          <Save className="h-3 w-3" />
          {saving ? 'Saving...' : 'Save Values'}
        </button>
      </div>

      <div className="flex items-start gap-1.5 text-[11px] text-muted-foreground bg-muted/50 px-2.5 py-2">
        <Info className="h-3.5 w-3.5 mt-0.5 shrink-0" />
        <span>
          Template variables use <code className="font-mono text-primary/80">{'{{variable_name}}'}</code> syntax in the skill body.
          Values set here are substituted during provider sync. If no value is set, the default is used. If neither is set, the placeholder remains.
        </span>
      </div>

      <div className="space-y-2.5">
        {entries.map((entry) => (
          <div key={entry.name} className="space-y-1">
            <div className="flex items-center gap-2">
              <label className="text-xs font-medium text-foreground font-mono">
                {entry.name}
              </label>
              {entry.description && (
                <span className="text-[10px] text-muted-foreground">
                  {entry.description}
                </span>
              )}
            </div>
            <input
              type="text"
              value={entry.value ?? ''}
              onChange={(e) => handleValueChange(entry.name, e.target.value)}
              placeholder={entry.default ? `Default: ${entry.default}` : `Value for {{${entry.name}}}`}
              className="w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
            />
          </div>
        ))}
      </div>
    </div>
  )
}
