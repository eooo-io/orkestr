import { useState } from 'react'
import { Loader2, Trash2 } from 'lucide-react'
import type { OrganizationMember } from '@/types'
import { Button } from '@/components/ui/button'

interface MembersTableProps {
  members: OrganizationMember[]
  currentUserId: number
  currentUserRole: string
  onUpdateRole: (userId: number, role: string) => Promise<void>
  onRemove: (userId: number) => Promise<void>
}

const ROLE_OPTIONS = ['admin', 'editor', 'viewer', 'member'] as const

function UserAvatar({ name, avatar }: { name: string; avatar: string | null }) {
  if (avatar) {
    return <img src={avatar} alt={name} className="h-8 w-8 rounded-full object-cover" />
  }
  const initials = name
    .split(' ')
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
  return (
    <div className="h-8 w-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-medium">
      {initials}
    </div>
  )
}

export function MembersTable({ members, currentUserId, currentUserRole, onUpdateRole, onRemove }: MembersTableProps) {
  const [updatingRole, setUpdatingRole] = useState<number | null>(null)
  const [removing, setRemoving] = useState<number | null>(null)
  const [confirmRemove, setConfirmRemove] = useState<number | null>(null)

  const canManageMembers = currentUserRole === 'owner' || currentUserRole === 'admin'

  const handleRoleChange = async (userId: number, role: string) => {
    setUpdatingRole(userId)
    try {
      await onUpdateRole(userId, role)
    } finally {
      setUpdatingRole(null)
    }
  }

  const handleRemove = async (userId: number) => {
    setRemoving(userId)
    try {
      await onRemove(userId)
      setConfirmRemove(null)
    } finally {
      setRemoving(null)
    }
  }

  return (
    <div className="bg-card elevation-1 overflow-hidden">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-border bg-muted/30">
            <th className="text-left px-4 py-2.5 font-medium">Member</th>
            <th className="text-left px-4 py-2.5 font-medium">Email</th>
            <th className="text-left px-4 py-2.5 font-medium">Role</th>
            {canManageMembers && (
              <th className="text-right px-4 py-2.5 font-medium w-24">Actions</th>
            )}
          </tr>
        </thead>
        <tbody>
          {members.map((member) => {
            const isOwner = member.role === 'owner'
            const isSelf = member.id === currentUserId
            const canEdit = canManageMembers && !isOwner && !isSelf

            return (
              <tr key={member.id} className="border-b border-border last:border-0">
                <td className="px-4 py-2.5">
                  <div className="flex items-center gap-2.5">
                    <UserAvatar name={member.name} avatar={member.avatar} />
                    <span className="font-medium">{member.name}</span>
                    {isSelf && (
                      <span className="text-[10px] px-1.5 py-0.5 bg-muted text-muted-foreground uppercase tracking-wider">
                        You
                      </span>
                    )}
                  </div>
                </td>
                <td className="px-4 py-2.5 text-muted-foreground">{member.email}</td>
                <td className="px-4 py-2.5">
                  {canEdit ? (
                    <div className="relative inline-block">
                      <select
                        value={member.role}
                        onChange={(e) => handleRoleChange(member.id, e.target.value)}
                        disabled={updatingRole === member.id}
                        className="text-sm border border-input bg-background px-2 py-1 pr-7 focus:outline-none focus:ring-1 focus:ring-ring appearance-none cursor-pointer"
                      >
                        {ROLE_OPTIONS.map((role) => (
                          <option key={role} value={role}>
                            {role.charAt(0).toUpperCase() + role.slice(1)}
                          </option>
                        ))}
                      </select>
                      {updatingRole === member.id && (
                        <Loader2 className="absolute right-1.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 animate-spin text-muted-foreground" />
                      )}
                    </div>
                  ) : (
                    <span className="text-sm capitalize">{member.role}</span>
                  )}
                </td>
                {canManageMembers && (
                  <td className="px-4 py-2.5 text-right">
                    {canEdit && (
                      confirmRemove === member.id ? (
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            size="xs"
                            variant="destructive"
                            onClick={() => handleRemove(member.id)}
                            disabled={removing === member.id}
                          >
                            {removing === member.id ? (
                              <Loader2 className="h-3 w-3 animate-spin" />
                            ) : (
                              'Confirm'
                            )}
                          </Button>
                          <Button
                            size="xs"
                            variant="ghost"
                            onClick={() => setConfirmRemove(null)}
                          >
                            Cancel
                          </Button>
                        </div>
                      ) : (
                        <Button
                          size="icon-xs"
                          variant="ghost"
                          onClick={() => setConfirmRemove(member.id)}
                          title="Remove member"
                        >
                          <Trash2 className="h-3.5 w-3.5 text-muted-foreground hover:text-destructive" />
                        </Button>
                      )
                    )}
                  </td>
                )}
              </tr>
            )
          })}
          {members.length === 0 && (
            <tr>
              <td colSpan={canManageMembers ? 4 : 3} className="px-4 py-8 text-center text-muted-foreground">
                No members yet.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
