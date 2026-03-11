import { useState } from 'react'
import { Menu } from 'lucide-react'
import { Sidebar } from './Sidebar'
import { Toast } from './Toast'

export function Layout({ children }: { children: React.ReactNode }) {
  const [sidebarOpen, setSidebarOpen] = useState(false)

  return (
    <div className="flex min-h-screen bg-background text-foreground">
      {/* Mobile top bar */}
      <div className="fixed top-0 left-0 right-0 z-40 flex items-center gap-3 px-4 py-3 bg-sidebar text-sidebar-foreground elevation-3 md:hidden">
        <button
          onClick={() => setSidebarOpen(true)}
          className="p-1 text-sidebar-muted hover:text-sidebar-foreground transition-colors"
        >
          <Menu className="h-5 w-5" />
        </button>
        <div className="flex items-center gap-2">
          <div className="h-6 w-6 bg-primary flex items-center justify-center">
            <span className="text-primary-foreground font-bold text-[11px]">A</span>
          </div>
          <span className="font-semibold text-sm tracking-tight">Agentis Studio</span>
        </div>
      </div>

      {/* Mobile sidebar overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-50 bg-foreground/30 backdrop-blur-sm md:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <div
        className={`fixed inset-y-0 left-0 z-50 transform transition-transform duration-200 md:relative md:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <Sidebar onClose={() => setSidebarOpen(false)} />
      </div>

      <main className="flex-1 overflow-auto pt-14 md:pt-0">
        {children}
      </main>
      <Toast />
    </div>
  )
}
