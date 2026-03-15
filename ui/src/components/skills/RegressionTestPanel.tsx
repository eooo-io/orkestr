import { useState, useEffect, useCallback } from 'react'
import {
  TestTube,
  Play,
  Plus,
  Trash2,
  Loader2,
  CheckCircle,
  XCircle,
  Zap,
  BarChart3,
} from 'lucide-react'
import {
  fetchSkillTestCases,
  createSkillTestCase,
  deleteSkillTestCase,
  runAllSkillTestCases,
  benchmarkSkill,
} from '@/api/client'
import { Button } from '@/components/ui/button'
import type { SkillTestCase, SkillTestCaseResult, SkillBenchmarkResult } from '@/types'

interface RegressionTestPanelProps {
  skillId: number
}

const ASSERTION_TYPES = ['contains', 'exact', 'regex', 'semantic'] as const

const assertionBadgeClass: Record<string, string> = {
  contains: 'bg-blue-500/10 text-blue-400',
  exact: 'bg-purple-500/10 text-purple-400',
  regex: 'bg-orange-500/10 text-orange-400',
  semantic: 'bg-green-500/10 text-green-400',
}

export function RegressionTestPanel({ skillId }: RegressionTestPanelProps) {
  const [testCases, setTestCases] = useState<SkillTestCase[]>([])
  const [testResults, setTestResults] = useState<Record<number, SkillTestCaseResult>>({})
  const [benchmarkResults, setBenchmarkResults] = useState<SkillBenchmarkResult[] | null>(null)
  const [loading, setLoading] = useState(false)
  const [runningTests, setRunningTests] = useState(false)
  const [runningBenchmark, setRunningBenchmark] = useState(false)
  const [showAddForm, setShowAddForm] = useState(false)
  const [formData, setFormData] = useState({
    name: '',
    input: '',
    expected_output: '',
    assertion_type: 'contains' as string,
    pass_threshold: 0.8,
  })

  const loadTestCases = useCallback(async () => {
    setLoading(true)
    try {
      const cases = await fetchSkillTestCases(skillId)
      setTestCases(cases)
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }, [skillId])

  useEffect(() => {
    loadTestCases()
  }, [loadTestCases])

  const handleAddTestCase = async () => {
    if (!formData.name.trim() || !formData.input.trim()) return
    try {
      const created = await createSkillTestCase(skillId, formData)
      setTestCases((prev) => [...prev, created])
      setFormData({ name: '', input: '', expected_output: '', assertion_type: 'contains', pass_threshold: 0.8 })
      setShowAddForm(false)
    } catch {
      // ignore
    }
  }

  const handleDelete = async (id: number) => {
    try {
      await deleteSkillTestCase(id)
      setTestCases((prev) => prev.filter((tc) => tc.id !== id))
      setTestResults((prev) => {
        const next = { ...prev }
        delete next[id]
        return next
      })
    } catch {
      // ignore
    }
  }

  const handleRunAll = async () => {
    setRunningTests(true)
    setTestResults({})
    try {
      const results = await runAllSkillTestCases(skillId)
      const mapped: Record<number, SkillTestCaseResult> = {}
      for (const r of results) {
        mapped[r.test_case_id] = r
      }
      setTestResults(mapped)
    } catch {
      // ignore
    } finally {
      setRunningTests(false)
    }
  }

  const handleBenchmark = async () => {
    setRunningBenchmark(true)
    setBenchmarkResults(null)
    try {
      const results = await benchmarkSkill(skillId)
      setBenchmarkResults(results)
    } catch {
      // ignore
    } finally {
      setRunningBenchmark(false)
    }
  }

  return (
    <div className="flex flex-col h-full">
      {/* Test Cases Section */}
      <div className="p-3 border-b border-border">
        <div className="flex items-center justify-between mb-2">
          <div className="flex items-center gap-1.5 text-xs font-medium">
            <TestTube className="h-3.5 w-3.5" />
            Test Cases
            {testCases.length > 0 && (
              <span className="text-muted-foreground">({testCases.length})</span>
            )}
          </div>
          <div className="flex items-center gap-1">
            <Button
              size="xs"
              variant="ghost"
              onClick={() => setShowAddForm(!showAddForm)}
              title="Add test case"
            >
              <Plus className="h-3 w-3" />
            </Button>
            {testCases.length > 0 && (
              <Button
                size="xs"
                variant="outline"
                onClick={handleRunAll}
                disabled={runningTests}
              >
                {runningTests ? (
                  <Loader2 className="h-3 w-3 mr-1 animate-spin" />
                ) : (
                  <Play className="h-3 w-3 mr-1" />
                )}
                Run All
              </Button>
            )}
          </div>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto">
        {/* Add Test Case Form */}
        {showAddForm && (
          <div className="p-3 border-b border-border space-y-2">
            <input
              type="text"
              placeholder="Test case name"
              value={formData.name}
              onChange={(e) => setFormData((f) => ({ ...f, name: e.target.value }))}
              className="w-full text-xs px-2 py-1.5 bg-muted/50 border border-border rounded focus:outline-none focus:ring-1 focus:ring-ring"
            />
            <textarea
              placeholder="Input"
              value={formData.input}
              onChange={(e) => setFormData((f) => ({ ...f, input: e.target.value }))}
              rows={2}
              className="w-full text-xs px-2 py-1.5 bg-muted/50 border border-border rounded resize-none focus:outline-none focus:ring-1 focus:ring-ring"
            />
            <textarea
              placeholder="Expected output"
              value={formData.expected_output}
              onChange={(e) => setFormData((f) => ({ ...f, expected_output: e.target.value }))}
              rows={2}
              className="w-full text-xs px-2 py-1.5 bg-muted/50 border border-border rounded resize-none focus:outline-none focus:ring-1 focus:ring-ring"
            />
            <div className="flex items-center gap-2">
              <select
                value={formData.assertion_type}
                onChange={(e) => setFormData((f) => ({ ...f, assertion_type: e.target.value }))}
                className="text-xs px-2 py-1.5 bg-muted/50 border border-border rounded focus:outline-none focus:ring-1 focus:ring-ring"
              >
                {ASSERTION_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {t}
                  </option>
                ))}
              </select>
              <input
                type="number"
                min={0}
                max={1}
                step={0.05}
                value={formData.pass_threshold}
                onChange={(e) =>
                  setFormData((f) => ({ ...f, pass_threshold: parseFloat(e.target.value) || 0 }))
                }
                className="w-16 text-xs px-2 py-1.5 bg-muted/50 border border-border rounded focus:outline-none focus:ring-1 focus:ring-ring"
                title="Pass threshold (0-1)"
              />
            </div>
            <div className="flex items-center gap-1.5">
              <Button size="xs" onClick={handleAddTestCase} disabled={!formData.name.trim() || !formData.input.trim()}>
                Add
              </Button>
              <Button size="xs" variant="ghost" onClick={() => setShowAddForm(false)}>
                Cancel
              </Button>
            </div>
          </div>
        )}

        {/* Test Cases List */}
        {loading && (
          <div className="flex items-center justify-center py-8 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
          </div>
        )}

        {!loading && testCases.length === 0 && !showAddForm && (
          <div className="flex flex-col items-center justify-center py-8 text-xs text-muted-foreground gap-1.5">
            <TestTube className="h-5 w-5" />
            <p>No test cases yet.</p>
          </div>
        )}

        {!loading && testCases.length > 0 && (
          <div className="p-2 space-y-1.5">
            {testCases.map((tc) => {
              const result = testResults[tc.id]
              return (
                <div
                  key={tc.id}
                  className={`border px-2.5 py-2 text-xs rounded ${
                    result
                      ? result.passed
                        ? 'border-green-500/30 bg-green-500/5'
                        : 'border-red-500/30 bg-red-500/5'
                      : 'border-border'
                  }`}
                >
                  <div className="flex items-start justify-between gap-1">
                    <div className="flex items-center gap-1.5 min-w-0">
                      {result && (
                        result.passed ? (
                          <CheckCircle className="h-3.5 w-3.5 text-green-500 shrink-0" />
                        ) : (
                          <XCircle className="h-3.5 w-3.5 text-red-500 shrink-0" />
                        )
                      )}
                      <span className="font-medium truncate">{tc.name}</span>
                    </div>
                    <button
                      onClick={() => handleDelete(tc.id)}
                      className="text-muted-foreground hover:text-destructive shrink-0"
                      title="Delete test case"
                    >
                      <Trash2 className="h-3 w-3" />
                    </button>
                  </div>
                  <div className="flex items-center gap-1.5 mt-1">
                    <span
                      className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${
                        assertionBadgeClass[tc.assertion_type] ?? 'bg-muted text-muted-foreground'
                      }`}
                    >
                      {tc.assertion_type}
                    </span>
                    <span className="text-[10px] text-muted-foreground">
                      threshold: {tc.pass_threshold}
                    </span>
                  </div>
                  {result && (
                    <div className="mt-1.5 pt-1.5 border-t border-border/50 text-[10px]">
                      {result.score !== null && (
                        <span className="text-muted-foreground mr-2">
                          score: {result.score.toFixed(2)}
                        </span>
                      )}
                      {result.error ? (
                        <span className="text-red-400">{result.error}</span>
                      ) : (
                        <p className="text-muted-foreground truncate" title={result.actual_output}>
                          {result.actual_output.length > 100
                            ? result.actual_output.slice(0, 100) + '...'
                            : result.actual_output}
                        </p>
                      )}
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        )}

        {/* Cross-Model Benchmark Section */}
        <div className="p-3 border-t border-border">
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-1.5 text-xs font-medium">
              <BarChart3 className="h-3.5 w-3.5" />
              Cross-Model Benchmark
            </div>
            <Button
              size="xs"
              variant="outline"
              onClick={handleBenchmark}
              disabled={runningBenchmark}
            >
              {runningBenchmark ? (
                <Loader2 className="h-3 w-3 mr-1 animate-spin" />
              ) : (
                <Zap className="h-3 w-3 mr-1" />
              )}
              Benchmark
            </Button>
          </div>

          {runningBenchmark && (
            <div className="flex items-center justify-center py-4 text-xs text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
              Running benchmark...
            </div>
          )}

          {!runningBenchmark && benchmarkResults === null && (
            <p className="text-[10px] text-muted-foreground">
              Run a benchmark to compare this skill across multiple models.
            </p>
          )}

          {benchmarkResults && benchmarkResults.length > 0 && (
            <div className="space-y-1 mt-1">
              {benchmarkResults.map((br) => (
                <div
                  key={br.model}
                  className={`border px-2.5 py-1.5 rounded text-[11px] ${
                    br.error ? 'border-red-500/30 bg-red-500/5' : 'border-border'
                  }`}
                >
                  <div className="font-medium truncate">{br.model}</div>
                  {br.error ? (
                    <span className="text-red-400 text-[10px]">{br.error}</span>
                  ) : (
                    <div className="flex items-center gap-3 text-[10px] text-muted-foreground mt-0.5">
                      <span>{br.latency_ms}ms</span>
                      <span>{br.tokens} tok</span>
                      <span>{(br.cost_microcents / 100).toFixed(2)}c</span>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
