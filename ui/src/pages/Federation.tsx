import { useEffect, useState, useCallback } from 'react'
import {
  Globe,
  Plus,
  RefreshCw,
  Trash2,
  HeartPulse,
  Network,
  ArrowUpRight,
  ArrowDownLeft,
  Link2,
  Unlink,
  Shield,
  ShieldCheck,
  ShieldAlert,
  ShieldOff,
  CheckCircle,
  Clock,
  XCircle,
  AlertTriangle,
  X,
  ChevronDown,
  ChevronRight,
  User,
  Zap,
} from 'lucide-react'
import api from '@/api/client'
import { useAppStore } from '@/store/useAppStore'

// --- Types ---

interface FederationPeer {
  id: number
  uuid: string
  name: string
  base_url: string
  status: 'pending' | 'active' | 'suspended' | 'revoked'
  capabilities: Record<string, unknown> | null
  last_heartbeat_at: string | null
  last_sync_at: string | null
  trust_level: 'untrusted' | 'basic' | 'verified' | 'full'
  metadata: Record<string, unknown> | null
  delegations_count: number
  created_at: string | null
}

interface FederationDelegation {
  id: number
  uuid: string
  peer: { id: number; name: string; base_url: string } | null
  local_agent: { id: number; name: string; slug: string } | null
  remote_agent_slug: string
  direction: 'outbound' | 'inbound'
  status: 'pending' | 'active' | 'completed' | 'failed'
  input: Record<string, unknown> | null
  output: Record<string, unknown> | null
  cost_microcents: number
  duration_ms: number
  created_at: string | null
  completed_at: string | null
}

interface FederatedIdentity {
  id: number
  user_id: number
  peer: { id: number; name: string; base_url: string; status: string } | null
  remote_user_id: string
  remote_email: string | null
  remote_role: string | null
  verified_at: string | null
  created_at: string | null
}

type Tab = 'peers' | 'delegations' | 'identities'

// --- Status helpers ---

const PEER_STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
  active: 'bg-green-500/10 text-green-500 border-green-500/20',
  suspended: 'bg-orange-500/10 text-orange-500 border-orange-500/20',
  revoked: 'bg-red-500/10 text-red-500 border-red-500/20',
}

const TRUST_CONFIG: Record<string, { color: string; icon: React.ReactNode; label: string }> = {
  untrusted: { color: 'text-red-500', icon: <ShieldOff className="h-3.5 w-3.5" />, label: 'Untrusted' },
  basic: { color: 'text-yellow-500', icon: <Shield className="h-3.5 w-3.5" />, label: 'Basic' },
  verified: { color: 'text-blue-500', icon: <ShieldCheck className="h-3.5 w-3.5" />, label: 'Verified' },
  full: { color: 'text-green-500', icon: <ShieldAlert className="h-3.5 w-3.5" />, label: 'Full' },
}

const DELEGATION_STATUS_COLORS: Record<string, string> = {
  pending: 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20',
  active: 'bg-blue-500/10 text-blue-500 border-blue-500/20',
  completed: 'bg-green-500/10 text-green-500 border-green-500/20',
  failed: 'bg-red-500/10 text-red-500 border-red-500/20',
}

function timeAgo(iso: string | null): string {
  if (!iso) return 'Never'
  const diff = Date.now() - new Date(iso).getTime()
  const seconds = Math.floor(diff / 1000)
  if (seconds < 60) return `${seconds}s ago`
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  return `${days}d ago`
}

function formatCost(microcents: number): string {
  const dollars = microcents / 100_000_000
  if (dollars < 0.01) return '<$0.01'
  return `$${dollars.toFixed(2)}`
}

function formatDuration(ms: number): string {
  if (ms < 1000) return `${ms}ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`
  return `${Math.floor(ms / 60000)}m ${Math.round((ms % 60000) / 1000)}s`
}

