import { useEffect, useState, useCallback } from 'react'
import { MessageSquare, Check, Reply, Trash2 } from 'lucide-react'
import axios from 'axios'

interface CommentUser {
  id: number
  name: string
  email: string
}

interface Comment {
  id: number
  uuid: string
  user_id: number
  user: CommentUser
  resource_type: string
  resource_id: number
  thread_id: number | null
  line_number: number | null
  body: string
  resolved_at: string | null
  resolved_by: CommentUser | null
  replies: Comment[]
  created_at: string
}

interface CommentThreadProps {
  resourceType: 'skill' | 'agent' | 'workflow'
  resourceId: number
  currentUserId?: number
}

export function CommentThread({
  resourceType,
  resourceId,
  currentUserId,
}: CommentThreadProps) {
  const [comments, setComments] = useState<Comment[]>([])
  const [loading, setLoading] = useState(true)
  const [newBody, setNewBody] = useState('')
  const [replyingTo, setReplyingTo] = useState<number | null>(null)
  const [replyBody, setReplyBody] = useState('')
  const [filter, setFilter] = useState<'all' | 'unresolved'>('all')
  const [submitting, setSubmitting] = useState(false)

  const fetchComments = useCallback(async () => {
    try {
      const params: Record<string, string> = {}
      if (filter === 'unresolved') params.resolved = 'false'

      const { data } = await axios.get(
        `/api/collaboration/comments/${resourceType}/${resourceId}`,
        { params }
      )
      setComments(data.data ?? [])
    } catch {
      // Silently handle
    } finally {
      setLoading(false)
    }
  }, [resourceType, resourceId, filter])

  useEffect(() => {
    fetchComments()
  }, [fetchComments])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!newBody.trim() || submitting) return

    setSubmitting(true)
    try {
      await axios.post(
        `/api/collaboration/comments/${resourceType}/${resourceId}`,
        { body: newBody.trim() }
      )
      setNewBody('')
      fetchComments()
    } catch {
      // Handle error
    } finally {
      setSubmitting(false)
    }
  }

  const handleReply = async (threadId: number) => {
    if (!replyBody.trim() || submitting) return

    setSubmitting(true)
    try {
      await axios.post(
        `/api/collaboration/comments/${resourceType}/${resourceId}`,
        { body: replyBody.trim(), thread_id: threadId }
      )
      setReplyBody('')
      setReplyingTo(null)
      fetchComments()
    } catch {
      // Handle error
    } finally {
      setSubmitting(false)
    }
  }

  const handleResolve = async (commentId: number) => {
    try {
      await axios.post(`/api/collaboration/comments/${commentId}/resolve`)
      fetchComments()
    } catch {
      // Handle error
    }
  }

  const handleDelete = async (commentId: number) => {
    try {
      await axios.delete(`/api/collaboration/comments/${commentId}`)
      fetchComments()
    } catch {
      // Handle error
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8 text-sm text-muted-foreground">
        Loading comments...
      </div>
    )
  }

  return (
    <div className="flex flex-col h-full">
      {/* Header with filter */}
      <div className="flex items-center justify-between border-b px-4 py-2">
        <div className="flex items-center gap-2 text-sm font-medium">
          <MessageSquare className="h-4 w-4" />
          Comments ({comments.length})
        </div>
        <div className="flex gap-1">
          <button
            onClick={() => setFilter('all')}
            className={`rounded px-2 py-1 text-xs transition-colors ${
              filter === 'all'
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            All
          </button>
          <button
            onClick={() => setFilter('unresolved')}
            className={`rounded px-2 py-1 text-xs transition-colors ${
              filter === 'unresolved'
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:text-foreground'
            }`}
          >
            Unresolved
          </button>
        </div>
      </div>

      {/* Comments list */}
      <div className="flex-1 overflow-y-auto p-4 space-y-4">
        {comments.length === 0 ? (
          <div className="text-center text-sm text-muted-foreground py-8">
            No comments yet. Start a conversation below.
          </div>
        ) : (
          comments.map((comment) => (
            <CommentItem
              key={comment.id}
              comment={comment}
              currentUserId={currentUserId}
              replyingTo={replyingTo}
              replyBody={replyBody}
              submitting={submitting}
              onReplyClick={(id) => {
                setReplyingTo(replyingTo === id ? null : id)
                setReplyBody('')
              }}
              onReplyBodyChange={setReplyBody}
              onReplySubmit={handleReply}
              onResolve={handleResolve}
              onDelete={handleDelete}
            />
          ))
        )}
      </div>

      {/* New comment form */}
      <form onSubmit={handleSubmit} className="border-t p-4">
        <textarea
          value={newBody}
          onChange={(e) => setNewBody(e.target.value)}
          placeholder="Add a comment..."
          rows={2}
          className="w-full resize-none rounded-md border bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
        />
        <div className="mt-2 flex justify-end">
          <button
            type="submit"
            disabled={!newBody.trim() || submitting}
            className="rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
          >
            {submitting ? 'Posting...' : 'Comment'}
          </button>
        </div>
      </form>
    </div>
  )
}

interface CommentItemProps {
  comment: Comment
  currentUserId?: number
  replyingTo: number | null
  replyBody: string
  submitting: boolean
  onReplyClick: (id: number) => void
  onReplyBodyChange: (body: string) => void
  onReplySubmit: (threadId: number) => void
  onResolve: (id: number) => void
  onDelete: (id: number) => void
}

function CommentItem({
  comment,
  currentUserId,
  replyingTo,
  replyBody,
  submitting,
  onReplyClick,
  onReplyBodyChange,
  onReplySubmit,
  onResolve,
  onDelete,
}: CommentItemProps) {
  const isAuthor = currentUserId === comment.user_id
  const isResolved = !!comment.resolved_at

  return (
    <div
      className={`rounded-lg border p-3 ${isResolved ? 'border-muted bg-muted/30' : 'border-border'}`}
    >
      {/* Comment header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary/20 text-[10px] font-semibold text-primary">
            {comment.user?.name?.slice(0, 2).toUpperCase() ?? '??'}
          </div>
          <span className="text-sm font-medium">{comment.user?.name ?? 'Unknown'}</span>
          {comment.line_number && (
            <span className="text-xs text-muted-foreground">
              Line {comment.line_number}
            </span>
          )}
        </div>
        <div className="flex items-center gap-1">
          <span className="text-xs text-muted-foreground">
            {formatRelativeTime(comment.created_at)}
          </span>
          {isResolved && (
            <span className="ml-1 rounded bg-green-500/10 px-1.5 py-0.5 text-[10px] font-medium text-green-600">
              Resolved
            </span>
          )}
        </div>
      </div>

      {/* Comment body */}
      <p className="mt-2 text-sm text-foreground whitespace-pre-wrap">{comment.body}</p>

      {/* Actions */}
      <div className="mt-2 flex items-center gap-2">
        <button
          onClick={() => onReplyClick(comment.id)}
          className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
        >
          <Reply className="h-3 w-3" />
          Reply
        </button>
        {!isResolved && (
          <button
            onClick={() => onResolve(comment.id)}
            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-green-600 transition-colors"
          >
            <Check className="h-3 w-3" />
            Resolve
          </button>
        )}
        {isAuthor && (
          <button
            onClick={() => onDelete(comment.id)}
            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-red-500 transition-colors"
          >
            <Trash2 className="h-3 w-3" />
            Delete
          </button>
        )}
      </div>

      {/* Replies */}
      {comment.replies?.length > 0 && (
        <div className="mt-3 ml-4 space-y-3 border-l-2 border-muted pl-3">
          {comment.replies.map((reply) => (
            <div key={reply.id}>
              <div className="flex items-center gap-2">
                <div className="flex h-5 w-5 items-center justify-center rounded-full bg-primary/20 text-[9px] font-semibold text-primary">
                  {reply.user?.name?.slice(0, 2).toUpperCase() ?? '??'}
                </div>
                <span className="text-xs font-medium">{reply.user?.name ?? 'Unknown'}</span>
                <span className="text-xs text-muted-foreground">
                  {formatRelativeTime(reply.created_at)}
                </span>
                {isAuthor && (
                  <button
                    onClick={() => onDelete(reply.id)}
                    className="ml-auto text-muted-foreground hover:text-red-500"
                  >
                    <Trash2 className="h-3 w-3" />
                  </button>
                )}
              </div>
              <p className="mt-1 text-xs text-foreground whitespace-pre-wrap">{reply.body}</p>
            </div>
          ))}
        </div>
      )}

      {/* Reply form */}
      {replyingTo === comment.id && (
        <div className="mt-3 ml-4 border-l-2 border-primary/30 pl-3">
          <textarea
            value={replyBody}
            onChange={(e) => onReplyBodyChange(e.target.value)}
            placeholder="Write a reply..."
            rows={2}
            className="w-full resize-none rounded-md border bg-background px-3 py-2 text-xs placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
            autoFocus
          />
          <div className="mt-1 flex gap-2 justify-end">
            <button
              onClick={() => onReplyClick(comment.id)}
              className="rounded px-2 py-1 text-xs text-muted-foreground hover:text-foreground"
            >
              Cancel
            </button>
            <button
              onClick={() => onReplySubmit(comment.id)}
              disabled={!replyBody.trim() || submitting}
              className="rounded bg-primary px-2 py-1 text-xs font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
            >
              Reply
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

function formatRelativeTime(dateStr: string): string {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffSecs = Math.floor(diffMs / 1000)
  const diffMins = Math.floor(diffSecs / 60)
  const diffHours = Math.floor(diffMins / 60)
  const diffDays = Math.floor(diffHours / 24)

  if (diffSecs < 60) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays < 7) return `${diffDays}d ago`
  return date.toLocaleDateString()
}
