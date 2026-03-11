import { useState } from 'react'
import { Wand2, Loader2, X } from 'lucide-react'
import { generateSkill } from '@/api/client'
import { Button } from '@/components/ui/button'
import type { GeneratedSkill } from '@/types'

interface GenerateSkillModalProps {
  onGenerated: (skill: GeneratedSkill) => void
  onClose: () => void
}

export function GenerateSkillModal({
  onGenerated,
  onClose,
}: GenerateSkillModalProps) {
  const [description, setDescription] = useState('')
  const [constraints, setConstraints] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleGenerate = async () => {
    if (!description.trim()) return

    setLoading(true)
    setError(null)

    try {
      const result = await generateSkill(description, constraints)
      onGenerated(result)
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message || 'Generation failed. Please try again.'
      setError(message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="fixed inset-0 bg-foreground/30 flex items-center justify-center z-50 p-4">
      <div className="bg-background elevation-4 w-full max-w-lg">
        <div className="flex items-center justify-between px-4 py-3 border-b border-border">
          <div className="flex items-center gap-2">
            <Wand2 className="h-4 w-4 text-primary" />
            <h2 className="font-semibold text-sm">Generate Skill with AI</h2>
          </div>
          <button
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="p-4 space-y-4">
          <div>
            <label className="text-xs font-medium text-muted-foreground">
              What should this skill do?
            </label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="e.g. A code reviewer that focuses on security vulnerabilities, follows OWASP top 10, and suggests fixes with code examples"
              className="mt-1 w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring resize-none"
              rows={4}
              maxLength={2000}
              autoFocus
            />
            <p className="text-[10px] text-muted-foreground mt-1 text-right">
              {description.length}/2000
            </p>
          </div>

          <div>
            <label className="text-xs font-medium text-muted-foreground">
              Additional constraints (optional)
            </label>
            <textarea
              value={constraints}
              onChange={(e) => setConstraints(e.target.value)}
              placeholder="e.g. Keep it under 500 words, use bullet points, focus on PHP/Laravel"
              className="mt-1 w-full px-3 py-2 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring resize-none"
              rows={2}
              maxLength={1000}
            />
          </div>

          {error && (
            <div className="text-sm text-destructive bg-destructive/10 px-3 py-2">
              {error}
            </div>
          )}
        </div>

        <div className="flex items-center justify-end gap-2 px-4 py-3 border-t border-border">
          <Button variant="ghost" size="sm" onClick={onClose} disabled={loading}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={handleGenerate}
            disabled={loading || !description.trim()}
          >
            {loading ? (
              <>
                <Loader2 className="h-4 w-4 mr-1 animate-spin" />
                Generating...
              </>
            ) : (
              <>
                <Wand2 className="h-4 w-4 mr-1" />
                Generate
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  )
}
