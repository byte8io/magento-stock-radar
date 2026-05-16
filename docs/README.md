# Byte8 Stock Radar — Documentation Site

Docusaurus 3 site for [`byte8/module-stock-radar`](../README.md).

Hosted at **https://docs.byte8.io/stockradar/** — served under the unified Byte8 docs domain via Cloudflare Pages and a path-based Worker router (see `apps/docs-router/` in the byte8.io monorepo).

## Local development

```bash
cd docs
nvm use            # picks up Node 22 from .nvmrc
pnpm install
pnpm start
```

Opens at `http://localhost:3000/stockradar/` (the `baseUrl` prefix is honoured in dev too).

## Production build

```bash
pnpm build
```

Output goes to `build/`. Deployed via **Cloudflare Pages**:

- **Project:** `docs-magento-stock-radar`
- **Build command:** `pnpm install --frozen-lockfile && pnpm build`
- **Build output:** `build`
- **Root directory:** `docs` (since this Docusaurus project sits in a subfolder of the module repo)
- **Production URL:** `https://docs.byte8.io/stockradar/`

## Editing

- **Doc pages** live under `docs/docs/` — mirror the sidebar order in
  `sidebars.ts`.
- **Marketing pages** (`/`, `/pricing`) live under `src/pages/` —
  `index.tsx` is the homepage, `pricing.tsx` is the pricing page.
- **Theme overrides** live in `src/css/custom.css` — amber accent,
  matching the byte8.io marketing aesthetic. Don't edit Docusaurus
  defaults inline; change the variables in the `:root` and
  `[data-theme='dark']` blocks.
- **Blog** = changelog. One markdown file per release under `blog/`,
  authored as `byte8` (see `blog/authors.yml`).
