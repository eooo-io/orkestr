import { useState, useCallback } from 'react'
import { AlertTriangle, Lightbulb, RefreshCw, CheckCircle } from 'lucide-react'
import { lintSkill } from '@/api/client'
import { Button } from '@/components/ui/button'
import type { LintIssue } from '@/types'

interface LintPanelProps {
  skillId: number
}

export function LintPanel({ skillId }: LintPanelProps) {
  const [issues, setIssues] = useState<LintIssue[] | null>(null)
  const [loading, setLoading] = useState(false)

  const handleLint = useCallback(async () => {
    setLoading(true)
    try {
      const result = await lintSkill(skillId)
      setIssues(result)
    } catch {
      setIssues(null)
    } finally {
      setLoading(false)
    }
  }, [skillId])

  const warnings = issues?.filter((i) => i.severity === 'warning') ?? []
  const suggestions = issues?.filter((i) => i.severity === 'suggestion') ?? []

  return (
    <div className="flex flex-col h-full">
      <div className="p-3 border-b border-border">
        <Button
          size="xs"
          variant="outline"
          onClick={handleLint}
          disabled={loading}
          className="w-full"
        >
          <RefreshCw className={`h-3 w-3 mr-1 ${loading ? 'animate-spin' : ''}`} />
          {loading ? 'Linting...' : 'Run Lint'}
        </Button>
      </div>

      <div className="flex-1 overflow-y-auto">
        {issues === null && !loading && (
          <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
            Click "Run Lint" to check for prompt issues.
          </div>
        )}

        {issues !== null && issues.length === 0 && (
          <div className="flex flex-col items-center justify-center h-full text-sm text-muted-foreground gap-2">
            <CheckCircle className="h-6 w-6 text-green-500" />
            <p>No issues found!</p>
          </div>
        )}

        {issues !== null && issues.length > 0 && (
          <div className="p-3 space-y-2">
            <div className="flex items-center gap-3 text-[10px] text-muted-foreground mb-2">
              {warnings.length > 0 && (
                <span className="flex items-center gap-1">
                  <AlertTriangle className="h-3 w-3 text-yellow-500" />
                  {warnings.length} warning{warnings.length !== 1 && 's'}
                </span>
              )}
              {suggestions.length > 0 && (
                <span className="flex items-center gap-1">
                  <Lightbulb className="h-3 w-3 text-blue-500" />
                  {suggestions.length} suggestion{suggestions.length !== 1 && 's'}
                </span>
              )}
            </div>

            {issues.map((issue, idx) => (
              <div
                key={idx}
                className={`border px-3 py-2 text-xs ${
                  issue.severity === 'warning'
                    ? 'border-yellow-500/30 bg-yellow-500/5'
                    : 'border-blue-500/30 bg-blue-500/5'
                }`}
              >
                <div className="flex items-start gap-2">
                  {issue.severity === 'warning' ? (
                    <AlertTriangle className="h-3.5 w-3.5 text-yellow-500 shrink-0 mt-0.5" />
                  ) : (
                    <Lightbulb className="h-3.5 w-3.5 text-blue-500 shrink-0 mt-0.5" />
                  )}
                  <div>
                    <p className="font-medium">{issue.message}</p>
                    <p className="text-muted-foreground mt-0.5">
                      {issue.suggestion}
                    </p>
                    {issue.line && (
                      <p className="text-[10px] text-muted-foreground mt-1 font-mono">
                        Line {issue.line}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
