import { useState, useEffect } from 'react'
import { Plus, Trash2, Save, Loader2, Server, ToggleLeft, ToggleRight } from 'lucide-react'
import api from '@/api/client'
import { Button } from '@/components/ui/button'

interface McpServer {
  id: number
  name: string
  transport: 'stdio' | 'sse' | 'streamable-http'
  command: string | null
  args: string[] | null
  url: string | null
  env: Record<string, string> | null
  headers: Record<string, string> | null
  enabled: boolean
}

interface Props {
  projectId: number
}

export function McpServersTab({ projectId }: Props) {
  const [servers, setServers] = useState<McpServer[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState<number | null>(null)

  const load = async () => {
    setLoading(true)
    try {
      const res = await api.get(`/projects/${projectId}/mcp-servers`)
      setServers(res.data.mcp_servers)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [projectId])

  const addServer = async () => {
    try {
      const res = await api.post(`/projects/${projectId}/mcp-servers`, {
        name: 'new-server',
        transport: 'stdio',
        command: 'npx',
        args: ['-y', '@modelcontextprotocol/server-example'],
        enabled: true,
      })
      setServers([...servers, res.data.mcp_server])
    } catch {
      // handle error
    }
  }

  const updateServer = async (server: McpServer) => {
    setSaving(server.id)
    try {
      const res = await api.put(`/mcp-servers/${server.id}`, server)
      setServers(servers.map(s => s.id === server.id ? res.data.mcp_server : s))
    } finally {
      setSaving(null)
    }
  }

  const deleteServer = async (id: number) => {
    await api.delete(`/mcp-servers/${id}`)
    setServers(servers.filter(s => s.id !== id))
  }

  const toggleEnabled = async (server: McpServer) => {
    const updated = { ...server, enabled: !server.enabled }
    await updateServer(updated)
  }

  const updateField = (id: number, field: string, value: unknown) => {
    setServers(servers.map(s => s.id === id ? { ...s, [field]: value } : s))
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-sm font-medium">MCP Servers</h3>
          <p className="text-xs text-muted-foreground mt-0.5">
            Model Context Protocol servers shared across all provider targets.
          </p>
        </div>
        <Button size="sm" onClick={addServer}>
          <Plus className="h-4 w-4 mr-1" />
          Add Server
        </Button>
      </div>

      {servers.length === 0 ? (
        <div className="text-center py-12 text-muted-foreground text-sm">
          <Server className="h-8 w-8 mx-auto mb-2 opacity-30" />
          No MCP servers configured.
        </div>
      ) : (
        <div className="space-y-3">
          {servers.map((server) => (
            <div key={server.id} className="border border-border p-4 space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <button onClick={() => toggleEnabled(server)} className="text-muted-foreground hover:text-foreground">
                    {server.enabled
                      ? <ToggleRight className="h-5 w-5 text-green-500" />
                      : <ToggleLeft className="h-5 w-5" />}
                  </button>
                  <input
                    type="text"
                    value={server.name}
                    onChange={(e) => updateField(server.id, 'name', e.target.value)}
                    className="bg-transparent border-none text-sm font-medium focus:outline-none"
                    placeholder="Server name"
                  />
                </div>
                <div className="flex items-center gap-1">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => updateServer(server)}
                    disabled={saving === server.id}
                  >
                    {saving === server.id
                      ? <Loader2 className="h-3.5 w-3.5 animate-spin" />
                      : <Save className="h-3.5 w-3.5" />}
                  </Button>
                  <Button variant="ghost" size="sm" onClick={() => deleteServer(server.id)}>
                    <Trash2 className="h-3.5 w-3.5 text-destructive" />
                  </Button>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs text-muted-foreground mb-1 block">Transport</label>
                  <select
                    value={server.transport}
                    onChange={(e) => updateField(server.id, 'transport', e.target.value)}
                    className="w-full border border-border bg-background px-2 py-1.5 text-sm"
                  >
                    <option value="stdio">stdio</option>
                    <option value="sse">SSE</option>
                    <option value="streamable-http">Streamable HTTP</option>
                  </select>
                </div>

                {server.transport === 'stdio' ? (
                  <div>
                    <label className="text-xs text-muted-foreground mb-1 block">Command</label>
                    <input
                      type="text"
                      value={server.command || ''}
                      onChange={(e) => updateField(server.id, 'command', e.target.value)}
                      className="w-full border border-border bg-background px-2 py-1.5 text-sm"
                      placeholder="npx"
                    />
                  </div>
                ) : (
                  <div>
                    <label className="text-xs text-muted-foreground mb-1 block">URL</label>
                    <input
                      type="text"
                      value={server.url || ''}
                      onChange={(e) => updateField(server.id, 'url', e.target.value)}
                      className="w-full border border-border bg-background px-2 py-1.5 text-sm"
                      placeholder="http://localhost:3000/mcp"
                    />
                  </div>
                )}
              </div>

              {server.transport === 'stdio' && (
                <div>
                  <label className="text-xs text-muted-foreground mb-1 block">Args (one per line)</label>
                  <textarea
                    value={(server.args || []).join('\n')}
                    onChange={(e) => updateField(server.id, 'args', e.target.value.split('\n').filter(Boolean))}
                    rows={2}
                    className="w-full border border-border bg-background px-2 py-1.5 text-sm font-mono"
                    placeholder="-y&#10;@modelcontextprotocol/server-example"
                  />
                </div>
              )}

              <div>
                <label className="text-xs text-muted-foreground mb-1 block">
                  Environment Variables (KEY=VALUE, one per line)
                </label>
                <textarea
                  value={Object.entries(server.env || {}).map(([k, v]) => `${k}=${v}`).join('\n')}
                  onChange={(e) => {
                    const env: Record<string, string> = {}
                    e.target.value.split('\n').filter(Boolean).forEach(line => {
                      const idx = line.indexOf('=')
                      if (idx > 0) env[line.slice(0, idx)] = line.slice(idx + 1)
                    })
                    updateField(server.id, 'env', Object.keys(env).length ? env : null)
                  }}
                  rows={2}
                  className="w-full border border-border bg-background px-2 py-1.5 text-sm font-mono"
                  placeholder="API_KEY=sk-..."
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
