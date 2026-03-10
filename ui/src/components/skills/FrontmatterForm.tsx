import { Coins, Link2 } from 'lucide-react'
import { estimateTokens } from '@/api/client'
import type { Skill } from '@/types'

const MODEL_CONTEXT_LIMITS: Record<string, number> = {
  'claude-sonnet-4-20250514': 200000,
  'claude-opus-4-20250514': 200000,
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
  projectSkills?: Skill[]
}

const MODELS = [
  { value: 'claude-sonnet-4-20250514', label: 'Claude Sonnet 4' },
  { value: 'claude-opus-4-20250514', label: 'Claude Opus 4' },
  { value: 'claude-haiku-4-5-20251001', label: 'Claude Haiku 4.5' },
]

export function FrontmatterForm({ skill, onChange, projectSkills }: FrontmatterFormProps) {
  const currentIncludes = (skill.includes as string[]) || []
  const availableSkills = (projectSkills || []).filter(
    (s) => s.slug !== skill.slug,
  )

  const toggleInclude = (slug: string) => {
    const newIncludes = currentIncludes.includes(slug)
      ? currentIncludes.filter((s) => s !== slug)
      : [...currentIncludes, slug]
    onChange('includes', newIncludes)
  }

  return (
    <div className="space-y-3 p-4 border-b border-border bg-muted/30">
      <div className="grid grid-cols-2 gap-3">
        <div>
          <label className="text-xs font-medium text-muted-foreground">
            Name
          </label>
          <input
            type="text"
            value={skill.name || ''}
            onChange={(e) => onChange('name', e.target.value)}
            className="mt-1 w-full px-2.5 py-1.5 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
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
            className="mt-1 w-full px-2.5 py-1.5 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
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

      <div>
        <label className="text-xs font-medium text-muted-foreground">
          Description
        </label>
        <input
          type="text"
          value={skill.description || ''}
          onChange={(e) => onChange('description', e.target.value || null)}
          className="mt-1 w-full px-2.5 py-1.5 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
          placeholder="Brief description"
        />
      </div>

      <div className="grid grid-cols-2 gap-3">
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
            className="mt-1 w-full px-2.5 py-1.5 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
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
            className="mt-1 w-full px-2.5 py-1.5 text-sm rounded-md border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
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
              <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-primary/10 text-primary font-mono">
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
                  className={`text-[11px] px-2 py-0.5 rounded-full border transition-colors ${
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

      {/* Token Estimate */}
      {(() => {
        const tokens = estimateTokens(skill.resolved_body || skill.body || '')
        const model = skill.model || 'claude-sonnet-4-20250514'
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
