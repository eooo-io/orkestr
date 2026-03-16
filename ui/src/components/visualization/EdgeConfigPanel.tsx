import { useState, useEffect, useCallback } from 'react'
import { X, ArrowRight, Save, Check, AlertTriangle } from 'lucide-react'
import { saveDelegationConfigs } from '@/api/client'
import type { DelegationConfig } from '@/types'

export interface EdgeConfigData {
  edgeId: string
  sourceAgentName: string
  targetAgentName: string
  sourceAgentId?: number
  targetAgentId?: number
  delegationTrigger: string
  handoffContext: {
    pass_conversation: boolean
    pass_memory: boolean
    pass_tools: boolean
    custom_json: string
  }
  returnBehavior: 'report_back' | 'fire_and_forget' | 'chain_forward'
}

interface Props {
  edgeId: string
  sourceAgentName: string
  targetAgentName: string
  sourceAgentId?: number
  targetAgentId?: number
  projectId?: number
  config: EdgeConfigData | null
  onSave: (data: EdgeConfigData) => void
  onClose: () => void
}

export default function EdgeConfigPanel({
  edgeId,
  sourceAgentName,
  targetAgentName,
  sourceAgentId,
  targetAgentId,
  projectId,
  config,
  onSave,
  onClose,
}: Props) {
  const [trigger, setTrigger] = useState(config?.delegationTrigger ?? '')
  const [passConversation, setPassConversation] = useState(config?.handoffContext.pass_conversation ?? true)
  const [passMemory, setPassMemory] = useState(config?.handoffContext.pass_memory ?? false)
  const [passTools, setPassTools] = useState(config?.handoffContext.pass_tools ?? false)
  const [customJson, setCustomJson] = useState(config?.handoffContext.custom_json ?? '')
  const [returnBehavior, setReturnBehavior] = useState<EdgeConfigData['returnBehavior']>(
    config?.returnBehavior ?? 'report_back',
  )
  const [saving, setSaving] = useState(false)
  const [saved, setSaved] = useState(false)
  const [apiError, setApiError] = useState<string | null>(null)

  useEffect(() => {
    setTrigger(config?.delegationTrigger ?? '')
    setPassConversation(config?.handoffContext.pass_conversation ?? true)
    setPassMemory(config?.handoffContext.pass_memory ?? false)
    setPassTools(config?.handoffContext.pass_tools ?? false)
    setCustomJson(config?.handoffContext.custom_json ?? '')
    setReturnBehavior(config?.returnBehavior ?? 'report_back')
  }, [config, edgeId])

  const handleSave = useCallback(async () => {
    const configData: EdgeConfigData = {
      edgeId,
      sourceAgentName,
      targetAgentName,
      sourceAgentId,
      targetAgentId,
      delegationTrigger: trigger,
      handoffContext: {
        pass_conversation: passConversation,
        pass_memory: passMemory,
        pass_tools: passTools,
        custom_json: customJson,
      },
      returnBehavior,
    }

    // Save optimistically to local state
    onSave(configData)

    // Attempt to persist to backend (#347)
    if (projectId && sourceAgentId && targetAgentId) {
      setSaving(true)
      setApiError(null)
      setSaved(false)
      try {
        const delegationConfig: DelegationConfig = {
          edge_id: edgeId,
          source_agent_id: sourceAgentId,
          target_agent_id: targetAgentId,
          delegation_trigger: trigger,
          handoff_context: {
            pass_conversation: passConversation,
            pass_memory: passMemory,
            pass_tools: passTools,
            custom_json: customJson,
          },
          return_behavior: returnBehavior,
        }
        await saveDelegationConfigs(projectId, [delegationConfig])
        setSaved(true)
        setTimeout(() => setSaved(false), 2000)
      } catch {
        // Gracefully handle 404 if backend endpoint doesn't exist yet
        setApiError('Saved locally (backend sync pending)')
        setTimeout(() => setApiError(null), 3000)
      } finally {
        setSaving(false)
      }
    }
  }, [edgeId, sourceAgentName, targetAgentName, sourceAgentId, targetAgentId, projectId, trigger, passConversation, passMemory, passTools, customJson, returnBehavior, onSave])

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [onClose])

  return (
    <div
      className="fixed top-0 right-0 h-full w-[350px] bg-zinc-900 border-l border-zinc-700 shadow-2xl z-50 overflow-y-auto transition-transform duration-200 animate-slide-in-right"
      onClick={(e) => e.stopPropagation()}
    >
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
        <h3 className="text-sm font-semibold text-zinc-200">Delegation Edge</h3>
        <button
          onClick={onClose}
          className="p-1 text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800 rounded transition-colors"
        >
          <X className="h-4 w-4" />
        </button>
      </div>

      <div className="p-4 space-y-5">
        {/* Source -> Target */}
        <div className="flex items-center gap-2 px-3 py-2 bg-zinc-800/60 rounded-lg">
          <span className="text-xs font-medium text-violet-300 truncate">{sourceAgentName}</span>
          <ArrowRight className="h-3.5 w-3.5 text-cyan-400 shrink-0" />
          <span className="text-xs font-medium text-violet-300 truncate">{targetAgentName}</span>
        </div>

        {/* Delegation trigger */}
        <div className="space-y-1.5">
          <label className="text-xs font-medium text-zinc-400">Delegation Trigger</label>
          <textarea
            value={trigger}
            onChange={(e) => setTrigger(e.target.value)}
            placeholder="Condition under which this delegation happens..."
            className="w-full h-20 px-3 py-2 text-xs bg-zinc-800 border border-zinc-700 rounded-md text-zinc-200 placeholder-zinc-500 focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 resize-none"
          />
        </div>

        {/* Handoff context */}
        <div className="space-y-2">
          <label className="text-xs font-medium text-zinc-400">Handoff Context</label>

          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={passConversation}
              onChange={(e) => setPassConversation(e.target.checked)}
              className="w-3.5 h-3.5 rounded border-zinc-600 bg-zinc-800 text-cyan-500 focus:ring-cyan-600"
            />
            <span className="text-xs text-zinc-300">Pass conversation history</span>
          </label>

          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={passMemory}
              onChange={(e) => setPassMemory(e.target.checked)}
              className="w-3.5 h-3.5 rounded border-zinc-600 bg-zinc-800 text-cyan-500 focus:ring-cyan-600"
            />
            <span className="text-xs text-zinc-300">Pass agent memory</span>
          </label>

          <label className="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              checked={passTools}
              onChange={(e) => setPassTools(e.target.checked)}
              className="w-3.5 h-3.5 rounded border-zinc-600 bg-zinc-800 text-cyan-500 focus:ring-cyan-600"
            />
            <span className="text-xs text-zinc-300">Pass available tools</span>
          </label>

          <div className="mt-2 space-y-1">
            <label className="text-[11px] text-zinc-500">Custom context (JSON)</label>
            <textarea
              value={customJson}
              onChange={(e) => setCustomJson(e.target.value)}
              placeholder='{"key": "value"}'
              className="w-full h-16 px-3 py-2 text-xs font-mono bg-zinc-800 border border-zinc-700 rounded-md text-zinc-200 placeholder-zinc-600 focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 resize-none"
            />
          </div>
        </div>

        {/* Return behavior */}
        <div className="space-y-2">
          <label className="text-xs font-medium text-zinc-400">Return Behavior</label>

          {([
            { value: 'report_back' as const, label: 'Report Back', desc: 'Target returns results to source' },
            { value: 'fire_and_forget' as const, label: 'Fire & Forget', desc: 'No response expected' },
            { value: 'chain_forward' as const, label: 'Chain Forward', desc: 'Target passes to next in chain' },
          ]).map((opt) => (
            <label key={opt.value} className="flex items-start gap-2 cursor-pointer">
              <input
                type="radio"
                name="returnBehavior"
                value={opt.value}
                checked={returnBehavior === opt.value}
                onChange={() => setReturnBehavior(opt.value)}
                className="mt-0.5 w-3.5 h-3.5 border-zinc-600 bg-zinc-800 text-cyan-500 focus:ring-cyan-600"
              />
              <div>
                <span className="text-xs text-zinc-300">{opt.label}</span>
                <p className="text-[10px] text-zinc-500">{opt.desc}</p>
              </div>
            </label>
          ))}
        </div>

        {/* Save */}
        <button
          onClick={handleSave}
          disabled={saving}
          className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-cyan-700 hover:bg-cyan-600 text-white text-xs font-medium rounded-md transition-colors disabled:opacity-50"
        >
          {saved ? <Check className="h-3.5 w-3.5" /> : <Save className="h-3.5 w-3.5" />}
          {saving ? 'Saving...' : saved ? 'Saved' : 'Save Configuration'}
        </button>

        {apiError && (
          <p className="text-[10px] text-amber-400 text-center flex items-center justify-center gap-1">
            <AlertTriangle className="h-3 w-3" />
            {apiError}
          </p>
        )}

        {!apiError && !saved && (
          <p className="text-[10px] text-zinc-600 text-center">
            {projectId ? 'Saves to local state and backend' : 'Configuration stored in local canvas state'}
          </p>
        )}
      </div>
    </div>
  )
}
