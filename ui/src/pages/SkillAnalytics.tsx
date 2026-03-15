import { useEffect, useState } from 'react'
import {
  BarChart3,
  Download,
  Loader2,
  TrendingUp,
  Target,
  Zap,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  fetchTopSkills,
  fetchAnalyticsTrends,
  exportSkillsReport,
  exportUsageReport,
  exportAuditReport,
} from '@/api/client'

type Period = '7d' | '30d' | '90d'

interface TopSkill {
  skill_id: number
  skill_name: string
  test_runs: number
  pass_rate: number
}

interface TrendsData {
  total_tests: number
  pass_rate: number
  avg_tokens: number
  avg_cost: number
}

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

function passRateColor(rate: number): string {
  if (rate >= 90) return 'text-emerald-400'
  if (rate >= 70) return 'text-amber-400'
  return 'text-red-400'
}

function passRateBarColor(rate: number): string {
  if (rate >= 90) return 'bg-emerald-500/60'
  if (rate >= 70) return 'bg-amber-500/60'
  return 'bg-red-500/60'
}

export function SkillAnalytics() {
  const [period, setPeriod] = useState<Period>('30d')
  const [topSkills, setTopSkills] = useState<TopSkill[]>([])
  const [trends, setTrends] = useState<TrendsData | null>(null)
  const [loading, setLoading] = useState(true)
  const [exporting, setExporting] = useState<string | null>(null)

  useEffect(() => {
    setLoading(true)
    Promise.all([
      fetchTopSkills({ period }).then(setTopSkills).catch(() => setTopSkills([])),
      fetchAnalyticsTrends({ period }).then(setTrends).catch(() => setTrends(null)),
    ]).finally(() => setLoading(false))
  }, [period])

  const handleExport = async (type: 'skills' | 'usage' | 'audit') => {
    setExporting(type)
    try {
      let blob: Blob
      let filename: string
      if (type === 'skills') {
        blob = await exportSkillsReport()
        filename = 'skills-report.csv'
      } else if (type === 'usage') {
        blob = await exportUsageReport()
        filename = 'usage-report.csv'
      } else {
        blob = await exportAuditReport()
        filename = 'audit-report.csv'
      }
      downloadBlob(blob, filename)
    } catch {
      // silently fail
    } finally {
      setExporting(null)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold tracking-tight flex items-center gap-2">
            <BarChart3 className="h-5 w-5 text-primary" />
            Skill Analytics
          </h1>
          <p className="text-sm text-muted-foreground">Usage trends and performance insights</p>
        </div>
        <div className="flex items-center gap-1 bg-muted p-1">
          {(['7d', '30d', '90d'] as Period[]).map(p => (
            <button
              key={p}
              onClick={() => setPeriod(p)}
              className={`px-3 py-1.5 text-xs font-medium transition-colors ${
                period === p
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              {p}
            </button>
          ))}
        </div>
      </div>

      {/* Trends Overview */}
      {trends && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard
            icon={<BarChart3 className="h-4 w-4 text-blue-400" />}
            label="Total Tests"
            value={trends.total_tests.toLocaleString()}
          />
          <StatCard
            icon={<Target className="h-4 w-4 text-emerald-400" />}
            label="Pass Rate"
            value={`${trends.pass_rate.toFixed(1)}%`}
          />
          <StatCard
            icon={<Zap className="h-4 w-4 text-amber-400" />}
            label="Avg Tokens"
            value={trends.avg_tokens.toLocaleString()}
          />
          <StatCard
            icon={<TrendingUp className="h-4 w-4 text-purple-400" />}
            label="Avg Cost"
            value={trends.avg_cost < 0.01 && trends.avg_cost > 0 ? '< $0.01' : `$${trends.avg_cost.toFixed(2)}`}
          />
        </div>
      )}

      {/* Top Skills Table */}
      {topSkills.length > 0 && (
        <div className="rounded-lg border border-border bg-card p-4">
          <h3 className="text-sm font-medium text-foreground mb-3">Top Skills by Usage</h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-xs text-muted-foreground">
                  <th className="text-left py-2 px-2 font-medium">Skill</th>
                  <th className="text-left py-2 px-2 font-medium">Test Runs</th>
                  <th className="text-left py-2 px-2 font-medium w-64">Pass Rate</th>
                </tr>
              </thead>
              <tbody>
                {topSkills.map(skill => (
                  <tr key={skill.skill_id} className="border-b border-border/50 hover:bg-muted/30 transition-colors">
                    <td className="py-2 px-2 font-medium text-foreground">{skill.skill_name}</td>
                    <td className="py-2 px-2 tabular-nums">{skill.test_runs}</td>
                    <td className="py-2 px-2">
                      <div className="flex items-center gap-3">
                        <div className="flex-1 h-2 bg-muted rounded-full overflow-hidden">
                          <div
                            className={`h-full rounded-full ${passRateBarColor(skill.pass_rate)}`}
                            style={{ width: `${Math.max(skill.pass_rate, 2)}%` }}
                          />
                        </div>
                        <span className={`text-xs tabular-nums font-medium w-12 text-right ${passRateColor(skill.pass_rate)}`}>
                          {skill.pass_rate.toFixed(1)}%
                        </span>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Reports Export */}
      <div className="rounded-lg border border-border bg-card p-4">
        <h3 className="text-sm font-medium text-foreground mb-3 flex items-center gap-2">
          <Download className="h-4 w-4 text-muted-foreground" />
          Export Reports
        </h3>
        <p className="text-sm text-muted-foreground mb-4">Download CSV reports for offline analysis</p>
        <div className="flex flex-wrap gap-3">
          <Button
            variant="outline"
            size="sm"
            onClick={() => handleExport('skills')}
            disabled={exporting !== null}
          >
            {exporting === 'skills' ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <Download className="h-4 w-4 mr-2" />}
            Skills Report
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => handleExport('usage')}
            disabled={exporting !== null}
          >
            {exporting === 'usage' ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <Download className="h-4 w-4 mr-2" />}
            Usage Report
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => handleExport('audit')}
            disabled={exporting !== null}
          >
            {exporting === 'audit' ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <Download className="h-4 w-4 mr-2" />}
            Audit Report
          </Button>
        </div>
      </div>

      {/* Empty state */}
      {!trends && topSkills.length === 0 && (
        <div className="text-center py-16 text-muted-foreground">
          <BarChart3 className="h-8 w-8 mx-auto mb-3 opacity-40" />
          <p className="text-sm">No analytics data yet. Run some skill tests to see insights here.</p>
        </div>
      )}
    </div>
  )
}

function StatCard({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center gap-2 mb-1">
        {icon}
        <span className="text-xs text-muted-foreground">{label}</span>
      </div>
      <div className="text-lg font-semibold text-foreground">{value}</div>
    </div>
  )
}
