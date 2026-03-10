import { useState, useCallback } from 'react'
import { Upload, Loader2, X, CheckCircle, AlertTriangle } from 'lucide-react'
import { importBundlePreview, importBundle } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import type { BundlePreview, BundleImportResult } from '@/types'

interface ImportBundleModalProps {
  projectId: number
  onClose: () => void
  onImported: () => void
}

export function ImportBundleModal({
  projectId,
  onClose,
  onImported,
}: ImportBundleModalProps) {
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<BundlePreview | null>(null)
  const [conflictMode, setConflictMode] = useState<'skip' | 'overwrite' | 'rename'>('skip')
  const [loading, setLoading] = useState(false)
  const [importing, setImporting] = useState(false)
  const [result, setResult] = useState<BundleImportResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [dragOver, setDragOver] = useState(false)
  const { showToast } = useAppStore()

  const handleFile = useCallback(async (f: File) => {
    const ext = f.name.split('.').pop()?.toLowerCase()
    if (ext !== 'zip' && ext !== 'json') {
      setError('Unsupported file type. Please use .zip or .json files.')
      return
    }

    setFile(f)
    setError(null)
    setPreview(null)
    setResult(null)
    setLoading(true)

    try {
      const p = await importBundlePreview(projectId, f)
      setPreview(p)
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message || 'Failed to preview bundle.'
      setError(message)
    } finally {
      setLoading(false)
    }
  }, [projectId])

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault()
      setDragOver(false)
      const f = e.dataTransfer.files[0]
      if (f) handleFile(f)
    },
    [handleFile],
  )

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    setDragOver(true)
  }, [])

  const handleDragLeave = useCallback(() => {
    setDragOver(false)
  }, [])

  const handleFileInput = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const f = e.target.files?.[0]
      if (f) handleFile(f)
    },
    [handleFile],
  )

  const handleImport = async () => {
    if (!file) return

    setImporting(true)
    setError(null)

    try {
      const r = await importBundle(projectId, file, conflictMode)
      setResult(r)
      showToast(
        `Imported ${r.imported} item${r.imported !== 1 ? 's' : ''}${r.skipped > 0 ? `, ${r.skipped} skipped` : ''}`,
      )
      onImported()
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message || 'Import failed. Please try again.'
      setError(message)
    } finally {
      setImporting(false)
    }
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-background rounded-lg border border-border shadow-xl w-full max-w-lg max-h-[80vh] flex flex-col">
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <div className="flex items-center gap-2">
            <Upload className="h-4 w-4 text-primary" />
            <h2 className="font-semibold text-sm">Import Bundle</h2>
          </div>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="p-4 space-y-4 overflow-y-auto flex-1">
          {/* Result view */}
          {result ? (
            <div className="space-y-3">
              <div className="flex items-center gap-2 text-sm">
                <CheckCircle className="h-4 w-4 text-green-500" />
                <span>Import complete</span>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="bg-muted/50 rounded-md px-3 py-2 text-center">
                  <p className="text-lg font-semibold">{result.imported}</p>
                  <p className="text-xs text-muted-foreground">Imported</p>
                </div>
                <div className="bg-muted/50 rounded-md px-3 py-2 text-center">
                  <p className="text-lg font-semibold">{result.skipped}</p>
                  <p className="text-xs text-muted-foreground">Skipped</p>
                </div>
              </div>
              {result.errors.length > 0 && (
                <div className="space-y-1">
                  <p className="text-xs font-medium text-destructive flex items-center gap-1">
                    <AlertTriangle className="h-3 w-3" />
                    Errors ({result.errors.length})
                  </p>
                  <div className="bg-destructive/10 rounded-md p-2 max-h-32 overflow-y-auto">
                    {result.errors.map((err, i) => (
                      <p key={i} className="text-xs text-destructive">
                        {err}
                      </p>
                    ))}
                  </div>
                </div>
              )}
            </div>
          ) : !preview ? (
            <>
              {/* Drop zone */}
              <div
                onDrop={handleDrop}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                className={`border-2 border-dashed rounded-lg p-8 text-center transition-colors ${
                  dragOver
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:border-muted-foreground'
                }`}
              >
                {loading ? (
                  <div className="flex flex-col items-center gap-2">
                    <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                    <p className="text-sm text-muted-foreground">Reading bundle...</p>
                  </div>
                ) : (
                  <>
                    <Upload className="h-8 w-8 mx-auto text-muted-foreground mb-2" />
                    <p className="text-sm text-muted-foreground mb-1">
                      Drag & drop a .zip or .json bundle file
                    </p>
                    <p className="text-xs text-muted-foreground mb-3">or</p>
                    <label className="cursor-pointer">
                      <span className="text-sm text-primary hover:underline">
                        Browse files
                      </span>
                      <input
                        type="file"
                        accept=".zip,.json"
                        onChange={handleFileInput}
                        className="hidden"
                      />
                    </label>
                  </>
                )}
              </div>
            </>
          ) : (
            <>
              {/* Preview */}
              <div className="bg-muted/50 rounded-md px-3 py-2 flex items-center justify-between">
                <span className="text-sm truncate">{file?.name}</span>
                <button
                  onClick={() => {
                    setFile(null)
                    setPreview(null)
                  }}
                  className="text-xs text-muted-foreground hover:text-foreground ml-2"
                >
                  Change
                </button>
              </div>

              {preview.skills.length > 0 && (
                <div>
                  <label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                    Skills ({preview.skills.length})
                  </label>
                  <div className="mt-1 space-y-1 max-h-36 overflow-y-auto border border-border rounded-md p-2">
                    {preview.skills.map((skill, i) => (
                      <div
                        key={i}
                        className="flex items-center justify-between px-2 py-1.5 rounded bg-background"
                      >
                        <span className="text-sm truncate">{skill.name}</span>
                        {skill.tags && skill.tags.length > 0 && (
                          <span className="text-[10px] text-muted-foreground ml-2 shrink-0">
                            {skill.tags.join(', ')}
                          </span>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {preview.agents.length > 0 && (
                <div>
                  <label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                    Agents ({preview.agents.length})
                  </label>
                  <div className="mt-1 space-y-1 max-h-28 overflow-y-auto border border-border rounded-md p-2">
                    {preview.agents.map((agent, i) => (
                      <div
                        key={i}
                        className="flex items-center justify-between px-2 py-1.5 rounded bg-background"
                      >
                        <span className="text-sm truncate">{agent.name}</span>
                        {agent.role && (
                          <span className="text-[10px] text-muted-foreground ml-2 shrink-0">
                            {agent.role}
                          </span>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Conflict mode */}
              <div>
                <label className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                  If skill already exists
                </label>
                <div className="flex gap-2 mt-1">
                  {(['skip', 'overwrite', 'rename'] as const).map((mode) => (
                    <button
                      key={mode}
                      onClick={() => setConflictMode(mode)}
                      className={`flex-1 px-3 py-2 text-sm rounded-md border transition-colors capitalize ${
                        conflictMode === mode
                          ? 'border-primary bg-primary/10 text-foreground'
                          : 'border-input text-muted-foreground hover:text-foreground'
                      }`}
                    >
                      {mode}
                    </button>
                  ))}
                </div>
                <p className="text-[10px] text-muted-foreground mt-1">
                  {conflictMode === 'skip' && 'Existing skills will not be modified.'}
                  {conflictMode === 'overwrite' && 'Existing skills will be replaced with imported versions.'}
                  {conflictMode === 'rename' && 'Conflicting skills will be imported with "-imported" suffix.'}
                </p>
              </div>
            </>
          )}

          {error && (
            <div className="text-sm text-destructive bg-destructive/10 rounded-md px-3 py-2">
              {error}
            </div>
          )}
        </div>

        <div className="flex items-center justify-end gap-2 px-4 py-3 border-t border-border">
          <Button variant="ghost" size="sm" onClick={onClose} disabled={importing}>
            {result ? 'Close' : 'Cancel'}
          </Button>
          {preview && !result && (
            <Button
              size="sm"
              onClick={handleImport}
              disabled={importing}
            >
              {importing ? (
                <>
                  <Loader2 className="h-4 w-4 mr-1 animate-spin" />
                  Importing...
                </>
              ) : (
                <>
                  <Upload className="h-4 w-4 mr-1" />
                  Import
                </>
              )}
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}
