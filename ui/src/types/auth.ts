import type { Organization } from '@/types'

export interface AuthUser {
  id: number
  name: string
  email: string
  avatar: string | null
  auth_provider: string | null
  has_password: boolean
  email_verified_at: string | null
  current_organization_id: number | null
  organizations?: Organization[]
  created_at: string
}
