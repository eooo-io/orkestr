import { useState } from 'react'
import { Server, Network, Terminal, ChevronDown, ChevronUp } from 'lucide-react'
import { McpServersTab } from './McpServersTab'
import { A2aAgentsTab } from './A2aAgentsTab'
import { OpenClawConfigTab } from './OpenClawConfigTab'

interface ConnectionsTabProps {
  projectId: number
}

type Section = 'mcp' | 'a2a' | 'openclaw'

const SECTIONS: { id: Section; label: string; description: string; icon: typeof Server }[] = [
  { id: 'mcp', label: 'MCP Servers', description: 'Model Context Protocol — connect agents to external tools and data sources', icon: Server },
  { id: 'a2a', label: 'A2A Agents', description: 'Agent-to-Agent protocol — configure delegation targets for inter-agent communication', icon: Network },
  { id: 'openclaw', label: 'OpenClaw', description: 'OpenClaw configuration — shared agent configuration format', icon: Terminal },
]

export function ConnectionsTab({ projectId }: ConnectionsTabProps) {
  const [expanded, setExpanded] = useState<Set<Section>>(new Set(['mcp']))

  const toggle = (section: Section) => {
    setExpanded((prev) => {
      const next = new Set(prev)
      if (next.has(section)) {
        next.delete(section)
      } else {
        next.add(section)
      }
      return next
    })
  }

  return (
    <div className="space-y-3">
      {SECTIONS.map(({ id, label, description, icon: Icon }) => {
        const isExpanded = expanded.has(id)
        return (
          <div key={id} className="border border-border rounded">
            <button
              onClick={() => toggle(id)}
              className="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-accent/30 transition-colors"
            >
              <Icon className="h-5 w-5 text-muted-foreground shrink-0" />
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium">{label}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
              </div>
              {isExpanded ? (
                <ChevronUp className="h-4 w-4 text-muted-foreground shrink-0" />
              ) : (
                <ChevronDown className="h-4 w-4 text-muted-foreground shrink-0" />
              )}
            </button>
            {isExpanded && (
              <div className="px-4 pb-4 border-t border-border">
                {id === 'mcp' && <McpServersTab projectId={projectId} />}
                {id === 'a2a' && <A2aAgentsTab projectId={projectId} />}
                {id === 'openclaw' && <OpenClawConfigTab projectId={projectId} />}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}
