import { create } from 'zustand'
import type { Project, Skill } from '@/types'
import { fetchProjects } from '@/api/client'

interface AppState {
  // Projects
  projects: Project[]
  activeProjectId: number | null
  setProjects: (projects: Project[]) => void
  setActiveProjectId: (id: number | null) => void
  loadProjects: () => Promise<void>

  // Editor state
  isDirty: boolean
  setDirty: (dirty: boolean) => void

  // Toast
  toast: { message: string; type: 'success' | 'error' } | null
  showToast: (message: string, type?: 'success' | 'error') => void
  clearToast: () => void
}

export const useAppStore = create<AppState>((set) => ({
  projects: [],
  activeProjectId: null,
  isDirty: false,
  toast: null,

  setProjects: (projects) => set({ projects }),
  setActiveProjectId: (id) => set({ activeProjectId: id }),
  setDirty: (dirty) => set({ isDirty: dirty }),

  loadProjects: async () => {
    const projects = await fetchProjects()
    set({ projects })
  },

  showToast: (message, type = 'success') => {
    set({ toast: { message, type } })
    setTimeout(() => set({ toast: null }), 3000)
  },

  clearToast: () => set({ toast: null }),
}))
