import type { PresenceUser } from '@/hooks/usePresence'

interface PresenceAvatarsProps {
  users: PresenceUser[]
  maxVisible?: number
  currentUserId?: number
}

export function PresenceAvatars({
  users,
  maxVisible = 5,
  currentUserId,
}: PresenceAvatarsProps) {
  // Filter out the current user from the display
  const otherUsers = currentUserId
    ? users.filter((u) => u.user_id !== currentUserId)
    : users

  if (otherUsers.length === 0) return null

  const visible = otherUsers.slice(0, maxVisible)
  const overflow = otherUsers.length - maxVisible

  return (
    <div className="flex items-center gap-1">
      <div className="flex -space-x-2">
        {visible.map((user) => (
          <div key={user.user_id} className="group relative">
            <div
              className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-background text-[10px] font-semibold text-white shadow-sm transition-transform hover:scale-110 hover:z-10"
              style={{ backgroundColor: user.color }}
              title={user.user_name}
            >
              {getInitials(user.user_name)}
            </div>

            {/* Tooltip on hover */}
            <div className="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-popover px-2 py-1 text-xs text-popover-foreground shadow-md opacity-0 transition-opacity group-hover:opacity-100">
              {user.user_name}
              {user.cursor_position && (
                <span className="ml-1 text-muted-foreground">
                  L{user.cursor_position.line}:{user.cursor_position.column}
                </span>
              )}
            </div>
          </div>
        ))}

        {overflow > 0 && (
          <div className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-background bg-muted text-[10px] font-semibold text-muted-foreground shadow-sm">
            +{overflow}
          </div>
        )}
      </div>

      <span className="ml-1 text-xs text-muted-foreground">
        {otherUsers.length} editing
      </span>
    </div>
  )
}

function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}
