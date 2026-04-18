import { useState, useEffect } from 'react'
import { Coins, Link2, Variable, Plus, Trash2, Filter } from 'lucide-react'
import { estimateTokens, fetchSkillCategories } from '@/api/client'
import ConditionsEditor from '@/components/skills/ConditionsEditor'
import type { Skill, SkillCategory, TemplateVariable, SkillConditions } from '@/types'

const MODEL_CONTEXT_LIMITS: Record<string, number> = {
  'claude-opus-4-6': 200000,
  'claude-sonnet-4-6': 200000,
  'claude-haiku-4-5-20251001': 200000,
}

function getTokenColor(tokens: number, limit: number): string {
  const ratio = tokens / limit
  if (ratio >= 0.9) return 'text-red-500'
  if (ratio >= 0.75) return 'text-yellow-500'
  return 'text-muted-foreground'
}

function formatTokens(n: number): string {
  if (n >= 1000) return `${(n / 1000).toFixed(1)}k`
  return String(n)
}

interface FrontmatterFormProps {
  skill: Partial<Skill>
  onChange: (field: string, value: unknown) => void
  onTunedForModelChange?: (value: string | null) => void
  projectSkills?: Skill[]
}

const MODELS = [
  { value: 'claude-opus-4-6', label: 'Claude Opus 4.6' },
  { value: 'claude-sonnet-4-6', label: 'Claude Sonnet 4.6' },
  { value: 'claude-haiku-4-5-20251001', label: 'Claude Haiku 4.5' },
]

