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

  useEffect(() => {
    if (!isOpen) return
    setQuery('')
    setSelectedIndex(0)

    requestAnimationFrame(() => {
      inputRef.current?.focus()
    })

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

  const allItems = useMemo<CommandItem[]>(() => {
    const items: CommandItem[] = []

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

  const filteredItems = useMemo(() => {
    const q = query.trim().toLowerCase()

    if (!q) {
      const recentIds = getRecentIds()
      const recentItems = recentIds
        .map((id) => allItems.find((item) => item.id === id))
        .filter(Boolean) as CommandItem[]
      return recentItems
    }

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

  const flatItems = useMemo(
    () => groupedResults.flatMap((g) => g.items),
    [groupedResults]
  )

  useEffect(() => {
    setSelectedIndex(0)
  }, [query])

  useEffect(() => {
    if (!listRef.current) return
    const selected = listRef.current.querySelector('[data-selected="true"]')
    selected?.scrollIntoView({ block: 'nearest' })
  }, [selectedIndex])

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
      className="fixed inset-0 z-50 flex items-start justify-center pt-[15vh] sm:pt-[20vh] px-4 sm:px-0"
      onClick={onClose}
    >
      {/* Scrim */}
      <div className="absolute inset-0 bg-foreground/30 backdrop-blur-sm" />

      {/* Dialog */}
      <div
        className="relative w-full max-w-lg bg-popover elevation-5 overflow-hidden animate-in fade-in zoom-in-95"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Search input */}
        <div className="flex items-center gap-3 px-4">
          <Search className="h-5 w-5 text-primary shrink-0" />
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search skills, projects, pages..."
            className="flex-1 bg-transparent py-4 text-sm text-foreground placeholder:text-muted-foreground outline-none"
          />
          <kbd className="text-[10px] px-2 py-1 bg-muted text-muted-foreground font-mono shrink-0">
            Esc
          </kbd>
        </div>

        <div className="h-px bg-border mx-4" />

        {/* Results */}
        <div
          ref={listRef}
          className="max-h-80 overflow-y-auto p-2"
        >
          {groupedResults.length === 0 ? (
            <div className="py-10 text-center text-sm text-muted-foreground">
              {query.trim() ? 'No results found' : 'No recent items'}
            </div>
          ) : (
            groupedResults.map((group) => {
              const CatIcon = categoryIcon[group.category] ?? Layout
              return (
                <div key={group.category} className="mb-1 last:mb-0">
                  <div className="flex items-center gap-1.5 px-3 py-2 text-[11px] font-semibold text-muted-foreground uppercase tracking-widest">
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
                        className={`w-full flex items-center gap-3 px-3 py-2.5 text-sm transition-all duration-150 ${
                          isSelected
                            ? 'bg-primary/10 text-primary'
                            : 'text-foreground hover:bg-muted'
                        }`}
                      >
                        <Icon className={`h-4 w-4 shrink-0 ${isSelected ? 'text-primary' : 'text-muted-foreground'}`} />
                        <span className="flex-1 text-left truncate">
                          {item.label}
                        </span>
                        {item.subtitle && (
                          <span className="text-xs text-muted-foreground truncate max-w-[140px]">
                            {item.subtitle}
                          </span>
                        )}
                      </button>
                    )
                  })}
                </div>
              )
            })
          )}
        </div>

        {/* Footer */}
        <div className="px-4 py-2.5 border-t border-border flex items-center gap-4 text-[11px] text-muted-foreground">
          <span className="flex items-center gap-1">
            <kbd className="px-1.5 py-0.5 bg-muted font-mono text-[10px]">
              ↑↓
            </kbd>
            Navigate
          </span>
          <span className="flex items-center gap-1">
            <kbd className="px-1.5 py-0.5 bg-muted font-mono text-[10px]">
              Enter
            </kbd>
            Select
          </span>
          <span className="flex items-center gap-1">
            <kbd className="px-1.5 py-0.5 bg-muted font-mono text-[10px]">
              Esc
            </kbd>
            Close
          </span>
        </div>
      </div>
    </div>
  )
}
