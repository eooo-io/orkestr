import { useState, useEffect, useRef } from 'react'
import { X, Tag, Users, FolderInput, Trash2, Check } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  bulkTagSkills,
  bulkAssignSkills,
  bulkDeleteSkills,
  bulkMoveSkills,
  fetchTags,
  fetchProjects,
  fetchProjectAgents,
} from '@/api/client'
import type { Project, Tag as TagType, ProjectAgent } from '@/types'

interface BulkActionBarProps {
  selectedIds: Set<number>
  projectId: number
  onClearSelection: () => void
  onActionComplete: () => void
}

type ActivePopover = 'tag' | 'assign' | 'move' | 'delete' | null

export function BulkActionBar({
  selectedIds,
  projectId,
  onClearSelection,
  onActionComplete,
}: BulkActionBarProps) {
  const [activePopover, setActivePopover] = useState<ActivePopover>(null)
  const [loading, setLoading] = useState(false)
  const barRef = useRef<HTMLDivElement>(null)

  // Tag state
  const [tags, setTags] = useState<TagType[]>([])
  const [selectedAddTags, setSelectedAddTags] = useState<Set<string>>(new Set())
  const [selectedRemoveTags, setSelectedRemoveTags] = useState<Set<string>>(new Set())
  const [newTagName, setNewTagName] = useState('')

  // Assign state
  const [agents, setAgents] = useState<ProjectAgent[]>([])
  const [selectedAgentId, setSelectedAgentId] = useState<number | null>(null)

  // Move state
  const [projects, setProjects] = useState<Project[]>([])
  const [targetProjectId, setTargetProjectId] = useState<number | null>(null)

  const count = selectedIds.size
  const skillIds = Array.from(selectedIds)

  useEffect(() => {
    if (activePopover === 'tag') {
      fetchTags().then(setTags)
    } else if (activePopover === 'assign') {
      fetchProjectAgents(projectId).then(setAgents)
    } else if (activePopover === 'move') {
      fetchProjects().then((p) => setProjects(p.filter((proj) => proj.id !== projectId)))
    }
  }, [activePopover, projectId])

  const handleTagApply = async () => {
    if (selectedAddTags.size === 0 && selectedRemoveTags.size === 0 && !newTagName.trim()) return
    setLoading(true)
    try {
      const addTags = [...selectedAddTags]
      if (newTagName.trim()) addTags.push(newTagName.trim())
      await bulkTagSkills(skillIds, addTags, [...selectedRemoveTags])
      setActivePopover(null)
      setSelectedAddTags(new Set())
      setSelectedRemoveTags(new Set())
      setNewTagName('')
      onActionComplete()
    } finally {
      setLoading(false)
    }
  }

  const handleAssign = async () => {
    if (!selectedAgentId) return
    setLoading(true)
    try {
      await bulkAssignSkills(skillIds, selectedAgentId, projectId)
      setActivePopover(null)
      setSelectedAgentId(null)
      onActionComplete()
    } finally {
      setLoading(false)
    }
  }

  const handleMove = async () => {
    if (!targetProjectId) return
    setLoading(true)
    try {
      await bulkMoveSkills(skillIds, targetProjectId)
      setActivePopover(null)
      setTargetProjectId(null)
      onActionComplete()
    } finally {
      setLoading(false)
    }
  }

  const handleDelete = async () => {
    setLoading(true)
    try {
      await bulkDeleteSkills(skillIds)
      setActivePopover(null)
      onActionComplete()
    } finally {
      setLoading(false)
    }
  }

  const toggleAddTag = (name: string) => {
    const next = new Set(selectedAddTags)
    if (next.has(name)) {
      next.delete(name)
    } else {
      next.add(name)
      // Remove from remove set if present
      const nextRemove = new Set(selectedRemoveTags)
      nextRemove.delete(name)
      setSelectedRemoveTags(nextRemove)
    }
    setSelectedAddTags(next)
  }

  const toggleRemoveTag = (name: string) => {
    const next = new Set(selectedRemoveTags)
    if (next.has(name)) {
      next.delete(name)
    } else {
      next.add(name)
      // Remove from add set if present
      const nextAdd = new Set(selectedAddTags)
      nextAdd.delete(name)
      setSelectedAddTags(nextAdd)
    }
    setSelectedRemoveTags(next)
  }

  return (
    <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-50" ref={barRef}>
      {/* Popover panels */}
      {activePopover === 'tag' && (
        <div className="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-80 bg-popover border border-border rounded-lg shadow-xl p-4">
          <h4 className="text-sm font-medium mb-3">Manage Tags</h4>
          <div className="space-y-2 max-h-48 overflow-y-auto">
            {tags.map((tag) => (
              <div key={tag.id} className="flex items-center justify-between text-sm">
                <span className="truncate">{tag.name}</span>
                <div className="flex items-center gap-1 shrink-0">
                  <button
                    onClick={() => toggleAddTag(tag.name)}
                    className={`px-2 py-0.5 rounded text-xs ${
                      selectedAddTags.has(tag.name)
                        ? 'bg-green-600 text-white'
                        : 'bg-muted text-muted-foreground hover:bg-accent'
                    }`}
                  >
                    +Add
                  </button>
                  <button
                    onClick={() => toggleRemoveTag(tag.name)}
                    className={`px-2 py-0.5 rounded text-xs ${
                      selectedRemoveTags.has(tag.name)
                        ? 'bg-red-600 text-white'
                        : 'bg-muted text-muted-foreground hover:bg-accent'
                    }`}
                  >
                    -Remove
                  </button>
                </div>
              </div>
            ))}
          </div>
          <div className="mt-3 flex items-center gap-2">
            <input
              type="text"
              placeholder="New tag name..."
              value={newTagName}
              onChange={(e) => setNewTagName(e.target.value)}
              className="flex-1 text-sm px-2 py-1 rounded border border-border bg-background"
            />
          </div>
          <div className="mt-3 flex justify-end gap-2">
            <Button variant="ghost" size="sm" onClick={() => setActivePopover(null)}>
              Cancel
            </Button>
            <Button size="sm" onClick={handleTagApply} disabled={loading}>
              {loading ? 'Applying...' : 'Apply'}
            </Button>
          </div>
        </div>
      )}

      {activePopover === 'assign' && (
        <div className="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-72 bg-popover border border-border rounded-lg shadow-xl p-4">
          <h4 className="text-sm font-medium mb-3">Assign to Agent</h4>
          <div className="space-y-1 max-h-48 overflow-y-auto">
            {agents.length === 0 && (
              <p className="text-xs text-muted-foreground">No agents configured for this project.</p>
            )}
            {agents.map((agent) => (
              <button
                key={agent.id}
                onClick={() => setSelectedAgentId(agent.id)}
                className={`w-full text-left px-3 py-2 rounded text-sm flex items-center justify-between ${
                  selectedAgentId === agent.id
                    ? 'bg-primary text-primary-foreground'
                    : 'hover:bg-accent'
                }`}
              >
                <span>{agent.name}</span>
                {selectedAgentId === agent.id && <Check className="h-3.5 w-3.5" />}
              </button>
            ))}
          </div>
          <div className="mt-3 flex justify-end gap-2">
            <Button variant="ghost" size="sm" onClick={() => setActivePopover(null)}>
              Cancel
            </Button>
            <Button size="sm" onClick={handleAssign} disabled={loading || !selectedAgentId}>
              {loading ? 'Assigning...' : 'Assign'}
            </Button>
          </div>
        </div>
      )}

      {activePopover === 'move' && (
        <div className="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-72 bg-popover border border-border rounded-lg shadow-xl p-4">
          <h4 className="text-sm font-medium mb-3">Move to Project</h4>
          <div className="space-y-1 max-h-48 overflow-y-auto">
            {projects.length === 0 && (
              <p className="text-xs text-muted-foreground">No other projects available.</p>
            )}
            {projects.map((proj) => (
              <button
                key={proj.id}
                onClick={() => setTargetProjectId(proj.id)}
                className={`w-full text-left px-3 py-2 rounded text-sm flex items-center justify-between ${
                  targetProjectId === proj.id
                    ? 'bg-primary text-primary-foreground'
                    : 'hover:bg-accent'
                }`}
              >
                <span className="truncate">{proj.name}</span>
                {targetProjectId === proj.id && <Check className="h-3.5 w-3.5" />}
              </button>
            ))}
          </div>
          <div className="mt-3 flex justify-end gap-2">
            <Button variant="ghost" size="sm" onClick={() => setActivePopover(null)}>
              Cancel
            </Button>
            <Button size="sm" onClick={handleMove} disabled={loading || !targetProjectId}>
              {loading ? 'Moving...' : 'Move'}
            </Button>
          </div>
        </div>
      )}

      {activePopover === 'delete' && (
        <div className="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-72 bg-popover border border-border rounded-lg shadow-xl p-4">
          <h4 className="text-sm font-medium mb-2">Confirm Delete</h4>
          <p className="text-sm text-muted-foreground mb-4">
            Are you sure you want to delete {count} skill{count !== 1 ? 's' : ''}? This will also
            remove the .agentis files. This action cannot be undone.
          </p>
          <div className="flex justify-end gap-2">
            <Button variant="ghost" size="sm" onClick={() => setActivePopover(null)}>
              Cancel
            </Button>
            <Button variant="destructive" size="sm" onClick={handleDelete} disabled={loading}>
              {loading ? 'Deleting...' : `Delete ${count}`}
            </Button>
          </div>
        </div>
      )}

      {/* Main bar */}
      <div className="flex items-center gap-3 px-4 py-2.5 bg-zinc-900 dark:bg-zinc-800 text-white rounded-xl shadow-2xl border border-zinc-700">
        <span className="text-sm font-medium whitespace-nowrap">
          {count} selected
        </span>

        <div className="h-4 w-px bg-zinc-600" />

        <Button
          variant="ghost"
          size="sm"
          className="text-white hover:bg-zinc-700 hover:text-white"
          onClick={() => setActivePopover(activePopover === 'tag' ? null : 'tag')}
        >
          <Tag className="h-4 w-4 mr-1" />
          Tag
        </Button>

        <Button
          variant="ghost"
          size="sm"
          className="text-white hover:bg-zinc-700 hover:text-white"
          onClick={() => setActivePopover(activePopover === 'assign' ? null : 'assign')}
        >
          <Users className="h-4 w-4 mr-1" />
          Assign
        </Button>

        <Button
          variant="ghost"
          size="sm"
          className="text-white hover:bg-zinc-700 hover:text-white"
          onClick={() => setActivePopover(activePopover === 'move' ? null : 'move')}
        >
          <FolderInput className="h-4 w-4 mr-1" />
          Move
        </Button>

        <Button
          variant="ghost"
          size="sm"
          className="text-white hover:bg-zinc-700 hover:text-white"
          onClick={() => setActivePopover(activePopover === 'delete' ? null : 'delete')}
        >
          <Trash2 className="h-4 w-4 mr-1" />
          Delete
        </Button>

        <div className="h-4 w-px bg-zinc-600" />

        <button
          onClick={onClearSelection}
          className="text-zinc-400 hover:text-white transition-colors p-1"
          title="Deselect all"
        >
          <X className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}
