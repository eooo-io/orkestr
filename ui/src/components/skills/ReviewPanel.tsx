import { useState, useEffect, useCallback } from 'react'
import { User, Users, CheckCircle, XCircle, Clock, Send, Loader2, Edit3 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  fetchSkillReviews,
  fetchSkillOwnership,
  updateSkillOwnership,
  submitSkillReview,
  approveSkillReview,
  rejectSkillReview,
} from '@/api/client'
import type { SkillReview, SkillOwnership } from '@/types'

interface ReviewPanelProps {
  skillId: number
}

const statusConfig = {
  pending: { color: 'text-yellow-500', bg: 'bg-yellow-500/10 border-yellow-500/30', label: 'Pending', Icon: Clock },
  approved: { color: 'text-green-500', bg: 'bg-green-500/10 border-green-500/30', label: 'Approved', Icon: CheckCircle },
  rejected: { color: 'text-red-500', bg: 'bg-red-500/10 border-red-500/30', label: 'Rejected', Icon: XCircle },
}

export function ReviewPanel({ skillId }: ReviewPanelProps) {
  const [ownership, setOwnership] = useState<SkillOwnership | null>(null)
  const [reviews, setReviews] = useState<SkillReview[]>([])
  const [loading, setLoading] = useState(true)
  const [editingOwnership, setEditingOwnership] = useState(false)
  const [ownerIdInput, setOwnerIdInput] = useState('')
  const [codeownersInput, setCodeownersInput] = useState('')
  const [savingOwnership, setSavingOwnership] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [reviewComment, setReviewComment] = useState('')
  const [actionComment, setActionComment] = useState('')
  const [actioningId, setActioningId] = useState<number | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    try {
      const [ownershipData, reviewsData] = await Promise.all([
        fetchSkillOwnership(skillId),
        fetchSkillReviews(skillId),
      ])
      setOwnership(ownershipData)
      setReviews(reviewsData)
    } catch {
      // silently handle
    } finally {
      setLoading(false)
    }
  }, [skillId])

  useEffect(() => {
    loadData()
  }, [loadData])

  const handleEditOwnership = () => {
    setOwnerIdInput(ownership?.owner_id?.toString() ?? '')
    setCodeownersInput(ownership?.codeowners?.join(', ') ?? '')
    setEditingOwnership(true)
  }

  const handleSaveOwnership = async () => {
    setSavingOwnership(true)
    try {
      const data: { owner_id?: number | null; codeowners?: string[] } = {}
      data.owner_id = ownerIdInput ? Number(ownerIdInput) : null
      data.codeowners = codeownersInput
        ? codeownersInput.split(',').map((s) => s.trim()).filter(Boolean)
        : []
      const updated = await updateSkillOwnership(skillId, data)
      setOwnership(updated)
      setEditingOwnership(false)
    } catch {
      // silently handle
    } finally {
      setSavingOwnership(false)
    }
  }

  const handleSubmitReview = async () => {
    setSubmitting(true)
    try {
      const review = await submitSkillReview(skillId, {
        comments: reviewComment || undefined,
      })
      setReviews((prev) => [review, ...prev])
      setReviewComment('')
    } catch {
      // silently handle
    } finally {
      setSubmitting(false)
    }
  }

  const handleApprove = async (reviewId: number) => {
    setActioningId(reviewId)
    try {
      await approveSkillReview(reviewId, actionComment || undefined)
      await loadData()
      setActionComment('')
    } catch {
      // silently handle
    } finally {
      setActioningId(null)
    }
  }

  const handleReject = async (reviewId: number) => {
    setActioningId(reviewId)
    try {
      await rejectSkillReview(reviewId, actionComment || undefined)
      await loadData()
      setActionComment('')
    } catch {
      // silently handle
    } finally {
      setActioningId(null)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
        <Loader2 className="h-4 w-4 animate-spin mr-2" />
        Loading...
      </div>
    )
  }

  return (
    <div className="flex flex-col h-full">
      {/* Ownership Section */}
      <div className="p-3 border-b border-border">
        <div className="flex items-center justify-between mb-2">
          <h4 className="text-xs font-semibold uppercase text-muted-foreground tracking-wide">
            Ownership
          </h4>
          {!editingOwnership && (
            <Button size="xs" variant="ghost" onClick={handleEditOwnership}>
              <Edit3 className="h-3 w-3" />
            </Button>
          )}
        </div>

        {!editingOwnership ? (
          <div className="space-y-2 text-sm">
            <div className="flex items-center gap-2">
              <User className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
              <span>{ownership?.owner?.name ?? 'Unassigned'}</span>
            </div>
            {ownership?.codeowners && ownership.codeowners.length > 0 && (
              <div className="flex items-start gap-2">
                <Users className="h-3.5 w-3.5 text-muted-foreground shrink-0 mt-0.5" />
                <div className="flex flex-wrap gap-1">
                  {ownership.codeowners.map((co) => (
                    <span
                      key={co}
                      className="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium bg-muted rounded"
                    >
                      {co}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        ) : (
          <div className="space-y-2">
            <div>
              <label className="text-[10px] text-muted-foreground">Owner ID</label>
              <input
                type="text"
                value={ownerIdInput}
                onChange={(e) => setOwnerIdInput(e.target.value)}
                placeholder="User ID"
                className="w-full mt-0.5 px-2 py-1 text-xs border border-border rounded bg-background"
              />
            </div>
            <div>
              <label className="text-[10px] text-muted-foreground">Codeowners (comma-separated)</label>
              <input
                type="text"
                value={codeownersInput}
                onChange={(e) => setCodeownersInput(e.target.value)}
                placeholder="@team-a, @user-b"
                className="w-full mt-0.5 px-2 py-1 text-xs border border-border rounded bg-background"
              />
            </div>
            <div className="flex gap-1">
              <Button size="xs" onClick={handleSaveOwnership} disabled={savingOwnership}>
                {savingOwnership ? <Loader2 className="h-3 w-3 animate-spin" /> : 'Save'}
              </Button>
              <Button
                size="xs"
                variant="ghost"
                onClick={() => setEditingOwnership(false)}
                disabled={savingOwnership}
              >
                Cancel
              </Button>
            </div>
          </div>
        )}
      </div>

      {/* Reviews Section */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <div className="p-3 border-b border-border space-y-2">
          <h4 className="text-xs font-semibold uppercase text-muted-foreground tracking-wide">
            Reviews
          </h4>
          <textarea
            value={reviewComment}
            onChange={(e) => setReviewComment(e.target.value)}
            placeholder="Optional comment..."
            rows={2}
            className="w-full px-2 py-1 text-xs border border-border rounded bg-background resize-none"
          />
          <Button
            size="xs"
            variant="outline"
            onClick={handleSubmitReview}
            disabled={submitting}
            className="w-full"
          >
            {submitting ? (
              <Loader2 className="h-3 w-3 animate-spin mr-1" />
            ) : (
              <Send className="h-3 w-3 mr-1" />
            )}
            Submit for Review
          </Button>
        </div>

        <div className="flex-1 overflow-y-auto">
          {reviews.length === 0 ? (
            <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
              No reviews yet.
            </div>
          ) : (
            <div className="p-3 space-y-2">
              {reviews.map((review) => {
                const config = statusConfig[review.status]
                const isPending = review.status === 'pending'

                return (
                  <div key={review.id} className={`border px-3 py-2 text-xs rounded ${config.bg}`}>
                    <div className="flex items-center justify-between mb-1">
                      <span className="font-medium">
                        {review.submitter?.name ?? `User #${review.submitted_by}`}
                      </span>
                      <span className={`inline-flex items-center gap-1 ${config.color}`}>
                        <config.Icon className="h-3 w-3" />
                        {config.label}
                      </span>
                    </div>

                    {review.reviewer && (
                      <p className="text-[10px] text-muted-foreground">
                        Reviewer: {review.reviewer.name}
                      </p>
                    )}

                    {review.comments && (
                      <p className="text-muted-foreground mt-1">{review.comments}</p>
                    )}

                    {isPending && (
                      <div className="mt-2 space-y-1.5 border-t border-border/50 pt-2">
                        <input
                          type="text"
                          value={actioningId === review.id ? actionComment : ''}
                          onChange={(e) => {
                            setActioningId(review.id)
                            setActionComment(e.target.value)
                          }}
                          placeholder="Optional comment..."
                          className="w-full px-2 py-1 text-[10px] border border-border rounded bg-background"
                        />
                        <div className="flex gap-1">
                          <Button
                            size="xs"
                            variant="outline"
                            onClick={() => handleApprove(review.id)}
                            disabled={actioningId === review.id && !actionComment && actioningId !== null}
                            className="text-green-500 hover:text-green-600"
                          >
                            {actioningId === review.id ? (
                              <Loader2 className="h-3 w-3 animate-spin mr-1" />
                            ) : (
                              <CheckCircle className="h-3 w-3 mr-1" />
                            )}
                            Approve
                          </Button>
                          <Button
                            size="xs"
                            variant="outline"
                            onClick={() => handleReject(review.id)}
                            disabled={actioningId === review.id && !actionComment && actioningId !== null}
                            className="text-red-500 hover:text-red-600"
                          >
                            {actioningId === review.id ? (
                              <Loader2 className="h-3 w-3 animate-spin mr-1" />
                            ) : (
                              <XCircle className="h-3 w-3 mr-1" />
                            )}
                            Reject
                          </Button>
                        </div>
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
