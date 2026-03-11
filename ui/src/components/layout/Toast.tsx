import { useAppStore } from '@/store/useAppStore'
import { CheckCircle, XCircle, X } from 'lucide-react'

export function Toast() {
  const { toast, clearToast } = useAppStore()

  if (!toast) return null

  return (
    <div className="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-50 animate-in fade-in slide-in-from-bottom-3 max-w-[calc(100vw-2rem)]">
      <div
        className={`flex items-center gap-3 px-4 py-3 text-sm font-medium elevation-4 ${
          toast.type === 'error'
            ? 'bg-destructive text-white'
            : 'bg-card text-foreground border border-border'
        }`}
      >
        {toast.type === 'error' ? (
          <XCircle className="h-4 w-4 shrink-0" />
        ) : (
          <CheckCircle className="h-4 w-4 shrink-0 text-primary" />
        )}
        <span>{toast.message}</span>
        <button
          onClick={clearToast}
          className="ml-1 p-0.5 opacity-60 hover:opacity-100 transition-opacity"
        >
          <X className="h-3.5 w-3.5" />
        </button>
      </div>
    </div>
  )
}
