import { useState } from 'react'
import { FileText, Download, Loader2, Database, Shield } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  exportSkillsReport,
  exportUsageReport,
  exportAuditReport,
} from '@/api/client'

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

interface ReportCard {
  title: string
  description: string
  icon: React.ReactNode
  filename: string
  exportFn: () => Promise<Blob>
}

const REPORTS: ReportCard[] = [
  {
    title: 'Skill Inventory Report',
    description:
      'Export all skills across projects including tags, models, token estimates, and last updated dates.',
    icon: <FileText className="h-6 w-6 text-blue-400" />,
    filename: 'skills-report.csv',
    exportFn: exportSkillsReport,
  },
  {
    title: 'Usage Report',
    description:
      'Token usage breakdown by model and project, cost estimates, and daily usage trends.',
    icon: <Database className="h-6 w-6 text-emerald-400" />,
    filename: 'usage-report.csv',
    exportFn: exportUsageReport,
  },
  {
    title: 'Audit Log Report',
    description:
      'Full security audit trail of all actions, user activity, and configuration changes.',
    icon: <Shield className="h-6 w-6 text-amber-400" />,
    filename: 'audit-report.csv',
    exportFn: exportAuditReport,
  },
]

function ReportCardItem({ report }: { report: ReportCard }) {
  const [loading, setLoading] = useState(false)

  const handleExport = () => {
    setLoading(true)
    report
      .exportFn()
      .then((blob) => downloadBlob(blob, report.filename))
      .catch(() => {})
      .finally(() => setLoading(false))
  }

  return (
    <div className="bg-card elevation-1 border border-border p-6 flex flex-col gap-4">
      <div className="flex items-start gap-3">
        <div className="shrink-0 mt-0.5">{report.icon}</div>
        <div className="flex-1 min-w-0">
          <h3 className="text-sm font-medium">{report.title}</h3>
          <p className="text-sm text-muted-foreground mt-1">
            {report.description}
          </p>
        </div>
      </div>
      <div className="flex justify-end">
        <Button
          variant="outline"
          size="sm"
          onClick={handleExport}
          disabled={loading}
        >
          {loading ? (
            <Loader2 className="h-4 w-4 animate-spin mr-1.5" />
          ) : (
            <Download className="h-4 w-4 mr-1.5" />
          )}
          Export CSV
        </Button>
      </div>
    </div>
  )
}

export function Reports() {
  return (
    <div className="max-w-4xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-lg font-semibold flex items-center gap-2">
          <FileText className="h-5 w-5 text-primary" />
          Reports
        </h1>
        <p className="text-sm text-muted-foreground">
          Generate and download CSV reports for skills, usage, and audit data
        </p>
      </div>

      {/* Cards */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {REPORTS.map((report) => (
          <ReportCardItem key={report.filename} report={report} />
        ))}
      </div>
    </div>
  )
}
