export function UserGuide() {
  return (
    <div className="max-w-4xl mx-auto space-y-10">
      <div>
        <h1 className="text-2xl font-bold text-foreground">User Guide</h1>
        <p className="text-muted-foreground mt-1">
          Build your first agent in 7 steps — from project creation to orchestration.
        </p>
      </div>

      {/* Step 1 */}
      <section className="space-y-4">
        <div className="flex items-center gap-3">
          <span className="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground text-sm font-bold">1</span>
          <h2 className="text-xl font-semibold text-foreground">Create a Project</h2>
        </div>
        <p className="text-muted-foreground">
          A project is a container for your agents, skills, and workflows. Think of it as a workspace for a specific product, team, or use case.
        </p>
        <ol className="list-decimal list-inside space-y-2 text-sm text-foreground pl-4">
          <li>Navigate to <strong>Projects</strong> in the sidebar and click <strong>New Project</strong></li>
          <li>Fill in the basics: <strong>Name</strong>, <strong>Description</strong>, pick an <strong>Icon</strong> (emoji) and <strong>Color</strong></li>
          <li>Set the <strong>Default Model</strong> — the LLM your agents will use (e.g., <code className="bg-muted px-1.5 py-0.5 rounded text-xs">claude-sonnet-4-6</code>)</li>
          <li>Choose an <strong>Environment</strong> — Development, Staging, or Production</li>
          <li>Optionally set a <strong>Monthly Budget</strong> (USD spending cap across all agents)</li>
          <li>Click <strong>Save</strong></li>
        </ol>
        <div className="bg-muted/50 border border-border rounded-lg p-4 text-sm">
          <p className="font-medium text-foreground">Tip: Filesystem Path</p>
          <p className="text-muted-foreground mt-1">
            If you want to sync skills to a local directory (for use with Claude Code, Cursor, etc.), expand the <strong>Advanced</strong> section and set a filesystem path. This enables the provider sync feature.
          </p>
        </div>
      </section>

      {/* Step 2 */}
      <section className="space-y-4">
        <div className="flex items-center gap-3">
          <span className="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground text-sm font-bold">2</span>
          <h2 className="text-xl font-semibold text-foreground">Create an Agent</h2>
        </div>
        <p className="text-muted-foreground">
          Agents are the core of eooo.ai. An agent has an identity, a goal, a reasoning strategy, and permissions.
        </p>
        <ol className="list-decimal list-inside space-y-2 text-sm text-foreground pl-4">
          <li>From your project, go to the <strong>Agents</strong> tab and click <strong>New Agent</strong></li>
          <li>
            Set the <strong>Identity</strong>: Name, Role (a short slug like <code className="bg-muted px-1.5 py-0.5 rounded text-xs">code-reviewer</code>), Model, and Base Instructions
          </li>
          <li>
            Define the <strong>Goal</strong>: Objective, Success Criteria (one per line), Max Iterations, and Timeout
          </li>
          <li>
            Choose a <strong>Reasoning</strong> strategy:
          </li>
        </ol>
        <div className="grid grid-cols-2 gap-3 pl-4">
          {[
            { name: 'None', desc: 'Direct execution, no planning' },
            { name: 'Act', desc: 'Execute actions directly' },
            { name: 'Plan then Act', desc: 'Plan first, execute later' },
            { name: 'ReAct', desc: 'Alternate reasoning and acting (recommended)' },
          ].map((s) => (
            <div key={s.name} className="bg-muted/50 border border-border rounded-lg p-3">
              <p className="font-medium text-foreground text-sm">{s.name}</p>
              <p className="text-muted-foreground text-xs mt-0.5">{s.desc}</p>
            </div>
          ))}
        </div>

        <div className="bg-blue-500/10 border border-blue-500/20 rounded-lg p-4 text-sm">
          <p className="font-medium text-foreground">Autonomy Levels</p>
          <p className="text-muted-foreground mt-1">
            Under <strong>Autonomy & Permissions</strong>, control how much freedom the agent has:
            <strong> Supervised</strong> (every tool call needs approval),
            <strong> Semi-Autonomous</strong> (only sensitive ops), or
            <strong> Autonomous</strong> (runs freely).
            Start with Supervised until you trust the agent's behavior.
          </p>
        </div>
      </section>

      {/* Step 3 */}
      <section className="space-y-4">
        <div className="flex items-center gap-3">
          <span className="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground text-sm font-bold">3</span>
          <h2 className="text-xl font-semibold text-foreground">Create Skills</h2>
        </div>
        <p className="text-muted-foreground">
          Skills are reusable prompt modules that agents can use. They're written in Markdown with optional YAML frontmatter.
        </p>
        <ol className="list-decimal list-inside space-y-2 text-sm text-foreground pl-4">
          <li>From your project, go to the <strong>Skills</strong> tab and click <strong>New Skill</strong></li>
          <li>Set a <strong>Name</strong> and <strong>Description</strong> in the frontmatter panel</li>
          <li>Write the skill body in the <strong>Monaco Editor</strong></li>
          <li>Optionally add <strong>Tags</strong> for organization</li>
          <li>Press <kbd className="bg-muted border border-border px-1.5 py-0.5 rounded text-xs font-mono">Ctrl+S</kbd> to save</li>
        </ol>
        <div className="bg-muted/30 border border-border rounded-lg p-4 text-sm font-mono">
          <p className="text-muted-foreground text-xs mb-2 font-sans">Example skill body:</p>
          <pre className="text-foreground text-xs leading-relaxed whitespace-pre-wrap">{`When reviewing code, follow these rules:

1. Check for SQL injection, XSS, and OWASP Top 10 issues
2. Flag any hardcoded credentials or secrets
3. Verify error handling covers edge cases
4. Ensure new code has corresponding tests
5. Keep feedback constructive — suggest fixes, not just problems`}</pre>
        </div>
        <p className="text-sm text-muted-foreground">
          Once created, assign skills to your agent in the <strong>Agent Builder</strong>. Assigned skills are merged into the agent's system prompt at runtime.
        </p>
      </section>

      {/* Step 4 */}
      <section className="space-y-4">
        <div className="flex items-center gap-3">
          <span className="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground text-sm font-bold">4</span>
          <h2 className="text-xl font-semibold text-foreground">Test in the Playground</h2>
        </div>
        <p className="text-muted-foreground">
          Before running your agent in production, test it interactively.
        </p>
        <ol className="list-decimal list-inside space-y-2 text-sm text-foreground pl-4">
          <li>Navigate to <strong>Playground</strong> in the sidebar</li>
          <li>Select your <strong>Project</strong> from the dropdown</li>
          <li>Choose a system prompt source — pick a <strong>Skill</strong> to test one prompt, or an <strong>Agent</strong> to test the full composed prompt</li>
          <li>Select a <strong>Model</strong> (requires API key configured in Settings)</li>
          <li>Type a message and press <kbd className="bg-muted border border-border px-1.5 py-0.5 rounded text-xs font-mono">Ctrl+Enter</kbd> to send</li>
          <li>Watch the response stream in real-time with token counts</li>
        </ol>
        <p className="text-sm text-muted-foreground">
          The Playground supports multi-turn conversations, so you can iterate on prompts and test follow-ups.
        </p>
      </section>

      {/* Step 5 */}
      <section className="space-y-4">
        <div className="flex items-center gap-3">
          <span className="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground text-sm font-bold">5</span>
          <h2 className="text-xl font-semibold text-foreground">Run the Agent</h2>
        </div>
        <p className="text-muted-foreground">
          When you're ready, trigger an agent execution and monitor it in real-time.
        </p>
        <ol className="list-decimal list-inside space-y-2 text-sm text-foreground pl-4">
          <li>From your project, trigger an agent execution</li>
          <li>Monitor progress in the <strong>Execution Dashboard</strong></li>
          <li>See each execution step: <strong>Perceive</strong>, <strong>Reason</strong>, <strong>Act</strong>, <strong>Observe</strong></li>
          <li>If the agent is in <strong>Supervised</strong> mode, review and <strong>Approve</strong> or <strong>Reject</strong> proposed actions</li>
          <li>View the <strong>Final Output</strong> once the run completes</li>
        </ol>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { label: 'Total Runs', desc: 'Execution count' },
            { label: 'Total Cost', desc: 'Cumulative USD' },
            { label: 'Success Rate', desc: '% completed' },
            { label: 'Cost by Model', desc: 'Per-model breakdown' },
          ].map((stat) => (
            <div key={stat.label} className="bg-muted/50 border border-border rounded-lg p-3 text-center">
              <p className="text-sm font-medium text-foreground">{stat.label}</p>
              <p className="text-xs text-muted-foreground mt-0.5">{stat.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Step 6 */}
      <section className="space-y-4">
        <div className="flex items-center gap-3">
          <span className="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground text-sm font-bold">6</span>
          <h2 className="text-xl font-semibold text-foreground">Set Up Schedules</h2>
        </div>
        <p className="text-muted-foreground">
          Agents can run on a schedule, respond to webhooks, or trigger on events.
        </p>
        <ol className="list-decimal list-inside space-y-2 text-sm text-foreground pl-4">
          <li>From your project, go to the <strong>Schedules</strong> tab</li>
          <li>Click <strong>New Schedule</strong> and choose a trigger type:</li>
        </ol>
        <div className="grid grid-cols-3 gap-3 pl-4">
          {[
            { name: 'Cron', desc: 'Recurring schedule with visual builder' },
            { name: 'Webhook', desc: 'HTTP POST to a unique URL' },
            { name: 'Event', desc: 'System event triggers' },
          ].map((t) => (
            <div key={t.name} className="bg-muted/50 border border-border rounded-lg p-3">
              <p className="font-medium text-foreground text-sm">{t.name}</p>
              <p className="text-muted-foreground text-xs mt-0.5">{t.desc}</p>
            </div>
          ))}
        </div>
        <p className="text-sm text-muted-foreground pl-4">
          Select which agent to run, then toggle the schedule <strong>On</strong>. Each webhook schedule gets a unique URL and secret token — great for CI/CD integrations.
        </p>
      </section>

      {/* Step 7 */}
      <section className="space-y-4">
        <div className="flex items-center gap-3">
          <span className="flex items-center justify-center h-8 w-8 rounded-full bg-primary text-primary-foreground text-sm font-bold">7</span>
          <h2 className="text-xl font-semibold text-foreground">Build Workflows</h2>
        </div>
        <p className="text-muted-foreground">
          For multi-agent orchestration, use the visual Workflow Builder to create DAGs.
        </p>
        <ol className="list-decimal list-inside space-y-2 text-sm text-foreground pl-4">
          <li>Navigate to your project's <strong>Workflows</strong> tab and click <strong>New Workflow</strong></li>
          <li>Use the drag-and-drop canvas to build your pipeline:</li>
        </ol>
        <div className="grid grid-cols-3 sm:grid-cols-6 gap-2 pl-4">
          {['Start', 'Agent', 'Condition', 'Split', 'Join', 'End'].map((node) => (
            <div key={node} className="bg-muted/50 border border-border rounded-lg p-2 text-center">
              <p className="text-xs font-medium text-foreground">{node}</p>
            </div>
          ))}
        </div>
        <ol className="list-decimal list-inside space-y-2 text-sm text-foreground pl-4" start={3}>
          <li>Connect nodes by dragging edges between them</li>
          <li>Set a trigger (Manual, Schedule, Webhook, or Event)</li>
          <li>Save and set the workflow status to <strong>Active</strong></li>
        </ol>
      </section>

      {/* What's Next */}
      <section className="space-y-4">
        <h2 className="text-xl font-semibold text-foreground">What's Next?</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {[
            { title: 'Fallback Models', desc: 'Add fallback models so agents auto-retry with a different provider on failure' },
            { title: 'Budget Controls', desc: 'Set per-run and daily budgets to prevent runaway costs' },
            { title: 'Performance Dashboard', desc: 'Track KPIs, agent leaderboards, and cost trends' },
            { title: 'Audit Log', desc: 'Review every action taken by your agents for compliance' },
            { title: 'Marketplace', desc: 'Publish your best skills or install community skills' },
            { title: 'Provider Sync', desc: 'Sync skills to Claude, Cursor, Copilot, Windsurf, Cline, and OpenAI' },
          ].map((item) => (
            <div key={item.title} className="bg-muted/50 border border-border rounded-lg p-4">
              <p className="font-medium text-foreground text-sm">{item.title}</p>
              <p className="text-muted-foreground text-xs mt-1">{item.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Quick Reference */}
      <section className="space-y-4 pb-8">
        <h2 className="text-xl font-semibold text-foreground">Quick Reference</h2>
        <div className="border border-border rounded-lg overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-muted/50">
                <th className="text-left px-4 py-2 font-medium text-foreground">Shortcut</th>
                <th className="text-left px-4 py-2 font-medium text-foreground">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              <tr>
                <td className="px-4 py-2"><kbd className="bg-muted border border-border px-1.5 py-0.5 rounded text-xs font-mono">Cmd+K</kbd></td>
                <td className="px-4 py-2 text-muted-foreground">Command palette (fuzzy search)</td>
              </tr>
              <tr>
                <td className="px-4 py-2"><kbd className="bg-muted border border-border px-1.5 py-0.5 rounded text-xs font-mono">Ctrl+S</kbd></td>
                <td className="px-4 py-2 text-muted-foreground">Save current skill</td>
              </tr>
              <tr>
                <td className="px-4 py-2"><kbd className="bg-muted border border-border px-1.5 py-0.5 rounded text-xs font-mono">Ctrl+Enter</kbd></td>
                <td className="px-4 py-2 text-muted-foreground">Send message in Playground</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  )
}
