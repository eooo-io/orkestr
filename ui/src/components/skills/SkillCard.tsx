import { Link } from 'react-router-dom'
import { FileText, Tag, Coins, Check } from 'lucide-react'
import type { Skill } from '@/types'

function formatTokens(n: number): string {
  if (n >= 1000) return `${(n / 1000).toFixed(1)}k`
  return String(n)
}

interface SkillCardProps {
  skill: Skill
  selectable?: boolean
  selected?: boolean
  onToggleSelect?: (id: number) => void
}

export function SkillCard({ skill, selectable, selected, onToggleSelect }: SkillCardProps) {
  const handleCheckboxClick = (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    onToggleSelect?.(skill.id)
  }

  const card = (
    <div className="relative">
      {/* Checkbox overlay */}
      {(selectable || selected) && (
        <button
          onClick={handleCheckboxClick}
          className={`absolute top-2 left-2 z-10 h-5 w-5 rounded border-2 flex items-center justify-center transition-all duration-150 ${
            selected
              ? 'bg-primary border-primary text-primary-foreground'
              : 'border-muted-foreground/40 bg-background hover:border-primary/60'
          }`}
        >
          {selected && <Check className="h-3 w-3" />}
        </button>
      )}

      <div
        className={`block p-4 bg-card elevation-1 hover:elevation-2 transition-all duration-150 group ${
          selected ? 'border border-primary/60 bg-primary/5' : ''
        } ${selectable ? 'pl-9' : ''}`}
      >
        <div className="flex items-start gap-3">
          <FileText className="h-5 w-5 text-muted-foreground mt-0.5 shrink-0" />
          <div className="min-w-0 flex-1">
            <div className="flex items-center justify-between gap-2">
              <h3 className="font-medium text-sm group-hover:text-primary transition-all duration-150 truncate">
                {skill.name}
              </h3>
              {skill.token_estimate > 0 && (
                <span className="text-[10px] px-1.5 py-0.5 bg-muted text-muted-foreground font-mono flex items-center gap-0.5 shrink-0">
                  <Coins className="h-2.5 w-2.5" />
                  {formatTokens(skill.token_estimate)}
                </span>
              )}
            </div>
            {skill.description && (
              <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
                {skill.description}
              </p>
            )}
            <div className="flex items-center gap-2 mt-2 flex-wrap">
              {skill.model && (
                <span className="text-[10px] px-1.5 py-0.5 bg-primary/10 text-primary font-medium font-mono">
                  {skill.model}
                </span>
              )}
              {skill.tags?.map((tag) => (
                <span
                  key={tag}
                  className="text-[10px] px-1.5 py-0.5 rounded bg-accent text-accent-foreground flex items-center gap-0.5"
                >
                  <Tag className="h-2.5 w-2.5" />
                  {tag}
                </span>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  )

  // When in selectable mode, clicking the card toggles selection instead of navigating
  if (selectable) {
    return (
      <div className="cursor-pointer" onClick={() => onToggleSelect?.(skill.id)}>
        {card}
      </div>
    )
  }

  return (
    <Link to={`/skills/${skill.id}`}>
      {card}
    </Link>
  )
}
