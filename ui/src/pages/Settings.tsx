import { useState, useEffect } from 'react'
import { Loader2, CheckCircle, XCircle, ExternalLink } from 'lucide-react'
import { fetchSettings } from '@/api/client'
import { Button } from '@/components/ui/button'

interface SettingsData {
  anthropic_api_key_set: boolean
  default_model: string
}

const PROVIDERS = [
  {
    name: 'Claude',
    slug: 'claude',
    output: '.claude/CLAUDE.md',
    format: 'H2 headings per skill',
  },
  {
    name: 'Cursor',
    slug: 'cursor',
    output: '.cursor/rules/{slug}.mdc',
    format: 'One MDC file per skill',
  },
  {
    name: 'Copilot',
    slug: 'copilot',
    output: '.github/copilot-instructions.md',
    format: 'All skills concatenated',
  },
  {
    name: 'Windsurf',
    slug: 'windsurf',
    output: '.windsurf/rules/{slug}.md',
    format: 'One file per skill',
  },
  {
    name: 'Cline',
    slug: 'cline',
    output: '.clinerules',
    format: 'Single flat file',
  },
  {
    name: 'OpenAI',
    slug: 'openai',
    output: '.openai/instructions.md',
    format: 'All skills concatenated',
  },
]

export function Settings() {
  const [settings, setSettings] = useState<SettingsData | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchSettings()
      .then(setSettings)
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="flex items-center justify-center h-full">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="p-6 max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-bold">Settings</h1>
        <p className="text-sm text-muted-foreground mt-1">
          View configuration status. Edit settings in the{' '}
          <a
            href="/admin/settings"
            target="_blank"
            rel="noopener noreferrer"
            className="text-primary underline"
          >
            admin panel
          </a>
          .
        </p>
      </div>

      {/* API Key Status */}
      <section className="mb-8">
        <h2 className="text-lg font-semibold mb-3">API Configuration</h2>
        <div className="rounded-lg border border-border bg-card p-4 space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-sm">Anthropic API Key</span>
            {settings?.anthropic_api_key_set ? (
              <span className="flex items-center gap-1.5 text-sm text-green-500">
                <CheckCircle className="h-4 w-4" />
                Configured
              </span>
            ) : (
              <span className="flex items-center gap-1.5 text-sm text-destructive">
                <XCircle className="h-4 w-4" />
                Not set
              </span>
            )}
          </div>
          <div className="flex items-center justify-between">
            <span className="text-sm">Default Model</span>
            <span className="text-sm font-mono text-muted-foreground">
              {settings?.default_model || 'claude-sonnet-4-20250514'}
            </span>
          </div>
        </div>
      </section>

      {/* Provider Reference */}
      <section className="mb-8">
        <h2 className="text-lg font-semibold mb-3">Provider Sync Reference</h2>
        <div className="rounded-lg border border-border bg-card overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border bg-muted/30">
                <th className="text-left px-4 py-2 font-medium">Provider</th>
                <th className="text-left px-4 py-2 font-medium">Output Path</th>
                <th className="text-left px-4 py-2 font-medium">Format</th>
              </tr>
            </thead>
            <tbody>
              {PROVIDERS.map((p) => (
                <tr key={p.slug} className="border-b border-border last:border-0">
                  <td className="px-4 py-2 font-medium">{p.name}</td>
                  <td className="px-4 py-2 font-mono text-xs text-muted-foreground">
                    {p.output}
                  </td>
                  <td className="px-4 py-2 text-muted-foreground">{p.format}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {/* Admin Panel Link */}
      <section>
        <a href="/admin" target="_blank" rel="noopener noreferrer">
          <Button variant="outline">
            <ExternalLink className="h-4 w-4 mr-2" />
            Open Admin Panel
          </Button>
        </a>
      </section>
    </div>
  )
}
