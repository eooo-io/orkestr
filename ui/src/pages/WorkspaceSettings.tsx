import { useState, useEffect } from 'react'
import { Loader2, Save, UserPlus, AlertTriangle, Trash2 } from 'lucide-react'
import { useAuthStore } from '@/store/useAuthStore'
import { useAppStore } from '@/store/useAppStore'
import {
  updateOrganization,
  deleteOrganization,
  fetchOrgMembers,
  fetchOrgInvitations,
  inviteOrgMember,
  cancelInvitation,
  updateMemberRole,
  removeMember,
} from '@/api/client'
import type { OrganizationMember, OrganizationInvitation } from '@/types'
import { Button } from '@/components/ui/button'
import { MembersTable } from '@/components/workspace/MembersTable'
import { InvitationsTable } from '@/components/workspace/InvitationsTable'
import { InviteMemberModal } from '@/components/workspace/InviteMemberModal'

export function WorkspaceSettings() {
  const { user, currentOrganization, fetchOrganizations } = useAuthStore()
  const { showToast } = useAppStore()

  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [members, setMembers] = useState<OrganizationMember[]>([])
  const [invitations, setInvitations] = useState<OrganizationInvitation[]>([])
  const [showInviteModal, setShowInviteModal] = useState(false)
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false)
  const [deleteConfirmName, setDeleteConfirmName] = useState('')
  const [deleting, setDeleting] = useState(false)

  const orgId = currentOrganization?.id
  const userRole = currentOrganization?.role || 'member'
  const isOwner = userRole === 'owner'
  const canManage = isOwner || userRole === 'admin'

  useEffect(() => {
    if (!orgId) {
      setLoading(false)
      return
    }

    setName(currentOrganization?.name || '')
    setDescription(currentOrganization?.description || '')

    const loadData = async () => {
      try {
        const [membersData, invitationsData] = await Promise.all([
          fetchOrgMembers(orgId),
          canManage ? fetchOrgInvitations(orgId) : Promise.resolve([]),
        ])
        setMembers(membersData)
        setInvitations(invitationsData)
      } catch {
        showToast('Failed to load workspace data', 'error')
      } finally {
        setLoading(false)
      }
    }

    loadData()
  }, [orgId]) // eslint-disable-line react-hooks/exhaustive-deps

  const handleSave = async () => {
    if (!orgId) return
    setSaving(true)
    try {
      await updateOrganization(orgId, {
        name: name.trim(),
        description: description.trim() || undefined,
      })
      await fetchOrganizations()
      showToast('Workspace settings saved')
    } catch {
      showToast('Failed to save settings', 'error')
    } finally {
      setSaving(false)
    }
  }

  const handleInvite = async (email: string, role: string) => {
    if (!orgId) return
    await inviteOrgMember(orgId, { email, role })
    const updated = await fetchOrgInvitations(orgId)
    setInvitations(updated)
    showToast(`Invitation sent to ${email}`)
  }

  const handleCancelInvitation = async (invitationId: number) => {
    await cancelInvitation(invitationId)
    setInvitations((prev) => prev.filter((inv) => inv.id !== invitationId))
    showToast('Invitation cancelled')
  }

  const handleUpdateRole = async (userId: number, role: string) => {
    if (!orgId) return
    await updateMemberRole(orgId, userId, role)
    setMembers((prev) =>
      prev.map((m) =>
        m.id === userId ? { ...m, role: role as OrganizationMember['role'] } : m,
      ),
    )
    showToast('Role updated')
  }

  const handleRemoveMember = async (userId: number) => {
    if (!orgId) return
    await removeMember(orgId, userId)
    setMembers((prev) => prev.filter((m) => m.id !== userId))
    showToast('Member removed')
  }

  const handleDelete = async () => {
    if (!orgId || deleteConfirmName !== currentOrganization?.name) return
    setDeleting(true)
    try {
      await deleteOrganization(orgId)
      showToast('Workspace deleted')
      window.location.href = '/projects'
    } catch {
      showToast('Failed to delete workspace', 'error')
      setDeleting(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  if (!currentOrganization) {
    return (
      <div className="p-4 md:p-6 max-w-3xl">
        <div className="mb-8">
          <h1 className="text-2xl font-semibold tracking-tight">Workspace Settings</h1>
          <p className="text-sm text-muted-foreground mt-1">
            No workspace selected. Create or join a workspace to access settings.
          </p>
        </div>
      </div>
    )
  }

  const planColors: Record<string, string> = {
    free: 'bg-muted text-muted-foreground',
    pro: 'bg-primary/10 text-primary',
    teams: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
  }

  return (
    <div className="p-4 md:p-6 max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-semibold tracking-tight">Workspace Settings</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Manage your workspace, members, and team settings.
        </p>
      </div>

      {/* General Section */}
      <section className="mb-8">
        <h2 className="text-lg font-semibold mb-3">General</h2>
        <div className="bg-card elevation-1 p-4 space-y-4">
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Workspace Name</label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              disabled={!canManage}
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring disabled:opacity-50"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-sm font-medium">Description</label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              disabled={!canManage}
              rows={3}
              placeholder="What this workspace is for..."
              className="w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring resize-none disabled:opacity-50"
            />
          </div>

          <div className="flex items-center justify-between">
            <div className="space-y-0.5">
              <label className="text-sm font-medium">Plan</label>
              <div>
                <span className={`text-xs px-2 py-1 font-medium uppercase tracking-wider ${planColors[currentOrganization.plan] || planColors.free}`}>
                  {currentOrganization.plan}
                </span>
              </div>
            </div>
          </div>

          {canManage && (
            <div className="flex items-center gap-3 pt-2">
              <Button onClick={handleSave} disabled={saving || !name.trim()} size="sm">
                {saving ? (
                  <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
                ) : (
                  <Save className="h-4 w-4 mr-1.5" />
                )}
                Save Changes
              </Button>
            </div>
          )}
        </div>
      </section>

      {/* Members Section */}
      <section className="mb-8">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold">Members</h2>
          {canManage && (
            <Button size="sm" variant="outline" onClick={() => setShowInviteModal(true)}>
              <UserPlus className="h-4 w-4 mr-1.5" />
              Invite Member
            </Button>
          )}
        </div>

        <MembersTable
          members={members}
          currentUserId={user?.id ?? 0}
          currentUserRole={userRole}
          onUpdateRole={handleUpdateRole}
          onRemove={handleRemoveMember}
        />

        {canManage && invitations.length > 0 && (
          <div className="mt-4">
            <InvitationsTable
              invitations={invitations}
              onCancel={handleCancelInvitation}
            />
          </div>
        )}
      </section>

      {/* Danger Zone */}
      {isOwner && (
        <section className="mb-8">
          <h2 className="text-lg font-semibold mb-3 text-destructive">Danger Zone</h2>
          <div className="bg-card elevation-1 border border-destructive/20 p-4">
            {!showDeleteConfirm ? (
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">Delete Workspace</p>
                  <p className="text-xs text-muted-foreground mt-0.5">
                    Permanently delete this workspace and all associated data. This action cannot be undone.
                  </p>
                </div>
                <Button
                  variant="destructive"
                  size="sm"
                  onClick={() => setShowDeleteConfirm(true)}
                >
                  <Trash2 className="h-4 w-4 mr-1.5" />
                  Delete
                </Button>
              </div>
            ) : (
              <div className="space-y-3">
                <div className="flex items-start gap-2">
                  <AlertTriangle className="h-5 w-5 text-destructive shrink-0 mt-0.5" />
                  <div>
                    <p className="text-sm font-medium">Are you sure?</p>
                    <p className="text-xs text-muted-foreground mt-0.5">
                      Type <strong>{currentOrganization.name}</strong> to confirm deletion.
                    </p>
                  </div>
                </div>
                <input
                  type="text"
                  value={deleteConfirmName}
                  onChange={(e) => setDeleteConfirmName(e.target.value)}
                  placeholder={currentOrganization.name}
                  className="w-full px-3 py-2 text-sm border border-destructive/30 bg-background focus:outline-none focus:ring-1 focus:ring-destructive"
                />
                <div className="flex items-center gap-2">
                  <Button
                    variant="destructive"
                    size="sm"
                    onClick={handleDelete}
                    disabled={deleting || deleteConfirmName !== currentOrganization.name}
                  >
                    {deleting ? (
                      <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
                    ) : (
                      <Trash2 className="h-4 w-4 mr-1.5" />
                    )}
                    Delete Workspace
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => { setShowDeleteConfirm(false); setDeleteConfirmName('') }}
                  >
                    Cancel
                  </Button>
                </div>
              </div>
            )}
          </div>
        </section>
      )}

      {/* Invite modal */}
      <InviteMemberModal
        open={showInviteModal}
        onClose={() => setShowInviteModal(false)}
        onInvite={handleInvite}
      />
    </div>
  )
}
