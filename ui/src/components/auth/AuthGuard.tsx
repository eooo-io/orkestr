import { useEffect } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import { useAuthStore } from '@/store/useAuthStore'

interface Props {
  children: React.ReactNode
}

export function AuthGuard({ children }: Props) {
  const { user, initialized, fetchUser } = useAuthStore()
  const location = useLocation()

  useEffect(() => {
    if (!initialized) {
      fetchUser()
    }
  }, [initialized, fetchUser])

  if (!initialized) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  if (!user) {
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  return <>{children}</>
}
