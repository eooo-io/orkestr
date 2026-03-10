import { Save, Copy, Trash2, ArrowLeft, Wand2 } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'

interface ActionBarProps {
  onSave: () => void
  onDuplicate?: () => void
  onDelete?: () => void
  onGenerate?: () => void
  isSaving: boolean
  isDirty: boolean
  isNew: boolean
  projectId?: number
}

export function ActionBar({
  onSave,
  onDuplicate,
  onDelete,
  onGenerate,
  isSaving,
  isDirty,
  isNew,
  projectId,
}: ActionBarProps) {
  const navigate = useNavigate()

  return (
    <div className="flex items-center justify-between px-4 py-2 border-b border-border bg-muted/20">
      <div className="flex items-center gap-2">
        <Button
          variant="ghost"
          size="sm"
          onClick={() =>
            projectId ? navigate(`/projects/${projectId}`) : navigate(-1)
          }
        >
          <ArrowLeft className="h-4 w-4 mr-1" />
          Back
        </Button>
      </div>

      <div className="flex items-center gap-2">
        {onGenerate && (
          <Button variant="outline" size="sm" onClick={onGenerate}>
            <Wand2 className="h-4 w-4 mr-1" />
            Generate
          </Button>
        )}
        {!isNew && onDuplicate && (
          <Button variant="ghost" size="sm" onClick={onDuplicate}>
            <Copy className="h-4 w-4 mr-1" />
            Duplicate
          </Button>
        )}
        {!isNew && onDelete && (
          <Button variant="ghost" size="sm" onClick={onDelete} className="text-destructive hover:text-destructive">
            <Trash2 className="h-4 w-4 mr-1" />
            Delete
          </Button>
        )}
        <Button size="sm" onClick={onSave} disabled={isSaving || !isDirty}>
          <Save className="h-4 w-4 mr-1" />
          {isSaving ? 'Saving...' : 'Save'}
        </Button>
      </div>
    </div>
  )
}
