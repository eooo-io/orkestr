import { create } from 'zustand'
import type { Project } from '@/types'

interface AppState {
  projects: Project[]
  activeProjectId: number | null
  isDirty: boolean
  setProjects: (projects: Project[]) => void
  setActiveProjectId: (id: number | null) => void
  setDirty: (dirty: boolean) => void
}

export const useAppStore = create<AppState>((set) => ({
  projects: [],
  activeProjectId: null,
  isDirty: false,
  setProjects: (projects) => set({ projects }),
  setActiveProjectId: (id) => set({ activeProjectId: id }),
  setDirty: (dirty) => set({ isDirty: dirty }),
}))
