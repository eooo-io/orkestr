import { useState, useRef, useCallback, useEffect } from 'react'
import { Play, Square, Copy, Clock, Hash } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface LiveTestPanelProps {
  skillId: number
}

interface StreamStats {
  inputTokens: number | null
  outputTokens: number | null
  elapsed: number
}

export function LiveTestPanel({ skillId }: LiveTestPanelProps) {
  const [userMessage, setUserMessage] = useState('')
  const [output, setOutput] = useState('')
  const [isRunning, setIsRunning] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [stats, setStats] = useState<StreamStats>({
    inputTokens: null,
    outputTokens: null,
    elapsed: 0,
  })
  const abortRef = useRef<AbortController | null>(null)
  const timerRef = useRef<ReturnType<typeof setInterval> | null>(null)
  const outputRef = useRef<HTMLDivElement>(null)
  const startTimeRef = useRef<number>(0)

  // Auto-scroll output
  useEffect(() => {
    if (outputRef.current) {
      outputRef.current.scrollTop = outputRef.current.scrollHeight
    }
  }, [output])

  // Clean up on unmount
  useEffect(() => {
    return () => {
      abortRef.current?.abort()
      if (timerRef.current) clearInterval(timerRef.current)
    }
  }, [])

  const handleRun = useCallback(async () => {
    if (!userMessage.trim()) return

    setIsRunning(true)
    setOutput('')
    setError(null)
    setStats({ inputTokens: null, outputTokens: null, elapsed: 0 })
    startTimeRef.current = Date.now()

    // Elapsed timer
    timerRef.current = setInterval(() => {
      setStats((prev) => ({
        ...prev,
        elapsed: (Date.now() - startTimeRef.current) / 1000,
      }))
    }, 100)

    const controller = new AbortController()
    abortRef.current = controller

    try {
      const apiUrl = import.meta.env.VITE_API_URL || '/api'
      const response = await fetch(`${apiUrl}/skills/${skillId}/test`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
        body: JSON.stringify({ user_message: userMessage }),
        signal: controller.signal,
      })

      if (!response.ok || !response.body) {
        throw new Error(`HTTP ${response.status}`)
      }

      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })
        const lines = buffer.split('\n')
        buffer = lines.pop() || ''

        for (const line of lines) {
          if (!line.startsWith('data: ')) continue
          const jsonStr = line.slice(6)

          try {
            const data = JSON.parse(jsonStr)

            if (data.type === 'delta') {
              setOutput((prev) => prev + data.text)
            } else if (data.type === 'message_start') {
              setStats((prev) => ({
                ...prev,
                inputTokens: data.input_tokens,
              }))
            } else if (data.type === 'message_delta') {
              setStats((prev) => ({
                ...prev,
                outputTokens: data.output_tokens,
              }))
            } else if (data.type === 'error') {
              setError(data.error)
            } else if (data.type === 'done') {
              // Stream complete
            }
          } catch {
            // Skip malformed JSON
          }
        }
      }
    } catch (err: unknown) {
      if (err instanceof Error && err.name !== 'AbortError') {
        setError(err.message)
      }
    } finally {
      setIsRunning(false)
      if (timerRef.current) {
        clearInterval(timerRef.current)
        timerRef.current = null
      }
      setStats((prev) => ({
        ...prev,
        elapsed: (Date.now() - startTimeRef.current) / 1000,
      }))
    }
  }, [skillId, userMessage])

  const handleStop = () => {
    abortRef.current?.abort()
  }

  const handleCopy = () => {
    navigator.clipboard.writeText(output)
  }

  return (
    <div className="flex flex-col h-full">
      {/* Input */}
      <div className="p-3 border-b border-border">
        <textarea
          value={userMessage}
          onChange={(e) => setUserMessage(e.target.value)}
          placeholder="Enter a test message..."
          rows={3}
          className="w-full px-2.5 py-2 text-sm rounded-md border border-input bg-background resize-none focus:outline-none focus:ring-1 focus:ring-ring"
          onKeyDown={(e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
              e.preventDefault()
              handleRun()
            }
          }}
        />
        <div className="flex items-center justify-between mt-2">
          <span className="text-[10px] text-muted-foreground">
            Ctrl+Enter to run
          </span>
          <div className="flex gap-1.5">
            {isRunning ? (
              <Button variant="destructive" size="xs" onClick={handleStop}>
                <Square className="h-3 w-3 mr-1" />
                Stop
              </Button>
            ) : (
              <Button size="xs" onClick={handleRun} disabled={!userMessage.trim()}>
                <Play className="h-3 w-3 mr-1" />
                Run
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* Stats bar */}
      {(stats.elapsed > 0 || isRunning) && (
        <div className="flex items-center gap-3 px-3 py-1.5 border-b border-border text-[10px] text-muted-foreground bg-muted/30">
          <span className="flex items-center gap-1">
            <Clock className="h-3 w-3" />
            {stats.elapsed.toFixed(1)}s
          </span>
          {stats.inputTokens !== null && (
            <span className="flex items-center gap-1">
              <Hash className="h-3 w-3" />
              In: {stats.inputTokens}
            </span>
          )}
          {stats.outputTokens !== null && (
            <span className="flex items-center gap-1">
              <Hash className="h-3 w-3" />
              Out: {stats.outputTokens}
            </span>
          )}
        </div>
      )}

      {/* Output */}
      <div className="flex-1 overflow-auto relative" ref={outputRef}>
        {error && (
          <div className="m-3 p-3 rounded-md bg-destructive/10 text-destructive text-sm">
            {error}
          </div>
        )}
        {output ? (
          <div className="p-3">
            <pre className="text-sm whitespace-pre-wrap font-sans leading-relaxed">
              {output}
            </pre>
          </div>
        ) : (
          !isRunning &&
          !error && (
            <div className="flex items-center justify-center h-full text-sm text-muted-foreground">
              Enter a message and click Run to test this skill.
            </div>
          )
        )}
        {isRunning && !output && (
          <div className="flex items-center justify-center h-full">
            <div className="animate-pulse text-sm text-muted-foreground">
              Waiting for response...
            </div>
          </div>
        )}
      </div>

      {/* Copy button */}
      {output && !isRunning && (
        <div className="p-2 border-t border-border">
          <Button variant="ghost" size="xs" onClick={handleCopy} className="w-full">
            <Copy className="h-3 w-3 mr-1" />
            Copy Output
          </Button>
        </div>
      )}
    </div>
  )
}
