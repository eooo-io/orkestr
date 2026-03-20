import { useEffect, useState, useRef, useCallback } from 'react'
import {
  MessageSquare,
  Plus,
  Trash2,
  Send,
  Bot,
  User,
  Wrench,
  ChevronDown,
  ChevronRight,
  Loader2,
  Terminal,
  Sparkles,
  X,
} from 'lucide-react'
import { useAppStore } from '@/store/useAppStore'

// ── Types ───────────────────────────────────────────────────────────

interface ControlPlaneSession {
  id: number
  uuid: string
  title: string | null
  context: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

interface ControlPlaneMessage {
  id: number
  session_id: number
  role: 'user' | 'assistant' | 'system' | 'tool_result'
  content: string
  tool_calls: ToolCall[] | null
  metadata: Record<string, unknown> | null
  created_at: string
}

interface ToolCall {
  id: string
  name: string
  input: Record<string, unknown>
}

interface ToolResultEvent {
  type: 'tool_result'
  tool_name: string
  tool_id: string
  result: Record<string, unknown>
}

interface SSEEvent {
  type: 'delta' | 'tool_call' | 'tool_result' | 'done' | 'error'
  text?: string
  tool_name?: string
  tool_id?: string
  input?: Record<string, unknown>
  result?: Record<string, unknown>
  error?: string
  input_tokens?: number
  output_tokens?: number
}

// ── API helpers ─────────────────────────────────────────────────────

const API_BASE = '/api/control-plane'

async function fetchSessions(): Promise<ControlPlaneSession[]> {
  const res = await fetch(API_BASE, { credentials: 'include' })
  const json = await res.json()
  return json.data ?? []
}

async function createSession(
  context?: Record<string, unknown>,
): Promise<ControlPlaneSession> {
  const res = await fetch(API_BASE, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ context }),
  })
  const json = await res.json()
  return json.data
}

async function fetchSession(
  sessionId: number,
): Promise<{ session: ControlPlaneSession; messages: ControlPlaneMessage[] }> {
  const res = await fetch(`${API_BASE}/${sessionId}`, { credentials: 'include' })
  const json = await res.json()
  return { session: json.data, messages: json.data.messages ?? [] }
}

async function deleteSession(sessionId: number): Promise<void> {
  await fetch(`${API_BASE}/${sessionId}`, {
    method: 'DELETE',
    credentials: 'include',
  })
}

// ── Tool Result Card ────────────────────────────────────────────────

