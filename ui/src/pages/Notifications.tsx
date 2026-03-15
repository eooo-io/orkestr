import { useState, useEffect } from 'react'
import {
  Bell,
  BellOff,
  Check,
  CheckCheck,
  Loader2,
  Info,
  AlertTriangle,
  MessageSquare,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  fetchNotifications,
  markNotificationRead,
  markAllNotificationsRead,
} from '@/api/client'
import type { Notification } from '@/types'

function typeIcon(type: string) {
  if (type.includes('alert') || type.includes('error') || type.includes('fail'))
    return <AlertTriangle className="h-4 w-4 text-amber-400" />
  if (type.includes('message') || type.includes('comment'))
    return <MessageSquare className="h-4 w-4 text-blue-400" />
  return <Info className="h-4 w-4 text-muted-foreground" />
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

export function Notifications() {
  const [notifications, setNotifications] = useState<Notification[]>([])
  const [unreadCount, setUnreadCount] = useState(0)
  const [loading, setLoading] = useState(true)
  const [markingAll, setMarkingAll] = useState(false)
  const [markingId, setMarkingId] = useState<number | null>(null)

  const load = () => {
    setLoading(true)
    fetchNotifications()
      .then((res) => {
        setNotifications(res.data)
        setUnreadCount(res.unread_count)
      })
      .catch(() => {})
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    load()
  }, [])

  const handleMarkRead = (id: number) => {
    setMarkingId(id)
    markNotificationRead(id)
      .then(() => {
        setNotifications((prev) =>
          prev.map((n) =>
            n.id === id ? { ...n, read_at: new Date().toISOString() } : n,
          ),
        )
        setUnreadCount((c) => Math.max(0, c - 1))
      })
      .catch(() => {})
      .finally(() => setMarkingId(null))
  }

  const handleMarkAllRead = () => {
    setMarkingAll(true)
    markAllNotificationsRead()
      .then(() => {
        setNotifications((prev) =>
          prev.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() })),
        )
        setUnreadCount(0)
      })
      .catch(() => {})
      .finally(() => setMarkingAll(false))
  }

  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold flex items-center gap-2">
            <Bell className="h-5 w-5 text-primary" />
            Notifications
            {unreadCount > 0 && (
              <span className="ml-1 inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium bg-primary text-primary-foreground rounded-full">
                {unreadCount}
              </span>
            )}
          </h1>
          <p className="text-sm text-muted-foreground">
            Stay up to date with activity across your projects
          </p>
        </div>
        {unreadCount > 0 && (
          <Button
            variant="outline"
            size="sm"
            onClick={handleMarkAllRead}
            disabled={markingAll}
          >
            {markingAll ? (
              <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
            ) : (
              <CheckCheck className="h-4 w-4 mr-1.5" />
            )}
            Mark All Read
          </Button>
        )}
      </div>

      {/* List */}
      {loading ? (
        <div className="flex items-center justify-center h-40">
          <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
        </div>
      ) : notifications.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-20 text-muted-foreground">
          <BellOff className="h-10 w-10 mb-3 opacity-40" />
          <p className="text-sm">No notifications yet</p>
        </div>
      ) : (
        <div className="space-y-1">
          {notifications.map((n) => {
            const isUnread = !n.read_at
            return (
              <button
                key={n.id}
                onClick={() => isUnread && handleMarkRead(n.id)}
                disabled={!isUnread || markingId === n.id}
                className={`w-full text-left flex items-start gap-3 px-4 py-3 border border-border transition-colors ${
                  isUnread
                    ? 'bg-card elevation-1 hover:bg-muted/40 cursor-pointer'
                    : 'bg-transparent opacity-60 cursor-default'
                }`}
              >
                <div className="mt-0.5 shrink-0">{typeIcon(n.type)}</div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <span className={`text-sm ${isUnread ? 'font-medium' : ''}`}>
                      {n.title}
                    </span>
                    {isUnread && (
                      <span className="shrink-0 h-2 w-2 rounded-full bg-primary" />
                    )}
                  </div>
                  {n.body && (
                    <p className="text-sm text-muted-foreground mt-0.5 truncate">
                      {n.body}
                    </p>
                  )}
                  <span className="text-xs text-muted-foreground mt-1 block">
                    {timeAgo(n.created_at)}
                  </span>
                </div>
                <div className="shrink-0 mt-1">
                  {markingId === n.id ? (
                    <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                  ) : isUnread ? (
                    <Check className="h-4 w-4 text-muted-foreground opacity-0 group-hover:opacity-100" />
                  ) : (
                    <Check className="h-4 w-4 text-muted-foreground" />
                  )}
                </div>
              </button>
            )
          })}
        </div>
      )}
    </div>
  )
}
