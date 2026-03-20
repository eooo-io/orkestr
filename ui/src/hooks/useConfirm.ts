import { create } from 'zustand'

interface ConfirmState {
  open: boolean
  title: string
  message: string
  confirmLabel: string
  variant: 'danger' | 'warning' | 'default'
  resolve: ((value: boolean) => void) | null
  confirm: (opts: {
    title?: string
    message: string
    confirmLabel?: string
    variant?: 'danger' | 'warning' | 'default'
  }) => Promise<boolean>
  handleConfirm: () => void
  handleCancel: () => void
}

export const useConfirmStore = create<ConfirmState>((set, get) => ({
  open: false,
  title: 'Confirm',
  message: '',
  confirmLabel: 'Delete',
  variant: 'danger',
  resolve: null,

  confirm: (opts) =>
    new Promise<boolean>((resolve) => {
      set({
        open: true,
        title: opts.title ?? 'Confirm',
        message: opts.message,
        confirmLabel: opts.confirmLabel ?? 'Delete',
        variant: opts.variant ?? 'danger',
        resolve,
      })
    }),

  handleConfirm: () => {
    get().resolve?.(true)
    set({ open: false, resolve: null })
  },

  handleCancel: () => {
    get().resolve?.(false)
    set({ open: false, resolve: null })
  },
}))

export function useConfirm() {
  return useConfirmStore((s) => s.confirm)
}
