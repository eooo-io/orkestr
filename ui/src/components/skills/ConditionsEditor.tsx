import { useState } from 'react'
import type { SkillConditions } from '@/types'

interface ConditionsEditorProps {
  conditions: SkillConditions | null
  onChange: (conditions: SkillConditions | null) => void
}

export default function ConditionsEditor({ conditions, onChange }: ConditionsEditorProps) {
  const [newPattern, setNewPattern] = useState('')
  const [newPrefix, setNewPrefix] = useState('')

  const filePatterns = conditions?.file_patterns ?? []
  const pathPrefixes = conditions?.path_prefixes ?? []

  const updateConditions = (updates: Partial<SkillConditions>) => {
    const merged = { ...conditions, ...updates }
    const hasValues =
      (merged.file_patterns && merged.file_patterns.length > 0) ||
      (merged.path_prefixes && merged.path_prefixes.length > 0)
    onChange(hasValues ? merged : null)
  }

  const addPattern = () => {
    const trimmed = newPattern.trim()
    if (!trimmed || filePatterns.includes(trimmed)) return
    updateConditions({ file_patterns: [...filePatterns, trimmed] })
    setNewPattern('')
  }

  const removePattern = (index: number) => {
    updateConditions({ file_patterns: filePatterns.filter((_, i) => i !== index) })
  }

  const addPrefix = () => {
    const trimmed = newPrefix.trim()
    if (!trimmed || pathPrefixes.includes(trimmed)) return
    updateConditions({ path_prefixes: [...pathPrefixes, trimmed] })
    setNewPrefix('')
  }

  const removePrefix = (index: number) => {
    updateConditions({ path_prefixes: pathPrefixes.filter((_, i) => i !== index) })
  }

  return (
    <div className="space-y-4">
      <div>
        <div className="flex items-center justify-between mb-1">
          <label className="block text-sm font-medium text-zinc-300">
            File Patterns
          </label>
          <span className="text-xs text-zinc-500">
            Glob patterns — skill activates only for matching files
          </span>
        </div>
        <p className="text-xs text-zinc-500 mb-2">
          E.g. <code className="text-zinc-400">*.tsx</code>, <code className="text-zinc-400">src/api/**/*.ts</code>, <code className="text-zinc-400">*.test.{'{ts,tsx}'}</code>
        </p>
        <div className="flex gap-2">
          <input
            type="text"
            value={newPattern}
            onChange={(e) => setNewPattern(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addPattern())}
            className="flex-1 px-3 py-1.5 bg-zinc-800 border border-zinc-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="*.tsx"
          />
          <button
            onClick={addPattern}
            className="px-3 py-1.5 bg-zinc-700 hover:bg-zinc-600 text-white text-sm rounded-lg transition-colors"
          >
            Add
          </button>
        </div>
        {filePatterns.length > 0 && (
          <div className="flex flex-wrap gap-2 mt-2">
            {filePatterns.map((pattern, i) => (
              <span
                key={i}
                className="inline-flex items-center gap-1 px-2 py-1 bg-blue-500/15 text-blue-300 text-xs rounded-md border border-blue-500/30"
              >
                <code>{pattern}</code>
                <button
                  onClick={() => removePattern(i)}
                  className="hover:text-red-400 transition-colors ml-1"
                >
                  x
                </button>
              </span>
            ))}
          </div>
        )}
      </div>

      <div>
        <div className="flex items-center justify-between mb-1">
          <label className="block text-sm font-medium text-zinc-300">
            Path Prefixes
          </label>
          <span className="text-xs text-zinc-500">
            Skill activates only in these directories
          </span>
        </div>
        <div className="flex gap-2">
          <input
            type="text"
            value={newPrefix}
            onChange={(e) => setNewPrefix(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addPrefix())}
            className="flex-1 px-3 py-1.5 bg-zinc-800 border border-zinc-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="src/api/"
          />
          <button
            onClick={addPrefix}
            className="px-3 py-1.5 bg-zinc-700 hover:bg-zinc-600 text-white text-sm rounded-lg transition-colors"
          >
            Add
          </button>
        </div>
        {pathPrefixes.length > 0 && (
          <div className="flex flex-wrap gap-2 mt-2">
            {pathPrefixes.map((prefix, i) => (
              <span
                key={i}
                className="inline-flex items-center gap-1 px-2 py-1 bg-purple-500/15 text-purple-300 text-xs rounded-md border border-purple-500/30"
              >
                <code>{prefix}</code>
                <button
                  onClick={() => removePrefix(i)}
                  className="hover:text-red-400 transition-colors ml-1"
                >
                  x
                </button>
              </span>
            ))}
          </div>
        )}
      </div>

      {!conditions && (
        <p className="text-xs text-zinc-500 italic">
          No conditions set — this skill will always apply (alwaysApply: true in Cursor).
        </p>
      )}
    </div>
  )
}
