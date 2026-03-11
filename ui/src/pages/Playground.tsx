import { useEffect, useState, useRef, useCallback } from 'react'
import {
  Play,
  Square,
  Trash2,
  Copy,
  Check,
  Clock,
  Hash,
  Bot,
  User,
  Sparkles,
  Coins,
  ChevronDown,
  Settings2,
} from 'lucide-react'
import {
  fetchProjects,
  fetchSkills,
  fetchProjectAgents,
  fetchAgentCompose,
  fetchModels,
  estimateTokens,
} from '@/api/client'
import { Button } from '@/components/ui/button'
import type { Project, Skill, ProjectAgent, ModelGroup } from '@/types'

interface Message {
  role: 'user' | 'assistant'
  content: string
}

interface TurnStats {
  inputTokens: number | null
  outputTokens: number | null
  elapsed: number
}

type PromptSource =
  | { type: 'none' }
  | { type: 'skill'; skill: Skill }
  | { type: 'agent'; projectId: number; agent: ProjectAgent; composed?: string }

// Fallback models if the API call fails
const FALLBACK_MODELS: ModelGroup[] = [
  {
    provider: 'anthropic',
    label: 'Anthropic',
    configured: false,
    models: [
      { id: 'claude-opus-4-6', name: 'Claude Opus 4.6', provider: 'anthropic', context_window: 200000 },
      { id: 'claude-sonnet-4-6', name: 'Claude Sonnet 4.6', provider: 'anthropic', context_window: 200000 },
      { id: 'claude-haiku-4-5-20251001', name: 'Claude Haiku 4.5', provider: 'anthropic', context_window: 200000 },
    ],
  },
]

