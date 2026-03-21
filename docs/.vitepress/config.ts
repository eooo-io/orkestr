import { defineConfig } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'

export default withMermaid(
  defineConfig({
    title: 'Orkestr by eooo.ai',
    description: 'Self-hosted Agent OS — design, execute, and manage autonomous AI agents on your own infrastructure.',
    base: '/orkestr/',

    ignoreDeadLinks: [
      /localhost/,
    ],

    head: [
      ['link', { rel: 'icon', type: 'image/svg+xml', href: '/orkestr/logo.svg' }],
    ],

    themeConfig: {
      logo: '/logo.svg',

      nav: [
        { text: '101', link: '/101/' },
        { text: 'Guide', link: '/guide/getting-started' },
        {
          text: 'Learn',
          items: [
            { text: 'Deep Dives', link: '/deep-dive/' },
            { text: 'Cookbook', link: '/cookbook/' },
          ],
        },
        { text: 'Reference', link: '/reference/skill-format' },
        {
          text: 'v1.0.0',
          items: [
            { text: 'Changelog', link: '/changelog' },
            { text: 'GitHub', link: 'https://github.com/eooo-io/orkestr' },
          ],
        },
      ],

      sidebar: {
        '/101/': [
          {
            text: 'Orkestr 101',
            link: '/101/',
            items: [
              { text: 'What is Orkestr?', link: '/101/what-is-orkestr' },
              { text: 'The Three Layers', link: '/101/the-three-layers' },
            ],
          },
          {
            text: 'Foundations',
            items: [
              { text: 'What are Skills?', link: '/101/what-are-skills' },
              { text: 'What are Agents?', link: '/101/what-are-agents' },
              { text: 'The Agent Loop', link: '/101/the-agent-loop' },
            ],
          },
          {
            text: 'Tools & Communication',
            items: [
              { text: 'What are Tools & MCP?', link: '/101/what-are-tools' },
              { text: 'What is A2A?', link: '/101/what-is-a2a' },
              { text: 'What are Workflows?', link: '/101/what-are-workflows' },
              { text: 'What is the Canvas?', link: '/101/what-is-the-canvas' },
            ],
          },
          {
            text: 'Runtime & Safety',
            items: [
              { text: 'What is Execution?', link: '/101/what-is-execution' },
              { text: 'What is Agent Memory?', link: '/101/what-is-agent-memory' },
              { text: 'What are Guardrails?', link: '/101/what-are-guardrails' },
              { text: 'What are Schedules?', link: '/101/what-are-schedules' },
            ],
          },
          {
            text: 'Platform & Ecosystem',
            items: [
              { text: 'What are Projects?', link: '/101/what-are-projects' },
              { text: 'What is Provider Sync?', link: '/101/what-is-provider-sync' },
              { text: 'What is Multi-Model?', link: '/101/what-is-multi-model' },
              { text: 'What is Air-Gap Mode?', link: '/101/what-is-air-gap' },
            ],
          },
        ],
        '/guide/': [
          {
            text: 'Getting Started',
            items: [
              { text: 'Getting Started', link: '/guide/getting-started' },
              { text: 'Architecture', link: '/guide/architecture' },
              { text: 'Core Concepts', link: '/guide/core-concepts' },
            ],
          },
          {
            text: 'Deployment',
            items: [
              { text: 'Self-Hosted Deployment', link: '/guide/self-hosted-deployment' },
              { text: 'Hardware Recommendations', link: '/guide/hardware' },
              { text: 'Local Models', link: '/guide/local-models' },
              { text: 'Guardrails', link: '/guide/guardrails' },
            ],
          },
          {
            text: 'Building',
            items: [
              { text: 'Project Management', link: '/guide/projects' },
              { text: 'Skill Editor', link: '/guide/skill-editor' },
              { text: 'Creating Skills', link: '/guide/skills' },
              { text: 'Includes & Composition', link: '/guide/includes' },
              { text: 'Template Variables', link: '/guide/templates' },
              { text: 'Prompt Linting', link: '/guide/linting' },
              { text: 'Version History', link: '/guide/versions' },
              { text: 'Agent Configuration', link: '/guide/agents' },
              { text: 'Agent Teams', link: '/guide/agent-teams' },
              { text: 'Agent Compose', link: '/guide/agent-compose' },
              { text: 'Canvas', link: '/guide/canvas' },
              { text: 'Workflows', link: '/guide/workflows' },
            ],
          },
          {
            text: 'Running',
            items: [
              { text: 'Provider Sync', link: '/guide/provider-sync' },
              { text: 'Diff Preview', link: '/guide/diff-preview' },
              { text: 'Git Auto-Commit', link: '/guide/git-integration' },
              { text: 'Test Runner', link: '/guide/test-runner' },
              { text: 'Playground', link: '/guide/playground' },
              { text: 'Multi-Model Setup', link: '/guide/multi-model' },
              { text: 'Execution', link: '/guide/execution' },
              { text: 'Schedules', link: '/guide/schedules' },
              { text: 'Connections', link: '/guide/connections' },
            ],
          },
          {
            text: 'Managing',
            items: [
              { text: 'Security & Guardrails', link: '/guide/security' },
              { text: 'Analytics & Testing', link: '/guide/analytics' },
              { text: 'Import & Export', link: '/guide/import-export' },
              { text: 'API Access', link: '/guide/api-access' },
              { text: 'Library', link: '/guide/library' },
              { text: 'Skills.sh Import', link: '/guide/skills-sh' },
              { text: 'Bundle Export/Import', link: '/guide/bundles' },
              { text: 'Webhooks', link: '/guide/webhooks' },
              { text: 'Settings', link: '/guide/settings' },
            ],
          },
          {
            text: 'Help',
            items: [
              { text: 'Troubleshooting', link: '/guide/troubleshooting' },
              { text: 'User Guide', link: '/guide/user-guide' },
            ],
          },
        ],
        '/deep-dive/': [
          {
            text: 'Deep Dives',
            link: '/deep-dive/',
            items: [
              { text: 'Design Philosophy', link: '/deep-dive/design-philosophy' },
              { text: 'Agent Loop Architecture', link: '/deep-dive/agent-loop-architecture' },
              { text: 'MCP Integration', link: '/deep-dive/mcp-integration' },
              { text: 'A2A Protocol', link: '/deep-dive/a2a-protocol' },
              { text: 'Workflow DAG Engine', link: '/deep-dive/workflow-engine' },
              { text: 'Canvas Architecture', link: '/deep-dive/canvas-architecture' },
              { text: 'Guardrail System', link: '/deep-dive/guardrail-system' },
              { text: 'Multi-Model Routing', link: '/deep-dive/multi-model-routing' },
              { text: 'Provider Sync Engine', link: '/deep-dive/provider-sync-engine' },
              { text: 'Skill Composition', link: '/deep-dive/skill-composition' },
              { text: 'Data Architecture', link: '/deep-dive/data-architecture' },
            ],
          },
        ],
        '/cookbook/': [
          {
            text: 'Cookbook',
            link: '/cookbook/',
            items: [
              { text: 'Your First Agent Team', link: '/cookbook/first-agent-team' },
              { text: 'Skills from Scratch', link: '/cookbook/skills-from-scratch' },
            ],
          },
          {
            text: 'Agent Patterns',
            items: [
              { text: 'Code Review Pipeline', link: '/cookbook/code-review-pipeline' },
              { text: 'Scheduled Security Scanner', link: '/cookbook/scheduled-security-scanner' },
              { text: 'Architecture Review', link: '/cookbook/architecture-review' },
            ],
          },
          {
            text: 'Infrastructure',
            items: [
              { text: 'Air-Gapped Local Setup', link: '/cookbook/air-gapped-setup' },
              { text: 'MCP Tool Integration', link: '/cookbook/mcp-tool-integration' },
              { text: 'Production Deployment', link: '/cookbook/production-deployment' },
            ],
          },
          {
            text: 'Enterprise',
            items: [
              { text: 'Enterprise Guardrails', link: '/cookbook/enterprise-guardrails' },
              { text: 'CI/CD with GitHub Actions', link: '/cookbook/cicd-github-actions' },
            ],
          },
        ],
        '/reference/': [
          {
            text: 'Reference',
            items: [
              { text: 'Skill File Format', link: '/reference/skill-format' },
              { text: 'API Endpoints', link: '/reference/api' },
              { text: 'Keyboard Shortcuts', link: '/reference/shortcuts' },
              { text: 'CLI & Makefile', link: '/reference/cli' },
              { text: 'Settings', link: '/reference/settings' },
            ],
          },
        ],
      },

      socialLinks: [
        { icon: 'github', link: 'https://github.com/eooo-io/orkestr' },
      ],

      search: {
        provider: 'local',
      },

      footer: {
        message: 'Released under the MIT License.',
        copyright: 'Copyright 2026 eooo.ai',
      },
    },
  })
)
