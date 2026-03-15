import { useState, useEffect } from 'react'
import {
  Key,
  Plus,
  Trash2,
  Copy,
  Check,
  Loader2,
  Clock,
  AlertTriangle,
} from 'lucide-react'
import { fetchApiTokens, createApiToken, deleteApiToken } from '@/api/client'
import type { ApiToken, ApiTokenCreateResult } from '@/types'
import { Button } from '@/components/ui/button'

const ABILITIES = [
  { value: '*', label: 'Full access' },
  { value: 'skills:read', label: 'Skills: Read' },
  { value: 'skills:write', label: 'Skills: Write' },
  { value: 'projects:read', label: 'Projects: Read' },
  { value: 'projects:write', label: 'Projects: Write' },
  { value: 'agents:read', label: 'Agents: Read' },
  { value: 'agents:write', label: 'Agents: Write' },
]

const EXPIRATION_OPTIONS = [
  { value: 30, label: '30 days' },
  { value: 90, label: '90 days' },
  { value: 365, label: '1 year' },
  { value: 0, label: 'Never' },
]

function formatDate(dateString: string | null): string {
  if (!dateString) return 'Never'
  const date = new Date(dateString)
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function timeAgo(dateString: string): string {
  const date = new Date(dateString)
  const now = new Date()
  const seconds = Math.floor((now.getTime() - date.getTime()) / 1000)

  if (seconds < 60) return 'just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`
  return date.toLocaleDateString()
}

export function ApiTokens() {
  const [tokens, setTokens] = useState<ApiToken[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreateForm, setShowCreateForm] = useState(false)
  const [creating, setCreating] = useState(false)
  const [newTokenResult, setNewTokenResult] = useState<ApiTokenCreateResult | null>(null)
  const [copied, setCopied] = useState(false)
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null)

  // Create form state
  const [name, setName] = useState('')
  const [selectedAbilities, setSelectedAbilities] = useState<string[]>(['*'])
  const [expiresInDays, setExpiresInDays] = useState<number>(90)

  const loadTokens = () => {
    setLoading(true)
    fetchApiTokens()
      .then(setTokens)
      .catch(() => {})
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    loadTokens()
  }, [])

  const handleToggleAbility = (ability: string) => {
    if (ability === '*') {
      setSelectedAbilities((prev) =>
        prev.includes('*') ? [] : ['*']
      )
      return
    }
    setSelectedAbilities((prev) => {
      const without = prev.filter((a) => a !== '*')
      if (without.includes(ability)) {
        return without.filter((a) => a !== ability)
      }
      return [...without, ability]
    })
  }

  const handleCreate = async () => {
    if (!name.trim() || selectedAbilities.length === 0) return
    setCreating(true)
    try {
      const result = await createApiToken({
        name: name.trim(),
        abilities: selectedAbilities,
        expires_in_days: expiresInDays || undefined,
      })
      setNewTokenResult(result.data)
      setName('')
      setSelectedAbilities(['*'])
      setExpiresInDays(90)
      loadTokens()
    } catch {
      // error handled silently
    } finally {
      setCreating(false)
    }
  }

  const handleCopy = async (text: string) => {
    await navigator.clipboard.writeText(text)
    setCopied(true)
    setTimeout(() => setCopied(false), 2000)
  }

  const handleDelete = async (id: number) => {
    setDeletingId(id)
    try {
      await deleteApiToken(id)
      setTokens((prev) => prev.filter((t) => t.id !== id))
      setConfirmDeleteId(null)
    } catch {
      // error handled silently
    } finally {
      setDeletingId(null)
    }
  }

  const handleDismissCreateForm = () => {
    setShowCreateForm(false)
    setNewTokenResult(null)
    setName('')
    setSelectedAbilities(['*'])
    setExpiresInDays(90)
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight">API Tokens</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Manage personal access tokens for the API
          </p>
        </div>
        {!showCreateForm && !newTokenResult && (
          <Button onClick={() => setShowCreateForm(true)}>
            <Plus className="h-4 w-4 mr-1.5" />
            Create Token
          </Button>
        )}
      </div>

      {/* New token reveal */}
      {newTokenResult && (
        <div className="bg-card elevation-1 p-5 space-y-3 border border-amber-500/30">
          <div className="flex items-start gap-2">
            <AlertTriangle className="h-5 w-5 text-amber-400 shrink-0 mt-0.5" />
            <div className="space-y-1">
              <p className="text-sm font-medium">
                Token created: {newTokenResult.name}
              </p>
              <p className="text-sm text-muted-foreground">
                Copy this token now. It will not be shown again.
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <code className="flex-1 px-3 py-2 text-sm font-mono bg-background border border-input break-all select-all">
              {newTokenResult.plain_token}
            </code>
            <Button
              variant="outline"
              size="sm"
              onClick={() => handleCopy(newTokenResult.plain_token)}
            >
              {copied ? (
                <Check className="h-4 w-4 text-green-500" />
              ) : (
                <Copy className="h-4 w-4" />
              )}
            </Button>
          </div>
          <div className="flex justify-end">
            <Button variant="ghost" size="sm" onClick={handleDismissCreateForm}>
              Done
            </Button>
          </div>
        </div>
      )}

      {/* Create form */}
      {showCreateForm && !newTokenResult && (
        <div className="bg-card elevation-1 p-5 space-y-4">
          <h2 className="text-sm font-semibold">New API Token</h2>

          <div className="space-y-1.5">
            <label className="text-sm font-medium">Name</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. CI/CD Pipeline"
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-sm font-medium">Abilities</label>
            <div className="flex flex-wrap gap-3">
              {ABILITIES.map((ability) => (
                <label
                  key={ability.value}
                  className="flex items-center gap-1.5 text-sm cursor-pointer"
                >
                  <input
                    type="checkbox"
                    checked={selectedAbilities.includes(ability.value)}
                    onChange={() => handleToggleAbility(ability.value)}
                    className="accent-primary"
                  />
                  {ability.label}
                </label>
              ))}
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-sm font-medium">Expiration</label>
            <select
              value={expiresInDays}
              onChange={(e) => setExpiresInDays(Number(e.target.value))}
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            >
              {EXPIRATION_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>

          <div className="flex items-center gap-2 pt-1">
            <Button
              onClick={handleCreate}
              disabled={creating || !name.trim() || selectedAbilities.length === 0}
            >
              {creating ? (
                <Loader2 className="h-4 w-4 mr-1.5 animate-spin" />
              ) : (
                <Key className="h-4 w-4 mr-1.5" />
              )}
              Create Token
            </Button>
            <Button variant="ghost" onClick={handleDismissCreateForm}>
              Cancel
            </Button>
          </div>
        </div>
      )}

      {/* Token list */}
      {loading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : tokens.length === 0 ? (
        <div className="bg-card elevation-1 p-12 text-center space-y-3">
          <Key className="h-10 w-10 text-muted-foreground mx-auto" />
          <p className="text-sm text-muted-foreground">
            No API tokens yet. Create one to get started.
          </p>
        </div>
      ) : (
        <div className="space-y-3">
          {tokens.map((token) => (
            <div
              key={token.id}
              className="bg-card elevation-1 p-4 flex items-start justify-between gap-4"
            >
              <div className="space-y-1.5 min-w-0">
                <div className="flex items-center gap-2">
                  <Key className="h-4 w-4 text-muted-foreground shrink-0" />
                  <span className="text-sm font-medium truncate">
                    {token.name}
                  </span>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {token.abilities.map((ability) => (
                    <span
                      key={ability}
                      className="px-1.5 py-0.5 text-xs font-mono bg-muted border border-border"
                    >
                      {ability}
                    </span>
                  ))}
                </div>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                  <span>Created {timeAgo(token.created_at)}</span>
                  <span className="flex items-center gap-1">
                    <Clock className="h-3 w-3" />
                    {token.last_used_at
                      ? `Last used ${timeAgo(token.last_used_at)}`
                      : 'Never used'}
                  </span>
                  <span>
                    Expires: {formatDate(token.expires_at)}
                  </span>
                </div>
              </div>
              <div className="shrink-0">
                {confirmDeleteId === token.id ? (
                  <div className="flex items-center gap-1.5">
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => handleDelete(token.id)}
                      disabled={deletingId === token.id}
                    >
                      {deletingId === token.id ? (
                        <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      ) : (
                        'Confirm'
                      )}
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => setConfirmDeleteId(null)}
                    >
                      Cancel
                    </Button>
                  </div>
                ) : (
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => setConfirmDeleteId(token.id)}
                  >
                    <Trash2 className="h-4 w-4 text-muted-foreground hover:text-destructive" />
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