export function Federation() {
  const [tab, setTab] = useState<Tab>('peers')
  const [peers, setPeers] = useState<FederationPeer[]>([])
  const [delegations, setDelegations] = useState<FederationDelegation[]>([])
  const [identities, setIdentities] = useState<FederatedIdentity[]>([])
  const [loading, setLoading] = useState(true)
  const [showRegisterModal, setShowRegisterModal] = useState(false)
  const [showLinkModal, setShowLinkModal] = useState(false)
  const [expandedDelegation, setExpandedDelegation] = useState<number | null>(null)
  const { showToast } = useAppStore()

  const loadPeers = useCallback(async () => {
    try {
      const res = await api.get('/federation/peers')
      setPeers(res.data.data)
    } catch {
      // silent
    }
  }, [])

  const loadDelegations = useCallback(async () => {
    try {
      const res = await api.get('/federation/delegations')
      setDelegations(res.data.data)
    } catch {
      // silent
    }
  }, [])

  const loadIdentities = useCallback(async () => {
    try {
      const res = await api.get('/federation/identities')
      setIdentities(res.data.data)
    } catch {
      // silent
    }
  }, [])

  const loadAll = useCallback(async () => {
    setLoading(true)
    await Promise.all([loadPeers(), loadDelegations(), loadIdentities()])
    setLoading(false)
  }, [loadPeers, loadDelegations, loadIdentities])

  useEffect(() => {
    loadAll()
  }, [loadAll])

  // --- Peer Actions ---

  const handleHeartbeat = async (peer: FederationPeer) => {
    try {
      const res = await api.post(`/federation/peers/${peer.id}/heartbeat`)
      if (res.data.data.success) {
        showToast('Heartbeat successful')
      } else {
        showToast('Heartbeat failed', 'error')
      }
      loadPeers()
    } catch {
      showToast('Heartbeat check failed', 'error')
    }
  }

  const handleSyncCapabilities = async (peer: FederationPeer) => {
    try {
      await api.get(`/federation/peers/${peer.id}/capabilities`)
      showToast('Capabilities synced')
      loadPeers()
    } catch {
      showToast('Capability sync failed', 'error')
    }
  }

  const handleRemovePeer = async (peer: FederationPeer) => {
    if (!confirm(`Remove peer "${peer.name}"? This will revoke all access.`)) return
    try {
      await api.delete(`/federation/peers/${peer.id}`)
      showToast('Peer removed')
      loadPeers()
    } catch {
      showToast('Failed to remove peer', 'error')
    }
  }

  const handleUpdateTrust = async (peer: FederationPeer, level: string) => {
    try {
      await api.put(`/federation/peers/${peer.id}`, { trust_level: level })
      showToast(`Trust updated to ${level}`)
      loadPeers()
    } catch {
      showToast('Failed to update trust', 'error')
    }
  }

  const handleUnlinkIdentity = async (identity: FederatedIdentity) => {
    if (!confirm('Unlink this federated identity?')) return
    try {
      await api.delete(`/federation/identities/${identity.id}`)
      showToast('Identity unlinked')
      loadIdentities()
    } catch {
      showToast('Failed to unlink', 'error')
    }
  }

  // --- Tabs ---

  const tabs: { key: Tab; label: string; icon: React.ReactNode }[] = [
    { key: 'peers', label: 'Peers', icon: <Globe className="h-4 w-4" /> },
    { key: 'delegations', label: 'Delegations', icon: <Zap className="h-4 w-4" /> },
    { key: 'identities', label: 'Identities', icon: <User className="h-4 w-4" /> },
  ]

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-pulse text-muted-foreground">Loading federation...</div>
      </div>
    )
  }

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold flex items-center gap-2">
            <Network className="h-5 w-5 text-primary" />
            Federation
          </h1>
          <p className="text-sm text-muted-foreground mt-0.5">
            Cross-instance peer connections, delegated tasks, and identity mapping
          </p>
        </div>
        <button
          onClick={loadAll}
          className="flex items-center gap-1.5 text-sm px-3 py-1.5 bg-muted rounded hover:bg-muted/80"
        >
          <RefreshCw className="h-3.5 w-3.5" /> Refresh
        </button>
      </div>

      {/* Tab Bar */}
      <div className="flex gap-1 border-b border-border">
        {tabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`flex items-center gap-1.5 px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              tab === t.key
                ? 'border-primary text-foreground'
                : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
          >
            {t.icon} {t.label}
          </button>
        ))}
      </div>

      {/* Tab Content */}
      {tab === 'peers' && (
        <PeersTab
          peers={peers}
          onHeartbeat={handleHeartbeat}
          onSyncCapabilities={handleSyncCapabilities}
          onUpdateTrust={handleUpdateTrust}
          onRemove={handleRemovePeer}
          onRegister={() => setShowRegisterModal(true)}
        />
      )}
      {tab === 'delegations' && (
        <DelegationsTab
          delegations={delegations}
          expandedId={expandedDelegation}
          onToggle={(id) => setExpandedDelegation(expandedDelegation === id ? null : id)}
        />
      )}
      {tab === 'identities' && (
        <IdentitiesTab
          identities={identities}
          peers={peers}
          onUnlink={handleUnlinkIdentity}
          onLink={() => setShowLinkModal(true)}
        />
      )}

      {/* Register Peer Modal */}
      {showRegisterModal && (
        <RegisterPeerModal
          onClose={() => setShowRegisterModal(false)}
          onSuccess={() => {
            setShowRegisterModal(false)
            loadPeers()
          }}
        />
      )}

      {/* Link Identity Modal */}
      {showLinkModal && (
        <LinkIdentityModal
          peers={peers}
          onClose={() => setShowLinkModal(false)}
          onSuccess={() => {
            setShowLinkModal(false)
            loadIdentities()
          }}
        />
      )}
    </div>
  )
}

// --- Peers Tab ---

function PeersTab({
  peers,
  onHeartbeat,
  onSyncCapabilities,
  onUpdateTrust,
  onRemove,
  onRegister,
}: {
  peers: FederationPeer[]
  onHeartbeat: (p: FederationPeer) => void
  onSyncCapabilities: (p: FederationPeer) => void
  onUpdateTrust: (p: FederationPeer, level: string) => void
  onRemove: (p: FederationPeer) => void
  onRegister: () => void
}) {
  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <button
          onClick={onRegister}
          className="flex items-center gap-1.5 text-sm px-3 py-1.5 bg-primary text-primary-foreground rounded hover:bg-primary/90"
        >
          <Plus className="h-3.5 w-3.5" /> Register Peer
        </button>
      </div>

      {peers.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-muted-foreground bg-card elevation-1 rounded-lg">
          <Globe className="h-8 w-8 mb-2 opacity-30" />
          <p className="text-sm">No federation peers registered</p>
          <p className="text-xs mt-1">Register a peer to connect with another instance</p>
        </div>
      ) : (
        <div className="space-y-3">
          {peers.map((peer) => {
            const trust = TRUST_CONFIG[peer.trust_level] || TRUST_CONFIG.basic
            const capAgents = (peer.capabilities as { agents?: unknown[] } | null)?.agents
            return (
              <div key={peer.id} className="bg-card elevation-1 rounded-lg p-4">
                <div className="flex items-start justify-between">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <h3 className="font-medium truncate">{peer.name}</h3>
                      <span
                        className={`text-xs px-2 py-0.5 rounded-full border ${PEER_STATUS_COLORS[peer.status]}`}
                      >
                        {peer.status}
                      </span>
                      <span className={`flex items-center gap-1 text-xs ${trust.color}`}>
                        {trust.icon} {trust.label}
                      </span>
                    </div>
                    <p className="text-xs text-muted-foreground mt-1 truncate">{peer.base_url}</p>
                    <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground">
                      <span className="flex items-center gap-1">
                        <HeartPulse className="h-3 w-3" /> {timeAgo(peer.last_heartbeat_at)}
                      </span>
                      <span className="flex items-center gap-1">
                        <RefreshCw className="h-3 w-3" /> Synced {timeAgo(peer.last_sync_at)}
                      </span>
                      <span>{peer.delegations_count} delegations</span>
                      {capAgents && (
                        <span>{Array.isArray(capAgents) ? capAgents.length : 0} remote agents</span>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-1 ml-4">
                    <button
                      onClick={() => onHeartbeat(peer)}
                      className="p-1.5 text-muted-foreground hover:text-foreground rounded hover:bg-muted"
                      title="Heartbeat check"
                    >
                      <HeartPulse className="h-3.5 w-3.5" />
                    </button>
                    <button
                      onClick={() => onSyncCapabilities(peer)}
                      className="p-1.5 text-muted-foreground hover:text-foreground rounded hover:bg-muted"
                      title="Sync capabilities"
                    >
                      <RefreshCw className="h-3.5 w-3.5" />
                    </button>
                    <select
                      value={peer.trust_level}
                      onChange={(e) => onUpdateTrust(peer, e.target.value)}
                      className="text-xs bg-muted border border-border rounded px-1.5 py-1 text-foreground"
                    >
                      <option value="untrusted">Untrusted</option>
                      <option value="basic">Basic</option>
                      <option value="verified">Verified</option>
                      <option value="full">Full</option>
                    </select>
                    <button
                      onClick={() => onRemove(peer)}
                      className="p-1.5 text-muted-foreground hover:text-destructive rounded hover:bg-destructive/10"
                      title="Remove peer"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

// --- Delegations Tab ---

function DelegationsTab({
  delegations,
  expandedId,
  onToggle,
}: {
  delegations: FederationDelegation[]
  expandedId: number | null
  onToggle: (id: number) => void
}) {
  return (
    <div className="bg-card elevation-1 rounded-lg overflow-hidden">
      <div className="px-4 py-3 border-b border-border">
        <h2 className="text-sm font-semibold">Delegation History</h2>
      </div>

      {delegations.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
          <Zap className="h-8 w-8 mb-2 opacity-30" />
          <p className="text-sm">No delegations yet</p>
          <p className="text-xs mt-1">Delegate tasks to remote agents via federation peers</p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-xs text-muted-foreground uppercase">
                <th className="text-left px-4 py-2 w-8"></th>
                <th className="text-left px-4 py-2">Direction</th>
                <th className="text-left px-4 py-2">Local Agent</th>
                <th className="text-left px-4 py-2">Remote Agent</th>
                <th className="text-left px-4 py-2">Peer</th>
                <th className="text-left px-4 py-2">Status</th>
                <th className="text-right px-4 py-2">Cost</th>
                <th className="text-right px-4 py-2">Duration</th>
                <th className="text-left px-4 py-2">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {delegations.map((d) => (
                <DelegationRow
                  key={d.id}
                  delegation={d}
                  expanded={expandedId === d.id}
                  onToggle={() => onToggle(d.id)}
                />
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

function DelegationRow({
  delegation: d,
  expanded,
  onToggle,
}: {
  delegation: FederationDelegation
  expanded: boolean
  onToggle: () => void
}) {
  return (
    <>
      <tr className="hover:bg-muted/20 cursor-pointer" onClick={onToggle}>
        <td className="px-4 py-2.5">
          {expanded ? (
            <ChevronDown className="h-3.5 w-3.5 text-muted-foreground" />
          ) : (
            <ChevronRight className="h-3.5 w-3.5 text-muted-foreground" />
          )}
        </td>
        <td className="px-4 py-2.5">
          {d.direction === 'outbound' ? (
            <span className="flex items-center gap-1 text-blue-500 text-xs font-medium">
              <ArrowUpRight className="h-3.5 w-3.5" /> Outbound
            </span>
          ) : (
            <span className="flex items-center gap-1 text-purple-500 text-xs font-medium">
              <ArrowDownLeft className="h-3.5 w-3.5" /> Inbound
            </span>
          )}
        </td>
        <td className="px-4 py-2.5 font-medium">
          {d.local_agent?.name || <span className="text-muted-foreground">--</span>}
        </td>
        <td className="px-4 py-2.5 font-mono text-xs">{d.remote_agent_slug}</td>
        <td className="px-4 py-2.5 text-muted-foreground">{d.peer?.name || '--'}</td>
        <td className="px-4 py-2.5">
          <span
            className={`text-xs px-2 py-0.5 rounded-full border ${DELEGATION_STATUS_COLORS[d.status]}`}
          >
            {d.status}
          </span>
        </td>
        <td className="px-4 py-2.5 text-right font-mono text-xs">
          {d.cost_microcents > 0 ? formatCost(d.cost_microcents) : '--'}
        </td>
        <td className="px-4 py-2.5 text-right font-mono text-xs">
          {d.duration_ms > 0 ? formatDuration(d.duration_ms) : '--'}
        </td>
        <td className="px-4 py-2.5 text-xs text-muted-foreground">{timeAgo(d.created_at)}</td>
      </tr>
      {expanded && (
        <tr>
          <td colSpan={9} className="bg-muted/30 px-8 py-4">
            <div className="grid grid-cols-2 gap-6">
              <div>
                <h4 className="text-xs font-semibold text-muted-foreground uppercase mb-2">Input</h4>
                <pre className="text-xs bg-background rounded p-3 overflow-auto max-h-48">
                  {d.input ? JSON.stringify(d.input, null, 2) : 'null'}
                </pre>
              </div>
              <div>
                <h4 className="text-xs font-semibold text-muted-foreground uppercase mb-2">Output</h4>
                <pre className="text-xs bg-background rounded p-3 overflow-auto max-h-48">
                  {d.output ? JSON.stringify(d.output, null, 2) : 'null'}
                </pre>
              </div>
            </div>
          </td>
        </tr>
      )}
    </>
  )
}

// --- Identities Tab ---

function IdentitiesTab({
  identities,
  peers,
  onUnlink,
  onLink,
}: {
  identities: FederatedIdentity[]
  peers: FederationPeer[]
  onUnlink: (i: FederatedIdentity) => void
  onLink: () => void
}) {
  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <button
          onClick={onLink}
          className="flex items-center gap-1.5 text-sm px-3 py-1.5 bg-primary text-primary-foreground rounded hover:bg-primary/90"
        >
          <Link2 className="h-3.5 w-3.5" /> Link Identity
        </button>
      </div>

      <div className="bg-card elevation-1 rounded-lg overflow-hidden">
        <div className="px-4 py-3 border-b border-border">
          <h2 className="text-sm font-semibold">Federated Identities</h2>
        </div>

        {identities.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
            <User className="h-8 w-8 mb-2 opacity-30" />
            <p className="text-sm">No federated identities</p>
            <p className="text-xs mt-1">Link your account to identities on federated peers</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-xs text-muted-foreground uppercase">
                  <th className="text-left px-4 py-2">Peer</th>
                  <th className="text-left px-4 py-2">Remote User ID</th>
                  <th className="text-left px-4 py-2">Remote Email</th>
                  <th className="text-left px-4 py-2">Role</th>
                  <th className="text-left px-4 py-2">Verified</th>
                  <th className="text-left px-4 py-2">Linked</th>
                  <th className="text-right px-4 py-2">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {identities.map((i) => (
                  <tr key={i.id} className="hover:bg-muted/20">
                    <td className="px-4 py-2.5 font-medium">{i.peer?.name || '--'}</td>
                    <td className="px-4 py-2.5 font-mono text-xs">{i.remote_user_id}</td>
                    <td className="px-4 py-2.5 text-muted-foreground">{i.remote_email || '--'}</td>
                    <td className="px-4 py-2.5 text-muted-foreground">{i.remote_role || '--'}</td>
                    <td className="px-4 py-2.5">
                      {i.verified_at ? (
                        <span className="flex items-center gap-1 text-green-500 text-xs">
                          <CheckCircle className="h-3.5 w-3.5" /> Verified
                        </span>
                      ) : (
                        <span className="flex items-center gap-1 text-muted-foreground text-xs">
                          <Clock className="h-3.5 w-3.5" /> Unverified
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-2.5 text-xs text-muted-foreground">
                      {timeAgo(i.created_at)}
                    </td>
                    <td className="px-4 py-2.5 text-right">
                      <button
                        onClick={() => onUnlink(i)}
                        className="p-1.5 text-muted-foreground hover:text-destructive rounded hover:bg-destructive/10"
                        title="Unlink identity"
                      >
                        <Unlink className="h-3.5 w-3.5" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

// --- Register Peer Modal ---

function RegisterPeerModal({
  onClose,
  onSuccess,
}: {
  onClose: () => void
  onSuccess: () => void
}) {
  const [name, setName] = useState('')
  const [baseUrl, setBaseUrl] = useState('')
  const [apiKey, setApiKey] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const { showToast } = useAppStore()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await api.post('/federation/peers', { name, base_url: baseUrl, api_key: apiKey })
      showToast('Peer registered')
      onSuccess()
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'Failed to register peer'
      setError(msg)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-card rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between px-5 py-4 border-b border-border">
          <h3 className="font-semibold">Register Federation Peer</h3>
          <button onClick={onClose} className="text-muted-foreground hover:text-foreground">
            <X className="h-4 w-4" />
          </button>
        </div>
        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          {error && (
            <div className="flex items-center gap-2 text-sm text-destructive bg-destructive/10 rounded px-3 py-2">
              <AlertTriangle className="h-4 w-4 flex-shrink-0" /> {error}
            </div>
          )}
          <div>
            <label className="block text-xs font-medium mb-1">Name</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Production Instance"
              className="w-full px-3 py-2 text-sm bg-background border border-border rounded focus:outline-none focus:ring-1 focus:ring-primary"
              required
            />
          </div>
          <div>
            <label className="block text-xs font-medium mb-1">Base URL</label>
            <input
              type="url"
              value={baseUrl}
              onChange={(e) => setBaseUrl(e.target.value)}
              placeholder="https://peer.example.com"
              className="w-full px-3 py-2 text-sm bg-background border border-border rounded focus:outline-none focus:ring-1 focus:ring-primary"
              required
            />
          </div>
          <div>
            <label className="block text-xs font-medium mb-1">API Key</label>
            <input
              type="password"
              value={apiKey}
              onChange={(e) => setApiKey(e.target.value)}
              placeholder="Shared secret for authentication"
              className="w-full px-3 py-2 text-sm bg-background border border-border rounded focus:outline-none focus:ring-1 focus:ring-primary"
              required
              minLength={16}
            />
            <p className="text-xs text-muted-foreground mt-1">
              Both peers must use the same API key. Min 16 characters.
            </p>
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-3 py-1.5 text-sm bg-muted rounded hover:bg-muted/80"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting || !name || !baseUrl || !apiKey}
              className="px-3 py-1.5 text-sm bg-primary text-primary-foreground rounded hover:bg-primary/90 disabled:opacity-50"
            >
              {submitting ? 'Registering...' : 'Register'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// --- Link Identity Modal ---

function LinkIdentityModal({
  peers,
  onClose,
  onSuccess,
}: {
  peers: FederationPeer[]
  onClose: () => void
  onSuccess: () => void
}) {
  const activePeers = peers.filter((p) => p.status === 'active')
  const [peerId, setPeerId] = useState<number>(activePeers[0]?.id ?? 0)
  const [remoteUserId, setRemoteUserId] = useState('')
  const [remoteEmail, setRemoteEmail] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')
  const { showToast } = useAppStore()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!peerId) return
    setSubmitting(true)
    setError('')

    try {
      await api.post(`/federation/peers/${peerId}/link-identity`, {
        remote_user_id: remoteUserId,
        remote_email: remoteEmail || undefined,
      })
      showToast('Identity linked')
      onSuccess()
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'Failed to link identity'
      setError(msg)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="bg-card rounded-lg shadow-xl w-full max-w-md mx-4">
        <div className="flex items-center justify-between px-5 py-4 border-b border-border">
          <h3 className="font-semibold">Link Federated Identity</h3>
          <button onClick={onClose} className="text-muted-foreground hover:text-foreground">
            <X className="h-4 w-4" />
          </button>
        </div>
        <form onSubmit={handleSubmit} className="p-5 space-y-4">
          {error && (
            <div className="flex items-center gap-2 text-sm text-destructive bg-destructive/10 rounded px-3 py-2">
              <AlertTriangle className="h-4 w-4 flex-shrink-0" /> {error}
            </div>
          )}
          {activePeers.length === 0 ? (
            <div className="text-sm text-muted-foreground text-center py-4">
              No active peers available. Register and activate a peer first.
            </div>
          ) : (
            <>
              <div>
                <label className="block text-xs font-medium mb-1">Peer</label>
                <select
                  value={peerId}
                  onChange={(e) => setPeerId(Number(e.target.value))}
                  className="w-full px-3 py-2 text-sm bg-background border border-border rounded focus:outline-none focus:ring-1 focus:ring-primary"
                >
                  {activePeers.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name} ({p.base_url})
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium mb-1">Remote User ID</label>
                <input
                  type="text"
                  value={remoteUserId}
                  onChange={(e) => setRemoteUserId(e.target.value)}
                  placeholder="user-123 or email on remote instance"
                  className="w-full px-3 py-2 text-sm bg-background border border-border rounded focus:outline-none focus:ring-1 focus:ring-primary"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-medium mb-1">Remote Email (optional)</label>
                <input
                  type="email"
                  value={remoteEmail}
                  onChange={(e) => setRemoteEmail(e.target.value)}
                  placeholder="user@remote-instance.com"
                  className="w-full px-3 py-2 text-sm bg-background border border-border rounded focus:outline-none focus:ring-1 focus:ring-primary"
                />
              </div>
            </>
          )}
          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-3 py-1.5 text-sm bg-muted rounded hover:bg-muted/80"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting || !peerId || !remoteUserId || activePeers.length === 0}
              className="px-3 py-1.5 text-sm bg-primary text-primary-foreground rounded hover:bg-primary/90 disabled:opacity-50"
            >
              {submitting ? 'Linking...' : 'Link'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
