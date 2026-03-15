import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Orkestr by eooo.ai',
  description: 'Self-hosted agent orchestration platform with multi-model support, guardrails, and provider sync.',
  base: '/agentis-studio/',

  ignoreDeadLinks: [
    /localhost/,
  ],

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/agentis-studio/logo.svg' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'Reference', link: '/reference/skill-format' },
      {
        text: 'v1.0.0',
        items: [
          { text: 'Changelog', link: '/changelog' },
          { text: 'GitHub', link: 'https://github.com/eooo-io/agentis-studio' },
        ],
      },
    ],

    sidebar: {
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
            { text: 'Marketplace', link: '/guide/marketplace' },
            { text: 'Skills.sh Import', link: '/guide/skills-sh' },
            { text: 'Bundle Export/Import', link: '/guide/bundles' },
            { text: 'Webhooks', link: '/guide/webhooks' },
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
      { icon: 'github', link: 'https://github.com/eooo-io/agentis-studio' },
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
