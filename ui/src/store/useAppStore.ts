import { create } from 'zustand'
import type { Project, Skill } from '@/types'
import { fetchProjects } from '@/api/client'

export interface PendingEvalGate {
  skillId: number
  skillName: string
  runIds: number[]
  baselineInfo: Array<{
    suite_id: number
    suite_name: string
    run_id: number
    baseline_run_id: number | null
    baseline_score: number | null
  }>
  estDurationSeconds: number
  startedAt: number
}

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

  // Pending eval gates (survive navigation)
  pendingEvalGates: Record<number, PendingEvalGate>
  registerPendingGate: (gate: PendingEvalGate) => void
  clearPendingGate: (skillId: number) => void
}

export const useAppStore = create<AppState>((set) => ({
  projects: [],
  activeProjectId: null,
  isDirty: false,
  toast: null,
  pendingEvalGates: {},

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

  registerPendingGate: (gate) =>
    set((state) => ({
      pendingEvalGates: { ...state.pendingEvalGates, [gate.skillId]: gate },
    })),

  clearPendingGate: (skillId) =>
    set((state) => {
      const next = { ...state.pendingEvalGates }
      delete next[skillId]
      return { pendingEvalGates: next }
    }),
}))
