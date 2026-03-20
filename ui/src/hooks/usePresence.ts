import { useEffect, useRef, useState, useCallback } from 'react'
import axios from 'axios'

export interface PresenceUser {
  user_id: number
  user_name: string
  cursor_position: { line: number; column: number } | null
  selection: Record<string, unknown> | null
  color: string
  last_seen_at: string
}

interface UsePresenceOptions {
  resourceType: 'skill' | 'agent' | 'workflow'
  resourceId: number | null
  enabled?: boolean
}

interface UsePresenceReturn {
  users: PresenceUser[]
  isConnected: boolean
  conflict: { user_id: number; user_name: string; last_seen_at: string } | null
  sendCursor: (line: number, column: number) => void
}

export function usePresence({
  resourceType,
  resourceId,
  enabled = true,
}: UsePresenceOptions): UsePresenceReturn {
  const [users, setUsers] = useState<PresenceUser[]>([])
  const [isConnected, setIsConnected] = useState(false)
  const [conflict, setConflict] = useState<UsePresenceReturn['conflict']>(null)
  const cursorRef = useRef<{ line: number; column: number } | null>(null)
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const sendHeartbeat = useCallback(async () => {
    if (!resourceId) return

    try {
      const { data } = await axios.post('/api/collaboration/heartbeat', {
        resource_type: resourceType,
        resource_id: resourceId,
        cursor_position: cursorRef.current,
      })

      setIsConnected(true)
      setConflict(data.data?.conflict ?? null)
    } catch {
      setIsConnected(false)
    }
  }, [resourceType, resourceId])

  const fetchPresence = useCallback(async () => {
    if (!resourceId) return

    try {
      const { data } = await axios.get(
        `/api/collaboration/presence/${resourceType}/${resourceId}`
      )
      setUsers(data.data ?? [])
    } catch {
      // Silently ignore — presence is best-effort
    }
  }, [resourceType, resourceId])

  const sendCursor = useCallback((line: number, column: number) => {
    cursorRef.current = { line, column }
  }, [])

  // Start heartbeat + presence polling on mount
  useEffect(() => {
    if (!enabled || !resourceId) {
      setUsers([])
      setIsConnected(false)
      return
    }

    // Immediate heartbeat + presence fetch
    sendHeartbeat()
    fetchPresence()

    // Poll every 5 seconds
    intervalRef.current = setInterval(() => {
      sendHeartbeat()
      fetchPresence()
    }, 5000)

    return () => {
      // Cleanup: stop polling and notify server we left
      if (intervalRef.current) {
        clearInterval(intervalRef.current)
        intervalRef.current = null
      }

      // Fire-and-forget leave request
      axios
        .post('/api/collaboration/leave', {
          resource_type: resourceType,
          resource_id: resourceId,
        })
        .catch(() => {})
    }
  }, [enabled, resourceId, resourceType, sendHeartbeat, fetchPresence])

  return { users, isConnected, conflict, sendCursor }
}
