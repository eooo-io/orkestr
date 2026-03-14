import { useEffect } from 'react'
import { Link, useLocation } from 'react-router-dom'
import {
  FolderOpen,
  Search,
  BookOpen,
  Settings,
  CreditCard,
  Sun,
  Moon,
  Monitor,
  MessageSquare,
  ExternalLink,
  X,
  Brain,
  Building2,
  Shield,
  LayoutDashboard,
  TrendingUp,
} from 'lucide-react'
import { useAppStore } from '@/store/useAppStore'
import { useTheme } from '@/hooks/useTheme'
import { OrganizationSwitcher } from './OrganizationSwitcher'

export function Sidebar({ onClose }: { onClose?: () => void }) {
  const { projects, activeProjectId, setActiveProjectId, loadProjects } =
    useAppStore()
  const location = useLocation()
  const { theme, setTheme } = useTheme()

  useEffect(() => {
    loadProjects()
  }, [loadProjects])

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        const tag = (e.target as HTMLElement)?.tagName
        if (tag === 'INPUT' || tag === 'TEXTAREA') {
          ;(e.target as HTMLElement).blur()
        }
      }
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [])

  const navItems = [
    { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { to: '/projects', label: 'Projects', icon: FolderOpen },
    { to: '/agents', label: 'Agents', icon: Brain },
    { to: '/performance', label: 'Performance', icon: TrendingUp },
    { to: '/audit-log', label: 'Audit Log', icon: Shield },
    { to: '/workspace', label: 'Workspace', icon: Building2 },
    { to: '/search', label: 'Search', icon: Search, shortcut: 'K' },
    { to: '/library', label: 'Library', icon: BookOpen },
    // { to: '/marketplace', label: 'Marketplace', icon: Store },
    { to: '/playground', label: 'Playground', icon: MessageSquare },
    { to: '/settings', label: 'Settings', icon: Settings },
    { to: '/billing', label: 'Billing', icon: CreditCard },
  ]

  const themeOptions = [
    { value: 'light' as const, icon: Sun, label: 'Light' },
    { value: 'dark' as const, icon: Moon, label: 'Dark' },
    { value: 'system' as const, icon: Monitor, label: 'System' },
  ]

  const isActive = (to: string) =>
    location.pathname === to ||
    (to !== '/projects' && location.pathname.startsWith(to))

  return (
    <aside className="w-60 bg-sidebar text-sidebar-foreground flex flex-col h-screen sticky top-0 elevation-3">
      {/* Brand */}
      <div className="px-5 py-4 flex items-center justify-between">
        <Link to="/" className="flex items-center gap-2.5 group" onClick={onClose}>
          <img src="/logo.png" alt="eooo.ai" className="h-8 w-8 object-contain" />
          <div>
            <span className="font-semibold text-[15px] text-sidebar-foreground tracking-tight">
              eooo.ai
            </span>
          </div>
        </Link>
        {onClose && (
          <button
            onClick={onClose}
            className="p-1 text-sidebar-muted hover:text-sidebar-foreground md:hidden"
          >
            <X className="h-5 w-5" />
          </button>
        )}
      </div>

      {/* Organization Switcher */}
      <OrganizationSwitcher />

      {/* Navigation */}
      <nav className="flex-1 px-3 space-y-0.5 overflow-y-auto">
        {navItems.map(({ to, label, icon: Icon, shortcut }) => (
          <Link
            key={to}
            to={to}
            onClick={onClose}
            className={`state-layer flex items-center gap-2.5 px-3 py-2 text-sm transition-all duration-150 ${
              isActive(to)
                ? 'bg-primary/20 text-primary font-medium'
                : 'text-sidebar-muted hover:text-sidebar-foreground'
            }`}
          >
            <Icon className={`h-[18px] w-[18px] ${isActive(to) ? 'text-primary' : ''}`} />
            <span className="flex-1">{label}</span>
            {shortcut && (
              <kbd className="text-[10px] px-1.5 py-0.5 bg-sidebar-accent text-sidebar-muted font-mono">
                {shortcut}
              </kbd>
            )}
          </Link>
        ))}

        {/* Project list */}
        {projects.length > 0 && (
          <div className="pt-5">
            <p className="px-3 text-[11px] font-medium text-sidebar-muted uppercase tracking-widest mb-1.5">
              Projects
            </p>
            {projects.map((project) => {
              const active =
                activeProjectId === project.id ||
                location.pathname === `/projects/${project.id}`
              return (
                <Link
                  key={project.id}
                  to={`/projects/${project.id}`}
                  onClick={() => { setActiveProjectId(project.id); onClose?.() }}
                  className={`state-layer flex items-center justify-between px-3 py-1.5 text-sm transition-all duration-150 ${
                    active
                      ? 'bg-primary/20 text-primary font-medium'
                      : 'text-sidebar-muted hover:text-sidebar-foreground'
                  }`}
                >
                  <span className="truncate">{project.name}</span>
                  <span className={`text-xs tabular-nums ${active ? 'text-primary/60' : 'opacity-40'}`}>
                    {project.skills_count}
                  </span>
                </Link>
              )
            })}
          </div>
        )}
      </nav>

      {/* Footer */}
      <div className="px-3 pb-3 pt-2 space-y-2">
        {/* Theme toggle */}
        <div className="flex items-center bg-sidebar-accent p-1">
          {themeOptions.map(({ value, icon: Icon, label }) => (
            <button
              key={value}
              onClick={() => setTheme(value)}
              title={label}
              className={`flex-1 flex items-center justify-center py-1.5 text-xs transition-all duration-150 ${
                theme === value
                  ? 'bg-primary text-primary-foreground shadow-sm'
                  : 'text-sidebar-muted hover:text-sidebar-foreground'
              }`}
            >
              <Icon className="h-3.5 w-3.5" />
            </button>
          ))}
        </div>

        <a
          href="/admin"
          target="_blank"
          rel="noopener noreferrer"
          className="state-layer flex items-center gap-2 px-3 py-2 text-sm text-sidebar-muted hover:text-sidebar-foreground transition-colors duration-150"
        >
          <ExternalLink className="h-4 w-4" />
          Admin Panel
        </a>
      </div>
    </aside>
  )
}
