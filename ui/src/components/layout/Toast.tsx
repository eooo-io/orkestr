import { useAppStore } from '@/store/useAppStore'
import { CheckCircle, XCircle, X } from 'lucide-react'

export function Toast() {
  const { toast, clearToast } = useAppStore()

  if (!toast) return null

  return (
    <div className="fixed bottom-4 right-4 z-50 animate-in fade-in slide-in-from-bottom-2">
      <div
        className={`flex items-center gap-2 px-4 py-3 rounded-lg shadow-lg text-sm ${
          toast.type === 'error'
            ? 'bg-destructive text-white'
            : 'bg-primary text-primary-foreground'
        }`}
      >
        {toast.type === 'error' ? (
          <XCircle className="h-4 w-4" />
        ) : (
          <CheckCircle className="h-4 w-4" />
        )}
        {toast.message}
        <button onClick={clearToast} className="ml-2 opacity-70 hover:opacity-100">
          <X className="h-3 w-3" />
        </button>
      </div>
    </div>
  )
}
