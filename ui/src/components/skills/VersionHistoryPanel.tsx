import { useEffect, useState } from 'react'
import { History, RotateCcw, GitCompare } from 'lucide-react'
import { DiffEditor } from '@monaco-editor/react'
import { fetchVersions, restoreVersion } from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import { Button } from '@/components/ui/button'
import type { SkillVersion } from '@/types'

interface VersionHistoryPanelProps {
  skillId: number
  onRestore: () => void
}

export function VersionHistoryPanel({
  skillId,
  onRestore,
}: VersionHistoryPanelProps) {
  const confirm = useConfirm()
  const [versions, setVersions] = useState<SkillVersion[]>([])
  const [loading, setLoading] = useState(true)
  const [selected, setSelected] = useState<number[]>([])
  const [restoring, setRestoring] = useState(false)
  const [showDiff, setShowDiff] = useState(false)

  useEffect(() => {
    fetchVersions(skillId)
      .then(setVersions)
      .finally(() => setLoading(false))
  }, [skillId])

  const handleCheckbox = (versionNumber: number) => {
    setSelected((prev) => {
      if (prev.includes(versionNumber)) {
        return prev.filter((v) => v !== versionNumber)
      }
      // Max 2 selected for diff
      if (prev.length >= 2) {
        return [prev[1], versionNumber]
      }
      return [...prev, versionNumber]
    })
  }

  const handleRestore = async (versionNumber: number) => {
    if (!(await confirm({ message: `Restore to version ${versionNumber}? This creates a new version.`, title: 'Confirm Restore' })))
      return

    setRestoring(true)
    try {
      await restoreVersion(skillId, versionNumber)
      const updated = await fetchVersions(skillId)
      setVersions(updated)
      onRestore()
    } finally {
      setRestoring(false)
    }
  }

  const diffVersions = selected
    .sort((a, b) => a - b)
    .map((num) => versions.find((v) => v.version_number === num))
    .filter(Boolean) as SkillVersion[]

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
        Loading versions...
      </div>
    )
  }

  if (versions.length === 0) {
    return (
      <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
        <div className="text-center">
          <History className="h-8 w-8 mx-auto mb-2 opacity-30" />
          No version history yet.
        </div>
      </div>
    )
  }

  if (showDiff && diffVersions.length === 2) {
    return (
      <div className="flex flex-col h-full">
        <div className="flex items-center justify-between px-3 py-2 border-b border-border">
          <span className="text-xs font-medium">
            v{diffVersions[0].version_number} vs v
            {diffVersions[1].version_number}
          </span>
          <Button
            variant="ghost"
            size="xs"
            onClick={() => setShowDiff(false)}
          >
            Back
          </Button>
        </div>
        <div className="flex-1">
          <DiffEditor
            height="100%"
            original={diffVersions[0].body || ''}
            modified={diffVersions[1].body || ''}
            language="markdown"
            theme="vs-dark"
            options={{
              readOnly: true,
              minimap: { enabled: false },
              renderSideBySide: window.innerWidth >= 1024,
              scrollBeyondLastLine: false,
              fontSize: 12,
              automaticLayout: true,
            }}
          />
        </div>
      </div>
    )
  }

  return (
    <div className="flex flex-col h-full">
      {/* Actions bar */}
      {selected.length === 2 && (
        <div className="px-3 py-2 border-b border-border bg-muted/30">
          <Button
            size="xs"
            variant="outline"
            onClick={() => setShowDiff(true)}
          >
            <GitCompare className="h-3 w-3 mr-1" />
            Compare v{Math.min(...selected)} & v{Math.max(...selected)}
          </Button>
        </div>
      )}

      {/* Version list */}
      <div className="flex-1 overflow-y-auto">
        {versions.map((version) => (
          <div
            key={version.id}
            className="flex items-center gap-2 px-3 py-2.5 border-b border-border hover:bg-muted/30 transition-all duration-150"
          >
            <input
              type="checkbox"
              checked={selected.includes(version.version_number)}
              onChange={() => handleCheckbox(version.version_number)}
              className="rounded border-input h-3.5 w-3.5 accent-primary"
            />

            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <span className="text-xs font-mono font-medium">
                  v{version.version_number}
                </span>
                {version.note && (
                  <span className="text-[10px] text-muted-foreground truncate">
                    {version.note}
                  </span>
                )}
              </div>
              <span className="text-[10px] text-muted-foreground">
                {new Date(version.saved_at).toLocaleString()}
              </span>
            </div>

            <Button
              variant="ghost"
              size="icon-xs"
              onClick={() => handleRestore(version.version_number)}
              disabled={restoring}
              title="Restore this version"
            >
              <RotateCcw className="h-3 w-3" />
            </Button>
          </div>
        ))}
      </div>
    </div>
  )
}
