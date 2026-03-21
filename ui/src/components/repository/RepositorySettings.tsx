import { useEffect, useState } from 'react'
import {
  Plus,
  Trash2,
  Loader2,
  GitBranch,
  Check,
  X,
  RefreshCw,
  ArrowDownToLine,
  ArrowUpFromLine,
  ExternalLink,
  ShieldCheck,
  FolderTree,
  AlertCircle,
} from 'lucide-react'
import {
  fetchRepositories,
  connectRepository,
  disconnectRepository,
  updateRepository,
  fetchRepositoryStatus,
  fetchRepositoryFiles,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import { useAppStore } from '@/store/useAppStore'
import { Button } from '@/components/ui/button'
import type { ProjectRepository, RepositoryStatus, RepositoryFile } from '@/types'

const ALLOWED_PATHS = [
  '.orkestr/',
  '.claude/',
  '.cursor/rules/',
  '.github/copilot-instructions.md',
  '.windsurf/rules/',
  '.clinerules',
  '.openai/',
]

interface Props {
  projectId: number
}

export function RepositorySettings({ projectId }: Props) {
  const { showToast } = useAppStore()
  const confirm = useConfirm()
  const [repositories, setRepositories] = useState<ProjectRepository[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [statusMap, setStatusMap] = useState<Record<string, RepositoryStatus>>({})
  const [filesMap, setFilesMap] = useState<Record<string, RepositoryFile[]>>({})
  const [loadingStatus, setLoadingStatus] = useState<string | null>(null)
  const [loadingFiles, setLoadingFiles] = useState<string | null>(null)
  const [expandedProvider, setExpandedProvider] = useState<string | null>(null)

  // Form state
  const [formProvider, setFormProvider] = useState<'github' | 'gitlab'>('github')
  const [formFullName, setFormFullName] = useState('')
  const [formToken, setFormToken] = useState('')
  const [formAutoScan, setFormAutoScan] = useState(true)
  const [formAutoSync, setFormAutoSync] = useState(false)

  const loadRepositories = () => {
    fetchRepositories(projectId)
      .then(setRepositories)
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadRepositories()
  }, [projectId])

  const resetForm = () => {
    setFormProvider('github')
    setFormFullName('')
    setFormToken('')
    setFormAutoScan(true)
    setFormAutoSync(false)
    setShowForm(false)
  }

  const handleConnect = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!formFullName.trim()) return
    setSubmitting(true)

    try {
      await connectRepository(projectId, {
        provider: formProvider,
        full_name: formFullName.trim(),
        access_token: formToken.trim() || undefined,
        auto_scan_on_push: formAutoScan,
        auto_sync_on_push: formAutoSync,
      })
      showToast('Repository connected')
      resetForm()
      loadRepositories()
    } catch {
      showToast('Failed to connect repository', 'error')
    } finally {
      setSubmitting(false)
    }
  }

  const handleDisconnect = async (provider: string) => {
    if (!(await confirm({ message: 'Disconnect this repository? No files will be deleted.', title: 'Confirm Disconnect' }))) return
    try {
      await disconnectRepository(projectId, provider)
      showToast('Repository disconnected')
      loadRepositories()
    } catch {
      showToast('Failed to disconnect', 'error')
    }
  }

  const handleToggleSetting = async (
    repo: ProjectRepository,
    field: 'auto_scan_on_push' | 'auto_sync_on_push',
  ) => {
    try {
      await updateRepository(projectId, repo.provider, {
        [field]: !repo[field],
      })
      loadRepositories()
    } catch {
      showToast('Failed to update setting', 'error')
    }
  }

  const checkStatus = async (provider: string) => {
    setLoadingStatus(provider)
    try {
      const status = await fetchRepositoryStatus(projectId, provider)
      setStatusMap((prev) => ({ ...prev, [provider]: status }))
    } catch {
      showToast('Failed to check status', 'error')
    } finally {
      setLoadingStatus(null)
    }
  }

  const loadFiles = async (provider: string) => {
    if (expandedProvider === provider) {
      setExpandedProvider(null)
      return
    }
    setExpandedProvider(provider)
    setLoadingFiles(provider)
    try {
      const files = await fetchRepositoryFiles(projectId, provider)
      setFilesMap((prev) => ({ ...prev, [provider]: files }))
    } catch {
      showToast('Failed to load files', 'error')
    } finally {
      setLoadingFiles(null)
    }
  }

  const providerIcon = (provider: string) => {
    if (provider === 'github') {
      return (
        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z" />
        </svg>
      )
    }
    return (
      <svg className="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
        <path d="M22.65 14.39L12 22.13 1.35 14.39a.84.84 0 01-.3-.94l1.22-3.78 2.44-7.51A.42.42 0 014.82 2a.43.43 0 01.58 0 .42.42 0 01.11.18l2.44 7.49h8.1l2.44-7.51A.42.42 0 0118.6 2a.43.43 0 01.58 0 .42.42 0 01.11.18l2.44 7.51L23 13.45a.84.84 0 01-.35.94z" />
      </svg>
    )
  }

  const connectedProviders = repositories.map((r) => r.provider)

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-medium">Repository Connections</h3>
          <p className="text-xs text-muted-foreground mt-0.5">
            Connect GitHub or GitLab to push and pull AI skill files
          </p>
        </div>
        {!showForm && (
          <Button size="sm" onClick={() => setShowForm(true)}>
            <Plus className="h-4 w-4 mr-1" />
            Connect Repository
          </Button>
        )}
      </div>

      {/* Scoped access notice */}
      <div className="flex items-start gap-2 px-3 py-2.5 bg-primary/5 border border-primary/10 text-xs">
        <ShieldCheck className="h-4 w-4 text-primary shrink-0 mt-0.5" />
        <div>
          <span className="font-medium">Scoped access:</span> Repository operations
          are limited to AI configuration files only &mdash;{' '}
          <span className="font-mono text-[10px]">
            {ALLOWED_PATHS.join(', ')}
          </span>
        </div>
      </div>

      {showForm && (
        <form
          onSubmit={handleConnect}
          className="border border-border p-4 space-y-3 bg-muted/30"
        >
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                Provider
              </label>
              <select
                value={formProvider}
                onChange={(e) => setFormProvider(e.target.value as 'github' | 'gitlab')}
                className="w-full border border-border bg-background px-3 py-2 text-sm"
              >
                <option value="github" disabled={connectedProviders.includes('github')}>
                  GitHub {connectedProviders.includes('github') && '(connected)'}
                </option>
                <option value="gitlab" disabled={connectedProviders.includes('gitlab')}>
                  GitLab {connectedProviders.includes('gitlab') && '(connected)'}
                </option>
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                Repository (owner/name)
              </label>
              <input
                type="text"
                value={formFullName}
                onChange={(e) => setFormFullName(e.target.value)}
                placeholder="eooo-io/orkestr"
                required
                className="w-full border border-border bg-background px-3 py-2 text-sm font-mono"
              />
            </div>
          </div>
          <div>
            <label className="text-xs font-medium text-muted-foreground mb-1 block">
              Access Token (PAT with repo scope)
            </label>
            <input
              type="password"
              value={formToken}
              onChange={(e) => setFormToken(e.target.value)}
              placeholder="ghp_... or glpat-..."
              className="w-full border border-border bg-background px-3 py-2 text-sm font-mono"
            />
            <p className="text-[10px] text-muted-foreground mt-1">
              Required for private repos and write operations. Token is encrypted at rest.
            </p>
          </div>
          <div className="flex items-center gap-6">
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input
                type="checkbox"
                checked={formAutoScan}
                onChange={(e) => setFormAutoScan(e.target.checked)}
                className="rounded border-border"
              />
              Auto-scan on push
            </label>
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input
                type="checkbox"
                checked={formAutoSync}
                onChange={(e) => setFormAutoSync(e.target.checked)}
                className="rounded border-border"
              />
              Auto-sync on push
            </label>
          </div>
          <div className="flex items-center gap-2 pt-1">
            <Button type="submit" size="sm" disabled={submitting}>
              {submitting ? (
                <Loader2 className="h-4 w-4 animate-spin mr-1" />
              ) : (
                <Check className="h-4 w-4 mr-1" />
              )}
              Connect
            </Button>
            <Button type="button" variant="ghost" size="sm" onClick={resetForm}>
              <X className="h-4 w-4 mr-1" />
              Cancel
            </Button>
          </div>
        </form>
      )}

      {repositories.length === 0 && !showForm ? (
        <div className="text-center py-12 text-muted-foreground">
          <GitBranch className="h-8 w-8 mx-auto mb-2 opacity-40" />
          <p className="text-sm">No repositories connected.</p>
          <p className="text-xs mt-1">
            Connect a GitHub or GitLab repo to sync AI skills remotely.
          </p>
        </div>
      ) : (
        <div className="space-y-2">
          {repositories.map((repo) => (
            <div key={repo.id} className="border border-border overflow-hidden">
              {/* Header row */}
              <div className="flex items-center justify-between px-4 py-3">
                <div className="flex items-center gap-3 min-w-0">
                  <span className="text-muted-foreground">
                    {providerIcon(repo.provider)}
                  </span>
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium truncate">
                        {repo.full_name}
                      </span>
                      <a
                        href={repo.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-muted-foreground hover:text-foreground"
                      >
                        <ExternalLink className="h-3 w-3" />
                      </a>
                    </div>
                    <div className="flex items-center gap-3 text-xs text-muted-foreground mt-0.5">
                      <span className="flex items-center gap-1">
                        <GitBranch className="h-3 w-3" />
                        {repo.default_branch}
                      </span>
                      {repo.has_access_token && (
                        <span className="flex items-center gap-1 text-green-500">
                          <ShieldCheck className="h-3 w-3" />
                          Authenticated
                        </span>
                      )}
                      {repo.last_synced_at && (
                        <span>
                          Last synced {new Date(repo.last_synced_at).toLocaleDateString()}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-1.5 ml-3 shrink-0">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => checkStatus(repo.provider)}
                    disabled={loadingStatus === repo.provider}
                    title="Check connection status"
                  >
                    {loadingStatus === repo.provider ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <RefreshCw className="h-3.5 w-3.5" />
                    )}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => loadFiles(repo.provider)}
                    title="View AI files in repo"
                  >
                    <FolderTree className="h-3.5 w-3.5" />
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handleDisconnect(repo.provider)}
                    title="Disconnect repository"
                  >
                    <Trash2 className="h-3.5 w-3.5 text-red-500" />
                  </Button>
                </div>
              </div>

              {/* Settings row */}
              <div className="flex items-center gap-4 px-4 py-2 border-t border-border bg-muted/20 text-xs">
                <label className="flex items-center gap-1.5 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={repo.auto_scan_on_push}
                    onChange={() => handleToggleSetting(repo, 'auto_scan_on_push')}
                    className="rounded border-border"
                  />
                  <span className="text-muted-foreground">Auto-scan on push</span>
                </label>
                <label className="flex items-center gap-1.5 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={repo.auto_sync_on_push}
                    onChange={() => handleToggleSetting(repo, 'auto_sync_on_push')}
                    className="rounded border-border"
                  />
                  <span className="text-muted-foreground">Auto-sync on push</span>
                </label>
              </div>

              {/* Status display */}
              {statusMap[repo.provider] && (
                <div className="px-4 py-2 border-t border-border bg-muted/10 text-xs">
                  {statusMap[repo.provider].accessible ? (
                    <div className="flex items-center gap-2 text-green-500">
                      <Check className="h-3 w-3" />
                      Connected &amp; accessible
                      {statusMap[repo.provider].visibility && (
                        <span className="text-muted-foreground">
                          ({statusMap[repo.provider].visibility})
                        </span>
                      )}
                    </div>
                  ) : (
                    <div className="flex items-center gap-2 text-amber-500">
                      <AlertCircle className="h-3 w-3" />
                      {statusMap[repo.provider].reason}
                    </div>
                  )}
                </div>
              )}

              {/* File browser */}
              {expandedProvider === repo.provider && (
                <div className="border-t border-border bg-muted/20 px-4 py-3">
                  <h4 className="text-xs font-medium text-muted-foreground mb-2">
                    AI Config Files in Repository
                  </h4>
                  {loadingFiles === repo.provider ? (
                    <div className="flex items-center justify-center py-4">
                      <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                    </div>
                  ) : !filesMap[repo.provider]?.length ? (
                    <p className="text-xs text-muted-foreground py-2">
                      No AI configuration files found in this repository.
                    </p>
                  ) : (
                    <div className="space-y-1">
                      {filesMap[repo.provider].map((f) => (
                        <div
                          key={f.path}
                          className="flex items-center justify-between text-xs px-3 py-1.5 bg-background border border-border"
                        >
                          <span className="font-mono text-muted-foreground">
                            {f.path}
                          </span>
                          {f.size !== null && (
                            <span className="text-muted-foreground">
                              {f.size > 1024
                                ? `${(f.size / 1024).toFixed(1)} KB`
                                : `${f.size} B`}
                            </span>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
