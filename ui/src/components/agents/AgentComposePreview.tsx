import { useState, useEffect } from 'react'
import { X, Copy, Check, Loader2, AlertTriangle } from 'lucide-react'
import { fetchAgentCompose } from '@/api/client'
import { Button } from '@/components/ui/button'
import type { AgentComposed } from '@/types'

const MODEL_LIMITS: Record<string, number> = {
  'claude-sonnet-4-20250514': 200000,
  'claude-opus-4-20250514': 200000,
  'claude-haiku-4-5-20251001': 200000,
  'gpt-4o': 128000,
  'gpt-4.1': 1047576,
}

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

  useEffect(() => {
    fetchAgentCompose(projectId, agentId)
      .then(setComposed)
      .catch(() => setError('Failed to compose agent output'))
      .finally(() => setLoading(false))
  }, [projectId, agentId])

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

  const tokenPercent = composed
    ? Math.min((composed.token_estimate / DEFAULT_LIMIT) * 100, 100)
    : 0

  const tokenWarning = composed && composed.token_estimate > DEFAULT_LIMIT * 0.75

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-background border border-border rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-border">
          <div className="flex items-center gap-3">
            <div>
              <h2 className="text-lg font-semibold">
                {agentName} — Composed Output
              </h2>
              {composed && (
                <div className="flex items-center gap-3 mt-1">
                  <span className="text-xs text-muted-foreground">
                    ~{composed.token_estimate.toLocaleString()} tokens
                  </span>
                  <span className="text-xs text-muted-foreground">
                    {composed.skill_count} skill
                    {composed.skill_count !== 1 ? 's' : ''} included
                  </span>
                  {/* Token budget bar */}
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
                      {tokenPercent.toFixed(0)}%
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
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={handleCopy}
              disabled={!composed}
            >
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
            <button
              onClick={onClose}
              className="p-1.5 rounded-md hover:bg-muted transition-colors"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto">
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

          {composed && (
            <pre className="p-5 text-sm font-mono whitespace-pre-wrap leading-relaxed">
              {composed.content}
            </pre>
          )}
        </div>
      </div>
    </div>
  )
}
