import { useState, useEffect } from 'react'
import { Search as SearchIcon, Filter, X } from 'lucide-react'
import { searchSkills, fetchTags, fetchProjects } from '@/api/client'
import { SkillCard } from '@/components/skills/SkillCard'
import type { Skill, Tag, Project } from '@/types'

export function Search() {
  const [query, setQuery] = useState('')
  const [results, setResults] = useState<Skill[]>([])
  const [searched, setSearched] = useState(false)
  const [showFilters, setShowFilters] = useState(false)
  const [tags, setTags] = useState<Tag[]>([])
  const [projects, setProjects] = useState<Project[]>([])
  const [selectedTags, setSelectedTags] = useState<string[]>([])
  const [selectedProject, setSelectedProject] = useState<number | undefined>()
  const [selectedModel, setSelectedModel] = useState('')

  // Load filter options
  useEffect(() => {
    fetchTags().then(setTags)
    fetchProjects().then(setProjects)
  }, [])

  useEffect(() => {
    if (!query.trim() && !selectedTags.length && !selectedProject && !selectedModel) {
      setResults([])
      setSearched(false)
      return
    }

    const timer = setTimeout(async () => {
      const skills = await searchSkills({
        q: query || undefined,
        tags: selectedTags.length ? selectedTags.join(',') : undefined,
        project_id: selectedProject,
        model: selectedModel || undefined,
      })
      setResults(skills)
      setSearched(true)
    }, 300)

    return () => clearTimeout(timer)
  }, [query, selectedTags, selectedProject, selectedModel])

  const toggleTag = (tagName: string) => {
    setSelectedTags((prev) =>
      prev.includes(tagName)
        ? prev.filter((t) => t !== tagName)
        : [...prev, tagName],
    )
  }

  const clearFilters = () => {
    setSelectedTags([])
    setSelectedProject(undefined)
    setSelectedModel('')
  }

  const hasFilters = selectedTags.length > 0 || selectedProject || selectedModel

  // Group results by project
  const grouped = results.reduce<Record<string, Skill[]>>((acc, skill) => {
    const projectName = skill.project?.name || 'Unknown'
    if (!acc[projectName]) acc[projectName] = []
    acc[projectName].push(skill)
    return acc
  }, {})

  // Get unique models from results for the filter
  const modelOptions = ['claude-opus-4-6', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001']

  return (
    <div className="p-4 md:p-6">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold tracking-tight">Search</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Search skills across all projects
        </p>
      </div>

      <div className="flex items-center gap-2 max-w-xl mb-4">
        <div className="relative flex-1">
          <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <input
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search skills..."
            className="w-full pl-9 pr-4 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            autoFocus
          />
        </div>
        <button
          onClick={() => setShowFilters(!showFilters)}
          className={`p-2 border transition-all duration-150 ${
            showFilters || hasFilters
              ? 'border-primary text-primary bg-primary/5'
              : 'border-input text-muted-foreground hover:text-foreground'
          }`}
          title="Toggle filters"
        >
          <Filter className="h-4 w-4" />
        </button>
      </div>

      {/* Filters panel */}
      {showFilters && (
        <div className="max-w-xl mb-4 p-4 bg-card elevation-1 space-y-3">
          {/* Project filter */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1.5">
              Project
            </label>
            <select
              value={selectedProject || ''}
              onChange={(e) =>
                setSelectedProject(e.target.value ? parseInt(e.target.value) : undefined)
              }
              className="w-full px-3 py-1.5 text-sm border border-input bg-background"
            >
              <option value="">All projects</option>
              {projects.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </div>

          {/* Model filter */}
          <div>
            <label className="text-xs font-medium text-muted-foreground block mb-1.5">
              Model
            </label>
            <select
              value={selectedModel}
              onChange={(e) => setSelectedModel(e.target.value)}
              className="w-full px-3 py-1.5 text-sm border border-input bg-background"
            >
              <option value="">All models</option>
              {modelOptions.map((m) => (
                <option key={m} value={m}>
                  {m}
                </option>
              ))}
            </select>
          </div>

          {/* Tag filter */}
          {tags.length > 0 && (
            <div>
              <label className="text-xs font-medium text-muted-foreground block mb-1.5">
                Tags
              </label>
              <div className="flex flex-wrap gap-1.5">
                {tags.map((tag) => (
                  <button
                    key={tag.id}
                    onClick={() => toggleTag(tag.name)}
                    className={`text-xs px-2 py-0.5 border transition-all duration-150 ${
                      selectedTags.includes(tag.name)
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border hover:border-primary/40'
                    }`}
                  >
                    {tag.name}
                  </button>
                ))}
              </div>
            </div>
          )}

          {hasFilters && (
            <button
              onClick={clearFilters}
              className="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1"
            >
              <X className="h-3 w-3" />
              Clear filters
            </button>
          )}
        </div>
      )}

      {/* Active filter badges */}
      {hasFilters && !showFilters && (
        <div className="flex items-center gap-2 mb-4 flex-wrap">
          {selectedProject && (
            <span className="text-xs px-2 py-0.5 bg-primary/10 text-primary font-medium">
              {projects.find((p) => p.id === selectedProject)?.name}
            </span>
          )}
          {selectedModel && (
            <span className="text-xs px-2 py-0.5 bg-primary/10 text-primary font-medium font-mono">
              {selectedModel}
            </span>
          )}
          {selectedTags.map((tag) => (
            <span
              key={tag}
              className="text-xs px-2 py-0.5 bg-accent text-accent-foreground"
            >
              {tag}
            </span>
          ))}
          <button
            onClick={clearFilters}
            className="text-xs text-muted-foreground hover:text-foreground"
          >
            Clear
          </button>
        </div>
      )}

      {searched && results.length === 0 && (
        <p className="text-sm text-muted-foreground">
          No results{query ? ` for "${query}"` : ''}.
        </p>
      )}

      {Object.entries(grouped).map(([projectName, skills]) => (
        <div key={projectName} className="mb-6">
          <h2 className="text-sm font-semibold text-muted-foreground mb-2">
            {projectName}
            <span className="ml-1.5 font-normal">({skills.length})</span>
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {skills.map((skill) => (
              <SkillCard key={skill.id} skill={skill} />
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}
