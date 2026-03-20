import { useEffect, useState, useCallback } from 'react'
import { ShieldCheck, RefreshCw, Inbox } from 'lucide-react'
import { ApprovalCard } from '@/components/mobile/ApprovalCard'
import {
  fetchMobilePendingApprovals,
  approveApprovalGate,
  rejectApprovalGate,
} from '@/api/client'
import { useAppStore } from '@/store/useAppStore'

interface Approval {
  id: number
  uuid: string
  agent: { id: number; name: string } | null
  project: { id: number; name: string } | null
  type: string
  title: string
  description: string | null
  status: string
  requested_at: string | null
  expires_at: string | null
}

export function MobileApprovals() {
  const [approvals, setApprovals] = useState<Approval[]>([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [actionLoading, setActionLoading] = useState<number | null>(null)
  const { showToast } = useAppStore()

  const load = useCallback(async () => {
    try {
      const res = await fetchMobilePendingApprovals()
      setApprovals(res?.data ?? res ?? [])
    } catch {
      showToast('Failed to load approvals')
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [showToast])

  useEffect(() => {
    load()
  }, [load])

  const handleRefresh = () => {
    setRefreshing(true)
    load()
  }

  const handleApprove = async (id: number) => {
    setActionLoading(id)
    try {
      await approveApprovalGate(id)
      showToast('Approved')
      setApprovals((prev) => prev.filter((a) => a.id !== id))
    } catch {
      showToast('Failed to approve')
    } finally {
      setActionLoading(null)
    }
  }

  const handleReject = async (id: number) => {
    setActionLoading(id)
    try {
      await rejectApprovalGate(id)
      showToast('Rejected')
      setApprovals((prev) => prev.filter((a) => a.id !== id))
    } catch {
      showToast('Failed to reject')
    } finally {
      setActionLoading(null)
    }
  }

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-border">
        <div className="flex items-center gap-2">
          <ShieldCheck className="h-5 w-5 text-primary" />
          <h1 className="text-base font-semibold">Pending Approvals</h1>
          {approvals.length > 0 && (
            <span className="inline-flex items-center justify-center h-5 min-w-[20px] rounded-full bg-primary text-[10px] font-bold text-primary-foreground px-1.5">
              {approvals.length}
            </span>
          )}
        </div>
        <button
          onClick={handleRefresh}
          disabled={refreshing}
          className="p-2 rounded-lg transition-colors hover:bg-muted disabled:opacity-50"
          aria-label="Refresh"
        >
          <RefreshCw
            className={`h-4 w-4 text-muted-foreground ${
              refreshing ? 'animate-spin' : ''
            }`}
          />
        </button>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-y-auto pb-20">
        {loading ? (
          <div className="flex items-center justify-center py-16">
            <RefreshCw className="h-5 w-5 animate-spin text-muted-foreground" />
          </div>
        ) : approvals.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 px-4 text-center space-y-3">
            <Inbox className="h-12 w-12 text-muted-foreground/40" />
            <div>
              <p className="text-sm font-medium text-muted-foreground">
                No pending approvals
              </p>
              <p className="text-xs text-muted-foreground/70 mt-1">
                Approval requests from running agents will appear here.
              </p>
            </div>
          </div>
        ) : (
          <div className="p-4 space-y-3">
            {approvals.map((approval) => (
              <ApprovalCard
                key={approval.id}
                approval={approval}
                onApprove={handleApprove}
                onReject={handleReject}
                loading={actionLoading === approval.id}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