export function Playground() {
  // Config state
  const [projects, setProjects] = useState<Project[]>([])
  const [selectedProjectId, setSelectedProjectId] = useState<number | null>(null)
  const [skills, setSkills] = useState<Skill[]>([])
  const [agents, setAgents] = useState<ProjectAgent[]>([])
  const [promptSource, setPromptSource] = useState<PromptSource>({ type: 'none' })
  const [model, setModel] = useState('claude-sonnet-4-6')
  const [maxTokens, setMaxTokens] = useState(4096)
  const [modelGroups, setModelGroups] = useState<ModelGroup[]>(FALLBACK_MODELS)

  // Conversation state
  const [messages, setMessages] = useState<Message[]>([])
  const [input, setInput] = useState('')
  const [isStreaming, setIsStreaming] = useState(false)
  const [streamingText, setStreamingText] = useState('')
  const [turnStats, setTurnStats] = useState<TurnStats | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [copied, setCopied] = useState<number | null>(null)

  const abortRef = useRef<AbortController | null>(null)
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null)
  const startTimeRef = useRef<number>(0)
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLTextAreaElement>(null)

  // Load projects and models on mount
  useEffect(() => {
    fetchProjects().then(setProjects)
    fetchModels()
      .then(setModelGroups)
      .catch(() => setModelGroups(FALLBACK_MODELS))
  }, [])

  // Load skills + agents when project changes
  useEffect(() => {
    if (!selectedProjectId) {
      setSkills([])
      setAgents([])
      return
    }
    fetchSkills(selectedProjectId).then(setSkills)
    fetchProjectAgents(selectedProjectId).then((a) =>
      setAgents(a.filter((ag) => ag.is_enabled)),
    )
  }, [selectedProjectId])

  // Auto-scroll
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, streamingText])

  // Cleanup
  useEffect(() => {
    return () => {
      abortRef.current?.abort()
      if (timerRef.current) clearInterval(timerRef.current)
    }
  }, [])

  // Get system prompt from source
  const getSystemPrompt = useCallback(async (): Promise<string> => {
    if (promptSource.type === 'skill') {
      return promptSource.skill.body || ''
    }
    if (promptSource.type === 'agent') {
      if (promptSource.composed) return promptSource.composed
      const result = await fetchAgentCompose(
        promptSource.projectId,
        promptSource.agent.id,
      )
      // Cache it
      setPromptSource((prev) =>
        prev.type === 'agent' ? { ...prev, composed: result.content } : prev,
      )
      return result.content
    }
    return ''
  }, [promptSource])

  const handleSend = useCallback(async () => {
    const text = input.trim()
    if (!text || isStreaming) return

    setInput('')
    setError(null)
    setStreamingText('')
    setTurnStats(null)

    const userMessage: Message = { role: 'user', content: text }
    const allMessages = [...messages, userMessage]
    setMessages(allMessages)

    setIsStreaming(true)
    startTimeRef.current = Date.now()

    const stats: TurnStats = { inputTokens: null, outputTokens: null, elapsed: 0 }
    timerRef.current = setInterval(() => {
      setTurnStats((prev) => ({
        ...(prev || stats),
        elapsed: (Date.now() - startTimeRef.current) / 1000,
      }))
    }, 100)

    const controller = new AbortController()
    abortRef.current = controller

    try {
      const systemPrompt = await getSystemPrompt()
      const apiUrl = import.meta.env.VITE_API_URL || '/api'

      const response = await fetch(`${apiUrl}/playground`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
        },
        body: JSON.stringify({
          system_prompt: systemPrompt || undefined,
          messages: allMessages.map((m) => ({
            role: m.role,
            content: m.content,
          })),
          model,
          max_tokens: maxTokens,
        }),
        signal: controller.signal,
      })

      if (!response.ok || !response.body) {
        throw new Error(`HTTP ${response.status}`)
      }

      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''
      let fullText = ''

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() || ''

        for (const line of lines) {
          if (!line.startsWith('data: ')) continue
          try {
            const data = JSON.parse(line.slice(6))
            if (data.type === 'delta') {
              fullText += data.text
              setStreamingText(fullText)
            } else if (data.type === 'message_start') {
              stats.inputTokens = data.input_tokens
              setTurnStats({ ...stats })
            } else if (data.type === 'message_delta') {
              stats.outputTokens = data.output_tokens
              setTurnStats({ ...stats })
            } else if (data.type === 'error') {
              setError(data.error)
            }
          } catch {
            // skip malformed
          }
        }
      }

      // Add assistant message to conversation
      if (fullText) {
        setMessages((prev) => [...prev, { role: 'assistant', content: fullText }])
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name !== 'AbortError') {
        setError(err.message)
      }
    } finally {
      setIsStreaming(false)
      setStreamingText('')
      if (timerRef.current) {
        clearInterval(timerRef.current)
        timerRef.current = null
      }
      setTurnStats((prev) =>
        prev
          ? { ...prev, elapsed: (Date.now() - startTimeRef.current) / 1000 }
          : null,
      )
      inputRef.current?.focus()
    }
  }, [input, isStreaming, messages, model, maxTokens, getSystemPrompt])

  const handleStop = () => abortRef.current?.abort()

  const handleClear = () => {
    setMessages([])
    setStreamingText('')
    setTurnStats(null)
    setError(null)
  }

  const handleCopy = (idx: number, text: string) => {
    navigator.clipboard.writeText(text)
    setCopied(idx)
    setTimeout(() => setCopied(null), 2000)
  }

  const handleSourceChange = (value: string) => {
    if (value === 'none') {
      setPromptSource({ type: 'none' })
    } else if (value.startsWith('skill:')) {
      const skillId = parseInt(value.slice(6))
      const skill = skills.find((s) => s.id === skillId)
      if (skill) setPromptSource({ type: 'skill', skill })
    } else if (value.startsWith('agent:')) {
      const agentId = parseInt(value.slice(6))
      const agent = agents.find((a) => a.id === agentId)
      if (agent && selectedProjectId) {
        setPromptSource({
          type: 'agent',
          projectId: selectedProjectId,
          agent,
        })
      }
    }
  }

  const sourceValue =
    promptSource.type === 'skill'
      ? `skill:${promptSource.skill.id}`
      : promptSource.type === 'agent'
        ? `agent:${promptSource.agent.id}`
        : 'none'

  const systemPromptPreview =
    promptSource.type === 'skill'
      ? promptSource.skill.body?.slice(0, 200)
      : promptSource.type === 'agent' && promptSource.composed
        ? promptSource.composed.slice(0, 200)
        : null

  const systemTokens =
    promptSource.type === 'skill'
      ? estimateTokens(promptSource.skill.body || '')
      : promptSource.type === 'agent' && promptSource.composed
        ? estimateTokens(promptSource.composed)
        : 0

  const [configOpen, setConfigOpen] = useState(false)

  return (
    <div className="flex flex-col md:flex-row h-screen">
      {/* Mobile config toggle */}
      <div className="flex items-center justify-between px-4 py-2 border-b border-border bg-muted/20 md:hidden">
        <h1 className="text-base font-semibold flex items-center gap-2">
          <Bot className="h-4 w-4 text-primary" />
          Playground
        </h1>
        <Button variant="outline" size="sm" onClick={() => setConfigOpen(!configOpen)}>
          <Settings2 className="h-4 w-4 mr-1" />
          Config
        </Button>
      </div>

      {/* Left: Config sidebar */}
      <div className={`${configOpen ? 'block' : 'hidden'} md:block w-full md:w-72 border-b md:border-b-0 md:border-r border-border bg-muted/20 flex flex-col shrink-0`}>
        <div className="hidden md:block p-4 border-b border-border">
          <h1 className="text-lg font-semibold flex items-center gap-2">
            <Bot className="h-5 w-5 text-primary" />
            Playground
          </h1>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {/* Project picker */}
          <div>
            <label className="text-xs font-medium text-muted-foreground">
              Project
            </label>
            <div className="relative mt-1">
              <select
                value={selectedProjectId ?? ''}
                onChange={(e) => {
                  const v = e.target.value ? parseInt(e.target.value) : null
                  setSelectedProjectId(v)
                  setPromptSource({ type: 'none' })
                }}
                className="w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring appearance-none pr-8"
              >
                <option value="">Select a project...</option>
                {projects.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.name}
                  </option>
                ))}
              </select>
              <ChevronDown className="absolute right-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
            </div>
          </div>

          {/* System prompt source */}
          {selectedProjectId && (
            <div>
              <label className="text-xs font-medium text-muted-foreground">
                System Prompt
              </label>
              <div className="relative mt-1">
                <select
                  value={sourceValue}
                  onChange={(e) => handleSourceChange(e.target.value)}
                  className="w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring appearance-none pr-8"
                >
                  <option value="none">None (freeform)</option>
                  {skills.length > 0 && (
                    <optgroup label="Skills">
                      {skills.map((s) => (
                        <option key={`skill:${s.id}`} value={`skill:${s.id}`}>
                          {s.name}
                        </option>
                      ))}
                    </optgroup>
                  )}
                  {agents.length > 0 && (
                    <optgroup label="Agents (composed)">
                      {agents.map((a) => (
                        <option key={`agent:${a.id}`} value={`agent:${a.id}`}>
                          {a.name}
                        </option>
                      ))}
                    </optgroup>
                  )}
                </select>
                <ChevronDown className="absolute right-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
              </div>

              {/* System prompt preview */}
              {systemPromptPreview && (
                <div className="mt-2 p-2 bg-muted/50 border border-border">
                  <p className="text-[10px] text-muted-foreground line-clamp-3 font-mono">
                    {systemPromptPreview}...
                  </p>
                  {systemTokens > 0 && (
                    <div className="flex items-center gap-1 mt-1.5 text-[10px] text-muted-foreground">
                      <Coins className="h-2.5 w-2.5" />
                      ~{systemTokens.toLocaleString()} tokens
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* Model — grouped by provider */}
          <div>
            <label className="text-xs font-medium text-muted-foreground">
              Model
            </label>
            <div className="relative mt-1">
              <select
                value={model}
                onChange={(e) => setModel(e.target.value)}
                className="w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring appearance-none pr-8"
              >
                {modelGroups.map((group) => (
                  <optgroup
                    key={group.provider}
                    label={`${group.label}${group.configured ? '' : ' (not configured)'}`}
                  >
                    {group.models.map((m) => (
                      <option
                        key={m.id}
                        value={m.id}
                        disabled={!group.configured}
                      >
                        {m.name}
                        {m.context_window > 0
                          ? ` (${Math.round(m.context_window / 1000)}k ctx)`
                          : ''}
                      </option>
                    ))}
                  </optgroup>
                ))}
              </select>
              <ChevronDown className="absolute right-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground pointer-events-none" />
            </div>
            {/* Show provider for selected model */}
            {(() => {
              const group = modelGroups.find((g) =>
                g.models.some((m) => m.id === model),
              )
              return group ? (
                <p className="text-[10px] text-muted-foreground mt-1">
                  Provider: {group.label}
                  {!group.configured && (
                    <span className="text-destructive ml-1">
                      -- API key not set
                    </span>
                  )}
                </p>
              ) : null
            })()}
          </div>

          {/* Max Tokens */}
          <div>
            <label className="text-xs font-medium text-muted-foreground">
              Max Output Tokens
            </label>
            <input
              type="number"
              value={maxTokens}
              onChange={(e) => setMaxTokens(parseInt(e.target.value) || 4096)}
              min={1}
              max={128000}
              className="mt-1 w-full px-2.5 py-1.5 text-sm border border-input bg-background focus:outline-none focus:ring-1 focus:ring-ring"
            />
          </div>
        </div>

        {/* Clear button */}
        <div className="p-4 border-t border-border">
          <Button
            variant="outline"
            size="sm"
            className="w-full"
            onClick={handleClear}
            disabled={messages.length === 0 && !isStreaming}
          >
            <Trash2 className="h-3.5 w-3.5 mr-1" />
            Clear Conversation
          </Button>
        </div>
      </div>

      {/* Right: Chat area */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Messages */}
        <div className="flex-1 overflow-y-auto">
          {messages.length === 0 && !isStreaming && (
            <div className="flex flex-col items-center justify-center h-full text-muted-foreground gap-2">
              <Sparkles className="h-8 w-8 opacity-30" />
              <p className="text-sm">
                {selectedProjectId
                  ? 'Select a skill or agent, then send a message.'
                  : 'Select a project to get started.'}
              </p>
            </div>
          )}

          <div className="max-w-3xl mx-auto py-6 px-4 space-y-4">
            {messages.map((msg, idx) => (
              <div
                key={idx}
                className={`flex gap-3 ${msg.role === 'user' ? '' : ''}`}
              >
                <div
                  className={`shrink-0 w-7 h-7 flex items-center justify-center mt-0.5 ${
                    msg.role === 'user'
                      ? 'bg-primary text-primary-foreground'
                      : 'bg-muted text-muted-foreground'
                  }`}
                >
                  {msg.role === 'user' ? (
                    <User className="h-3.5 w-3.5" />
                  ) : (
                    <Bot className="h-3.5 w-3.5" />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="text-xs font-medium">
                      {msg.role === 'user' ? 'You' : 'Assistant'}
                    </span>
                    {msg.role === 'assistant' && (
                      <button
                        onClick={() => handleCopy(idx, msg.content)}
                        className="text-muted-foreground hover:text-foreground transition-all duration-150"
                      >
                        {copied === idx ? (
                          <Check className="h-3 w-3 text-green-500" />
                        ) : (
                          <Copy className="h-3 w-3" />
                        )}
                      </button>
                    )}
                  </div>
                  <pre className="text-sm whitespace-pre-wrap font-sans leading-relaxed">
                    {msg.content}
                  </pre>
                </div>
              </div>
            ))}

            {/* Streaming message */}
            {isStreaming && (
              <div className="flex gap-3">
                <div className="shrink-0 w-7 h-7 flex items-center justify-center mt-0.5 bg-muted text-muted-foreground">
                  <Bot className="h-3.5 w-3.5" />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="text-xs font-medium">Assistant</span>
                    <span className="text-[10px] text-muted-foreground animate-pulse">
                      streaming...
                    </span>
                  </div>
                  {streamingText ? (
                    <pre className="text-sm whitespace-pre-wrap font-sans leading-relaxed">
                      {streamingText}
                      <span className="inline-block w-1.5 h-4 bg-primary animate-pulse ml-0.5 align-text-bottom" />
                    </pre>
                  ) : (
                    <div className="text-sm text-muted-foreground animate-pulse">
                      Thinking...
                    </div>
                  )}
                </div>
              </div>
            )}

            <div ref={messagesEndRef} />
          </div>
        </div>

        {/* Stats bar */}
        {turnStats && (
          <div className="flex items-center gap-4 px-4 py-1.5 border-t border-border text-[10px] text-muted-foreground bg-muted/30 justify-center">
            <span className="flex items-center gap-1">
              <Clock className="h-3 w-3" />
              {turnStats.elapsed.toFixed(1)}s
            </span>
            {turnStats.inputTokens !== null && (
              <span className="flex items-center gap-1">
                <Hash className="h-3 w-3" />
                In: {turnStats.inputTokens.toLocaleString()}
              </span>
            )}
            {turnStats.outputTokens !== null && (
              <span className="flex items-center gap-1">
                <Hash className="h-3 w-3" />
                Out: {turnStats.outputTokens.toLocaleString()}
              </span>
            )}
          </div>
        )}

        {/* Error */}
        {error && (
          <div className="px-4 py-2 bg-destructive/10 text-destructive text-sm border-t border-border">
            {error}
          </div>
        )}

        {/* Input area */}
        <div className="border-t border-border p-4">
          <div className="max-w-3xl mx-auto flex gap-2">
            <textarea
              ref={inputRef}
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Send a message..."
              rows={2}
              className="flex-1 px-3 py-2 text-sm border border-input bg-background resize-none focus:outline-none focus:ring-1 focus:ring-ring"
              onKeyDown={(e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                  e.preventDefault()
                  handleSend()
                }
              }}
              disabled={isStreaming}
            />
            <div className="flex flex-col gap-1">
              {isStreaming ? (
                <Button
                  variant="destructive"
                  size="sm"
                  onClick={handleStop}
                  className="h-full"
                >
                  <Square className="h-4 w-4" />
                </Button>
              ) : (
                <Button
                  size="sm"
                  onClick={handleSend}
                  disabled={!input.trim()}
                  className="h-full"
                >
                  <Play className="h-4 w-4" />
                </Button>
              )}
            </div>
          </div>
          <p className="text-[10px] text-muted-foreground text-center mt-1.5">
            Ctrl+Enter to send
          </p>
        </div>
      </div>
    </div>
  )
}
