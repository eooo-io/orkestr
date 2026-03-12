import { useState } from 'react'
import { detectImportableSkills, importFromProvider } from '@/api/client'
import type { ImportDetectedSkill } from '@/types'

interface ImportTabProps {
  projectId: number
  projectPath: string
  onImported: () => void
}

const PROVIDERS = [
  { value: '', label: 'All Providers' },
  { value: 'claude', label: 'Claude (.claude/CLAUDE.md)' },
  { value: 'cursor', label: 'Cursor (.cursor/rules/)' },
  { value: 'copilot', label: 'Copilot (.github/copilot-instructions.md)' },
  { value: 'windsurf', label: 'Windsurf (.windsurf/rules/)' },
  { value: 'cline', label: 'Cline (.clinerules)' },
  { value: 'openai', label: 'OpenAI (.openai/instructions.md)' },
]

export default function ImportTab({ projectId, projectPath, onImported }: ImportTabProps) {
  const [path, setPath] = useState(projectPath)
  const [provider, setProvider] = useState('')
  const [detected, setDetected] = useState<Record<string, ImportDetectedSkill[]> | null>(null)
  const [loading, setLoading] = useState(false)
  const [importing, setImporting] = useState(false)
  const [result, setResult] = useState<{ created: number; skipped: number } | null>(null)
  const [error, setError] = useState('')

  const handleDetect = async () => {
    setLoading(true)
    setError('')
    setResult(null)
    try {
      const data = await detectImportableSkills(path, provider || undefined)
      setDetected(data)
      if (Object.keys(data).length === 0) {
        setError('No provider config files found at this path.')
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Detection failed'
      setError(message)
    } finally {
      setLoading(false)
    }
  }

  const handleImport = async () => {
    setImporting(true)
    setError('')
    try {
      const data = await importFromProvider(projectId, path, provider || undefined)
      setResult(data)
      setDetected(null)
      onImported()
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Import failed'
      setError(message)
    } finally {
      setImporting(false)
    }
  }

  const totalSkills = detected
    ? Object.values(detected).reduce((sum, skills) => sum + skills.length, 0)
    : 0

  return (
    <div className="space-y-6">
      <div>
        <h3 className="text-lg font-semibold text-white mb-1">Import from Existing Config</h3>
        <p className="text-sm text-zinc-400">
          Reverse-sync: detect skills from existing provider config files and import them into this project.
        </p>
      </div>

      <div className="space-y-4">
        <div>
          <label className="block text-sm font-medium text-zinc-300 mb-1">Source Path</label>
          <input
            type="text"
            value={path}
            onChange={(e) => setPath(e.target.value)}
            className="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="/path/to/project"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-zinc-300 mb-1">Provider Filter</label>
          <select
            value={provider}
            onChange={(e) => setProvider(e.target.value)}
            className="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            {PROVIDERS.map((p) => (
              <option key={p.value} value={p.value}>{p.label}</option>
            ))}
          </select>
        </div>

        <button
          onClick={handleDetect}
          disabled={loading || !path}
          className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
        >
          {loading ? 'Scanning...' : 'Scan for Skills'}
        </button>
      </div>

      {error && (
        <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-sm">
          {error}
        </div>
      )}

      {result && (
        <div className="p-3 bg-green-500/10 border border-green-500/30 rounded-lg text-green-400 text-sm">
          Imported {result.created} skill(s), {result.skipped} skipped (already exist).
        </div>
      )}

      {detected && totalSkills > 0 && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h4 className="text-sm font-medium text-zinc-300">
              Found {totalSkills} skill(s) across {Object.keys(detected).length} provider(s)
            </h4>
            <button
              onClick={handleImport}
              disabled={importing}
              className="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
            >
              {importing ? 'Importing...' : `Import ${totalSkills} Skill(s)`}
            </button>
          </div>

          {Object.entries(detected).map(([prov, skills]) => (
            <div key={prov} className="border border-zinc-700 rounded-lg overflow-hidden">
              <div className="px-4 py-2 bg-zinc-800 border-b border-zinc-700">
                <span className="text-sm font-medium text-white capitalize">{prov}</span>
                <span className="text-xs text-zinc-400 ml-2">({skills.length} skills)</span>
              </div>
              <div className="divide-y divide-zinc-800">
                {skills.map((skill) => (
                  <div key={skill.slug} className="px-4 py-2 flex items-center justify-between">
                    <div>
                      <span className="text-sm text-white">{skill.name}</span>
                      <span className="text-xs text-zinc-500 ml-2">{skill.slug}</span>
                      {skill.description && (
                        <p className="text-xs text-zinc-400 mt-0.5">{skill.description}</p>
                      )}
                    </div>
                    <div className="flex items-center gap-2">
                      {skill.tags.map((tag) => (
                        <span key={tag} className="px-1.5 py-0.5 bg-zinc-700 text-zinc-300 text-xs rounded">
                          {tag}
                        </span>
                      ))}
                      <span className="text-xs text-zinc-500">{skill.body_length} chars</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
