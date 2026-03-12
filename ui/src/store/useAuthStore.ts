import { create } from 'zustand'
import type { AuthUser } from '@/types/auth'
import api from '@/api/client'

interface AuthState {
  user: AuthUser | null
  loading: boolean
  initialized: boolean

  fetchUser: () => Promise<void>
  login: (email: string, password: string, remember?: boolean) => Promise<void>
  register: (name: string, email: string, password: string, passwordConfirmation: string) => Promise<void>
  logout: () => Promise<void>
  setUser: (user: AuthUser | null) => void
}

export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  loading: false,
  initialized: false,

  fetchUser: async () => {
    try {
      const response = await api.get<{ user: AuthUser }>('/auth/me')
      set({ user: response.data.user, initialized: true })
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
      set({ user: null })
    }
  },

  setUser: (user) => set({ user }),
}))
