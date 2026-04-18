import { useState } from 'react'
import { X, Copy, Check, Loader2, AlertTriangle } from 'lucide-react'
import axios from 'axios'
import { createComposeShareLink } from '@/api/client'
import { Button } from '@/components/ui/button'

interface Props {
  projectId: number
  agentId: number
  modelOverride: string | null
  depth: 'index' | 'full' | 'deep'
  onClose: () => void
}

interface SecretIssue {
  rule: string
  message: string
  line: number | null
}

export function ShareComposeModal({ projectId, agentId, modelOverride, depth, onClose }: Props) {
  const [expiresInDays, setExpiresInDays] = useState<number>(7)
  const [isSnapshot, setIsSnapshot] = useState<boolean>(true)
  const [creating, setCreating] = useState(false)
  const [url, setUrl] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [secrets, setSecrets] = useState<SecretIssue[] | null>(null)

  const handleCreate = async () => {
    setCreating(true)
    setError(null)
    setSecrets(null)
    try {
      const link = await createComposeShareLink(projectId, agentId, {
        model: modelOverride,
        depth,
        is_snapshot: isSnapshot,
        expires_in_days: expiresInDays,
      })
      setUrl(link.url)
    } catch (err) {
      if (axios.isAxiosError(err) && err.response?.status === 422) {
        setSecrets(err.response.data?.secrets ?? [])
        setError(err.response.data?.error ?? 'Refused: output contains secrets')
      } else {
        setError('Failed to create share link')
      }
    } finally {
      setCreating(false)
    }
  }

  const handleCopy = async () => {
    if (!url) return
    await navigator.clipboard.writeText(url)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-foreground/30">
      <div className="bg-background elevation-4 w-full max-w-md border border-border rounded">
        <div className="flex items-center justify-between p-4 border-b border-border">
          <h3 className="text-sm font-semibold">Share compose preview</h3>
          <button onClick={onClose} className="p-1 hover:bg-muted rounded">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          {url ? (
            <div className="space-y-3">
              <p className="text-sm text-foreground">Share link created.</p>
              <div className="flex items-center gap-2">
                <input
                  type="text"
                  value={url}
                  readOnly
                  className="flex-1 text-xs px-2 py-1.5 border border-input bg-muted/30 rounded"
                />
                <Button size="sm" variant="outline" onClick={handleCopy}>
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
              </div>
            </div>
          ) : (
            <>
              <div>
                <label className="text-xs font-medium text-muted-foreground">
                  Expiry
                </label>
                <select
                  value={expiresInDays}
                  onChange={(e) => setExpiresInDays(parseInt(e.target.value))}
                  className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background rounded"
                >
                  <option value={1}>1 day</option>
                  <option value={7}>7 days (default)</option>
                  <option value={30}>30 days</option>
                  <option value={90}>90 days</option>
                </select>
              </div>

              <label className="flex items-start gap-2 text-xs cursor-pointer">
                <input
                  type="checkbox"
                  checked={isSnapshot}
                  onChange={(e) => setIsSnapshot(e.target.checked)}
                  className="mt-0.5 rounded border-input"
                />
                <div>
                  <div className="font-medium text-foreground">Snapshot (recommended)</div>
                  <div className="text-muted-foreground">
                    Freeze current content. Disable to re-render live on each view.
                  </div>
                </div>
              </label>

              {error && (
                <div className="border border-destructive/40 bg-destructive/10 rounded p-3 space-y-2">
                  <div className="flex items-start gap-2 text-xs text-destructive">
                    <AlertTriangle className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                    <span className="font-medium">{error}</span>
                  </div>
                  {secrets && secrets.length > 0 && (
                    <ul className="text-[11px] text-destructive/90 space-y-0.5 pl-5 list-disc">
                      {secrets.map((s, i) => (
                        <li key={i}>
                          {s.message}
                          {s.line !== null && ` (line ${s.line})`}
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
            </>
          )}
        </div>

        <div className="flex items-center justify-end gap-2 p-4 border-t border-border">
          {url ? (
            <Button size="sm" onClick={onClose}>
              Done
            </Button>
          ) : (
            <>
              <Button size="sm" variant="ghost" onClick={onClose}>
                Cancel
              </Button>
              <Button size="sm" onClick={handleCreate} disabled={creating}>
                {creating ? (
                  <>
                    <Loader2 className="h-3.5 w-3.5 mr-1 animate-spin" />
                    Creating…
                  </>
                ) : (
                  'Create share link'
                )}
              </Button>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
