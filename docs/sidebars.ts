import type { SidebarsConfig } from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  docsSidebar: [
    'intro',
    {
      type: 'category',
      label: 'Getting started',
      collapsed: false,
      items: [
        'getting-started/quick-start',
        'getting-started/installation',
        'getting-started/first-subscription',
      ],
    },
    {
      type: 'category',
      label: 'Configuration',
      items: [
        'configuration/general',
        'configuration/dispatch',
        'configuration/email',
      ],
    },
    {
      type: 'category',
      label: 'Admin',
      items: [
        'admin/subscription-grid',
        'admin/demand-heatmap',
      ],
    },
    {
      type: 'category',
      label: 'Front-end',
      items: [
        'frontend/luma',
        'frontend/hyva',
        'frontend/velafront',
      ],
    },
    {
      type: 'category',
      label: 'Advanced',
      items: [
        'advanced/graphql',
        'advanced/events',
        'advanced/gdpr',
        'advanced/plenty-bridge',
      ],
    },
  ],
};

export default sidebars;
