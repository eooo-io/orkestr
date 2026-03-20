import { useState, useEffect, useCallback } from 'react'
import {
  Shield,
  ShieldCheck,
  ShieldAlert,
  Plus,
  Trash2,
  Loader2,
  AlertTriangle,
  CheckCircle,
  XCircle,
  Filter,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  fetchGuardrailPolicies,
  createGuardrailPolicy,
  updateGuardrailPolicy,
  deleteGuardrailPolicy,
  fetchGuardrailProfiles,
  createGuardrailProfile,
  deleteGuardrailProfile,
  fetchGuardrailViolations,
  dismissGuardrailViolation,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import type {
  GuardrailPolicy,
  GuardrailProfile,
  GuardrailViolation,
} from '@/types'

// TODO: get from org context
const ORG_ID = 1

type Tab = 'policies' | 'profiles' | 'violations'

const SEVERITY_COLORS: Record<string, string> = {
  low: 'bg-blue-500/15 text-blue-400',
  medium: 'bg-yellow-500/15 text-yellow-400',
  high: 'bg-orange-500/15 text-orange-400',
  critical: 'bg-red-500/15 text-red-400',
}

// ─── Policies Tab ─────────────────────────────────────────────

function PoliciesTab() {
  const confirm = useConfirm()
  const [policies, setPolicies] = useState<GuardrailPolicy[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [saving, setSaving] = useState(false)

  const [form, setForm] = useState({
    name: '',
    description: '',
    scope: 'org',
    priority: 100,
    approval_level: 'none',
  })

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchGuardrailPolicies(ORG_ID)
      setPolicies(Array.isArray(data) ? data : [])
    } catch (err) {
      console.error('Failed to load policies', err)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])

  const resetForm = () => {
    setForm({ name: '', description: '', scope: 'org', priority: 100, approval_level: 'none' })
    setEditingId(null)
    setShowForm(false)
  }

  const startEdit = (p: GuardrailPolicy) => {
    setForm({
      name: p.name,
      description: p.description || '',
      scope: p.scope,
      priority: p.priority,
      approval_level: p.approval_level || 'none',
    })
    setEditingId(p.id)
    setShowForm(true)
  }

  const handleSubmit = async () => {
    setSaving(true)
    try {
      if (editingId) {
        await updateGuardrailPolicy(editingId, form)
      } else {
        await createGuardrailPolicy(ORG_ID, form)
      }
      resetForm()
      await load()
    } catch (err) {
      console.error('Failed to save policy', err)
    } finally {
      setSaving(false)
    }
  }

  const handleToggleActive = async (p: GuardrailPolicy) => {
    try {
      await updateGuardrailPolicy(p.id, { is_active: !p.is_active })
      await load()
    } catch (err) {
      console.error('Failed to toggle policy', err)
    }
  }

  const handleDelete = async (id: number) => {
    if (!(await confirm({ message: 'Delete this policy?', title: 'Confirm Delete' }))) return
    try {
      await deleteGuardrailPolicy(id)
      await load()
    } catch (err) {
      console.error('Failed to delete policy', err)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Guardrail Policies</h2>
        <Button size="sm" onClick={() => { resetForm(); setShowForm(true) }}>
          <Plus className="h-4 w-4 mr-1" /> New Policy
        </Button>
      </div>

      {showForm && (
        <div className="bg-card elevation-1 p-4 space-y-3">
          <h3 className="text-sm font-medium">{editingId ? 'Edit Policy' : 'Create Policy'}</h3>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">Name</label>
              <input
                className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                placeholder="Policy name"
              />
            </div>
            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">Scope</label>
              <select
                className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                value={form.scope}
                onChange={(e) => setForm({ ...form, scope: e.target.value })}
              >
                <option value="org">Organization</option>
                <option value="project">Project</option>
                <option value="agent">Agent</option>
              </select>
            </div>
            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">Priority</label>
              <input
                type="number"
                className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                value={form.priority}
                onChange={(e) => setForm({ ...form, priority: Number(e.target.value) })}
              />
            </div>
            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">Approval Level</label>
              <select
                className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                value={form.approval_level}
                onChange={(e) => setForm({ ...form, approval_level: e.target.value })}
              >
                <option value="none">None</option>
                <option value="auto">Auto</option>
                <option value="manual">Manual</option>
              </select>
            </div>
            <div className="col-span-2 space-y-1">
              <label className="text-xs text-muted-foreground">Description</label>
              <textarea
                className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring resize-none"
                rows={2}
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                placeholder="What does this policy enforce?"
              />
            </div>
          </div>
          <div className="flex gap-2 justify-end">
            <Button variant="ghost" size="sm" onClick={resetForm}>Cancel</Button>
            <Button size="sm" onClick={handleSubmit} disabled={saving || !form.name.trim()}>
              {saving && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
              {editingId ? 'Update' : 'Create'}
            </Button>
          </div>
        </div>
      )}

      {policies.length === 0 ? (
        <div className="bg-card elevation-1 p-8 text-center text-muted-foreground">
          <Shield className="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p className="text-sm">No policies defined yet.</p>
        </div>
      ) : (
        <div className="space-y-2">
          {policies.map((p) => (
            <div key={p.id} className="bg-card elevation-1 p-4 flex items-center justify-between gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-sm truncate">{p.name}</span>
                  <span className="text-xs px-1.5 py-0.5 bg-muted text-muted-foreground rounded">{p.scope}</span>
                  <span className="text-xs text-muted-foreground">Priority: {p.priority}</span>
                </div>
                {p.description && (
                  <p className="text-xs text-muted-foreground mt-1 truncate">{p.description}</p>
                )}
              </div>
              <div className="flex items-center gap-2 shrink-0">
                <button
                  onClick={() => handleToggleActive(p)}
                  className={`text-xs px-2 py-1 rounded transition-colors ${
                    p.is_active
                      ? 'bg-green-500/15 text-green-400 hover:bg-green-500/25'
                      : 'bg-muted text-muted-foreground hover:bg-muted/80'
                  }`}
                >
                  {p.is_active ? 'Active' : 'Inactive'}
                </button>
                <Button variant="ghost" size="sm" onClick={() => startEdit(p)}>Edit</Button>
                <Button variant="ghost" size="sm" onClick={() => handleDelete(p.id)}>
                  <Trash2 className="h-4 w-4 text-destructive" />
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

// ─── Profiles Tab ─────────────────────────────────────────────

function ProfilesTab() {
  const confirm = useConfirm()
  const [profiles, setProfiles] = useState<GuardrailProfile[]>([])
  const [loading, setLoading] = useState(true)
  const [showForm, setShowForm] = useState(false)
  const [saving, setSaving] = useState(false)

  const [form, setForm] = useState({ name: '', description: '' })

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const data = await fetchGuardrailProfiles()
      setProfiles(Array.isArray(data) ? data : [])
    } catch (err) {
      console.error('Failed to load profiles', err)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])

  const handleCreate = async () => {
    setSaving(true)
    try {
      await createGuardrailProfile(form)
      setForm({ name: '', description: '' })
      setShowForm(false)
      await load()
    } catch (err) {
      console.error('Failed to create profile', err)
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (id: number) => {
    if (!(await confirm({ message: 'Delete this profile?', title: 'Confirm Delete' }))) return
    try {
      await deleteGuardrailProfile(id)
      await load()
    } catch (err) {
      console.error('Failed to delete profile', err)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Guardrail Profiles</h2>
        <Button size="sm" onClick={() => setShowForm(true)}>
          <Plus className="h-4 w-4 mr-1" /> Custom Profile
        </Button>
      </div>

      {showForm && (
        <div className="bg-card elevation-1 p-4 space-y-3">
          <h3 className="text-sm font-medium">Create Custom Profile</h3>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">Name</label>
              <input
                className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                placeholder="Profile name"
              />
            </div>
            <div className="space-y-1">
              <label className="text-xs text-muted-foreground">Description</label>
              <input
                className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                placeholder="What this profile enforces"
              />
            </div>
          </div>
          <div className="flex gap-2 justify-end">
            <Button variant="ghost" size="sm" onClick={() => setShowForm(false)}>Cancel</Button>
            <Button size="sm" onClick={handleCreate} disabled={saving || !form.name.trim()}>
              {saving && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
              Create
            </Button>
          </div>
        </div>
      )}

      {profiles.length === 0 ? (
        <div className="bg-card elevation-1 p-8 text-center text-muted-foreground">
          <ShieldCheck className="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p className="text-sm">No profiles found.</p>
        </div>
      ) : (
        <div className="space-y-2">
          {profiles.map((p) => (
            <div key={p.id} className="bg-card elevation-1 p-4 flex items-center justify-between gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-sm">{p.name}</span>
                  <span className="text-xs text-muted-foreground font-mono">{p.slug}</span>
                  {p.is_system && (
                    <span className="text-xs px-1.5 py-0.5 bg-purple-500/15 text-purple-400 rounded">System</span>
                  )}
                </div>
                {p.description && (
                  <p className="text-xs text-muted-foreground mt-1 truncate">{p.description}</p>
                )}
              </div>
              <div className="shrink-0">
                {!p.is_system && (
                  <Button variant="ghost" size="sm" onClick={() => handleDelete(p.id)}>
                    <Trash2 className="h-4 w-4 text-destructive" />
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

// ─── Violations Tab ───────────────────────────────────────────

function ViolationsTab() {
  const [violations, setViolations] = useState<GuardrailViolation[]>([])
  const [loading, setLoading] = useState(true)
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [severityFilter, setSeverityFilter] = useState('')
  const [guardTypeFilter, setGuardTypeFilter] = useState('')
  const [dismissingId, setDismissingId] = useState<number | null>(null)
  const [dismissReason, setDismissReason] = useState('')

  const load = useCallback(async () => {
    setLoading(true)
    try {
      const params: Record<string, string | number> = { page }
      if (severityFilter) params.severity = severityFilter
      if (guardTypeFilter) params.guard_type = guardTypeFilter
      const result = await fetchGuardrailViolations(ORG_ID, params)
      setViolations(Array.isArray(result?.data) ? result.data : [])
      setLastPage(result.last_page)
      setTotal(result.total)
    } catch (err) {
      console.error('Failed to load violations', err)
    } finally {
      setLoading(false)
    }
  }, [page, severityFilter, guardTypeFilter])

  useEffect(() => { load() }, [load])

  const handleDismiss = async (id: number) => {
    if (!dismissReason.trim()) return
    try {
      await dismissGuardrailViolation(id, dismissReason)
      setDismissingId(null)
      setDismissReason('')
      await load()
    } catch (err) {
      console.error('Failed to dismiss violation', err)
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Violations ({total})</h2>
        <div className="flex items-center gap-2">
          <Filter className="h-4 w-4 text-muted-foreground" />
          <select
            className="px-2 py-1 text-xs border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            value={severityFilter}
            onChange={(e) => { setSeverityFilter(e.target.value); setPage(1) }}
          >
            <option value="">All Severities</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
          <select
            className="px-2 py-1 text-xs border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            value={guardTypeFilter}
            onChange={(e) => { setGuardTypeFilter(e.target.value); setPage(1) }}
          >
            <option value="">All Guard Types</option>
            <option value="budget">Budget</option>
            <option value="tool">Tool</option>
            <option value="output">Output</option>
            <option value="access">Access</option>
            <option value="content">Content</option>
            <option value="network">Network</option>
          </select>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        </div>
      ) : violations.length === 0 ? (
        <div className="bg-card elevation-1 p-8 text-center text-muted-foreground">
          <CheckCircle className="h-8 w-8 mx-auto mb-2 opacity-50" />
          <p className="text-sm">No violations found.</p>
        </div>
      ) : (
        <>
          <div className="space-y-2">
            {violations.map((v) => (
              <div key={v.id} className="bg-card elevation-1 p-4 space-y-2">
                <div className="flex items-center justify-between gap-4">
                  <div className="flex items-center gap-2 min-w-0">
                    {v.severity === 'critical' ? (
                      <XCircle className="h-4 w-4 text-red-400 shrink-0" />
                    ) : v.severity === 'high' ? (
                      <AlertTriangle className="h-4 w-4 text-orange-400 shrink-0" />
                    ) : (
                      <ShieldAlert className="h-4 w-4 text-muted-foreground shrink-0" />
                    )}
                    <span className="font-medium text-sm truncate">{v.rule_name}</span>
                    <span className={`text-xs px-1.5 py-0.5 rounded ${SEVERITY_COLORS[v.severity] || ''}`}>
                      {v.severity}
                    </span>
                    <span className="text-xs px-1.5 py-0.5 bg-muted text-muted-foreground rounded">
                      {v.guard_type}
                    </span>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    {v.dismissed_at ? (
                      <span className="text-xs text-muted-foreground">Dismissed</span>
                    ) : dismissingId === v.id ? (
                      <div className="flex items-center gap-1">
                        <input
                          className="px-2 py-1 text-xs border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring w-40"
                          value={dismissReason}
                          onChange={(e) => setDismissReason(e.target.value)}
                          placeholder="Reason..."
                          onKeyDown={(e) => e.key === 'Enter' && handleDismiss(v.id)}
                        />
                        <Button size="sm" variant="ghost" onClick={() => handleDismiss(v.id)} disabled={!dismissReason.trim()}>
                          <CheckCircle className="h-3.5 w-3.5" />
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => { setDismissingId(null); setDismissReason('') }}>
                          <XCircle className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    ) : (
                      <Button size="sm" variant="ghost" onClick={() => setDismissingId(v.id)}>
                        Dismiss
                      </Button>
                    )}
                  </div>
                </div>
                <p className="text-xs text-muted-foreground">{v.message}</p>
                <div className="flex items-center gap-3 text-xs text-muted-foreground">
                  <span>Action: {v.action_taken}</span>
                  <span>{new Date(v.created_at).toLocaleString()}</span>
                </div>
              </div>
            ))}
          </div>

          {lastPage > 1 && (
            <div className="flex items-center justify-center gap-2 pt-2">
              <Button
                variant="ghost"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage(page - 1)}
              >
                Previous
              </Button>
              <span className="text-xs text-muted-foreground">
                Page {page} of {lastPage}
              </span>
              <Button
                variant="ghost"
                size="sm"
                disabled={page >= lastPage}
                onClick={() => setPage(page + 1)}
              >
                Next
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  )
}

// ─── Main Page ────────────────────────────────────────────────

export function Guardrails() {
  const [activeTab, setActiveTab] = useState<Tab>('policies')

  const tabs: { key: Tab; label: string; icon: React.ReactNode }[] = [
    { key: 'policies', label: 'Policies', icon: <Shield className="h-4 w-4" /> },
    { key: 'profiles', label: 'Profiles', icon: <ShieldCheck className="h-4 w-4" /> },
    { key: 'violations', label: 'Violations', icon: <ShieldAlert className="h-4 w-4" /> },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Guardrails</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Manage safety policies, enforcement profiles, and review violations.
        </p>
      </div>

      <div className="flex items-center gap-1 border-b border-border">
        {tabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setActiveTab(t.key)}
            className={`flex items-center gap-1.5 px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              activeTab === t.key
                ? 'border-primary text-foreground'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            {t.icon}
            {t.label}
          </button>
        ))}
      </div>

      {activeTab === 'policies' && <PoliciesTab />}
      {activeTab === 'profiles' && <ProfilesTab />}
      {activeTab === 'violations' && <ViolationsTab />}
    </div>
  )
}
