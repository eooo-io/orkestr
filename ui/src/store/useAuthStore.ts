import { create } from 'zustand'
import type { AuthUser } from '@/types/auth'
import type { Organization } from '@/types'
import api from '@/api/client'
import { fetchOrganizations as apiFetchOrganizations, switchOrganization as apiSwitchOrganization } from '@/api/client'

interface AuthState {
  user: AuthUser | null
  loading: boolean
  initialized: boolean
  organizations: Organization[]
  currentOrganization: Organization | null

  fetchUser: () => Promise<void>
  login: (email: string, password: string, remember?: boolean) => Promise<void>
  register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>
  logout: () => Promise<void>
  setUser: (user: AuthUser | null) => void
  fetchOrganizations: () => Promise<void>
  switchOrg: (orgId: number) => Promise<void>
}

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  loading: false,
  initialized: false,
  organizations: [],
  currentOrganization: null,

  fetchUser: async () => {
    try {
      const response = await api.get<{ user: AuthUser }>('/auth/me')
      const user = response.data.user
      set({ user, initialized: true })

      // Also fetch organizations
      try {
        const orgs = await apiFetchOrganizations()
        const current = orgs.find((o) => o.id === user.current_organization_id) || orgs[0] || null
        set({ organizations: orgs, currentOrganization: current })
      } catch {
        // Organizations may not be available yet
      }
    } catch {
      set({ user: null, initialized: true })
    }
  },

  login: async (email, password, remember = false) => {
    set({ loading: true })
    try {
      const response = await api.post<{ user: AuthUser }>('/auth/login', {
        email,
        password,
        remember,
      })
      set({ user: response.data.user, loading: false })

      // Fetch orgs after login
      try {
        const orgs = await apiFetchOrganizations()
        const current = orgs.find((o) => o.id === response.data.user.current_organization_id) || orgs[0] || null
        set({ organizations: orgs, currentOrganization: current })
      } catch {
        // Organizations may not be available yet
      }
    } catch (error) {
      set({ loading: false })
      throw error
    }
  },

  register: async (name, email, password, passwordConfirmation) => {
    set({ loading: true })
    try {
      const response = await api.post<{ user: AuthUser }>('/auth/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      set({ user: response.data.user, loading: false })
    } catch (error) {
      set({ loading: false })
      throw error
    }
  },

  logout: async () => {
    try {
      await api.post('/auth/logout')
    } finally {
      set({ user: null, organizations: [], currentOrganization: null })
    }
  },

  setUser: (user) => set({ user }),

  fetchOrganizations: async () => {
    try {
      const orgs = await apiFetchOrganizations()
      const user = get().user
      const current = orgs.find((o) => o.id === user?.current_organization_id) || orgs[0] || null
      set({ organizations: orgs, currentOrganization: current })
    } catch {
      // ignore
    }
  },

  switchOrg: async (orgId: number) => {
    await apiSwitchOrganization(orgId)
    // Refresh user to get new current_organization_id
    const response = await api.get<{ user: AuthUser }>('/auth/me')
    const user = response.data.user
    const orgs = get().organizations
    const current = orgs.find((o) => o.id === orgId) || null
    set({ user, currentOrganization: current })
  },
}))
