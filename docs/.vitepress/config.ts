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
          text: 'Introduction',
          items: [
            { text: 'Getting Started', link: '/guide/getting-started' },
            { text: 'Architecture', link: '/guide/architecture' },
            { text: 'User Guide', link: '/guide/user-guide' },
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
          text: 'Skills',
          items: [
            { text: 'Creating Skills', link: '/guide/skills' },
            { text: 'Includes & Composition', link: '/guide/includes' },
            { text: 'Template Variables', link: '/guide/templates' },
            { text: 'Prompt Linting', link: '/guide/linting' },
            { text: 'Version History', link: '/guide/versions' },
          ],
        },
        {
          text: 'Agents',
          items: [
            { text: 'Agent Configuration', link: '/guide/agents' },
            { text: 'Agent Compose', link: '/guide/agent-compose' },
          ],
        },
        {
          text: 'Provider Sync',
          items: [
            { text: 'Sync Overview', link: '/guide/provider-sync' },
            { text: 'Diff Preview', link: '/guide/diff-preview' },
            { text: 'Git Auto-Commit', link: '/guide/git-integration' },
          ],
        },
        {
          text: 'Testing',
          items: [
            { text: 'Test Runner', link: '/guide/test-runner' },
            { text: 'Playground', link: '/guide/playground' },
            { text: 'Multi-Model Setup', link: '/guide/multi-model' },
          ],
        },
        {
          text: 'Sharing',
          items: [
            { text: 'Library', link: '/guide/library' },
            { text: 'Marketplace', link: '/guide/marketplace' },
            { text: 'Skills.sh Import', link: '/guide/skills-sh' },
            { text: 'Bundle Export/Import', link: '/guide/bundles' },
          ],
        },
        {
          text: 'Automation',
          items: [
            { text: 'Webhooks', link: '/guide/webhooks' },
          ],
        },
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Skill File Format', link: '/reference/skill-format' },
            { text: 'API Endpoints', link: '/reference/api' },
            { text: 'CLI & Makefile', link: '/reference/cli' },
            { text: 'Settings', link: '/reference/settings' },
            { text: 'Keyboard Shortcuts', link: '/reference/shortcuts' },
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
