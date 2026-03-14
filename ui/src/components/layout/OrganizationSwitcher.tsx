import { useState, useRef, useEffect } from 'react'
import { Building2, Check, ChevronDown, Plus, Loader2 } from 'lucide-react'
import { useAuthStore } from '@/store/useAuthStore'
import { createOrganization } from '@/api/client'
import { useAppStore } from '@/store/useAppStore'

export function OrganizationSwitcher() {
  const { organizations, currentOrganization, switchOrg, fetchOrganizations } = useAuthStore()
  const { showToast } = useAppStore()
  const [open, setOpen] = useState(false)
  const [showCreate, setShowCreate] = useState(false)
  const [createName, setCreateName] = useState('')
  const [createDesc, setCreateDesc] = useState('')
  const [creating, setCreating] = useState(false)
  const [switching, setSwitching] = useState<number | null>(null)
  const dropdownRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setOpen(false)
        setShowCreate(false)
      }
    }
    if (open) {
      document.addEventListener('mousedown', handleClickOutside)
      return () => document.removeEventListener('mousedown', handleClickOutside)
    }
  }, [open])

  const handleSwitch = async (orgId: number) => {
    if (orgId === currentOrganization?.id) {
      setOpen(false)
      return
    }
    setSwitching(orgId)
    try {
      await switchOrg(orgId)
      setOpen(false)
      // Reload projects for the new org context
      window.location.reload()
    } catch {
      showToast('Failed to switch workspace', 'error')
    } finally {
      setSwitching(null)
    }
  }

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!createName.trim()) return
    setCreating(true)
    try {
      const org = await createOrganization({ name: createName.trim(), description: createDesc.trim() || undefined })
      await fetchOrganizations()
      await switchOrg(org.id)
      setCreateName('')
      setCreateDesc('')
      setShowCreate(false)
      setOpen(false)
      showToast(`Workspace "${org.name}" created`)
      window.location.reload()
    } catch {
      showToast('Failed to create workspace', 'error')
    } finally {
      setCreating(false)
    }
  }

  const planColors: Record<string, string> = {
    free: 'bg-muted text-muted-foreground',
    pro: 'bg-primary/10 text-primary',
    teams: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
  }

  if (organizations.length === 0) return null

  return (
    <div className="relative px-3 mb-1" ref={dropdownRef}>
      <button
        onClick={() => setOpen(!open)}
        className="w-full flex items-center gap-2 px-2.5 py-2 text-sm text-sidebar-foreground hover:bg-sidebar-accent transition-colors duration-150"
      >
        <Building2 className="h-4 w-4 text-sidebar-muted shrink-0" />
        <span className="flex-1 truncate text-left text-[13px]">
          {currentOrganization?.name || 'Select Workspace'}
        </span>
        <ChevronDown className={`h-3.5 w-3.5 text-sidebar-muted transition-transform duration-150 ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className="absolute left-3 right-3 top-full mt-1 bg-card border border-border elevation-3 z-50 overflow-hidden">
          {!showCreate ? (
            <>
              <div className="py-1">
                {organizations.map((org) => (
                  <button
                    key={org.id}
                    onClick={() => handleSwitch(org.id)}
                    disabled={switching !== null}
                    className="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-muted transition-colors duration-150 text-left"
                  >
                    <Building2 className="h-4 w-4 text-muted-foreground shrink-0" />
                    <span className="flex-1 truncate">{org.name}</span>
                    <span className={`text-[10px] px-1.5 py-0.5 font-medium uppercase tracking-wider ${planColors[org.plan] || planColors.free}`}>
                      {org.plan}
                    </span>
                    {org.id === currentOrganization?.id && (
                      switching === org.id ? (
                        <Loader2 className="h-3.5 w-3.5 animate-spin text-primary" />
                      ) : (
                        <Check className="h-3.5 w-3.5 text-primary" />
                      )
                    )}
                    {switching === org.id && org.id !== currentOrganization?.id && (
                      <Loader2 className="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                    )}
                  </button>
                ))}
              </div>
              <div className="border-t border-border">
                <button
                  onClick={() => setShowCreate(true)}
                  className="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-muted-foreground hover:text-foreground hover:bg-muted transition-colors duration-150"
                >
                  <Plus className="h-4 w-4" />
                  Create Workspace
                </button>
              </div>
            </>
          ) : (
            <form onSubmit={handleCreate} className="p-3 space-y-3">
              <div>
                <label className="text-xs font-medium text-muted-foreground">Workspace Name</label>
                <input
                  type="text"
                  value={createName}
                  onChange={(e) => setCreateName(e.target.value)}
                  placeholder="My Team"
                  autoFocus
                  className="w-full mt-1 px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
              </div>
              <div>
                <label className="text-xs font-medium text-muted-foreground">Description (optional)</label>
                <input
                  type="text"
                  value={createDesc}
                  onChange={(e) => setCreateDesc(e.target.value)}
                  placeholder="What this workspace is for"
                  className="w-full mt-1 px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
                />
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="submit"
                  disabled={creating || !createName.trim()}
                  className="flex-1 flex items-center justify-center gap-1.5 px-3 py-1.5 text-sm bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                >
                  {creating && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                  Create
                </button>
                <button
                  type="button"
                  onClick={() => { setShowCreate(false); setCreateName(''); setCreateDesc('') }}
                  className="px-3 py-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                  Cancel
                </button>
              </div>
            </form>
          )}
        </div>
      )}
    </div>
  )
}
