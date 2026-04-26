import { themes as prismThemes } from 'prism-react-renderer';
import type { Config } from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const config: Config = {
  title: 'Byte8 Stock Radar',
  tagline: 'Back-in-stock notifications for Magento 2 — throttled, headless, with a real demand heatmap',
  favicon: 'img/favicon.svg',

  future: {
    v4: true,
  },

  url: 'https://magento-stock-radar.byte8.dev',
  baseUrl: '/',

  organizationName: 'byte8io',
  projectName: 'magento-stock-radar',
  trailingSlash: false,

  onBrokenLinks: 'warn',

  markdown: {
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          routeBasePath: 'docs',
          editUrl:
            'https://github.com/byte8io/magento-stock-radar/edit/main/docs/',
        },
        blog: {
          showReadingTime: true,
          blogTitle: 'Changelog & updates',
          blogDescription: 'Release notes for Byte8 Stock Radar',
          postsPerPage: 10,
          feedOptions: {
            type: ['rss', 'atom'],
            xslt: true,
          },
          editUrl:
            'https://github.com/byte8io/magento-stock-radar/edit/main/docs/',
        },
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    image: 'img/social-card.png',
    colorMode: {
      defaultMode: 'dark',
      disableSwitch: false,
      respectPrefersColorScheme: false,
    },
    navbar: {
      title: 'Byte8',
      logo: {
        alt: 'Byte8 Stock Radar',
        src: 'img/logo.svg',
        srcDark: 'img/logo.svg',
        width: 32,
        height: 32,
      },
      items: [
        {
          type: 'docSidebar',
          sidebarId: 'docsSidebar',
          position: 'left',
          label: 'Docs',
        },
        { to: '/blog', label: 'Changelog', position: 'left' },
        { to: '/pricing', label: 'Pricing', position: 'left' },
        {
          href: 'https://github.com/byte8io/magento-stock-radar',
          position: 'right',
          className: 'header-github-link',
          'aria-label': 'GitHub repository',
        },
        {
          href: 'https://byte8.io/magento-stock-radar',
          label: 'Get Started',
          position: 'right',
          className: 'navbar-cta-button',
        },
      ],
    },
    footer: {
      style: 'dark',
      logo: {
        alt: 'Byte8',
        src: 'img/logo.svg',
        href: 'https://byte8.io',
        width: 32,
        height: 32,
      },
      links: [
        {
          title: 'Docs',
          items: [
            { label: 'Quick start', to: '/docs/getting-started/quick-start' },
            { label: 'Configuration', to: '/docs/configuration/general' },
            { label: 'Demand heatmap', to: '/docs/admin/demand-heatmap' },
            { label: 'GraphQL', to: '/docs/advanced/graphql' },
          ],
        },
        {
          title: 'Resources',
          items: [
            { label: 'Changelog', to: '/blog' },
            { label: 'GitHub', href: 'https://github.com/byte8io/magento-stock-radar' },
            { label: 'Plenty bridge', to: '/docs/advanced/plenty-bridge' },
          ],
        },
        {
          title: 'Byte8',
          items: [
            { label: 'byte8.io', href: 'https://byte8.io' },
            { label: 'VAT Validator', href: 'https://magento-vat-validator.byte8.dev' },
            { label: 'Contact', href: 'mailto:helo@byte8.io' },
          ],
        },
      ],
      copyright: `© ${new Date().getFullYear()} Byte8 Ltd. MIT licensed.`,
    },
    prism: {
      theme: prismThemes.vsDark,
      darkTheme: prismThemes.vsDark,
      additionalLanguages: ['php', 'bash', 'json', 'xml-doc', 'tsx', 'sql', 'graphql'],
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
