import { useCallback, useEffect, useState } from 'react'
import {
  fetchEvalSuites,
  fetchEvalSuite,
  createEvalSuite,
  deleteEvalSuite,
  createEvalPrompt,
  deleteEvalPrompt,
  runEval,
  fetchEvalRuns,
  scoreDescription,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import { useEvalRunStatus } from '@/hooks/useEvalRunStatus'
import { GateConfigPanel } from '@/components/skills/GateConfigPanel'
import type {
  SkillEvalSuite,
  SkillEvalRun,
  DescriptionScore,
} from '@/types'

interface EvalPanelProps {
  skillId: number
}

export function EvalPanel({ skillId }: EvalPanelProps) {
  const confirm = useConfirm()
  const [suites, setSuites] = useState<SkillEvalSuite[]>([])
  const [loading, setLoading] = useState(true)
  const [selectedSuite, setSelectedSuite] = useState<SkillEvalSuite | null>(
    null,
  )
  const [runs, setRuns] = useState<SkillEvalRun[]>([])
  const [descScore, setDescScore] = useState<DescriptionScore | null>(null)
  const [showCreateSuite, setShowCreateSuite] = useState(false)
  const [showAddPrompt, setShowAddPrompt] = useState(false)
  const [newSuiteName, setNewSuiteName] = useState('')
  const [newPrompt, setNewPrompt] = useState('')
  const [newExpected, setNewExpected] = useState('')
  const [runModel, setRunModel] = useState('claude-sonnet-4-6')
  const [runMode, setRunMode] = useState<string>('with_skill')
  const [running, setRunning] = useState(false)
  const [activeRunId, setActiveRunId] = useState<number | null>(null)
  const { run: pollingRun, isLive: pollingLive } = useEvalRunStatus(activeRunId)

  useEffect(() => {
    if (!pollingRun || pollingLive) return
    if (selectedSuite) {
      fetchEvalRuns(selectedSuite.id).then(setRuns)
    }
    setActiveRunId(null)
  }, [pollingRun, pollingLive, selectedSuite])

  const load = useCallback(() => {
    fetchEvalSuites(skillId)
      .then(setSuites)
      .finally(() => setLoading(false))
  }, [skillId])

  useEffect(() => {
    load()
  }, [load])

  useEffect(() => {
    if (selectedSuite) {
      fetchEvalRuns(selectedSuite.id).then(setRuns)
    }
  }, [selectedSuite])

  const handleCreateSuite = async () => {
    if (!newSuiteName.trim()) return
    const suite = await createEvalSuite(skillId, { name: newSuiteName })
    setNewSuiteName('')
    setShowCreateSuite(false)
    load()
    setSelectedSuite(suite)
  }

  const handleDeleteSuite = async (id: number) => {
    if (!(await confirm({ message: 'Delete this eval suite?', title: 'Confirm Delete' }))) return
    await deleteEvalSuite(id)
    if (selectedSuite?.id === id) setSelectedSuite(null)
    load()
  }

  const handleAddPrompt = async () => {
    if (!selectedSuite || !newPrompt.trim()) return
    await createEvalPrompt(selectedSuite.id, {
      prompt: newPrompt,
      expected_behavior: newExpected || undefined,
    })
    setNewPrompt('')
    setNewExpected('')
    setShowAddPrompt(false)
    load()
    const updated = await fetchEvalSuite(selectedSuite.id)
    setSelectedSuite(updated)
  }

  const handleDeletePrompt = async (id: number) => {
    if (!selectedSuite) return
    await deleteEvalPrompt(id)
    load()
    const updated = await fetchEvalSuite(selectedSuite.id)
    setSelectedSuite(updated)
  }

  const handleRun = async () => {
    if (!selectedSuite) return
    setRunning(true)
    try {
      const newRun = await runEval(selectedSuite.id, { model: runModel, mode: runMode })
      setActiveRunId(newRun.id)
      fetchEvalRuns(selectedSuite.id).then(setRuns)
    } finally {
      setRunning(false)
    }
  }

  const handleScoreDescription = async () => {
    const result = await scoreDescription(skillId)
    setDescScore(result)
  }

  if (loading) {
    return (
      <div className="p-4 text-sm text-muted-foreground animate-pulse">
        Loading eval suites...
      </div>
    )
  }

  return (
    <div className="flex flex-col h-full">
      <GateConfigPanel skillId={skillId} skillName="this skill" suites={suites} />

      {/* Description Score */}
      <div className="p-3 border-b border-border">
        <button
          onClick={handleScoreDescription}
          className="text-xs px-3 py-1 bg-muted hover:bg-muted/80 rounded"
        >
          Score Description Quality
        </button>
        {descScore && (
          <div className="mt-2 text-xs space-y-1">
            <div className="flex items-center gap-2">
              <span className="font-medium">Score:</span>
              <span
                className={`font-bold ${descScore.score >= 80 ? 'text-green-500' : descScore.score >= 50 ? 'text-yellow-500' : 'text-red-500'}`}
              >
                {descScore.score}/100
              </span>
            </div>
            {descScore.issues.map((issue, i) => (
              <div key={i} className="text-muted-foreground">
                - {issue}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Suite list / detail */}
      {!selectedSuite ? (
        <div className="flex-1 overflow-y-auto">
          <div className="p-3 border-b border-border">
            {showCreateSuite ? (
              <div className="space-y-2">
                <input
                  value={newSuiteName}
                  onChange={(e) => setNewSuiteName(e.target.value)}
                  placeholder="Suite name"
                  className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded"
                />
                <div className="flex gap-2">
                  <button
                    onClick={handleCreateSuite}
                    className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded"
                  >
                    Create
                  </button>
                  <button
                    onClick={() => setShowCreateSuite(false)}
                    className="text-xs px-3 py-1 text-muted-foreground"
                  >
                    Cancel
                  </button>
                </div>
              </div>
            ) : (
              <button
                onClick={() => setShowCreateSuite(true)}
                className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded"
              >
                + New Eval Suite
              </button>
            )}
          </div>

          {suites.length === 0 ? (
            <div className="flex items-center justify-center h-32 text-sm text-muted-foreground">
              No eval suites yet.
            </div>
          ) : (
            <div className="divide-y divide-border">
              {suites.map((suite) => (
                <div
                  key={suite.id}
                  className="p-3 hover:bg-muted/30 cursor-pointer group"
                  onClick={() => setSelectedSuite(suite)}
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="text-sm font-medium">{suite.name}</div>
                      <div className="text-xs text-muted-foreground">
                        {suite.prompts_count ?? 0} prompts,{' '}
                        {suite.runs_count ?? 0} runs
                      </div>
                    </div>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        handleDeleteSuite(suite.id)
                      }}
                      className="opacity-0 group-hover:opacity-100 text-xs text-destructive"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ) : (
        <div className="flex-1 overflow-y-auto">
          <div className="p-3 border-b border-border flex items-center justify-between">
            <button
              onClick={() => setSelectedSuite(null)}
              className="text-xs text-muted-foreground hover:text-foreground"
            >
              Back to suites
            </button>
            <span className="text-sm font-medium">{selectedSuite.name}</span>
          </div>

          {/* Run controls */}
          <div className="p-3 border-b border-border flex items-center gap-2 flex-wrap">
            <select
              value={runModel}
              onChange={(e) => setRunModel(e.target.value)}
              className="text-xs border border-input bg-background rounded px-2 py-1"
            >
              <option value="claude-sonnet-4-6">Sonnet 4.6</option>
              <option value="claude-opus-4-6">Opus 4.6</option>
              <option value="claude-haiku-4-5-20251001">Haiku 4.5</option>
            </select>
            <select
              value={runMode}
              onChange={(e) => setRunMode(e.target.value)}
              className="text-xs border border-input bg-background rounded px-2 py-1"
            >
              <option value="with_skill">With Skill</option>
              <option value="without_skill">Without Skill</option>
              <option value="ab_test">A/B Test</option>
            </select>
            <button
              onClick={handleRun}
              disabled={running || (selectedSuite.prompts_count ?? 0) === 0}
              className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded disabled:opacity-50"
            >
              {running ? 'Running...' : 'Run Eval'}
            </button>
          </div>

          {/* Prompts */}
          <div className="p-3 border-b border-border">
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-muted-foreground uppercase">
                Prompts
              </span>
              <button
                onClick={() => setShowAddPrompt(!showAddPrompt)}
                className="text-xs text-primary"
              >
                + Add
              </button>
            </div>

            {showAddPrompt && (
              <div className="space-y-2 mb-3">
                <textarea
                  value={newPrompt}
                  onChange={(e) => setNewPrompt(e.target.value)}
                  placeholder="Test prompt..."
                  rows={2}
                  className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded resize-none"
                />
                <input
                  value={newExpected}
                  onChange={(e) => setNewExpected(e.target.value)}
                  placeholder="Expected behavior (optional)"
                  className="w-full text-sm px-2.5 py-1.5 border border-input bg-background rounded"
                />
                <button
                  onClick={handleAddPrompt}
                  className="text-xs px-3 py-1 bg-primary text-primary-foreground rounded"
                >
                  Add Prompt
                </button>
              </div>
            )}

            {selectedSuite.prompts?.map((p) => (
              <div
                key={p.id}
                className="text-xs p-2 bg-muted/30 rounded mb-1 group flex justify-between"
              >
                <span className="truncate">{p.prompt}</span>
                <button
                  onClick={() => handleDeletePrompt(p.id)}
                  className="opacity-0 group-hover:opacity-100 text-destructive ml-2 shrink-0"
                >
                  x
                </button>
              </div>
            )) ?? null}
          </div>

          {/* Run history */}
          <div className="p-3">
            <span className="text-xs font-medium text-muted-foreground uppercase">
              Run History
            </span>
            {runs.length === 0 ? (
              <div className="text-xs text-muted-foreground mt-2">
                No runs yet.
              </div>
            ) : (
              <div className="space-y-2 mt-2">
                {runs.map((run) => (
                  <div key={run.id} className="text-xs p-2 bg-muted/30 rounded">
                    <div className="flex justify-between">
                      <span>
                        {run.model} / {run.mode.replace('_', ' ')}
                      </span>
                      <span
                        className={`font-bold ${run.status === 'completed' ? 'text-green-500' : run.status === 'failed' ? 'text-red-500' : 'text-yellow-500'}`}
                      >
                        {run.status === 'completed' && run.overall_score !== null
                          ? `${run.overall_score}/100`
                          : run.status}
                      </span>
                    </div>
                    {run.completed_at && (
                      <div className="text-muted-foreground/60 mt-0.5">
                        {new Date(run.completed_at).toLocaleString()}
                      </div>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
