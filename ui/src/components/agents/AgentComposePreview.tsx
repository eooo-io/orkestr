import { useState, useEffect, useMemo } from 'react'
import { X, Copy, Check, Loader2, AlertTriangle, Share2, ChevronDown, ChevronRight } from 'lucide-react'
import { fetchAgentCompose, fetchModels } from '@/api/client'
import { Button } from '@/components/ui/button'
import { ShareComposeModal } from '@/components/agents/ShareComposeModal'
import type { AgentComposed, ModelGroup } from '@/types'

const DEFAULT_LIMIT = 200000

interface Props {
  projectId: number
  agentId: number
  agentName: string
  onClose: () => void
}

export function AgentComposePreview({ projectId, agentId, agentName, onClose }: Props) {
  const [composed, setComposed] = useState<AgentComposed | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)
  const [modelOverride, setModelOverride] = useState<string | null>(null)
  const [modelGroups, setModelGroups] = useState<ModelGroup[]>([])
  const [breakdownOpen, setBreakdownOpen] = useState(true)
  const [hoverSlug, setHoverSlug] = useState<string | null>(null)
  const [showShareModal, setShowShareModal] = useState(false)

  useEffect(() => {
    fetchModels()
      .then(setModelGroups)
      .catch(() => {})
  }, [])

  useEffect(() => {
    setLoading(true)
    fetchAgentCompose(projectId, agentId, modelOverride ?? undefined)
      .then(setComposed)
      .catch(() => setError('Failed to compose agent output'))
      .finally(() => setLoading(false))
  }, [projectId, agentId, modelOverride])

  useEffect(() => {
    const handleEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleEsc)
    return () => window.removeEventListener('keydown', handleEsc)
  }, [onClose])

  const handleCopy = async () => {
    if (!composed) return
    await navigator.clipboard.writeText(composed.content)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  const contextLimit = composed?.model_context_window || DEFAULT_LIMIT
  const tokenPercent = composed
    ? Math.min((composed.token_estimate / contextLimit) * 100, 100)
    : 0
  const tokenWarning = composed && composed.token_estimate > contextLimit * 0.75

  const highlighted = useMemo(() => {
    if (!composed) return null
    if (!hoverSlug) return { before: composed.content, match: '', after: '' }

    const entry = composed.skill_breakdown.find((e) => e.slug === hoverSlug)
    if (!entry) return { before: composed.content, match: '', after: '' }

    return {
      before: composed.content.slice(0, entry.starts_at_char),
      match: composed.content.slice(entry.starts_at_char, entry.ends_at_char),
      after: composed.content.slice(entry.ends_at_char),
    }
  }, [composed, hoverSlug])

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/30">
      <div className="bg-background elevation-4 w-full max-w-5xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-border gap-4">
          <div className="flex items-center gap-3 min-w-0">
            <div className="min-w-0">
              <h2 className="text-lg font-semibold tracking-tight truncate">
                {agentName} — Composed Output
              </h2>
              {composed && (
                <div className="flex items-center gap-3 mt-1 flex-wrap">
                  <span className="text-xs text-muted-foreground">
                    ~{composed.token_estimate.toLocaleString()} tokens
                  </span>
                  <span className="text-xs text-muted-foreground">
                    {composed.skill_count} skill{composed.skill_count !== 1 ? 's' : ''}
                  </span>
                  <div className="flex items-center gap-1.5">
                    <div className="w-24 h-1.5 bg-muted rounded-full overflow-hidden">
                      <div
                        className={`h-full rounded-full transition-all ${
                          tokenPercent > 90
                            ? 'bg-destructive'
                            : tokenPercent > 75
                              ? 'bg-yellow-500'
                              : 'bg-primary'
                        }`}
                        style={{ width: `${tokenPercent}%` }}
                      />
                    </div>
                    <span className="text-[10px] text-muted-foreground">
                      {tokenPercent.toFixed(0)}% of {contextLimit.toLocaleString()}
                    </span>
                  </div>
                  {tokenWarning && (
                    <span className="flex items-center gap-1 text-[10px] text-yellow-600 dark:text-yellow-400">
                      <AlertTriangle className="h-3 w-3" />
                      High token usage
                    </span>
                  )}
                </div>
              )}
            </div>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <select
              value={modelOverride ?? ''}
              onChange={(e) => setModelOverride(e.target.value || null)}
              className="text-xs px-2 py-1.5 border border-input bg-background rounded"
              title="Preview against a specific model"
            >
              <option value="">Agent default</option>
              {modelGroups.flatMap((group) =>
                group.models.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.name}
                  </option>
                )),
              )}
            </select>
            <Button variant="outline" size="sm" onClick={handleCopy} disabled={!composed}>
              {copied ? (
                <>
                  <Check className="h-3.5 w-3.5 mr-1" />
                  Copied
                </>
              ) : (
                <>
                  <Copy className="h-3.5 w-3.5 mr-1" />
                  Copy
                </>
              )}
            </Button>
            <Button variant="outline" size="sm" onClick={() => setShowShareModal(true)} disabled={!composed}>
              <Share2 className="h-3.5 w-3.5 mr-1" />
              Share
            </Button>
            <button
              onClick={onClose}
              className="p-1.5 hover:bg-muted transition-all duration-150"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-hidden grid grid-cols-1 lg:grid-cols-[1fr_260px]">
          <div className="overflow-y-auto min-h-0">
            {loading && (
              <div className="flex items-center justify-center py-20">
                <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
              </div>
            )}
            {error && (
              <div className="flex items-center justify-center py-20 text-muted-foreground">
                <p className="text-sm">{error}</p>
              </div>
            )}
            {composed && highlighted && (
              <pre className="p-5 text-sm font-mono whitespace-pre-wrap leading-relaxed">
                {highlighted.before}
                {highlighted.match && (
                  <span className="bg-primary/15 rounded px-0.5">{highlighted.match}</span>
                )}
                {highlighted.after}
              </pre>
            )}
          </div>

          {composed && composed.skill_breakdown.length > 0 && (
            <aside className="border-t lg:border-t-0 lg:border-l border-border overflow-y-auto min-h-0 bg-muted/20">
              <button
                onClick={() => setBreakdownOpen(!breakdownOpen)}
                className="w-full flex items-center gap-1.5 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground border-b border-border"
              >
                {breakdownOpen ? (
                  <ChevronDown className="h-3 w-3" />
                ) : (
                  <ChevronRight className="h-3 w-3" />
                )}
                Skill breakdown ({composed.skill_breakdown.length})
              </button>
              {breakdownOpen && (
                <ul>
                  {composed.skill_breakdown.map((skill) => (
                    <li
                      key={skill.slug}
                      onMouseEnter={() => setHoverSlug(skill.slug)}
                      onMouseLeave={() => setHoverSlug(null)}
                      className={`px-3 py-2 border-b border-border text-xs cursor-default transition-colors ${
                        hoverSlug === skill.slug ? 'bg-primary/5' : ''
                      }`}
                    >
                      <div className="font-medium text-foreground">{skill.name}</div>
                      <div className="text-muted-foreground">
                        {skill.token_estimate} tokens
                      </div>
                      {(skill.tuned_for_model || skill.last_validated_model) && (
                        <div className="mt-1 flex flex-wrap gap-1">
                          {skill.tuned_for_model && (
                            <span className="font-mono px-1.5 py-0.5 bg-muted/60 rounded text-[10px]">
                              tuned: {skill.tuned_for_model}
                            </span>
                          )}
                          {skill.last_validated_model && (
                            <span className="font-mono px-1.5 py-0.5 bg-muted/60 rounded text-[10px]">
                              validated: {skill.last_validated_model}
                            </span>
                          )}
                        </div>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </aside>
          )}
        </div>
      </div>

      {showShareModal && (
        <ShareComposeModal
          projectId={projectId}
          agentId={agentId}
          modelOverride={modelOverride}
          depth="full"
          onClose={() => setShowShareModal(false)}
        />
      )}
    </div>
  )
}
