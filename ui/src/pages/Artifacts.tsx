import { useEffect, useState, useCallback } from 'react'
import { useParams } from 'react-router-dom'
import DOMPurify from 'dompurify'
import {
  FileText,
  Code,
  BarChart3,
  CheckCircle,
  FileImage,
  File,
  Eye,
  Download,
  ThumbsUp,
  ThumbsDown,
  Trash2,
} from 'lucide-react'
import {
  fetchArtifacts,
  approveArtifact,
  rejectArtifact,
  deleteArtifact,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import { useAppStore } from '@/store/useAppStore'
import type { Artifact } from '@/types'

const TYPE_ICONS: Record<string, React.ReactNode> = {
  report: <FileText className="h-4 w-4" />,
  code: <Code className="h-4 w-4" />,
  dataset: <BarChart3 className="h-4 w-4" />,
  decision: <CheckCircle className="h-4 w-4" />,
  document: <FileText className="h-4 w-4" />,
  image: <FileImage className="h-4 w-4" />,
  other: <File className="h-4 w-4" />,
}

const STATUS_STYLES: Record<string, string> = {
  draft: 'bg-muted text-muted-foreground',
  pending_review: 'bg-yellow-500/10 text-yellow-600',
  approved: 'bg-green-500/10 text-green-600',
  rejected: 'bg-red-500/10 text-red-500',
  published: 'bg-primary/10 text-primary',
}

export function Artifacts() {
  const { id } = useParams<{ id: string }>()
  const projectId = parseInt(id || '0')
  const [artifacts, setArtifacts] = useState<Artifact[]>([])
  const [loading, setLoading] = useState(true)
  const [typeFilter, setTypeFilter] = useState<string>('')
  const [statusFilter, setStatusFilter] = useState<string>('')
  const [selectedArtifact, setSelectedArtifact] = useState<Artifact | null>(null)
  const confirm = useConfirm()
  const { showToast } = useAppStore()

  const load = useCallback(() => {
    const params: Record<string, string> = {}
    if (typeFilter) params.type = typeFilter
    if (statusFilter) params.status = statusFilter
    fetchArtifacts(projectId, params)
      .then(setArtifacts)
      .finally(() => setLoading(false))
  }, [projectId, typeFilter, statusFilter])

  useEffect(() => {
    load()
  }, [load])

  const handleApprove = async (artifact: Artifact) => {
    await approveArtifact(artifact.id)
    showToast('Artifact approved')
    load()
  }

  const handleReject = async (artifact: Artifact) => {
    await rejectArtifact(artifact.id)
    showToast('Artifact rejected')
    load()
  }

  const handleDelete = async (artifact: Artifact) => {
    if (!(await confirm({ message: `Delete "${artifact.title}"?`, title: 'Delete Artifact' })))
      return
    await deleteArtifact(artifact.id)
    showToast('Artifact deleted')
    if (selectedArtifact?.id === artifact.id) setSelectedArtifact(null)
    load()
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-pulse text-muted-foreground">Loading artifacts...</div>
      </div>
    )
  }

  return (
    <div className="flex h-screen">
      {/* List */}
      <div className="w-full lg:w-[400px] border-r border-border flex flex-col">
        <div className="p-4 border-b border-border">
          <h1 className="text-lg font-semibold">Artifacts</h1>
          <div className="flex gap-2 mt-3">
            <select
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value)}
              className="text-xs border border-input bg-background rounded px-2 py-1 flex-1"
            >
              <option value="">All types</option>
              {['report', 'code', 'dataset', 'decision', 'document', 'image', 'other'].map(
                (t) => (
                  <option key={t} value={t}>
                    {t}
                  </option>
                ),
              )}
            </select>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="text-xs border border-input bg-background rounded px-2 py-1 flex-1"
            >
              <option value="">All statuses</option>
              {['draft', 'pending_review', 'approved', 'rejected', 'published'].map((s) => (
                <option key={s} value={s}>
                  {s.replace('_', ' ')}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="flex-1 overflow-y-auto">
          {artifacts.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-sm text-muted-foreground p-4 text-center">
              <File className="h-8 w-8 mb-2 opacity-40" />
              No artifacts yet. Agents will create artifacts during execution.
            </div>
          ) : (
            <div className="divide-y divide-border">
              {artifacts.map((artifact) => (
                <button
                  key={artifact.id}
                  onClick={() => setSelectedArtifact(artifact)}
                  className={`w-full text-left p-3 hover:bg-muted/30 transition-colors ${
                    selectedArtifact?.id === artifact.id ? 'bg-muted/50' : ''
                  }`}
                >
                  <div className="flex items-start gap-2.5">
                    <span className="mt-0.5 text-muted-foreground">
                      {TYPE_ICONS[artifact.type] || TYPE_ICONS.other}
                    </span>
                    <div className="flex-1 min-w-0">
                      <div className="text-sm font-medium truncate">{artifact.title}</div>
                      {artifact.description && (
                        <p className="text-xs text-muted-foreground mt-0.5 line-clamp-1">
                          {artifact.description}
                        </p>
                      )}
                      <div className="flex items-center gap-2 mt-1.5">
                        <span
                          className={`text-[10px] px-1.5 py-0.5 rounded font-medium capitalize ${STATUS_STYLES[artifact.status]}`}
                        >
                          {artifact.status.replace('_', ' ')}
                        </span>
                        {artifact.agent && (
                          <span className="text-[10px] text-muted-foreground">
                            by {artifact.agent.name}
                          </span>
                        )}
                        <span className="text-[10px] text-muted-foreground/60">
                          v{artifact.version_number}
                        </span>
                      </div>
                    </div>
                  </div>
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Detail / Preview */}
      <div className="hidden lg:flex flex-1 flex-col">
        {selectedArtifact ? (
          <>
            <div className="p-4 border-b border-border flex items-center justify-between">
              <div>
                <h2 className="text-base font-semibold">{selectedArtifact.title}</h2>
                {selectedArtifact.description && (
                  <p className="text-sm text-muted-foreground mt-0.5">
                    {selectedArtifact.description}
                  </p>
                )}
              </div>
              <div className="flex items-center gap-2">
                {selectedArtifact.status === 'pending_review' && (
                  <>
                    <button
                      onClick={() => handleApprove(selectedArtifact)}
                      className="flex items-center gap-1 text-xs px-3 py-1.5 bg-green-600 text-white rounded hover:bg-green-700"
                    >
                      <ThumbsUp className="h-3 w-3" /> Approve
                    </button>
                    <button
                      onClick={() => handleReject(selectedArtifact)}
                      className="flex items-center gap-1 text-xs px-3 py-1.5 bg-red-600 text-white rounded hover:bg-red-700"
                    >
                      <ThumbsDown className="h-3 w-3" /> Reject
                    </button>
                  </>
                )}
                <a
                  href={`/api/artifacts/${selectedArtifact.id}/download`}
                  className="flex items-center gap-1 text-xs px-3 py-1.5 bg-muted rounded hover:bg-muted/80"
                >
                  <Download className="h-3 w-3" /> Download
                </a>
                <button
                  onClick={() => handleDelete(selectedArtifact)}
                  className="flex items-center gap-1 text-xs px-3 py-1.5 text-destructive hover:bg-destructive/10 rounded"
                >
                  <Trash2 className="h-3 w-3" />
                </button>
              </div>
            </div>

            {/* Content preview */}
            <div className="flex-1 overflow-y-auto p-6">
              {selectedArtifact.content ? (
                selectedArtifact.format === 'json' ? (
                  <pre className="text-sm font-mono bg-muted/30 p-4 rounded overflow-x-auto whitespace-pre-wrap">
                    {(() => {
                      try {
                        return JSON.stringify(JSON.parse(selectedArtifact.content), null, 2)
                      } catch {
                        return selectedArtifact.content
                      }
                    })()}
                  </pre>
                ) : selectedArtifact.format === 'csv' ? (
                  <div className="overflow-x-auto">
                    <table className="text-sm border-collapse w-full">
                      <tbody>
                        {selectedArtifact.content.split('\n').filter(Boolean).map((row, i) => (
                          <tr key={i} className={i === 0 ? 'font-semibold bg-muted/30' : ''}>
                            {row.split(',').map((cell, j) => (
                              <td key={j} className="border border-border px-2 py-1">
                                {cell.trim()}
                              </td>
                            ))}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : selectedArtifact.format === 'html' ? (
                  <div
                    className="prose prose-sm max-w-none dark:prose-invert"
                    dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(selectedArtifact.content) }}
                  />
                ) : (
                  <div className="prose prose-sm max-w-none dark:prose-invert whitespace-pre-wrap">
                    {selectedArtifact.content}
                  </div>
                )
              ) : (
                <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
                  No inline content. Use download to access the file.
                </div>
              )}
            </div>

            {/* Metadata footer */}
            <div className="p-3 border-t border-border text-xs text-muted-foreground flex items-center gap-4">
              <span>Type: {selectedArtifact.type}</span>
              <span>Format: {selectedArtifact.format}</span>
              <span>Version: {selectedArtifact.version_number}</span>
              {selectedArtifact.file_size && (
                <span>Size: {(selectedArtifact.file_size / 1024).toFixed(1)} KB</span>
              )}
              <span>Created: {new Date(selectedArtifact.created_at).toLocaleString()}</span>
            </div>
          </>
        ) : (
          <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
            Select an artifact to preview
          </div>
        )}
      </div>
    </div>
  )
}
