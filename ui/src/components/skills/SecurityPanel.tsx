import { useState, useCallback } from 'react'
import { Shield, ShieldAlert, Loader2, AlertTriangle, CheckCircle, Search } from 'lucide-react'
import { scanSkillSecurity, reviewSkillContent } from '@/api/client'
import { Button } from '@/components/ui/button'
import type { SecurityScanResult, ContentReviewResult } from '@/types'

interface SecurityPanelProps {
  skillId: number
}

const riskLevelColors: Record<string, string> = {
  low: 'bg-green-500/15 text-green-500 border-green-500/30',
  medium: 'bg-yellow-500/15 text-yellow-500 border-yellow-500/30',
  high: 'bg-orange-500/15 text-orange-500 border-orange-500/30',
  critical: 'bg-red-500/15 text-red-500 border-red-500/30',
}

const severityColors: Record<string, string> = {
  low: 'text-green-500',
  medium: 'text-yellow-500',
  high: 'text-orange-500',
  critical: 'text-red-500',
  info: 'text-blue-500',
  warning: 'text-yellow-500',
  error: 'text-red-500',
}

function riskScoreColor(score: number): string {
  if (score < 30) return 'text-green-500'
  if (score < 70) return 'text-yellow-500'
  return 'text-red-500'
}

function riskScoreBarColor(score: number): string {
  if (score < 30) return 'bg-green-500'
  if (score < 70) return 'bg-yellow-500'
  return 'bg-red-500'
}

export function SecurityPanel({ skillId }: SecurityPanelProps) {
  const [scanResult, setScanResult] = useState<SecurityScanResult | null>(null)
  const [scanLoading, setScanLoading] = useState(false)
  const [reviewResult, setReviewResult] = useState<ContentReviewResult | null>(null)
  const [reviewLoading, setReviewLoading] = useState(false)

  const handleScan = useCallback(async () => {
    setScanLoading(true)
    try {
      const result = await scanSkillSecurity(skillId)
      setScanResult(result)
    } catch {
      setScanResult(null)
    } finally {
      setScanLoading(false)
    }
  }, [skillId])

  const handleReview = useCallback(async () => {
    setReviewLoading(true)
    try {
      const result = await reviewSkillContent(skillId)
      setReviewResult(result)
    } catch {
      setReviewResult(null)
    } finally {
      setReviewLoading(false)
    }
  }, [skillId])

  return (
    <div className="flex flex-col h-full">
      {/* Security Scan Section */}
      <div className="border-b border-border">
        <div className="p-3">
          <Button
            size="xs"
            variant="outline"
            onClick={handleScan}
            disabled={scanLoading}
            className="w-full"
          >
            {scanLoading ? (
              <Loader2 className="h-3 w-3 mr-1 animate-spin" />
            ) : (
              <Shield className="h-3 w-3 mr-1" />
            )}
            {scanLoading ? 'Scanning...' : 'Run Security Scan'}
          </Button>
        </div>

        {scanResult && (
          <div className="px-3 pb-3 space-y-2">
            <div className="flex items-center gap-2">
              <span
                className={`inline-flex items-center px-2 py-0.5 text-[10px] font-medium border rounded ${riskLevelColors[scanResult.risk_level] ?? riskLevelColors.low}`}
              >
                {scanResult.risk_level.toUpperCase()}
              </span>
              <span className="text-[10px] text-muted-foreground">
                {scanResult.findings.length} finding{scanResult.findings.length !== 1 && 's'}
              </span>
            </div>

            {scanResult.findings.length === 0 && (
              <div className="flex items-center gap-2 text-xs text-muted-foreground bg-background rounded p-2">
                <CheckCircle className="h-3.5 w-3.5 text-green-500 shrink-0" />
                No security issues found.
              </div>
            )}

            {scanResult.findings.map((finding, idx) => (
              <div
                key={idx}
                className="bg-background border border-border rounded px-3 py-2 text-xs"
              >
                <div className="flex items-start gap-2">
                  <ShieldAlert
                    className={`h-3.5 w-3.5 shrink-0 mt-0.5 ${severityColors[finding.severity] ?? 'text-muted-foreground'}`}
                  />
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="font-medium">{finding.type}</span>
                      <span
                        className={`text-[10px] ${severityColors[finding.severity] ?? 'text-muted-foreground'}`}
                      >
                        {finding.severity}
                      </span>
                    </div>
                    <p className="text-muted-foreground mt-0.5">{finding.message}</p>
                    {finding.line !== null && (
                      <p className="text-[10px] text-muted-foreground mt-1 font-mono">
                        Line {finding.line}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Content Review Section */}
      <div className="flex-1 overflow-y-auto">
        <div className="p-3">
          <Button
            size="xs"
            variant="outline"
            onClick={handleReview}
            disabled={reviewLoading}
            className="w-full"
          >
            {reviewLoading ? (
              <Loader2 className="h-3 w-3 mr-1 animate-spin" />
            ) : (
              <Search className="h-3 w-3 mr-1" />
            )}
            {reviewLoading ? 'Reviewing...' : 'Run Content Review'}
          </Button>
        </div>

        {reviewResult && (
          <div className="px-3 pb-3 space-y-2">
            <div className="flex items-center gap-3">
              <span className={`text-lg font-bold ${riskScoreColor(reviewResult.risk_score)}`}>
                {reviewResult.risk_score}
              </span>
              <div className="flex-1">
                <div className="text-[10px] text-muted-foreground mb-1">Risk Score</div>
                <div className="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                  <div
                    className={`h-full rounded-full transition-all ${riskScoreBarColor(reviewResult.risk_score)}`}
                    style={{ width: `${reviewResult.risk_score}%` }}
                  />
                </div>
              </div>
            </div>

            {reviewResult.findings.length === 0 && (
              <div className="flex items-center gap-2 text-xs text-muted-foreground bg-background rounded p-2">
                <CheckCircle className="h-3.5 w-3.5 text-green-500 shrink-0" />
                No content issues found.
              </div>
            )}

            {reviewResult.findings.map((finding, idx) => (
              <div
                key={idx}
                className="bg-background border border-border rounded px-3 py-2 text-xs"
              >
                <div className="flex items-start gap-2">
                  <AlertTriangle
                    className={`h-3.5 w-3.5 shrink-0 mt-0.5 ${severityColors[finding.severity] ?? 'text-muted-foreground'}`}
                  />
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="font-medium">{finding.category}</span>
                      <span
                        className={`text-[10px] ${severityColors[finding.severity] ?? 'text-muted-foreground'}`}
                      >
                        {finding.severity}
                      </span>
                    </div>
                    <p className="text-muted-foreground mt-0.5">{finding.description}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {!reviewResult && !reviewLoading && (
          <div className="flex items-center justify-center h-32 text-sm text-muted-foreground">
            Run scans to check for security and content issues.
          </div>
        )}
      </div>
    </div>
  )
}
