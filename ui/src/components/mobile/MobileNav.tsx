import { useLocation, useNavigate } from 'react-router-dom'
import { LayoutDashboard, ShieldCheck, Power, Settings } from 'lucide-react'

const TABS = [
  { path: '/mobile', icon: LayoutDashboard, label: 'Dashboard' },
  { path: '/mobile/approvals', icon: ShieldCheck, label: 'Approvals' },
  { path: '/mobile/kill', icon: Power, label: 'Kill Switch' },
  { path: '/settings', icon: Settings, label: 'Settings' },
] as const

export function MobileNav() {
  const location = useLocation()
  const navigate = useNavigate()

  return (
    <nav className="fixed bottom-0 left-0 right-0 z-50 border-t border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:hidden">
      <div className="flex items-center justify-around h-16 px-2">
        {TABS.map((tab) => {
          const isActive =
            tab.path === '/mobile'
              ? location.pathname === '/mobile'
              : location.pathname.startsWith(tab.path)
          const Icon = tab.icon

          return (
            <button
              key={tab.path}
              onClick={() => navigate(tab.path)}
              className={`flex flex-col items-center justify-center gap-0.5 flex-1 h-full transition-colors ${
                isActive
                  ? 'text-primary'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              <Icon
                className={`h-5 w-5 ${
                  tab.path === '/mobile/kill' && isActive
                    ? 'text-red-500'
                    : ''
                }`}
              />
              <span className="text-[10px] font-medium leading-tight">
                {tab.label}
              </span>
            </button>
          )
        })}
      </div>
      {/* Safe area for devices with home indicator */}
      <div className="h-[env(safe-area-inset-bottom)]" />
    </nav>
  )
}
