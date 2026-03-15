import { useState, useEffect } from 'react'
import {
  Github,
  Search,
  Download,
  Loader2,
  CheckCircle,
  AlertTriangle,
  ArrowRight,
  ArrowLeft,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  fetchProjects,
  discoverGitHubOrg,
  importFromGitHub,
} from '@/api/client'
import type {
  Project,
  GitHubDiscoveredRepo,
  GitHubImportResult,
} from '@/types'

type Step = 1 | 2 | 3 | 4

export function GitHubImport() {
  const [step, setStep] = useState<Step>(1)

  // Step 1
  const [org, setOrg] = useState('')
  const [token, setToken] = useState('')

  // Step 2
  const [repos, setRepos] = useState<GitHubDiscoveredRepo[]>([])
  const [discovering, setDiscovering] = useState(false)
  const [discoverError, setDiscoverError] = useState<string | null>(null)

  // Step 3
  const [selectedRepos, setSelectedRepos] = useState<Set<string>>(new Set())
  const [projects, setProjects] = useState<Project[]>([])
  const [targetProjectId, setTargetProjectId] = useState<number | null>(null)
  const [importing, setImporting] = useState(false)

  // Step 4
  const [result, setResult] = useState<GitHubImportResult | null>(null)

  useEffect(() => {
    fetchProjects()
      .then(setProjects)
      .catch(() => setProjects([]))
  }, [])

  const handleDiscover = () => {
    if (!org.trim()) return
    setDiscovering(true)
    setDiscoverError(null)
    discoverGitHubOrg(org.trim(), token.trim() || undefined)
      .then((discovered) => {
        setRepos(discovered)
        setSelectedRepos(new Set(discovered.map((r) => r.full_name)))
        setStep(2)
      })
      .catch((err) => {
        setDiscoverError(
          err.response?.data?.message || 'Failed to discover repositories',
        )
      })
      .finally(() => setDiscovering(false))
  }

  const toggleRepo = (fullName: string) => {
    setSelectedRepos((prev) => {
      const next = new Set(prev)
      if (next.has(fullName)) next.delete(fullName)
      else next.add(fullName)
      return next
    })
  }

  const handleImport = () => {
    if (!targetProjectId || selectedRepos.size === 0) return
    setImporting(true)
    importFromGitHub({
      org: org.trim(),
      repos: Array.from(selectedRepos),
      project_id: targetProjectId,
      token: token.trim() || undefined,
    })
      .then((res) => {
        setResult(res)
        setStep(4)
      })
      .catch(() => {})
      .finally(() => setImporting(false))
  }

  const reset = () => {
    setStep(1)
    setOrg('')
    setToken('')
    setRepos([])
    setSelectedRepos(new Set())
    setTargetProjectId(null)
    setResult(null)
    setDiscoverError(null)
  }

  return (
    <div className="max-w-3xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-lg font-semibold flex items-center gap-2">
          <Github className="h-5 w-5 text-primary" />
          GitHub Import
        </h1>
        <p className="text-sm text-muted-foreground">
          Import skills from GitHub organization repositories
        </p>
      </div>

      {/* Steps indicator */}
      <div className="flex items-center gap-2 text-xs text-muted-foreground">
        {[1, 2, 3, 4].map((s) => (
          <div key={s} className="flex items-center gap-2">
            <span
              className={`inline-flex items-center justify-center h-6 w-6 text-xs font-medium border ${
                step >= s
                  ? 'bg-primary text-primary-foreground border-primary'
                  : 'border-border'
              }`}
            >
              {s}
            </span>
            {s < 4 && <span className="w-6 border-t border-border" />}
          </div>
        ))}
      </div>

      {/* Step 1: Enter org */}
      {step === 1 && (
        <div className="bg-card elevation-1 border border-border p-6 space-y-4">
          <h2 className="text-sm font-medium">Organization Details</h2>
          <div className="space-y-3">
            <div>
              <label className="text-xs text-muted-foreground block mb-1">
                GitHub Organization
              </label>
              <input
                type="text"
                value={org}
                onChange={(e) => setOrg(e.target.value)}
                placeholder="e.g. my-org"
                className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
              />
            </div>
            <div>
              <label className="text-xs text-muted-foreground block mb-1">
                Access Token (optional)
              </label>
              <input
                type="password"
                value={token}
                onChange={(e) => setToken(e.target.value)}
                placeholder="ghp_..."
                className="w-full px-3 py-2 bg-background border border-border text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary"
              />
              <p className="text-xs text-muted-foreground mt-1">
                Required for private repositories
              </p>
            </div>
          </div>
          {discoverError && (
            <div className="flex items-center gap-2 text-sm text-red-400">
              <AlertTriangle className="h-4 w-4 shrink-0" />
              {discoverError}
            </div>
          )}
          <div className="flex justify-end">
            <Button
              size="sm"
              onClick={handleDiscover}
              disabled={!org.trim() || discovering}
            >
              {discovering ? (
                <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
              ) : (
                <Search className="h-4 w-4 mr-1.5" />
              )}
              Discover Repos
            </Button>
          </div>
        </div>
      )}

      {/* Step 2: Show discovered repos */}
      {step === 2 && (
        <div className="bg-card elevation-1 border border-border p-6 space-y-4">
          <h2 className="text-sm font-medium">
            Discovered Repositories ({repos.length})
          </h2>
          {repos.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No repositories with skills found in this organization.
            </p>
          ) : (
            <div className="space-y-2 max-h-80 overflow-y-auto">
              {repos.map((repo) => (
                <label
                  key={repo.full_name}
                  className="flex items-center gap-3 px-3 py-2 border border-border hover:bg-muted/30 transition-colors cursor-pointer"
                >
                  <input
                    type="checkbox"
                    checked={selectedRepos.has(repo.full_name)}
                    onChange={() => toggleRepo(repo.full_name)}
                    className="shrink-0"
                  />
                  <div className="flex-1 min-w-0">
                    <span className="text-sm font-mono">{repo.full_name}</span>
                    <span className="text-xs text-muted-foreground ml-2">
                      {repo.skills_count} skill{repo.skills_count !== 1 ? 's' : ''}
                    </span>
                  </div>
                </label>
              ))}
            </div>
          )}
          <div className="flex justify-between">
            <Button variant="outline" size="sm" onClick={() => setStep(1)}>
              <ArrowLeft className="h-4 w-4 mr-1.5" />
              Back
            </Button>
            <Button
              size="sm"
              onClick={() => setStep(3)}
              disabled={selectedRepos.size === 0}
            >
              Continue
              <ArrowRight className="h-4 w-4 ml-1.5" />
            </Button>
          </div>
        </div>
      )}

      {/* Step 3: Select target project and import */}
      {step === 3 && (
        <div className="bg-card elevation-1 border border-border p-6 space-y-4">
          <h2 className="text-sm font-medium">Import Settings</h2>
          <div>
            <label className="text-xs text-muted-foreground block mb-1">
              Target Project
            </label>
            <select
              value={targetProjectId ?? ''}
              onChange={(e) =>
                setTargetProjectId(e.target.value ? Number(e.target.value) : null)
              }
              className="w-full px-3 py-2 bg-background border border-border text-sm focus:outline-none focus:ring-1 focus:ring-primary"
            >
              <option value="">Select a project...</option>
              {projects.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </div>
          <div className="text-sm text-muted-foreground">
            Importing from{' '}
            <span className="font-medium text-foreground">
              {selectedRepos.size}
            </span>{' '}
            repository{selectedRepos.size !== 1 ? 'ies' : ''}
          </div>
          <div className="flex justify-between">
            <Button variant="outline" size="sm" onClick={() => setStep(2)}>
              <ArrowLeft className="h-4 w-4 mr-1.5" />
              Back
            </Button>
            <Button
              size="sm"
              onClick={handleImport}
              disabled={!targetProjectId || importing}
            >
              {importing ? (
                <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
              ) : (
                <Download className="h-4 w-4 mr-1.5" />
              )}
              Import Skills
            </Button>
          </div>
        </div>
      )}

      {/* Step 4: Results */}
      {step === 4 && result && (
        <div className="bg-card elevation-1 border border-border p-6 space-y-4">
          <h2 className="text-sm font-medium flex items-center gap-2">
            <CheckCircle className="h-5 w-5 text-emerald-400" />
            Import Complete
          </h2>
          <div className="grid grid-cols-3 gap-4 text-center">
            <div className="p-4 border border-border">
              <div className="text-2xl font-semibold text-emerald-400">
                {result.imported}
              </div>
              <div className="text-xs text-muted-foreground mt-1">Imported</div>
            </div>
            <div className="p-4 border border-border">
              <div className="text-2xl font-semibold text-muted-foreground">
                {result.skipped}
              </div>
              <div className="text-xs text-muted-foreground mt-1">Skipped</div>
            </div>
            <div className="p-4 border border-border">
              <div className="text-2xl font-semibold text-red-400">
                {result.errors.length}
              </div>
              <div className="text-xs text-muted-foreground mt-1">Errors</div>
            </div>
          </div>
          {result.errors.length > 0 && (
            <div className="space-y-1">
              {result.errors.map((err, i) => (
                <div
                  key={i}
                  className="flex items-start gap-2 text-sm text-red-400"
                >
                  <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                  {err}
                </div>
              ))}
            </div>
          )}
          <div className="flex justify-end">
            <Button variant="outline" size="sm" onClick={reset}>
              Import More
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
