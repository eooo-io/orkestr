import { useCallback, useEffect, useState, useRef } from 'react'
import {
  fetchSkillAssets,
  uploadSkillAssets,
  deleteSkillAsset,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import type { SkillAsset } from '@/types'

interface AssetsPanelProps {
  skillId: number
}

const DIRECTORIES = ['assets', 'scripts', 'data'] as const

const DIR_LABELS: Record<string, { label: string; desc: string }> = {
  assets: { label: 'Assets', desc: 'Images, reference outputs, examples' },
  scripts: { label: 'Scripts', desc: 'Automation scripts, shell commands' },
  data: { label: 'Data', desc: 'Reference data, templates, configs' },
}

const FILE_ICONS: Record<string, string> = {
  md: '\u{1F4DD}',
  txt: '\u{1F4DD}',
  json: '\u{1F4CB}',
  yaml: '\u{1F4CB}',
  yml: '\u{1F4CB}',
  sh: '\u{2699}\uFE0F',
  py: '\u{1F40D}',
  js: '\u{1F4E6}',
  ts: '\u{1F4E6}',
  png: '\u{1F5BC}\uFE0F',
  jpg: '\u{1F5BC}\uFE0F',
  jpeg: '\u{1F5BC}\uFE0F',
  svg: '\u{1F5BC}\uFE0F',
  csv: '\u{1F4CA}',
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export function AssetsPanel({ skillId }: AssetsPanelProps) {
  const confirm = useConfirm()
  const [assets, setAssets] = useState<SkillAsset[]>([])
  const [isFolder, setIsFolder] = useState(false)
  const [loading, setLoading] = useState(true)
  const [uploading, setUploading] = useState(false)
  const [targetDir, setTargetDir] = useState<(typeof DIRECTORIES)[number]>('assets')
  const fileInputRef = useRef<HTMLInputElement>(null)

  const loadAssets = useCallback(() => {
    fetchSkillAssets(skillId)
      .then((res) => {
        setAssets(res.data)
        setIsFolder(res.is_folder)
      })
      .finally(() => setLoading(false))
  }, [skillId])

  useEffect(() => {
    loadAssets()
  }, [loadAssets])

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files
    if (!files || files.length === 0) return

    setUploading(true)
    try {
      await uploadSkillAssets(skillId, Array.from(files), targetDir)
      loadAssets()
    } catch {
      // toast handled at higher level
    } finally {
      setUploading(false)
      if (fileInputRef.current) fileInputRef.current.value = ''
    }
  }

  const handleDelete = async (path: string) => {
    if (!(await confirm({ message: `Delete ${path}?`, title: 'Confirm Delete' }))) return
    try {
      await deleteSkillAsset(skillId, path)
      loadAssets()
    } catch {
      // silent
    }
  }

  const handleDrop = useCallback(
    async (e: React.DragEvent<HTMLDivElement>) => {
      e.preventDefault()
      e.stopPropagation()
      const files = Array.from(e.dataTransfer.files)
      if (files.length === 0) return

      setUploading(true)
      try {
        await uploadSkillAssets(skillId, files, targetDir)
        loadAssets()
      } finally {
        setUploading(false)
      }
    },
    [skillId, targetDir, loadAssets],
  )

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault()
    e.stopPropagation()
  }

  if (loading) {
    return (
      <div className="p-4 text-sm text-muted-foreground animate-pulse">
        Loading assets...
      </div>
    )
  }

  // Group assets by directory
  const grouped = DIRECTORIES.reduce(
    (acc, dir) => {
      acc[dir] = assets.filter((a) => a.directory === dir)
      return acc
    },
    {} as Record<string, SkillAsset[]>,
  )

  return (
    <div className="flex flex-col h-full">
      {/* Upload area */}
      <div
        className="p-3 border-b border-border"
        onDrop={handleDrop}
        onDragOver={handleDragOver}
      >
        <div className="flex items-center gap-2 mb-2">
          <select
            value={targetDir}
            onChange={(e) =>
              setTargetDir(e.target.value as (typeof DIRECTORIES)[number])
            }
            className="text-xs bg-background border border-border rounded px-2 py-1"
          >
            {DIRECTORIES.map((d) => (
              <option key={d} value={d}>
                {DIR_LABELS[d].label}
              </option>
            ))}
          </select>
          <button
            onClick={() => fileInputRef.current?.click()}
            disabled={uploading}
            className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded hover:opacity-90 disabled:opacity-50"
          >
            {uploading ? 'Uploading...' : 'Upload'}
          </button>
        </div>
        <input
          ref={fileInputRef}
          type="file"
          multiple
          onChange={handleUpload}
          className="hidden"
        />
        {!isFolder && assets.length === 0 && (
          <p className="text-xs text-muted-foreground">
            Drop files here or click Upload to convert this skill to folder
            format.
          </p>
        )}
        {isFolder && assets.length === 0 && (
          <p className="text-xs text-muted-foreground">
            No assets yet. Drop files or click Upload.
          </p>
        )}
      </div>

      {/* File tree */}
      <div className="flex-1 overflow-y-auto p-2">
        {DIRECTORIES.map((dir) => {
          const dirAssets = grouped[dir]
          if (dirAssets.length === 0) return null

          return (
            <div key={dir} className="mb-3">
              <div className="text-xs font-medium text-muted-foreground uppercase tracking-wider px-2 mb-1">
                {DIR_LABELS[dir].label}{' '}
                <span className="text-muted-foreground/60">
                  ({dirAssets.length})
                </span>
              </div>
              <div className="space-y-0.5">
                {dirAssets.map((asset) => (
                  <div
                    key={asset.path}
                    className="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-muted/50 group"
                  >
                    <span className="text-sm">
                      {FILE_ICONS[asset.type] || '\u{1F4C4}'}
                    </span>
                    <span className="flex-1 text-xs truncate text-foreground">
                      {asset.name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                      {formatSize(asset.size)}
                    </span>
                    <button
                      onClick={() => handleDelete(asset.path)}
                      className="opacity-0 group-hover:opacity-100 text-xs text-destructive hover:text-destructive/80 transition-opacity"
                      title="Delete"
                    >
                      x
                    </button>
                  </div>
                ))}
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
