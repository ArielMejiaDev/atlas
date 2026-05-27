import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Atlas',
  description: 'Offline geocoder for Laravel — fills latitude/longitude from a bundled SQLite database, no API calls.',

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo.svg' }],
  ],

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'Guide', link: '/guide/getting-started' },
      { text: 'Reference', link: '/reference/configuration' },
      {
        text: 'Links',
        items: [
          { text: 'GitHub', link: 'https://github.com/arielmejiadev/atlas' },
          { text: 'Packagist', link: 'https://packagist.org/packages/arielmejiadev/atlas' },
        ],
      },
    ],

    sidebar: {
      '/guide/': [
        {
          text: 'Introduction',
          items: [
            { text: 'What is Atlas?', link: '/guide/what-is-atlas' },
            { text: 'Getting Started', link: '/guide/getting-started' },
          ],
        },
        {
          text: 'Essentials',
          items: [
            { text: 'Usage', link: '/guide/usage' },
            { text: 'Backfill Command', link: '/guide/backfill' },
            { text: 'Auto-Geocoding', link: '/guide/auto-geocoding' },
          ],
        },
        {
          text: 'Advanced',
          items: [
            { text: 'Geocoding Methods', link: '/guide/methods' },
            { text: 'Extending Atlas', link: '/guide/extending' },
          ],
        },
      ],
      '/reference/': [
        {
          text: 'Reference',
          items: [
            { text: 'Configuration', link: '/reference/configuration' },
            { text: 'Artisan Commands', link: '/reference/commands' },
            { text: 'API', link: '/reference/api' },
          ],
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/arielmejiadev/atlas' },
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Geocoding data by <a href="https://www.geonames.org/" target="_blank">GeoNames</a> (CC BY 4.0)',
    },

    search: {
      provider: 'local',
    },
  },
})
