import { useState, useEffect, useRef, useMemo, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Search,
  FileText,
  FolderOpen,
  Layout,
  Zap,
  BookOpen,
  MessageSquare,
  Settings,
} from 'lucide-react'
import { useAppStore } from '@/store/useAppStore'
import { fetchSkills } from '@/api/client'
import type { Skill } from '@/types'

type CommandCategory = 'Skills' | 'Projects' | 'Pages' | 'Actions'

interface CommandItem {
  id: string
  label: string
  subtitle?: string
  category: CommandCategory
  icon: React.ComponentType<{ className?: string }>
  action: () => void
}

const RECENT_KEY = 'agentis-command-palette-recent'
const MAX_RECENT = 5

function getRecentIds(): string[] {
  try {
    const raw = localStorage.getItem(RECENT_KEY)
    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

function addRecent(id: string) {
  const recent = getRecentIds().filter((r) => r !== id)
  recent.unshift(id)
  localStorage.setItem(RECENT_KEY, JSON.stringify(recent.slice(0, MAX_RECENT)))
}

interface CommandPaletteProps {
  isOpen: boolean
  onClose: () => void
}

export function CommandPalette({ isOpen, onClose }: CommandPaletteProps) {
  const [query, setQuery] = useState('')
  const [selectedIndex, setSelectedIndex] = useState(0)
  const [allSkills, setAllSkills] = useState<Skill[]>([])
  const inputRef = useRef<HTMLInputElement>(null)
  const listRef = useRef<HTMLDivElement>(null)
  const navigate = useNavigate()
  const projects = useAppStore((s) => s.projects)

  // Load skills from all projects when palette opens
  useEffect(() => {
    if (!isOpen) return
    setQuery('')
    setSelectedIndex(0)

    // Focus input after render
    requestAnimationFrame(() => {
      inputRef.current?.focus()
    })

    // Fetch skills for all projects
    const loadSkills = async () => {
      try {
        const skillArrays = await Promise.all(
          projects.map((p) => fetchSkills(p.id).catch(() => [] as Skill[]))
        )
        setAllSkills(skillArrays.flat())
      } catch {
        setAllSkills([])
      }
    }
    loadSkills()
  }, [isOpen, projects])

  const executeItem = useCallback(
    (item: CommandItem) => {
      addRecent(item.id)
      item.action()
      onClose()
    },
    [onClose]
  )

  // Build all command items
  const allItems = useMemo<CommandItem[]>(() => {
    const items: CommandItem[] = []

    // Skills
    for (const skill of allSkills) {
      const project = projects.find((p) => p.id === skill.project_id)
      items.push({
        id: `skill-${skill.id}`,
        label: skill.name,
        subtitle: project?.name ?? 'Unknown project',
        category: 'Skills',
        icon: FileText,
        action: () => navigate(`/skills/${skill.id}`),
      })
    }

    // Projects
    for (const project of projects) {
      items.push({
        id: `project-${project.id}`,
        label: project.name,
        subtitle: `${project.skills_count} skill${project.skills_count !== 1 ? 's' : ''}`,
        category: 'Projects',
        icon: FolderOpen,
        action: () => navigate(`/projects/${project.id}`),
      })
    }

    // Pages
    const pages: { label: string; path: string; icon: typeof Layout }[] = [
      { label: 'Library', path: '/library', icon: BookOpen },
      { label: 'Search', path: '/search', icon: Search },
      { label: 'Playground', path: '/playground', icon: MessageSquare },
      { label: 'Settings', path: '/settings', icon: Settings },
    ]
    for (const page of pages) {
      items.push({
        id: `page-${page.path}`,
        label: page.label,
        category: 'Pages',
        icon: page.icon,
        action: () => navigate(page.path),
      })
    }

    // Actions
    items.push({
      id: 'action-new-project',
      label: 'New Project',
      subtitle: 'Create a new project',
      category: 'Actions',
      icon: Zap,
      action: () => navigate('/projects/new'),
    })

    return items
  }, [allSkills, projects, navigate])

  // Filter and rank results
  const filteredItems = useMemo(() => {
    const q = query.trim().toLowerCase()

    if (!q) {
      // Show recent items when query is empty
      const recentIds = getRecentIds()
      const recentItems = recentIds
        .map((id) => allItems.find((item) => item.id === id))
        .filter(Boolean) as CommandItem[]
      return recentItems
    }

    // Score items: exact match > starts-with > contains
    const scored = allItems
      .map((item) => {
        const label = item.label.toLowerCase()
        const subtitle = (item.subtitle ?? '').toLowerCase()
        let score = 0

        if (label === q) score = 100
        else if (label.startsWith(q)) score = 80
        else if (label.includes(q)) score = 60
        else if (subtitle.includes(q)) score = 40
        else return null

        return { item, score }
      })
      .filter(Boolean) as { item: CommandItem; score: number }[]

    scored.sort((a, b) => b.score - a.score)
    return scored.map((s) => s.item)
  }, [query, allItems])

  // Group results by category
  const groupedResults = useMemo(() => {
    const q = query.trim()
    if (!q && filteredItems.length > 0) {
      return [{ category: 'Recent' as string, items: filteredItems }]
    }

    const groups: { category: string; items: CommandItem[] }[] = []
    const categoryOrder: CommandCategory[] = [
      'Skills',
      'Projects',
      'Pages',
      'Actions',
    ]

    for (const cat of categoryOrder) {
      const items = filteredItems.filter((item) => item.category === cat)
      if (items.length > 0) {
        groups.push({ category: cat, items })
      }
    }
    return groups
  }, [filteredItems, query])

  // Flatten for keyboard navigation
  const flatItems = useMemo(
    () => groupedResults.flatMap((g) => g.items),
    [groupedResults]
  )

  // Reset selection when results change
  useEffect(() => {
    setSelectedIndex(0)
  }, [query])

  // Scroll selected item into view
  useEffect(() => {
    if (!listRef.current) return
    const selected = listRef.current.querySelector('[data-selected="true"]')
    selected?.scrollIntoView({ block: 'nearest' })
  }, [selectedIndex])

  // Keyboard navigation
  useEffect(() => {
    if (!isOpen) return

    const handler = (e: KeyboardEvent) => {
      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault()
          setSelectedIndex((i) => Math.min(i + 1, flatItems.length - 1))
          break
        case 'ArrowUp':
          e.preventDefault()
          setSelectedIndex((i) => Math.max(i - 1, 0))
          break
        case 'Enter':
          e.preventDefault()
          if (flatItems[selectedIndex]) {
            executeItem(flatItems[selectedIndex])
          }
          break
      }
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [isOpen, flatItems, selectedIndex, executeItem])

  if (!isOpen) return null

  const categoryIcon: Record<string, React.ComponentType<{ className?: string }>> = {
    Skills: FileText,
    Projects: FolderOpen,
    Pages: Layout,
    Actions: Zap,
    Recent: Search,
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-start justify-center pt-[20vh]"
      onClick={onClose}
    >
      {/* Backdrop */}
      <div className="absolute inset-0 bg-background/80 backdrop-blur-sm" />

      {/* Dialog */}
      <div
        className="relative w-full max-w-lg bg-popover border border-border rounded-xl shadow-2xl overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Search input */}
        <div className="flex items-center gap-3 px-4 border-b border-border">
          <Search className="h-4 w-4 text-muted-foreground shrink-0" />
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search skills, projects, pages..."
            className="flex-1 bg-transparent py-3 text-sm text-foreground placeholder:text-muted-foreground outline-none"
          />
          <kbd className="text-[10px] px-1.5 py-0.5 rounded bg-muted text-muted-foreground border border-border font-mono shrink-0">
            Esc
          </kbd>
        </div>

        {/* Results */}
        <div
          ref={listRef}
          className="max-h-80 overflow-y-auto p-2"
        >
          {groupedResults.length === 0 ? (
            <div className="py-8 text-center text-sm text-muted-foreground">
              {query.trim() ? 'No results found' : 'No recent items'}
            </div>
          ) : (
            groupedResults.map((group) => {
              const CatIcon = categoryIcon[group.category] ?? Layout
              return (
                <div key={group.category} className="mb-2 last:mb-0">
                  <div className="flex items-center gap-1.5 px-2 py-1.5 text-xs font-medium text-muted-foreground uppercase tracking-wider">
                    <CatIcon className="h-3 w-3" />
                    {group.category}
                  </div>
                  {group.items.map((item) => {
                    const globalIndex = flatItems.indexOf(item)
                    const isSelected = globalIndex === selectedIndex
                    const Icon = item.icon
                    return (
                      <button
                        key={item.id}
                        data-selected={isSelected}
                        onClick={() => executeItem(item)}
                        onMouseEnter={() => setSelectedIndex(globalIndex)}
                        className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${
                          isSelected
                            ? 'bg-accent text-accent-foreground'
                            : 'text-foreground hover:bg-accent/50'
                        }`}
                      >
                        <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
                        <span className="flex-1 text-left truncate">
                          {item.label}
                        </span>
                        {item.subtitle && (
                          <span className="text-xs text-muted-foreground truncate max-w-[140px]">
                            {item.subtitle}
                          </span>
                        )}
                        <span
                          className={`text-[10px] px-1.5 py-0.5 rounded font-medium shrink-0 ${
                            isSelected
                              ? 'bg-background/20 text-accent-foreground'
                              : 'bg-muted text-muted-foreground'
                          }`}
                        >
                          {item.category}
                        </span>
                      </button>
                    )
                  })}
                </div>
              )
            })
          )}
        </div>

        {/* Footer hints */}
        <div className="px-4 py-2 border-t border-border flex items-center gap-3 text-[11px] text-muted-foreground">
          <span>
            <kbd className="px-1 py-0.5 rounded bg-muted border border-border font-mono">
              ↑↓
            </kbd>{' '}
            Navigate
          </span>
          <span>
            <kbd className="px-1 py-0.5 rounded bg-muted border border-border font-mono">
              Enter
            </kbd>{' '}
            Select
          </span>
          <span>
            <kbd className="px-1 py-0.5 rounded bg-muted border border-border font-mono">
              Esc
            </kbd>{' '}
            Close
          </span>
        </div>
      </div>
    </div>
  )
}
