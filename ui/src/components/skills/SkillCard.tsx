import { Link } from 'react-router-dom'
import { FileText, Tag, Coins } from 'lucide-react'
import type { Skill } from '@/types'

function formatTokens(n: number): string {
  if (n >= 1000) return `${(n / 1000).toFixed(1)}k`
  return String(n)
}

interface SkillCardProps {
  skill: Skill
}

export function SkillCard({ skill }: SkillCardProps) {
  return (
    <Link
      to={`/skills/${skill.id}`}
      className="block p-4 rounded-lg border border-border bg-card hover:border-primary/40 hover:shadow-sm transition-all group"
    >
      <div className="flex items-start gap-3">
        <FileText className="h-5 w-5 text-muted-foreground mt-0.5 shrink-0" />
        <div className="min-w-0 flex-1">
          <div className="flex items-center justify-between gap-2">
            <h3 className="font-medium text-sm group-hover:text-primary transition-colors truncate">
              {skill.name}
            </h3>
            {skill.token_estimate > 0 && (
              <span className="text-[10px] px-1.5 py-0.5 rounded bg-muted text-muted-foreground font-mono flex items-center gap-0.5 shrink-0">
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
              <span className="text-[10px] px-1.5 py-0.5 rounded bg-secondary text-secondary-foreground font-mono">
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
    </Link>
  )
}
