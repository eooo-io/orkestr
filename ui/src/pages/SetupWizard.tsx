import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  fetchSetupStatus,
  setupApiKeys,
  setupDefaultModel,
  setupQuickStart,
  completeSetup,
  fetchModels,
} from '@/api/client'
import type { SetupStatus } from '@/types'
import { Check, ChevronRight, Key, Cpu, Rocket, Loader2 } from 'lucide-react'

type Step = 'api-keys' | 'default-model' | 'quick-start' | 'done'

const STEPS: { id: Step; label: string; icon: typeof Key }[] = [
  { id: 'api-keys', label: 'API Keys', icon: Key },
  { id: 'default-model', label: 'Default Model', icon: Cpu },
  { id: 'quick-start', label: 'Quick Start', icon: Rocket },
  { id: 'done', label: 'Complete', icon: Check },
]

export function SetupWizard() {
  const navigate = useNavigate()
  const [status, setStatus] = useState<SetupStatus | null>(null)
  const [currentStep, setCurrentStep] = useState<Step>('api-keys')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  // API keys form
  const [anthropicKey, setAnthropicKey] = useState('')
  const [openaiKey, setOpenaiKey] = useState('')
  const [geminiKey, setGeminiKey] = useState('')

  // Default model
  const [models, setModels] = useState<string[]>([])
  const [selectedModel, setSelectedModel] = useState('claude-sonnet-4-6')

  useEffect(() => {
    fetchSetupStatus()
      .then((s) => {
        setStatus(s)
        if (s.completed) {
          navigate('/dashboard', { replace: true })
        }
      })
      .finally(() => setLoading(false))

    fetchModels()
      .then((m) => setModels(m.map((mod: { id: string }) => mod.id)))
      .catch(() => {})
  }, [navigate])

  const stepIndex = STEPS.findIndex((s) => s.id === currentStep)

  const handleSaveKeys = async () => {
    setSaving(true)
    try {
      const keys: Record<string, string> = {}
      if (anthropicKey) keys.anthropic = anthropicKey
      if (openaiKey) keys.openai = openaiKey
      if (geminiKey) keys.gemini = geminiKey
      await setupApiKeys(keys)
      setCurrentStep('default-model')
    } catch {
      // handled silently
    } finally {
      setSaving(false)
    }
  }

  const handleSaveModel = async () => {
    setSaving(true)
    try {
      await setupDefaultModel(selectedModel)
      setCurrentStep('quick-start')
    } catch {
      // handled silently
    } finally {
      setSaving(false)
    }
  }

  const handleQuickStart = async () => {
    setSaving(true)
    try {
      await setupQuickStart()
      setCurrentStep('done')
    } catch {
      // handled silently
    } finally {
      setSaving(false)
    }
  }

  const handleSkipQuickStart = () => {
    setCurrentStep('done')
  }

  const handleComplete = async () => {
    setSaving(true)
    try {
      await completeSetup()
      navigate('/dashboard', { replace: true })
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen bg-background">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-6">
      <div className="w-full max-w-2xl">
        {/* Header */}
        <div className="text-center mb-8">
          <img src="/logo.png" alt="Orkestr" className="h-12 w-12 mx-auto mb-3" />
          <h1 className="text-2xl font-bold text-foreground">Welcome to Orkestr</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Let's get your workspace set up in a few quick steps.
          </p>
        </div>

        {/* Step indicators */}
        <div className="flex items-center justify-center gap-2 mb-8">
          {STEPS.map((step, i) => {
            const Icon = step.icon
            const isComplete = i < stepIndex
            const isCurrent = i === stepIndex
            return (
              <div key={step.id} className="flex items-center gap-2">
                {i > 0 && (
                  <ChevronRight className="h-4 w-4 text-muted-foreground/40" />
                )}
                <div
                  className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${
                    isComplete
                      ? 'bg-primary/20 text-primary'
                      : isCurrent
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground'
                  }`}
                >
                  <Icon className="h-3.5 w-3.5" />
                  {step.label}
                </div>
              </div>
            )
          })}
        </div>

        {/* Step content */}
        <div className="border border-border rounded-lg bg-card p-6">
          {currentStep === 'api-keys' && (
            <div className="space-y-4">
              <div>
                <h2 className="text-lg font-semibold">Configure API Keys</h2>
                <p className="text-sm text-muted-foreground mt-1">
                  Add at least one provider key to enable AI features. You can add more later in Settings.
                </p>
              </div>

              <div className="space-y-3">
                <div>
                  <label className="block text-sm font-medium mb-1">Anthropic API Key</label>
                  <input
                    type="password"
                    value={anthropicKey}
                    onChange={(e) => setAnthropicKey(e.target.value)}
                    placeholder="sk-ant-..."
                    className="w-full px-3 py-2 text-sm border border-border rounded bg-background"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">OpenAI API Key</label>
                  <input
                    type="password"
                    value={openaiKey}
                    onChange={(e) => setOpenaiKey(e.target.value)}
                    placeholder="sk-..."
                    className="w-full px-3 py-2 text-sm border border-border rounded bg-background"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium mb-1">Gemini API Key</label>
                  <input
                    type="password"
                    value={geminiKey}
                    onChange={(e) => setGeminiKey(e.target.value)}
                    placeholder="AI..."
                    className="w-full px-3 py-2 text-sm border border-border rounded bg-background"
                  />
                </div>
              </div>

              <div className="flex justify-between pt-2">
                <button
                  onClick={() => setCurrentStep('default-model')}
                  className="text-sm text-muted-foreground hover:text-foreground"
                >
                  Skip for now
                </button>
                <button
                  onClick={handleSaveKeys}
                  disabled={saving || (!anthropicKey && !openaiKey && !geminiKey)}
                  className="px-4 py-2 text-sm bg-primary text-primary-foreground rounded hover:bg-primary/90 disabled:opacity-50"
                >
                  {saving ? 'Saving...' : 'Save & Continue'}
                </button>
              </div>
            </div>
          )}

          {currentStep === 'default-model' && (
            <div className="space-y-4">
              <div>
                <h2 className="text-lg font-semibold">Choose Default Model</h2>
                <p className="text-sm text-muted-foreground mt-1">
                  Select the default model for new skills and playground sessions.
                </p>
              </div>

              <div>
                <select
                  value={selectedModel}
                  onChange={(e) => setSelectedModel(e.target.value)}
                  className="w-full px-3 py-2 text-sm border border-border rounded bg-background"
                >
                  {models.length > 0 ? (
                    models.map((m) => (
                      <option key={m} value={m}>
                        {m}
                      </option>
                    ))
                  ) : (
                    <>
                      <option value="claude-sonnet-4-6">claude-sonnet-4-6</option>
                      <option value="claude-opus-4-6">claude-opus-4-6</option>
                      <option value="gpt-5.4">gpt-5.4</option>
                      <option value="gemini-3.1-pro">gemini-3.1-pro</option>
                    </>
                  )}
                </select>
              </div>

              <div className="flex justify-end pt-2">
                <button
                  onClick={handleSaveModel}
                  disabled={saving}
                  className="px-4 py-2 text-sm bg-primary text-primary-foreground rounded hover:bg-primary/90 disabled:opacity-50"
                >
                  {saving ? 'Saving...' : 'Continue'}
                </button>
              </div>
            </div>
          )}

          {currentStep === 'quick-start' && (
            <div className="space-y-4">
              <div>
                <h2 className="text-lg font-semibold">Quick Start Project</h2>
                <p className="text-sm text-muted-foreground mt-1">
                  Create a demo project with sample skills so you can explore the interface right away.
                </p>
              </div>

              <div className="border border-border rounded p-4 bg-muted/30">
                <p className="text-sm font-medium">This will create:</p>
                <ul className="mt-2 space-y-1 text-sm text-muted-foreground">
                  <li>A "Getting Started" project</li>
                  <li>3 sample skills (summarizer, code reviewer, translator)</li>
                  <li>Provider configurations for Claude and OpenAI</li>
                </ul>
              </div>

              <div className="flex justify-between pt-2">
                <button
                  onClick={handleSkipQuickStart}
                  className="text-sm text-muted-foreground hover:text-foreground"
                >
                  Skip — I'll start from scratch
                </button>
                <button
                  onClick={handleQuickStart}
                  disabled={saving}
                  className="px-4 py-2 text-sm bg-primary text-primary-foreground rounded hover:bg-primary/90 disabled:opacity-50"
                >
                  {saving ? 'Creating...' : 'Create Demo Project'}
                </button>
              </div>
            </div>
          )}

          {currentStep === 'done' && (
            <div className="text-center space-y-4 py-4">
              <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary/20 text-primary">
                <Check className="h-6 w-6" />
              </div>
              <div>
                <h2 className="text-lg font-semibold">You're all set!</h2>
                <p className="text-sm text-muted-foreground mt-1">
                  Your workspace is ready. You can always change these settings later.
                </p>
              </div>
              <button
                onClick={handleComplete}
                disabled={saving}
                className="px-6 py-2 text-sm bg-primary text-primary-foreground rounded hover:bg-primary/90 disabled:opacity-50"
              >
                {saving ? 'Finishing...' : 'Go to Dashboard'}
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
