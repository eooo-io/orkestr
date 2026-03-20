import { useEffect, useState, useCallback } from 'react'
import { Link } from 'react-router-dom'
import {
  FileText,
  Code,
  BarChart3,
  CheckCircle,
  FileImage,
  File,
  ExternalLink,
  ThumbsUp,
  ThumbsDown,
} from 'lucide-react'
import { fetchArtifacts, approveArtifact, rejectArtifact } from '@/api/client'
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

interface ArtifactsTabProps {
  projectId: number
}

export function ArtifactsTab({ projectId }: ArtifactsTabProps) {
  const [artifacts, setArtifacts] = useState<Artifact[]>([])
  const [loading, setLoading] = useState(true)
  const { showToast } = useAppStore()

  const load = useCallback(() => {
    fetchArtifacts(projectId)
      .then(setArtifacts)
      .finally(() => setLoading(false))
  }, [projectId])

  useEffect(() => {
    load()
  }, [load])

  const handleApprove = async (id: number) => {
    await approveArtifact(id)
    showToast('Artifact approved')
    load()
  }

  const handleReject = async (id: number) => {
    await rejectArtifact(id)
    showToast('Artifact rejected')
    load()
  }

  if (loading) {
    return (
      <div className="p-6 text-sm text-muted-foreground animate-pulse">
        Loading artifacts...
      </div>
    )
  }

  if (artifacts.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center h-full text-muted-foreground p-8">
        <File className="h-10 w-10 mb-3 opacity-30" />
        <p className="text-sm">No artifacts yet</p>
        <p className="text-xs mt-1">Agents will create artifacts during execution.</p>
      </div>
    )
  }

  return (
    <div className="flex flex-col h-full">
      <div className="p-3 border-b border-border flex items-center justify-between">
        <span className="text-sm font-medium">{artifacts.length} artifacts</span>
        <Link
          to={`/projects/${projectId}/artifacts`}
          className="text-xs text-primary hover:underline flex items-center gap-1"
        >
          Full browser <ExternalLink className="h-3 w-3" />
        </Link>
      </div>

      <div className="flex-1 overflow-y-auto divide-y divide-border">
        {artifacts.map((artifact) => (
          <div key={artifact.id} className="p-3 hover:bg-muted/20 group">
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
                    {artifact.format} &middot; v{artifact.version_number}
                  </span>
                </div>
              </div>
              {artifact.status === 'pending_review' && (
                <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button
                    onClick={() => handleApprove(artifact.id)}
                    className="p-1 text-green-600 hover:bg-green-500/10 rounded"
                    title="Approve"
                  >
                    <ThumbsUp className="h-3.5 w-3.5" />
                  </button>
                  <button
                    onClick={() => handleReject(artifact.id)}
                    className="p-1 text-red-500 hover:bg-red-500/10 rounded"
                    title="Reject"
                  >
                    <ThumbsDown className="h-3.5 w-3.5" />
                  </button>
                </div>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
