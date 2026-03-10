import { Sidebar } from './Sidebar'
import { Toast } from './Toast'

export function Layout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen bg-background text-foreground">
      <Sidebar />
      <main className="flex-1 overflow-auto">{children}</main>
      <Toast />
    </div>
  )
}
