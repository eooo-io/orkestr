import { useState, useEffect, useRef } from 'react'
import { ArrowUpFromLine, Loader2, X, Plus, Pencil, Minus, Equal } from 'lucide-react'
import { fetchSyncPreview, syncProject } from '@/api/client'
import { Button } from '@/components/ui/button'
import type { SyncPreviewFile } from '@/types'
import * as monaco from 'monaco-editor'

interface SyncPreviewModalProps {
  projectId: number
  onClose: () => void
  onSynced: () => void
}

const STATUS_CONFIG = {
  added: { label: 'Added', color: 'bg-green-500/15 text-green-400 border-green-500/30' },
  modified: { label: 'Modified', color: 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30' },
  unchanged: { label: 'Unchanged', color: 'bg-muted text-muted-foreground border-border' },
  deleted: { label: 'Deleted', color: 'bg-red-500/15 text-red-400 border-red-500/30' },
} as const

function StatusBadge({ status }: { status: SyncPreviewFile['status'] }) {
  const config = STATUS_CONFIG[status]
  return (
    <span className={`text-[10px] px-1.5 py-0.5 rounded border font-medium ${config.color}`}>
      {config.label}
    </span>
  )
}

function StatusIcon({ status }: { status: SyncPreviewFile['status'] }) {
  switch (status) {
    case 'added':
      return <Plus className="h-3.5 w-3.5 text-green-400" />
    case 'modified':
      return <Pencil className="h-3.5 w-3.5 text-yellow-400" />
    case 'deleted':
      return <Minus className="h-3.5 w-3.5 text-red-400" />
    case 'unchanged':
      return <Equal className="h-3.5 w-3.5 text-muted-foreground" />
  }
}

function DiffViewer({ file }: { file: SyncPreviewFile }) {
  const containerRef = useRef<HTMLDivElement>(null)
  const editorRef = useRef<monaco.editor.IStandaloneDiffEditor | null>(null)

  useEffect(() => {
    if (!containerRef.current) return

    const original = monaco.editor.createModel(file.current || '', 'markdown')
    const modified = monaco.editor.createModel(file.proposed || '', 'markdown')

    const diffEditor = monaco.editor.createDiffEditor(containerRef.current, {
      readOnly: true,
      renderSideBySide: true,
      automaticLayout: true,
      minimap: { enabled: false },
      scrollBeyondLastLine: false,
      fontSize: 12,
      lineNumbers: 'on',
      theme: document.documentElement.classList.contains('dark')
        ? 'vs-dark'
        : 'vs',
    })

    diffEditor.setModel({ original, modified })
    editorRef.current = diffEditor

    return () => {
      diffEditor.dispose()
      original.dispose()
      modified.dispose()
    }
  }, [file])

  return (
    <div ref={containerRef} className="h-[400px] border border-border rounded-md overflow-hidden" />
  )
}

export function SyncPreviewModal({ projectId, onClose, onSynced }: SyncPreviewModalProps) {
  const [files, setFiles] = useState<SyncPreviewFile[]>([])
  const [loading, setLoading] = useState(true)
  const [syncing, setSyncing] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [activeProvider, setActiveProvider] = useState<string | null>(null)
  const [expandedFile, setExpandedFile] = useState<string | null>(null)

  useEffect(() => {
    fetchSyncPreview(projectId)
      .then((data) => {
        setFiles(data)
        // Auto-select first provider that has changes
        const providers = [...new Set(data.map((f) => f.provider))]
        const providerWithChanges = providers.find((p) =>
          data.some((f) => f.provider === p && f.status !== 'unchanged'),
        )
        setActiveProvider(providerWithChanges || providers[0] || null)
      })
      .catch(() => setError('Failed to fetch sync preview'))
      .finally(() => setLoading(false))
  }, [projectId])

  const handleSync = async () => {
    setSyncing(true)
    setError(null)
    try {
      await syncProject(projectId)
      onSynced()
      onClose()
    } catch {
      setError('Sync failed. Please try again.')
    } finally {
      setSyncing(false)
    }
  }

  // Group files by provider
  const providers = [...new Set(files.map((f) => f.provider))]
  const activeFiles = files.filter((f) => f.provider === activeProvider)

  const counts = {
    added: files.filter((f) => f.status === 'added').length,
    modified: files.filter((f) => f.status === 'modified').length,
    unchanged: files.filter((f) => f.status === 'unchanged').length,
    deleted: files.filter((f) => f.status === 'deleted').length,
  }

  const hasChanges = counts.added > 0 || counts.modified > 0 || counts.deleted > 0

  const providerHasChanges = (provider: string) =>
    files.some(
      (f) => f.provider === provider && f.status !== 'unchanged',
    )

  // Extract short filename from full path
  const shortPath = (path: string) => {
    const parts = path.split('/')
    // Show last 2-3 path segments
    return parts.slice(-3).join('/')
  }

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-background rounded-lg border border-border shadow-xl w-full max-w-4xl max-h-[85vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <div className="flex items-center gap-2">
            <ArrowUpFromLine className="h-4 w-4 text-primary" />
            <h2 className="font-semibold text-sm">Sync Preview</h2>
          </div>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto">
          {loading ? (
            <div className="flex items-center justify-center py-16">
              <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
              <span className="ml-2 text-sm text-muted-foreground">
                Generating preview...
              </span>
            </div>
          ) : error && files.length === 0 ? (
            <div className="p-4 text-sm text-destructive bg-destructive/10 rounded-md m-4">
              {error}
            </div>
          ) : files.length === 0 ? (
            <div className="text-center py-16 text-muted-foreground">
              <p className="text-sm">No providers configured for this project.</p>
              <p className="text-xs mt-1">Add providers in the project settings to enable sync.</p>
            </div>
          ) : (
            <div className="p-4 space-y-4">
              {/* Summary bar */}
              <div className="flex items-center gap-3 text-xs">
                <span className="text-muted-foreground">Summary:</span>
                {counts.added > 0 && (
                  <span className="flex items-center gap-1 text-green-400">
                    <Plus className="h-3 w-3" />
                    {counts.added} added
                  </span>
                )}
                {counts.modified > 0 && (
                  <span className="flex items-center gap-1 text-yellow-400">
                    <Pencil className="h-3 w-3" />
                    {counts.modified} modified
                  </span>
                )}
                {counts.deleted > 0 && (
                  <span className="flex items-center gap-1 text-red-400">
                    <Minus className="h-3 w-3" />
                    {counts.deleted} deleted
                  </span>
                )}
                {counts.unchanged > 0 && (
                  <span className="flex items-center gap-1 text-muted-foreground">
                    <Equal className="h-3 w-3" />
                    {counts.unchanged} unchanged
                  </span>
                )}
                {!hasChanges && (
                  <span className="text-muted-foreground">
                    All files are up to date -- nothing to sync.
                  </span>
                )}
              </div>

              {/* Provider tabs */}
              {providers.length > 1 && (
                <div className="flex items-center gap-1 border-b border-border">
                  {providers.map((provider) => (
                    <button
                      key={provider}
                      onClick={() => {
                        setActiveProvider(provider)
                        setExpandedFile(null)
                      }}
                      className={`px-3 py-2 text-xs font-medium border-b-2 transition-colors -mb-px capitalize ${
                        activeProvider === provider
                          ? 'border-primary text-foreground'
                          : 'border-transparent text-muted-foreground hover:text-foreground'
                      }`}
                    >
                      {provider}
                      {providerHasChanges(provider) && (
                        <span className="ml-1.5 h-1.5 w-1.5 rounded-full bg-yellow-400 inline-block" />
                      )}
                    </button>
                  ))}
                </div>
              )}

              {/* File list for active provider */}
              <div className="space-y-2">
                {activeFiles.map((file) => (
                  <div
                    key={file.path}
                    className="border border-border rounded-md overflow-hidden"
                  >
                    <button
                      onClick={() =>
                        setExpandedFile(
                          expandedFile === file.path ? null : file.path,
                        )
                      }
                      className="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-accent/50 transition-colors"
                    >
                      <StatusIcon status={file.status} />
                      <span className="text-xs font-mono flex-1 truncate">
                        {shortPath(file.path)}
                      </span>
                      <StatusBadge status={file.status} />
                    </button>

                    {expandedFile === file.path && file.status !== 'unchanged' && (
                      <div className="border-t border-border">
                        <DiffViewer file={file} />
                      </div>
                    )}

                    {expandedFile === file.path && file.status === 'unchanged' && (
                      <div className="border-t border-border p-4 text-center text-xs text-muted-foreground">
                        File content is identical -- no changes to apply.
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between px-4 py-3 border-t border-border">
          <div className="text-xs text-muted-foreground">
            {files.length} file{files.length !== 1 && 's'} across{' '}
            {providers.length} provider{providers.length !== 1 && 's'}
          </div>
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="sm" onClick={onClose} disabled={syncing}>
              Cancel
            </Button>
            <Button
              size="sm"
              onClick={handleSync}
              disabled={syncing || !hasChanges}
            >
              {syncing ? (
                <>
                  <Loader2 className="h-4 w-4 mr-1 animate-spin" />
                  Syncing...
                </>
              ) : (
                <>
                  <ArrowUpFromLine className="h-4 w-4 mr-1" />
                  Confirm Sync
                </>
              )}
            </Button>
          </div>
        </div>

        {error && files.length > 0 && (
          <div className="px-4 pb-3">
            <div className="text-sm text-destructive bg-destructive/10 rounded-md px-3 py-2">
              {error}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
