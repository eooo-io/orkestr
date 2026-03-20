import { useState, useEffect, useCallback } from 'react'
import { Power, AlertTriangle, CheckCircle, ChevronDown } from 'lucide-react'
import { fetchProjects, mobileEmergencyKill } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'

interface Project {
  id: number
  name: string
}

interface KillResult {
  killed_count: number
  processes: Array<{
    id: number
    uuid: string
    agent_name: string
    previous_status: string
  }>
}

export function KillSwitch() {
  const [projects, setProjects] = useState<Project[]>([])
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null)
  const [showConfirm, setShowConfirm] = useState(false)
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState<KillResult | null>(null)
  const [dropdownOpen, setDropdownOpen] = useState(false)
  const { showToast } = useAppStore()

  useEffect(() => {
    fetchProjects().then((data) => {
      const list = Array.isArray(data) ? data : data?.data ?? []
      setProjects(list)
      if (list.length === 1) {
        setSelectedProjectId(list[0].id)
      }
    })
  }, [])

  const selectedProject = projects.find((p) => p.id === selectedProjectId)

  const handleKill = useCallback(async () => {
    if (!selectedProjectId) return
    setLoading(true)
    setShowConfirm(false)
    try {
      const res = await mobileEmergencyKill(selectedProjectId)
      const data = res?.data ?? res
      setResult(data)
      if (data.killed_count > 0) {
        showToast(`Killed ${data.killed_count} agent process(es)`)
      } else {
        showToast('No running agents found')
      }
    } catch (err: unknown) {
      const msg =
        err instanceof Error ? err.message : 'Emergency kill failed'
      showToast(msg)
    } finally {
      setLoading(false)
    }
  }, [selectedProjectId, showToast])

  const resetResult = () => {
    setResult(null)
  }

  return (
    <div className="flex flex-col items-center justify-center min-h-[60vh] px-4 py-8 space-y-6">
      <div className="text-center space-y-2">
        <Power className="h-10 w-10 text-red-500 mx-auto" />
        <h1 className="text-xl font-bold">Emergency Kill Switch</h1>
        <p className="text-sm text-muted-foreground max-w-xs mx-auto">
          Immediately stop all running agent processes for a project.
        </p>
      </div>

      {/* Project selector */}
      <div className="w-full max-w-xs relative">
        <button
          onClick={() => setDropdownOpen(!dropdownOpen)}
          className="w-full flex items-center justify-between rounded-lg border border-border bg-card px-4 py-3 text-sm font-medium transition-colors hover:bg-muted"
        >
          <span className={selectedProject ? '' : 'text-muted-foreground'}>
            {selectedProject?.name ?? 'Select a project...'}
          </span>
          <ChevronDown
            className={`h-4 w-4 text-muted-foreground transition-transform ${
              dropdownOpen ? 'rotate-180' : ''
            }`}
          />
        </button>

        {dropdownOpen && (
          <div className="absolute top-full left-0 right-0 z-10 mt-1 rounded-lg border border-border bg-card shadow-lg max-h-60 overflow-y-auto">
            {projects.length === 0 ? (
              <div className="px-4 py-3 text-sm text-muted-foreground">
                No projects found
              </div>
            ) : (
              projects.map((project) => (
                <button
                  key={project.id}
                  onClick={() => {
                    setSelectedProjectId(project.id)
                    setDropdownOpen(false)
                    resetResult()
                  }}
                  className={`w-full text-left px-4 py-2.5 text-sm transition-colors hover:bg-muted ${
                    project.id === selectedProjectId
                      ? 'bg-primary/10 text-primary font-medium'
                      : ''
                  }`}
                >
                  {project.name}
                </button>
              ))
            )}
          </div>
        )}
      </div>

      {/* Kill button */}
      {!result && (
        <>
          {!showConfirm ? (
            <button
              onClick={() => setShowConfirm(true)}
              disabled={!selectedProjectId || loading}
              className="w-full max-w-xs rounded-2xl bg-red-600 px-6 py-5 text-lg font-bold text-white shadow-lg transition-all hover:bg-red-700 active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              {loading ? 'Stopping...' : 'Kill All Agents'}
            </button>
          ) : (
            <div className="w-full max-w-xs space-y-3">
              <div className="rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-center space-y-2">
                <AlertTriangle className="h-6 w-6 text-red-500 mx-auto" />
                <p className="text-sm font-medium">
                  Stop all agents in{' '}
                  <span className="font-bold">{selectedProject?.name}</span>?
                </p>
                <p className="text-xs text-muted-foreground">
                  This will immediately terminate all running agent processes.
                  This action cannot be undone.
                </p>
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setShowConfirm(false)}
                  className="flex-1 rounded-lg border border-border px-4 py-3 text-sm font-medium transition-colors hover:bg-muted"
                >
                  Cancel
                </button>
                <button
                  onClick={handleKill}
                  disabled={loading}
                  className="flex-1 rounded-lg bg-red-600 px-4 py-3 text-sm font-bold text-white transition-colors hover:bg-red-700 active:bg-red-800 disabled:opacity-50"
                >
                  {loading ? 'Stopping...' : 'Confirm Kill'}
                </button>
              </div>
            </div>
          )}
        </>
      )}

      {/* Result */}
      {result && (
        <div className="w-full max-w-xs space-y-3">
          <div className="rounded-xl border border-border bg-card p-4 text-center space-y-2">
            <CheckCircle className="h-8 w-8 text-green-500 mx-auto" />
            <p className="text-sm font-semibold">
              {result.killed_count > 0
                ? `${result.killed_count} agent${result.killed_count !== 1 ? 's' : ''} stopped`
                : 'No running agents found'}
            </p>
            {result.processes.length > 0 && (
              <ul className="text-xs text-muted-foreground space-y-1 mt-2">
                {result.processes.map((p) => (
                  <li key={p.id} className="flex items-center justify-between">
                    <span className="truncate">{p.agent_name}</span>
                    <span className="text-red-400 shrink-0 ml-2">stopped</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
          <button
            onClick={resetResult}
            className="w-full rounded-lg border border-border px-4 py-3 text-sm font-medium transition-colors hover:bg-muted"
          >
            Done
          </button>
        </div>
      )}
    </div>
  )
}