export function FrontmatterForm({ skill, onChange, onTunedForModelChange, projectSkills }: FrontmatterFormProps) {
  const [categories, setCategories] = useState<SkillCategory[]>([])
  const currentIncludes = (skill.includes as string[]) || []
  const availableSkills = (projectSkills || []).filter(
    (s) => s.slug !== skill.slug,
  )

  useEffect(() => {
    fetchSkillCategories().then(setCategories).catch(() => {})
  }, [])

  const toggleInclude = (slug: string) => {
    const newIncludes = currentIncludes.includes(slug)
      ? currentIncludes.filter((s) => s !== slug)
      : [...currentIncludes, slug]
    onChange('includes', newIncludes)
  }

  return (
    <div className="space-y-3 p-4 border-b border-border bg-muted/30">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label className="text-xs font-medium text-muted-foreground">
            Name
          </label>
          <input
            type="text"
            value={skill.name || ''}
            onChange={(e) => onChange('name', e.target.value)}
            className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            placeholder="Skill name"
          />
        </div>
        <div>
          <label className="text-xs font-medium text-muted-foreground">
            Model
          </label>
          <select
            value={skill.model || ''}
            onChange={(e) => onChange('model', e.target.value || null)}
            className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
          >
            <option value="">Default</option>
            {MODELS.map((m) => (
              <option key={m.value} value={m.value}>
                {m.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {onTunedForModelChange && (
        <div>
          <label className="text-xs font-medium text-muted-foreground">
            Tuned for model{' '}
            <span className="text-muted-foreground/60 font-normal">
              (baseline for staleness tracking)
            </span>
          </label>
          <select
            value={skill.tuned_for_model || ''}
            onChange={(e) => onTunedForModelChange(e.target.value || null)}
            className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
          >
            <option value="">Not set</option>
            {MODELS.map((m) => (
              <option key={m.value} value={m.value}>
                {m.label}
              </option>
            ))}
          </select>
        </div>
      )}

      <div>
        <label className="text-xs font-medium text-muted-foreground">
          Description
        </label>
        <input
          type="text"
          value={skill.description || ''}
          onChange={(e) => onChange('description', e.target.value || null)}
          className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
          placeholder="Brief description"
        />
      </div>

      <div>
        <label className="text-xs font-medium text-muted-foreground">
          Summary{' '}
          <span className="text-muted-foreground/60 font-normal">
            (tier-1 hint for progressive disclosure, max 500 chars)
          </span>
        </label>
        <textarea
          value={skill.summary || ''}
          onChange={(e) => onChange('summary', e.target.value || null)}
          maxLength={500}
          rows={2}
          className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring resize-none"
          placeholder="Short summary for agent context index (~100 tokens)"
        />
        <div className="text-right text-[10px] text-muted-foreground/60 mt-0.5">
          {(skill.summary || '').length}/500
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label className="text-xs font-medium text-muted-foreground">
            Category
          </label>
          <select
            value={skill.category_id ?? ''}
            onChange={(e) => onChange('category_id', e.target.value ? parseInt(e.target.value) : null)}
            className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
          >
            <option value="">None</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="text-xs font-medium text-muted-foreground">
            Skill Type
          </label>
          <select
            value={skill.skill_type ?? ''}
            onChange={(e) => onChange('skill_type', e.target.value || null)}
            className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
          >
            <option value="">None</option>
            <option value="capability_uplift">Capability Uplift</option>
            <option value="encoded_preference">Encoded Preference</option>
            <option value="hybrid">Hybrid</option>
          </select>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label className="text-xs font-medium text-muted-foreground">
            Max Tokens
          </label>
          <input
            type="number"
            value={skill.max_tokens ?? ''}
            onChange={(e) =>
              onChange(
                'max_tokens',
                e.target.value ? parseInt(e.target.value) : null,
              )
            }
            className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            placeholder="e.g. 1000"
          />
        </div>
        <div>
          <label className="text-xs font-medium text-muted-foreground">
            Tags (comma-separated)
          </label>
          <input
            type="text"
            value={(skill.tags as string[])?.join(', ') || ''}
            onChange={(e) =>
              onChange(
                'tags',
                e.target.value
                  .split(',')
                  .map((t) => t.trim())
                  .filter(Boolean),
              )
            }
            className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            placeholder="e.g. code, review"
          />
        </div>
      </div>

      {/* Includes */}
      {availableSkills.length > 0 && (
        <div>
          <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
            <Link2 className="h-3 w-3" />
            Includes
            {currentIncludes.length > 0 && (
              <span className="text-[10px] px-1.5 py-0.5 bg-primary/10 text-primary font-mono">
                {currentIncludes.length}
              </span>
            )}
          </label>
          <div className="mt-1 flex flex-wrap gap-1.5">
            {availableSkills.map((s) => {
              const isIncluded = currentIncludes.includes(s.slug)
              return (
                <button
                  key={s.slug}
                  type="button"
                  onClick={() => toggleInclude(s.slug)}
                  className={`text-[11px] px-2 py-0.5 border transition-all duration-150 ${
                    isIncluded
                      ? 'bg-primary/10 border-primary/30 text-primary'
                      : 'bg-muted/50 border-border text-muted-foreground hover:border-primary/30'
                  }`}
                >
                  {s.name}
                </button>
              )
            })}
          </div>
          {currentIncludes.length > 0 && (
            <p className="text-[10px] text-muted-foreground mt-1">
              Included skills are prepended when syncing and composing.
            </p>
          )}
        </div>
      )}

      {/* Conditional Activation */}
      <ConditionsSection
        conditions={(skill.conditions as SkillConditions | null) ?? null}
        onChange={(conditions) => onChange('conditions', conditions)}
      />

      {/* Template Variables Definitions */}
      <TemplateVariablesEditor
        variables={(skill.template_variables as TemplateVariable[]) || []}
        onChange={(vars) => onChange('template_variables', vars.length > 0 ? vars : null)}
      />

      {/* Token Estimate */}
      {(() => {
        const tokens = estimateTokens(skill.resolved_body || skill.body || '')
        const model = skill.model || 'claude-sonnet-4-6'
        const limit = MODEL_CONTEXT_LIMITS[model] || 200000
        const color = getTokenColor(tokens, limit)
        return (
          <div className="flex items-center justify-between">
            <div className={`flex items-center gap-1 text-xs ${color}`}>
              <Coins className="h-3 w-3" />
              <span className="font-mono">
                {formatTokens(tokens)} tokens
              </span>
              {currentIncludes.length > 0 && (
                <span className="text-muted-foreground">(resolved)</span>
              )}
              <span className="text-muted-foreground">
                / {formatTokens(limit)} context
              </span>
            </div>
            {tokens / limit >= 0.75 && (
              <span className={`text-xs font-medium ${color}`}>
                {tokens / limit >= 0.9 ? 'Near context limit!' : 'High token usage'}
              </span>
            )}
          </div>
        )
      })()}
    </div>
  )
}

function ConditionsSection({
  conditions,
  onChange,
}: {
  conditions: SkillConditions | null
  onChange: (conditions: SkillConditions | null) => void
}) {
  const [expanded, setExpanded] = useState(!!conditions)
  const hasConditions = conditions &&
    ((conditions.file_patterns?.length ?? 0) > 0 || (conditions.path_prefixes?.length ?? 0) > 0)

  return (
    <div>
      <button
        type="button"
        onClick={() => setExpanded(!expanded)}
        className="text-xs font-medium text-muted-foreground flex items-center gap-1 hover:text-foreground transition-all duration-150"
      >
        <Filter className="h-3 w-3" />
        Conditional Activation
        {hasConditions && (
          <span className="text-[10px] px-1.5 py-0.5 bg-blue-500/10 text-blue-400 font-mono">
            active
          </span>
        )}
      </button>
      {expanded && (
        <div className="mt-2">
          <ConditionsEditor conditions={conditions} onChange={onChange} />
        </div>
      )}
    </div>
  )
}

function TemplateVariablesEditor({
  variables,
  onChange,
}: {
  variables: TemplateVariable[]
  onChange: (vars: TemplateVariable[]) => void
}) {
  const [expanded, setExpanded] = useState(false)

  const addVariable = () => {
    onChange([...variables, { name: '', description: '', default: '' }])
    setExpanded(true)
  }

  const removeVariable = (index: number) => {
    onChange(variables.filter((_, i) => i !== index))
  }

  const updateVariable = (index: number, field: keyof TemplateVariable, value: string) => {
    const updated = variables.map((v, i) =>
      i === index ? { ...v, [field]: value } : v,
    )
    onChange(updated)
  }

  return (
    <div>
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={() => setExpanded(!expanded)}
          className="text-xs font-medium text-muted-foreground flex items-center gap-1 hover:text-foreground transition-all duration-150"
        >
          <Variable className="h-3 w-3" />
          Template Variables
          {variables.length > 0 && (
            <span className="text-[10px] px-1.5 py-0.5 bg-primary/10 text-primary font-mono">
              {variables.length}
            </span>
          )}
        </button>
        <button
          type="button"
          onClick={addVariable}
          className="text-xs text-muted-foreground hover:text-foreground flex items-center gap-0.5 transition-all duration-150"
        >
          <Plus className="h-3 w-3" />
          Add
        </button>
      </div>

      {expanded && variables.length > 0 && (
        <div className="mt-2 space-y-2">
          {variables.map((v, i) => (
            <div
              key={i}
              className="grid grid-cols-1 sm:grid-cols-[1fr_1.5fr_1fr_auto] gap-1.5 items-start"
            >
              <input
                type="text"
                value={v.name}
                onChange={(e) =>
                  updateVariable(i, 'name', e.target.value.replace(/\W/g, '_'))
                }
                className="px-2 py-1 text-xs border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring font-mono"
                placeholder="var_name"
              />
              <input
                type="text"
                value={v.description}
                onChange={(e) => updateVariable(i, 'description', e.target.value)}
                className="px-2 py-1 text-xs border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                placeholder="Description"
              />
              <input
                type="text"
                value={v.default || ''}
                onChange={(e) => updateVariable(i, 'default', e.target.value)}
                className="px-2 py-1 text-xs border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                placeholder="Default"
              />
              <button
                type="button"
                onClick={() => removeVariable(i)}
                className="p-1 text-muted-foreground hover:text-destructive transition-all duration-150"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </div>
          ))}
          <p className="text-[10px] text-muted-foreground">
            Use <code className="font-mono">{'{{var_name}}'}</code> in the skill body. Values are set per-project.
          </p>
        </div>
      )}
    </div>
  )
}
