import { useEffect } from 'react'
import { Link, useLocation } from 'react-router-dom'
import {
  FolderOpen,
  Search,
  BookOpen,
  Store,
  Settings,
  Zap,
  Sun,
  Moon,
  Monitor,
  MessageSquare,
} from 'lucide-react'
import { useAppStore } from '@/store/useAppStore'
import { useTheme } from '@/hooks/useTheme'

export function Sidebar() {
  const { projects, activeProjectId, setActiveProjectId, loadProjects } =
    useAppStore()
  const location = useLocation()
  const { theme, setTheme } = useTheme()

  useEffect(() => {
    loadProjects()
  }, [loadProjects])

  // Global keyboard shortcuts (Ctrl+K is handled by CommandPalette)
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
    { to: '/projects', label: 'Projects', icon: FolderOpen },
    { to: '/search', label: 'Search', icon: Search, shortcut: 'K' },
    { to: '/library', label: 'Library', icon: BookOpen },
    { to: '/marketplace', label: 'Marketplace', icon: Store },
    { to: '/playground', label: 'Playground', icon: MessageSquare },
    { to: '/settings', label: 'Settings', icon: Settings },
  ]

  const themeOptions = [
    { value: 'light' as const, icon: Sun, label: 'Light' },
    { value: 'dark' as const, icon: Moon, label: 'Dark' },
    { value: 'system' as const, icon: Monitor, label: 'System' },
  ]

  return (
    <aside className="w-60 border-r border-border bg-sidebar text-sidebar-foreground flex flex-col h-screen sticky top-0">
      <div className="p-4 border-b border-border">
        <Link to="/" className="flex items-center gap-2 font-semibold text-lg">
          <Zap className="h-5 w-5 text-primary" />
          Agentis Studio
        </Link>
      </div>

      <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
        {navItems.map(({ to, label, icon: Icon, shortcut }) => (
          <Link
            key={to}
            to={to}
            className={`flex items-center gap-2 px-3 py-2 rounded-md text-sm transition-colors ${
              location.pathname === to ||
              (to !== '/projects' && location.pathname.startsWith(to))
                ? 'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
                : 'text-muted-foreground hover:bg-sidebar-accent/50 hover:text-sidebar-foreground'
            }`}
          >
            <Icon className="h-4 w-4" />
            <span className="flex-1">{label}</span>
            {shortcut && (
              <kbd className="text-[10px] px-1 py-0.5 rounded bg-muted text-muted-foreground border border-border font-mono">
                {shortcut}
              </kbd>
            )}
          </Link>
        ))}

        {projects.length > 0 && (
          <div className="pt-4">
            <p className="px-3 text-xs font-medium text-muted-foreground uppercase tracking-wider mb-2">
              Projects
            </p>
            {projects.map((project) => (
              <Link
                key={project.id}
                to={`/projects/${project.id}`}
                onClick={() => setActiveProjectId(project.id)}
                className={`flex items-center justify-between px-3 py-1.5 rounded-md text-sm transition-colors ${
                  activeProjectId === project.id ||
                  location.pathname === `/projects/${project.id}`
                    ? 'bg-sidebar-accent text-sidebar-accent-foreground font-medium'
                    : 'text-muted-foreground hover:bg-sidebar-accent/50 hover:text-sidebar-foreground'
                }`}
              >
                <span className="truncate">{project.name}</span>
                <span className="text-xs opacity-60">
                  {project.skills_count}
                </span>
              </Link>
            ))}
          </div>
        )}
      </nav>

      <div className="p-3 border-t border-border space-y-2">
        {/* Theme toggle */}
        <div className="flex items-center rounded-md border border-border bg-muted/50 p-0.5">
          {themeOptions.map(({ value, icon: Icon, label }) => (
            <button
              key={value}
              onClick={() => setTheme(value)}
              title={label}
              className={`flex-1 flex items-center justify-center gap-1 py-1 rounded text-xs transition-colors ${
                theme === value
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
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
          className="flex items-center gap-2 px-3 py-2 rounded-md text-sm text-muted-foreground hover:bg-sidebar-accent/50 hover:text-sidebar-foreground transition-colors"
        >
          <Settings className="h-4 w-4" />
          Admin Panel
        </a>
      </div>
    </aside>
  )
}