function ToolResultCard({
  toolName,
  input,
  result,
}: {
  toolName: string
  input?: Record<string, unknown>
  result?: Record<string, unknown>
}) {
  const [expanded, setExpanded] = useState(false)

  const hasError = result && 'error' in result
  const borderColor = hasError ? 'border-red-500/30' : 'border-green-500/30'
  const bgColor = hasError ? 'bg-red-500/5' : 'bg-green-500/5'

  return (
    <div className={`rounded border ${borderColor} ${bgColor} my-2 text-sm`}>
      <button
        onClick={() => setExpanded(!expanded)}
        className="flex items-center gap-2 w-full px-3 py-2 text-left hover:bg-muted/20 transition-colors"
      >
        {expanded ? (
          <ChevronDown className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
        ) : (
          <ChevronRight className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
        )}
        <Wrench className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
        <span className="font-mono text-xs">{toolName}</span>
        {hasError && (
          <span className="text-[10px] text-red-500 font-medium ml-auto">
            error
          </span>
        )}
        {!hasError && (
          <span className="text-[10px] text-green-600 font-medium ml-auto">
            success
          </span>
        )}
      </button>
      {expanded && (
        <div className="px-3 pb-3 space-y-2">
          {input && Object.keys(input).length > 0 && (
            <div>
              <div className="text-[10px] uppercase text-muted-foreground font-semibold mb-1">
                Input
              </div>
              <pre className="text-xs font-mono bg-muted/30 rounded p-2 overflow-x-auto whitespace-pre-wrap">
                {JSON.stringify(input, null, 2)}
              </pre>
            </div>
          )}
          {result && (
            <div>
              <div className="text-[10px] uppercase text-muted-foreground font-semibold mb-1">
                Result
              </div>
              <pre className="text-xs font-mono bg-muted/30 rounded p-2 overflow-x-auto whitespace-pre-wrap">
                {JSON.stringify(result, null, 2)}
              </pre>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

// ── Message Bubble ──────────────────────────────────────────────────

function MessageBubble({
  message,
  toolResults,
}: {
  message: ControlPlaneMessage
  toolResults?: ToolResultEvent[]
}) {
  const isUser = message.role === 'user'

  if (message.role === 'tool_result') {
    const meta = message.metadata as Record<string, unknown> | null
    return (
      <ToolResultCard
        toolName={(meta?.tool_name as string) ?? 'unknown'}
        input={meta?.input as Record<string, unknown> | undefined}
        result={JSON.parse(message.content || '{}')}
      />
    )
  }

  return (
    <div className={`flex gap-3 ${isUser ? 'justify-end' : 'justify-start'}`}>
      {!isUser && (
        <div className="shrink-0 w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center mt-1">
          <Bot className="h-4 w-4 text-primary" />
        </div>
      )}
      <div
        className={`max-w-[80%] rounded-lg px-4 py-2.5 text-sm whitespace-pre-wrap ${
          isUser
            ? 'bg-primary text-primary-foreground'
            : 'bg-muted/50 text-foreground'
        }`}
      >
        {message.content}
        {/* Inline tool results for assistant messages */}
        {toolResults &&
          toolResults.map((tr, i) => (
            <ToolResultCard
              key={i}
              toolName={tr.tool_name}
              result={tr.result}
            />
          ))}
      </div>
      {isUser && (
        <div className="shrink-0 w-7 h-7 rounded-full bg-muted flex items-center justify-center mt-1">
          <User className="h-4 w-4 text-muted-foreground" />
        </div>
      )}
    </div>
  )
}

// ── Streaming Message ───────────────────────────────────────────────

function StreamingMessage({
  text,
  toolEvents,
}: {
  text: string
  toolEvents: SSEEvent[]
}) {
  return (
    <div className="flex gap-3 justify-start">
      <div className="shrink-0 w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center mt-1">
        <Bot className="h-4 w-4 text-primary" />
      </div>
      <div className="max-w-[80%] rounded-lg px-4 py-2.5 text-sm bg-muted/50 text-foreground">
        {toolEvents.map((ev, i) => {
          if (ev.type === 'tool_call') {
            return (
              <div
                key={`tc-${i}`}
                className="flex items-center gap-2 text-xs text-muted-foreground my-1"
              >
                <Loader2 className="h-3 w-3 animate-spin" />
                <span className="font-mono">{ev.tool_name}</span>
              </div>
            )
          }
          if (ev.type === 'tool_result') {
            return (
              <ToolResultCard
                key={`tr-${i}`}
                toolName={ev.tool_name ?? 'unknown'}
                input={ev.input}
                result={ev.result}
              />
            )
          }
          return null
        })}
        {text && <span className="whitespace-pre-wrap">{text}</span>}
        {!text && toolEvents.length === 0 && (
          <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
        )}
      </div>
    </div>
  )
}

// ── Main Component ──────────────────────────────────────────────────

export function ControlPlane() {
  const [sessions, setSessions] = useState<ControlPlaneSession[]>([])
  const [activeSessionId, setActiveSessionId] = useState<number | null>(null)
  const [messages, setMessages] = useState<ControlPlaneMessage[]>([])
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(true)
  const [streaming, setStreaming] = useState(false)
  const [streamText, setStreamText] = useState('')
  const [streamToolEvents, setStreamToolEvents] = useState<SSEEvent[]>([])
  const [sidebarOpen, setSidebarOpen] = useState(true)
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLTextAreaElement>(null)
  const { showToast } = useAppStore()

  const scrollToBottom = useCallback(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [])

  // Load sessions on mount
  useEffect(() => {
    fetchSessions()
      .then(setSessions)
      .finally(() => setLoading(false))
  }, [])

  // Scroll on new messages
  useEffect(() => {
    scrollToBottom()
  }, [messages, streamText, scrollToBottom])

  const loadSession = useCallback(async (sessionId: number) => {
    setActiveSessionId(sessionId)
    try {
      const data = await fetchSession(sessionId)
      setMessages(data.messages)
    } catch {
      setMessages([])
    }
  }, [])

  const handleNewSession = async () => {
    try {
      const session = await createSession()
      setSessions((prev) => [session, ...prev])
      setActiveSessionId(session.id)
      setMessages([])
      inputRef.current?.focus()
    } catch {
      showToast('Failed to create session', 'error')
    }
  }

  const handleDeleteSession = async (sessionId: number) => {
    try {
      await deleteSession(sessionId)
      setSessions((prev) => prev.filter((s) => s.id !== sessionId))
      if (activeSessionId === sessionId) {
        setActiveSessionId(null)
        setMessages([])
      }
      showToast('Session deleted')
    } catch {
      showToast('Failed to delete session', 'error')
    }
  }

  const handleSend = async () => {
    if (!input.trim() || streaming) return

    let sessionId = activeSessionId

    // Auto-create session if none active
    if (!sessionId) {
      try {
        const session = await createSession()
        setSessions((prev) => [session, ...prev])
        sessionId = session.id
        setActiveSessionId(session.id)
      } catch {
        showToast('Failed to create session', 'error')
        return
      }
    }

    const userMessage = input.trim()
    setInput('')
    setStreaming(true)
    setStreamText('')
    setStreamToolEvents([])

    // Optimistic: add user message locally
    const optimisticUserMsg: ControlPlaneMessage = {
      id: Date.now(),
      session_id: sessionId,
      role: 'user',
      content: userMessage,
      tool_calls: null,
      metadata: null,
      created_at: new Date().toISOString(),
    }
    setMessages((prev) => [...prev, optimisticUserMsg])

    try {
      const response = await fetch(`${API_BASE}/${sessionId}/chat`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: userMessage }),
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`)
      }

      const reader = response.body?.getReader()
      if (!reader) throw new Error('No response body')

      const decoder = new TextDecoder()
      let buffer = ''
      let accumulatedText = ''
      const accumulatedToolEvents: SSEEvent[] = []

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() || ''

        for (const line of lines) {
          if (!line.startsWith('data: ')) continue
          const jsonStr = line.slice(6).trim()
          if (!jsonStr) continue

          try {
            const event: SSEEvent = JSON.parse(jsonStr)

            if (event.type === 'delta' && event.text) {
              accumulatedText += event.text
              setStreamText(accumulatedText)
            } else if (event.type === 'tool_call') {
              accumulatedToolEvents.push(event)
              setStreamToolEvents([...accumulatedToolEvents])
            } else if (event.type === 'tool_result') {
              accumulatedToolEvents.push(event)
              setStreamToolEvents([...accumulatedToolEvents])
            } else if (event.type === 'error') {
              showToast(event.error || 'An error occurred', 'error')
            }
          } catch {
            // Skip malformed JSON
          }
        }
      }

      // Streaming done — reload session messages for clean state
      const data = await fetchSession(sessionId)
      setMessages(data.messages)

      // Update session title in sidebar
      setSessions((prev) =>
        prev.map((s) =>
          s.id === sessionId ? { ...s, title: data.session.title, updated_at: data.session.updated_at } : s,
        ),
      )
    } catch (e) {
      showToast(
        `Failed to send message: ${e instanceof Error ? e.message : 'Unknown error'}`,
        'error',
      )
    } finally {
      setStreaming(false)
      setStreamText('')
      setStreamToolEvents([])
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSend()
    }
  }

  const activeSession = sessions.find((s) => s.id === activeSessionId)
  const contextProject = activeSession?.context?.project_name as string | undefined

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-pulse text-muted-foreground">
          Loading control plane...
        </div>
      </div>
    )
  }

  return (
    <div className="flex h-screen">
      {/* Session sidebar */}
      {sidebarOpen && (
        <div className="w-72 border-r border-border flex flex-col bg-card">
          <div className="p-3 border-b border-border flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Terminal className="h-4 w-4 text-primary" />
              <h2 className="text-sm font-semibold">Control Plane</h2>
            </div>
            <button
              onClick={handleNewSession}
              className="p-1.5 rounded hover:bg-muted transition-colors"
              title="New session"
            >
              <Plus className="h-4 w-4" />
            </button>
          </div>
          <div className="flex-1 overflow-y-auto">
            {sessions.length === 0 ? (
              <div className="flex flex-col items-center justify-center h-full text-sm text-muted-foreground p-4 text-center">
                <MessageSquare className="h-8 w-8 mb-2 opacity-40" />
                No sessions yet. Start a conversation.
              </div>
            ) : (
              <div className="divide-y divide-border">
                {sessions.map((session) => (
                  <div
                    key={session.id}
                    className={`group flex items-center gap-2 px-3 py-2.5 cursor-pointer hover:bg-muted/30 transition-colors ${
                      activeSessionId === session.id ? 'bg-muted/50' : ''
                    }`}
                    onClick={() => loadSession(session.id)}
                  >
                    <MessageSquare className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                    <div className="flex-1 min-w-0">
                      <div className="text-sm truncate">
                        {session.title || 'New session'}
                      </div>
                      <div className="text-[10px] text-muted-foreground">
                        {new Date(session.updated_at).toLocaleDateString(undefined, {
                          month: 'short',
                          day: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </div>
                    </div>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        handleDeleteSession(session.id)
                      }}
                      className="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-destructive/10 text-destructive transition-opacity"
                    >
                      <Trash2 className="h-3 w-3" />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Main chat area */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Header */}
        <div className="h-12 px-4 border-b border-border flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <button
              onClick={() => setSidebarOpen(!sidebarOpen)}
              className="p-1 rounded hover:bg-muted transition-colors lg:hidden"
            >
              {sidebarOpen ? <X className="h-4 w-4" /> : <MessageSquare className="h-4 w-4" />}
            </button>
            <div className="flex items-center gap-2">
              <Sparkles className="h-4 w-4 text-primary" />
              <span className="text-sm font-medium">
                {activeSession?.title || 'Control Plane'}
              </span>
            </div>
          </div>
          {contextProject && (
            <div className="flex items-center gap-1.5 text-xs text-muted-foreground bg-muted/50 px-2.5 py-1 rounded">
              <span className="opacity-60">Project:</span>
              <span className="font-medium">{contextProject}</span>
            </div>
          )}
        </div>

        {/* Messages */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {!activeSessionId && messages.length === 0 && (
            <div className="flex flex-col items-center justify-center h-full text-center max-w-md mx-auto">
              <div className="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mb-4">
                <Terminal className="h-7 w-7 text-primary" />
              </div>
              <h2 className="text-lg font-semibold mb-2">Control Plane</h2>
              <p className="text-sm text-muted-foreground mb-6">
                Manage your agents, skills, and executions with natural language.
                Ask me to list agents, start runs, check diagnostics, or search
                skills.
              </p>
              <div className="grid grid-cols-2 gap-2 text-xs w-full">
                {[
                  'List all my agents',
                  'Show fleet status',
                  'Search for skills about testing',
                  'Check provider health',
                  'List recent failures',
                  'Show all projects',
                ].map((suggestion) => (
                  <button
                    key={suggestion}
                    onClick={() => {
                      setInput(suggestion)
                      inputRef.current?.focus()
                    }}
                    className="text-left px-3 py-2 rounded border border-border hover:bg-muted/30 transition-colors text-muted-foreground hover:text-foreground"
                  >
                    {suggestion}
                  </button>
                ))}
              </div>
            </div>
          )}

          {messages.map((msg) => (
            <MessageBubble key={msg.id} message={msg} />
          ))}

          {streaming && (
            <StreamingMessage text={streamText} toolEvents={streamToolEvents} />
          )}

          <div ref={messagesEndRef} />
        </div>

        {/* Input */}
        <div className="p-4 border-t border-border shrink-0">
          <div className="flex items-end gap-2 max-w-4xl mx-auto">
            <textarea
              ref={inputRef}
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder="Ask me to manage agents, skills, or check system status..."
              rows={1}
              className="flex-1 resize-none rounded-lg border border-input bg-background px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring max-h-32"
              style={{
                height: 'auto',
                minHeight: '40px',
              }}
              onInput={(e) => {
                const target = e.target as HTMLTextAreaElement
                target.style.height = 'auto'
                target.style.height = Math.min(target.scrollHeight, 128) + 'px'
              }}
              disabled={streaming}
            />
            <button
              onClick={handleSend}
              disabled={!input.trim() || streaming}
              className="shrink-0 p-2.5 rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              {streaming ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Send className="h-4 w-4" />
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
