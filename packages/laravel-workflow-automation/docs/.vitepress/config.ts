import { defineConfig } from 'vitepress'

const advancedSection = {
  text: 'Advanced',
  collapsed: false,
  items: [
    { text: 'Authorization', link: '/advanced/authorization' },
    { text: 'Credential Vault', link: '/advanced/credentials' },
    { text: 'Plugins', link: '/advanced/plugins' },
    { text: 'Custom Nodes', link: '/advanced/custom-nodes' },
    { text: 'Workflow Chaining', link: '/advanced/workflow-chaining' },
    { text: 'Execution Engine', link: '/advanced/execution-engine' },
    { text: 'Error Handling', link: '/advanced/error-handling' },
    { text: 'Rate Limiting', link: '/advanced/rate-limiting' },
    { text: 'Security', link: '/advanced/security' },
    { text: 'Testing', link: '/advanced/testing' },
  ],
}

const referenceSection = {
  text: 'Reference',
  collapsed: false,
  items: [
    { text: 'PHP API', link: '/api/php-api' },
    { text: 'REST Endpoints', link: '/api/rest-endpoints' },
    { text: 'Configuration', link: '/configuration' },
    { text: 'Events', link: '/events' },
    { text: 'Artisan Commands', link: '/commands' },
    { text: 'Database Schema', link: '/database' },
  ],
}

export default defineConfig({
  title: 'Laravel Workflow Automation',
  description: 'Turn your Laravel app into a programmable automation platform — design workflows visually, let AI agents extend your app, and keep your core code clean.',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
  ],

  themeConfig: {
    nav: [
      { text: 'Guide', link: '/getting-started/installation' },
      { text: 'Nodes', link: '/nodes/send-mail' },
      { text: 'Editor', link: '/ui-editor' },
      { text: 'Examples', link: '/examples/ecommerce-order' },
      { text: 'API', link: '/api/php-api' },
    ],

    sidebar: {
      '/': [
        {
          text: 'Getting Started',
          items: [
            { text: 'Why Use This?', link: '/getting-started/why-use-this' },
            { text: 'Installation', link: '/getting-started/installation' },
            { text: 'Quick Start', link: '/getting-started/quick-start' },
            { text: 'Core Concepts', link: '/getting-started/concepts' },
          ],
        },
        {
          text: 'Triggers',
          collapsed: false,
          items: [
            { text: 'Manual', link: '/triggers/manual' },
            { text: 'Model Event', link: '/triggers/model-event' },
            { text: 'Event', link: '/triggers/event' },
            { text: 'Webhook', link: '/triggers/webhook' },
            { text: 'Schedule', link: '/triggers/schedule' },
            { text: 'Workflow', link: '/triggers/workflow' },
          ],
        },
        {
          text: 'Action Nodes',
          collapsed: false,
          items: [
            { text: 'AI', link: '/nodes/ai' },
            { text: 'Send Mail', link: '/nodes/send-mail' },
            { text: 'HTTP Request', link: '/nodes/http-request' },
            { text: 'Update Model', link: '/nodes/update-model' },
            { text: 'Dispatch Job', link: '/nodes/dispatch-job' },
            { text: 'Send Notification', link: '/nodes/send-notification' },
            { text: 'Run Command', link: '/nodes/run-command' },
          ],
        },
        {
          text: 'Condition Nodes',
          collapsed: false,
          items: [
            { text: 'IF Condition', link: '/nodes/if-condition' },
            { text: 'Switch', link: '/nodes/switch' },
          ],
        },
        {
          text: 'Transformer Nodes',
          collapsed: false,
          items: [
            { text: 'Set Fields', link: '/nodes/set-fields' },
            { text: 'Parse Data', link: '/nodes/parse-data' },
          ],
        },
        {
          text: 'Control Nodes',
          collapsed: false,
          items: [
            { text: 'Loop', link: '/nodes/loop' },
            { text: 'Merge', link: '/nodes/merge' },
            { text: 'Delay', link: '/nodes/delay' },
            { text: 'Sub Workflow', link: '/nodes/sub-workflow' },
            { text: 'Error Handler', link: '/nodes/error-handler' },
            { text: 'Wait / Resume', link: '/nodes/wait-resume' },
          ],
        },
        {
          text: 'Utility Nodes',
          collapsed: false,
          items: [
            { text: 'Filter', link: '/nodes/filter' },
            { text: 'Aggregate', link: '/nodes/aggregate' },
            { text: 'Code', link: '/nodes/code' },
          ],
        },
        {
          text: 'Expressions',
          items: [
            { text: 'Expression Engine', link: '/expressions/' },
          ],
        },
        {
          text: 'Integrations',
          collapsed: false,
          items: [
            { text: 'Visual Editor', link: '/ui-editor' },
            { text: 'MCP Server', link: '/mcp' },
            { text: 'AI Builder', link: '/ai-builder' },
          ],
        },
        advancedSection,
        referenceSection,
      ],
      '/examples/': [
        {
          text: 'Examples',
          items: [
            { text: 'E-Commerce Order', link: '/examples/ecommerce-order' },
            { text: 'User Onboarding', link: '/examples/user-onboarding' },
            { text: 'Data Pipeline', link: '/examples/data-pipeline' },
            { text: 'Approval Workflow', link: '/examples/approval-workflow' },
            { text: 'Stripe Webhook', link: '/examples/stripe-webhook' },
            { text: 'Scheduled Report', link: '/examples/scheduled-report' },
            { text: 'Email Drip Campaign', link: '/examples/email-drip-campaign' },
            { text: 'Inventory Sync', link: '/examples/inventory-sync' },
            { text: 'Content Moderation', link: '/examples/content-moderation' },
            { text: 'Multi-Step Form', link: '/examples/multi-step-form' },
          ],
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/aftandilmmd/laravel-workflow-automation' },
    ],

    search: {
      provider: 'local',
    },

    footer: {
      message: 'Released under the MIT License.',
    },
  },
})
