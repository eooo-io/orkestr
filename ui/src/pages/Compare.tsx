import { Link } from 'react-router-dom'
import { Check, X, ArrowLeft, ArrowRight } from 'lucide-react'
import { Button } from '@/components/ui/button'

interface ComparisonRow {
  feature: string
  eooo: boolean | string
  dify: boolean | string
  langflow: boolean | string
  autogen: boolean | string
  relevance: boolean | string
}

const COMPARISON_DATA: ComparisonRow[] = [
  { feature: 'Multi-model routing', eooo: true, dify: 'Partial', langflow: false, autogen: false, relevance: false },
  { feature: 'Visual workflow builder', eooo: true, dify: true, langflow: true, autogen: false, relevance: 'Partial' },
  { feature: 'Agent autonomy levels', eooo: true, dify: false, langflow: false, autogen: false, relevance: false },
  { feature: 'Per-agent budgets', eooo: true, dify: false, langflow: false, autogen: false, relevance: 'Partial' },
  { feature: 'Human approval gates', eooo: true, dify: false, langflow: false, autogen: false, relevance: 'Partial' },
  { feature: 'Audit logging', eooo: true, dify: false, langflow: false, autogen: false, relevance: 'Partial' },
  { feature: 'Provider-agnostic', eooo: true, dify: false, langflow: 'Partial', autogen: 'Partial', relevance: false },
  { feature: 'Cron scheduling', eooo: true, dify: false, langflow: false, autogen: false, relevance: true },
  { feature: 'Webhook triggers', eooo: true, dify: true, langflow: false, autogen: false, relevance: true },
  { feature: 'Team/org management', eooo: true, dify: true, langflow: false, autogen: false, relevance: true },
  { feature: 'Open source', eooo: true, dify: true, langflow: true, autogen: true, relevance: false },
  { feature: 'Self-hostable', eooo: true, dify: true, langflow: true, autogen: true, relevance: false },
]

const COMPETITORS = ['Orkestr', 'Dify', 'LangFlow', 'AutoGen Studio', 'Relevance AI']

function CellValue({ value }: { value: boolean | string }) {
  if (value === true) return <Check className="h-4 w-4 text-emerald-400 mx-auto" />
  if (value === false) return <X className="h-4 w-4 text-muted-foreground/40 mx-auto" />
  return <span className="text-xs text-amber-400">{value}</span>
}

export function Compare() {
  return (
    <div className="min-h-screen bg-background">
      {/* Nav */}
      <header className="border-b border-border bg-card/80 backdrop-blur-sm sticky top-0 z-10">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
          <Link to="/" className="flex items-center gap-2 group">
            <ArrowLeft className="h-4 w-4 text-muted-foreground group-hover:text-foreground transition-colors" />
            <img src="/logo.png" alt="Orkestr" className="h-6 w-6 object-contain" />
            <span className="font-semibold text-sm">Orkestr</span>
          </Link>
          <div className="flex items-center gap-3">
            <Link to="/login">
              <Button variant="ghost" size="sm">Log in</Button>
            </Link>
            <Link to="/register">
              <Button size="sm" className="gap-1.5">
                Get Started <ArrowRight className="h-3.5 w-3.5" />
              </Button>
            </Link>
          </div>
        </div>
      </header>

      <main className="max-w-6xl mx-auto px-4 sm:px-6 py-12 sm:py-20">
        {/* Hero */}
        <div className="text-center mb-12">
          <h1 className="text-3xl sm:text-4xl font-bold tracking-tight">
            How Orkestr compares
          </h1>
          <p className="text-muted-foreground mt-3 text-lg max-w-2xl mx-auto">
            A side-by-side look at Orkestr versus other AI agent platforms.
            Multi-model routing, agent autonomy controls, and runtime guardrails set Orkestr apart.
          </p>
        </div>

        {/* Comparison Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-sm border-collapse">
            <thead>
              <tr>
                <th className="text-left py-3 px-4 font-medium text-muted-foreground border-b border-border">
                  Feature
                </th>
                {COMPETITORS.map((name, i) => (
                  <th
                    key={name}
                    className={`py-3 px-4 text-center font-medium border-b border-border ${
                      i === 0
                        ? 'bg-primary/5 text-primary border-l border-r border-primary/20'
                        : 'text-muted-foreground'
                    }`}
                  >
                    {name}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {COMPARISON_DATA.map((row, idx) => (
                <tr
                  key={row.feature}
                  className={idx % 2 === 0 ? 'bg-muted/20' : ''}
                >
                  <td className="py-3 px-4 font-medium text-foreground border-b border-border/50">
                    {row.feature}
                  </td>
                  <td className="py-3 px-4 text-center bg-primary/5 border-l border-r border-primary/20 border-b border-border/50">
                    <CellValue value={row.eooo} />
                  </td>
                  <td className="py-3 px-4 text-center border-b border-border/50">
                    <CellValue value={row.dify} />
                  </td>
                  <td className="py-3 px-4 text-center border-b border-border/50">
                    <CellValue value={row.langflow} />
                  </td>
                  <td className="py-3 px-4 text-center border-b border-border/50">
                    <CellValue value={row.autogen} />
                  </td>
                  <td className="py-3 px-4 text-center border-b border-border/50">
                    <CellValue value={row.relevance} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Differentiators */}
        <div className="mt-16 grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="border border-border bg-card p-6">
            <h3 className="font-semibold text-foreground mb-2">Multi-Model Routing</h3>
            <p className="text-sm text-muted-foreground leading-relaxed">
              Route each agent to the optimal model with automatic fallback chains.
              Mix Claude, GPT, Gemini, and Ollama models within a single workflow.
            </p>
          </div>
          <div className="border border-border bg-card p-6">
            <h3 className="font-semibold text-foreground mb-2">Agent Autonomy & Permissions</h3>
            <p className="text-sm text-muted-foreground leading-relaxed">
              Three autonomy tiers (supervised, semi-autonomous, autonomous) with per-agent budgets,
              tool allowlists, and human approval gates. Stay in control.
            </p>
          </div>
          <div className="border border-border bg-card p-6">
            <h3 className="font-semibold text-foreground mb-2">Provider-Agnostic</h3>
            <p className="text-sm text-muted-foreground leading-relaxed">
              Define skills once, sync to Claude, Cursor, Copilot, Windsurf, Cline, and OpenAI.
              Your agent definitions are never locked to a single vendor.
            </p>
          </div>
        </div>

        {/* CTA */}
        <div className="mt-16 text-center">
          <h2 className="text-2xl font-bold tracking-tight mb-3">Ready to build?</h2>
          <p className="text-muted-foreground mb-6">
            Start building your agent team for free. No credit card required.
          </p>
          <Link to="/register">
            <Button size="lg" className="gap-2">
              Get Started Free
              <ArrowRight className="h-4 w-4" />
            </Button>
          </Link>
        </div>
      </main>
    </div>
  )
}
