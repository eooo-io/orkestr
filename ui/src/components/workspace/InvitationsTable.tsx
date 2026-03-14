import { useState } from 'react'
import { Loader2, X } from 'lucide-react'
import type { OrganizationInvitation } from '@/types'
import { Button } from '@/components/ui/button'

interface InvitationsTableProps {
  invitations: OrganizationInvitation[]
  onCancel: (invitationId: number) => Promise<void>
}

export function InvitationsTable({ invitations, onCancel }: InvitationsTableProps) {
  const [cancelling, setCancelling] = useState<number | null>(null)

  if (invitations.length === 0) return null

  const handleCancel = async (id: number) => {
    setCancelling(id)
    try {
      await onCancel(id)
    } finally {
      setCancelling(null)
    }
  }

  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr)
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
  }

  const isExpired = (dateStr: string) => {
    return new Date(dateStr) < new Date()
  }

  return (
    <div>
      <h3 className="text-sm font-medium text-muted-foreground mb-2">Pending Invitations</h3>
      <div className="bg-card elevation-1 overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border bg-muted/30">
              <th className="text-left px-4 py-2.5 font-medium">Email</th>
              <th className="text-left px-4 py-2.5 font-medium">Role</th>
              <th className="text-left px-4 py-2.5 font-medium">Invited By</th>
              <th className="text-left px-4 py-2.5 font-medium">Expires</th>
              <th className="text-right px-4 py-2.5 font-medium w-20">Actions</th>
            </tr>
          </thead>
          <tbody>
            {invitations.map((inv) => (
              <tr key={inv.id} className="border-b border-border last:border-0">
                <td className="px-4 py-2.5 font-medium">{inv.email}</td>
                <td className="px-4 py-2.5 capitalize text-muted-foreground">{inv.role}</td>
                <td className="px-4 py-2.5 text-muted-foreground">
                  {inv.invited_by?.name || '-'}
                </td>
                <td className="px-4 py-2.5">
                  <span className={isExpired(inv.expires_at) ? 'text-destructive' : 'text-muted-foreground'}>
                    {isExpired(inv.expires_at) ? 'Expired' : formatDate(inv.expires_at)}
                  </span>
                </td>
                <td className="px-4 py-2.5 text-right">
                  <Button
                    size="icon-xs"
                    variant="ghost"
                    onClick={() => handleCancel(inv.id)}
                    disabled={cancelling === inv.id}
                    title="Cancel invitation"
                  >
                    {cancelling === inv.id ? (
                      <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    ) : (
                      <X className="h-3.5 w-3.5 text-muted-foreground hover:text-destructive" />
                    )}
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
