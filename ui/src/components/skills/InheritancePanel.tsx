import { useState, useEffect, useCallback } from 'react'
import {
  GitBranch,
  GitMerge,
  ChevronDown,
  ChevronRight,
  Loader2,
  Link,
  Unlink,
  Eye,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  resolveSkillInheritance,
  fetchSkillChildren,
  updateSkillInheritance,
} from '@/api/client'
import type { SkillInheritanceInfo } from '@/types'

interface InheritancePanelProps {
  skillId: number
}

export function InheritancePanel({ skillId }: InheritancePanelProps) {
  const [info, setInfo] = useState<SkillInheritanceInfo | null>(null)
  const [children, setChildren] = useState<
    Array<{ id: number; name: string; slug: string }>
  >([])
  const [loading, setLoading] = useState(false)
  const [updating, setUpdating] = useState(false)
  const [showResolved, setShowResolved] = useState(false)
  const [showParentInput, setShowParentInput] = useState(false)
  const [parentIdInput, setParentIdInput] = useState('')
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const [inheritance, kids] = await Promise.all([
        resolveSkillInheritance(skillId),
        fetchSkillChildren(skillId),
      ])
      setInfo(inheritance)
      setChildren(kids)
    } catch {
      setError('Failed to load inheritance info')
    } finally {
      setLoading(false)
    }
  }, [skillId])

  useEffect(() => {
    load()
  }, [load])

  const handleSetParent = async () => {
    const id = parseInt(parentIdInput, 10)
    if (isNaN(id)) return
    setUpdating(true)
    setError(null)
    try {
      await updateSkillInheritance(skillId, { extends_skill_id: id })
      setShowParentInput(false)
      setParentIdInput('')
      await load()
    } catch {
      setError('Failed to update parent skill')
    } finally {
      setUpdating(false)
    }
  }

  const handleRemoveParent = async () => {
    setUpdating(true)
    setError(null)
    try {
      await updateSkillInheritance(skillId, { extends_skill_id: null })
      await load()
    } catch {
      setError('Failed to remove parent skill')
    } finally {
      setUpdating(false)
    }
  }

  if (loading && !info) {
    return (
      <div className="flex items-center justify-center h-32 text-sm text-muted-foreground">
        <Loader2 className="h-4 w-4 animate-spin mr-2" />
        Loading...
      </div>
    )
  }

  return (
    <div className="flex flex-col h-full text-sm">
      {error && (
        <div className="px-3 py-2 text-xs text-red-500 bg-red-500/5 border-b border-red-500/20">
          {error}
        </div>
      )}

      {/* Parent Skill (Extends) */}
      <div className="p-3 border-b border-border">
        <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground mb-2">
          <GitBranch className="h-3 w-3" />
          Parent Skill (Extends)
        </div>

        {info?.parent ? (
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <Link className="h-3 w-3 text-primary shrink-0" />
              <span className="font-medium text-primary truncate">
                {info.parent.name}
              </span>
              <span className="text-xs text-muted-foreground font-mono">
                {info.parent.slug}
              </span>
            </div>
            <div className="flex gap-1.5">
              <Button
                size="xs"
                variant="outline"
                onClick={() => setShowParentInput(true)}
                disabled={updating}
              >
                Change
              </Button>
              <Button
                size="xs"
                variant="outline"
                onClick={handleRemoveParent}
                disabled={updating}
              >
                {updating ? (
                  <Loader2 className="h-3 w-3 animate-spin mr-1" />
                ) : (
                  <Unlink className="h-3 w-3 mr-1" />
                )}
                Remove
              </Button>
            </div>
          </div>
        ) : (
          <div className="space-y-2">
            <p className="text-xs text-muted-foreground">
              None — this is a base skill
            </p>
            {!showParentInput && (
              <Button
                size="xs"
                variant="outline"
                onClick={() => setShowParentInput(true)}
              >
                <Link className="h-3 w-3 mr-1" />
                Set Parent
              </Button>
            )}
          </div>
        )}

        {showParentInput && (
          <div className="mt-2 flex gap-1.5">
            <input
              type="number"
              value={parentIdInput}
              onChange={(e) => setParentIdInput(e.target.value)}
              placeholder="Skill ID"
              className="flex-1 h-7 px-2 text-xs border border-border bg-background rounded"
              onKeyDown={(e) => e.key === 'Enter' && handleSetParent()}
            />
            <Button
              size="xs"
              variant="default"
              onClick={handleSetParent}
              disabled={updating || !parentIdInput}
            >
              {updating ? (
                <Loader2 className="h-3 w-3 animate-spin" />
              ) : (
                'Save'
              )}
            </Button>
            <Button
              size="xs"
              variant="ghost"
              onClick={() => {
                setShowParentInput(false)
                setParentIdInput('')
              }}
            >
              Cancel
            </Button>
          </div>
        )}
      </div>

      {/* Resolved Preview */}
      <div className="p-3 border-b border-border">
        <button
          className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground w-full"
          onClick={() => setShowResolved(!showResolved)}
        >
          {showResolved ? (
            <ChevronDown className="h-3 w-3" />
          ) : (
            <ChevronRight className="h-3 w-3" />
          )}
          <GitMerge className="h-3 w-3" />
          Resolved Preview
          <Eye className="h-3 w-3 ml-auto opacity-50" />
        </button>

        {showResolved && info && (
          <pre className="mt-2 p-2 bg-muted/50 border border-border rounded text-xs font-mono overflow-auto max-h-64 whitespace-pre-wrap">
            {info.resolved_body || '(empty)'}
          </pre>
        )}
      </div>

      {/* Children */}
      <div className="p-3 flex-1 overflow-y-auto">
        <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground mb-2">
          <GitBranch className="h-3 w-3" />
          Children
          {children.length > 0 && (
            <span className="ml-auto text-[10px] bg-muted px-1.5 py-0.5 rounded">
              {children.length}
            </span>
          )}
        </div>

        {children.length === 0 ? (
          <p className="text-xs text-muted-foreground">
            No skills extend this one
          </p>
        ) : (
          <ul className="space-y-1">
            {children.map((child) => (
              <li
                key={child.id}
                className="flex items-center gap-2 py-1 px-1.5 rounded hover:bg-muted/50"
              >
                <GitBranch className="h-3 w-3 text-muted-foreground shrink-0" />
                <span className="font-medium text-primary truncate">
                  {child.name}
                </span>
                <span className="text-xs font-mono text-muted-foreground ml-auto shrink-0">
                  {child.slug}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  )
}
