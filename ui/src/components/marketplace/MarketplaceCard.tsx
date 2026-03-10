import {
  Download,
  Tag,
  ThumbsUp,
  ThumbsDown,
  User,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import type { MarketplaceSkill } from '@/types'

interface MarketplaceCardProps {
  skill: MarketplaceSkill
  onInstall: (skill: MarketplaceSkill) => void
  onVote: (skill: MarketplaceSkill, vote: 'up' | 'down') => void
  onSelect: (skill: MarketplaceSkill) => void
}

export function MarketplaceCard({
  skill,
  onInstall,
  onVote,
  onSelect,
}: MarketplaceCardProps) {
  return (
    <div
      className="p-4 rounded-lg border border-border bg-card hover:border-primary/40 hover:shadow-sm transition-all cursor-pointer"
      onClick={() => onSelect(skill)}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0 flex-1">
          <h3 className="font-medium text-sm truncate">{skill.name}</h3>
          {skill.description && (
            <p className="text-xs text-muted-foreground mt-1 line-clamp-2">
              {skill.description}
            </p>
          )}
        </div>
        <Button
          variant="ghost"
          size="icon-xs"
          onClick={(e) => {
            e.stopPropagation()
            onInstall(skill)
          }}
          title="Install skill"
        >
          <Download className="h-3.5 w-3.5" />
        </Button>
      </div>

      {skill.author && (
        <div className="flex items-center gap-1 mt-2 text-xs text-muted-foreground">
          <User className="h-3 w-3" />
          <span>{skill.author}</span>
        </div>
      )}

      <div className="flex items-center gap-2 mt-3 flex-wrap">
        {skill.category && (
          <span className="text-[10px] px-1.5 py-0.5 rounded bg-secondary text-secondary-foreground font-medium">
            {skill.category}
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

      <div className="flex items-center justify-between mt-3 pt-2 border-t border-border/50">
        <div className="flex items-center gap-3 text-xs text-muted-foreground">
          <span className="flex items-center gap-1">
            <Download className="h-3 w-3" />
            {skill.downloads}
          </span>
          <span className="text-[10px]">v{skill.version}</span>
        </div>
        <div className="flex items-center gap-1">
          <button
            onClick={(e) => {
              e.stopPropagation()
              onVote(skill, 'up')
            }}
            className="flex items-center gap-0.5 text-xs text-muted-foreground hover:text-green-500 transition-colors p-1 rounded"
            title="Upvote"
          >
            <ThumbsUp className="h-3 w-3" />
            <span>{skill.upvotes}</span>
          </button>
          <button
            onClick={(e) => {
              e.stopPropagation()
              onVote(skill, 'down')
            }}
            className="flex items-center gap-0.5 text-xs text-muted-foreground hover:text-red-500 transition-colors p-1 rounded"
            title="Downvote"
          >
            <ThumbsDown className="h-3 w-3" />
            <span>{skill.downvotes}</span>
          </button>
        </div>
      </div>
    </div>
  )
}
