export interface AuthUser {
  id: number
  name: string
  email: string
  avatar: string | null
  auth_provider: string | null
  has_password: boolean
  email_verified_at: string | null
  current_organization_id: number | null
  created_at: string
}
