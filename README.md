# Gr8r Studio

A calm, structured project management workspace: home dashboard, inbox, my tasks, projects with
board / list / table / calendar / timeline / files views, members, teams, activity and settings.

Built with plain JavaScript ES modules and CSS, bundled by [Vite](https://vite.dev). There is no
UI framework and no runtime dependency.

![Gr8r Studio home dashboard](docs/preview.png)

## Getting started

Requires Node.js 22 (`.nvmrc`); `npm test` relies on its glob support in `node --test`.

```bash
npm install
npm run dev       # http://localhost:5173 with hot reload
npm run build     # production build in dist/
npm run preview   # serve the production build locally
npm run lint      # ESLint
npm run format    # Prettier
npm test          # unit tests (node --test)
```

### Backend mode

By default the app is a static demo: the workspace lives in this browser's localStorage. With
`VITE_API_URL` set at build time it runs against the Symfony backend in `backend/` instead
(see `backend/README.md` to start it on port 8000, and `docs/adr/0002`, `0004`):

```bash
VITE_API_URL=/api npm run dev   # or put VITE_API_URL=/api in .env.local (git-ignored)
```

The dev server forwards `/api` to `http://127.0.0.1:8000`, so the session cookie stays on one
origin. Sign in as `hello@gr8rstudio.com` with `gr8r-studio-demo`. The app asks `/api/me` at boot
(and shows the login screen without a session), loads the workspace from `/api/bootstrap`, and
every `save()` sends the changed records to `/api/sync` (`src/actions/sync.js`; the synced
collections are listed in `SYNCED`, so far `projects`, `tasks`, the sidebar's `projOrder`, `comments`, `activity`
and the `read` flag of `notifs`). Preferences and view settings stay in
localStorage for now. Teammates' changes come in when the tab regains focus and every 60 s
while it is visible (after pending changes are sent, and not while you are typing in a field or
have a dialog open). Sign-up, social sign-in, onboarding and the demo reset are not available
in this mode yet.

## Project structure

```
index.html              HTML entry; loads src/main.js
public/favicon.svg      brand mark
vite.config.js          Vite config, including the Lucide icon-subset plugin
eslint.config.js        lint rules (ESLint flat config); formatting by Prettier (.prettierrc.json)
.github/workflows/      CI: lint, format check, unit tests, build; backend PHPUnit
docs/preview.png        README screenshot
src/
  main.js               entry: imports styles and modules, then boots the app
  styles/
    index.css           imports every stylesheet in cascade order
    tokens.css          colours, spacing, type, radius, the shared --gutter
    base.css  components.css  shell.css  panels.css  overlays.css  states.css  motion.css  responsive.css
    views/              list, board, table, calendar, timeline, projects & files
    pages/              settings, auth & onboarding
  core/                 utilities, constants, icons, store (state + lookups), theme,
                        API client and the sync diff (backend mode)
  data/seed.js          demo workspace data
  ui/                   shared render helpers, toasts, the select dropdown
  shell/                render loop and router, sidebar/topbar layout, skeletons, view engine
  components/           task list renderer
  pages/                one module per route
  views/                project views: board, table, calendar, timeline, files, overview
  overlays/             task drawer, modals, popovers, context menu, command palette
  features/             teams, subtasks, archive (rendering)
  actions/              user actions, event wiring, keyboard shortcuts, drag and drop,
                        sync with the backend
```

### Layers

Dependencies point downward:

1. `core/` and `data/` depend on nothing else in the app.
2. `ui/`, `shell/`, `components/`, `pages/`, `views/`, `overlays/` and `features/` render HTML from state.
3. `actions/` change state and re-render. Each action module registers its handlers on the
   shared action table (`A`) when it loads, so `main.js` imports them after `actions/actions.js`.
   In backend mode `actions/sync.js` registers on the store's save hook (`onSave`) the same way,
   so `core/store.js` never imports from a higher layer.

## Conventions

- **Rendering:** each page or view is a function that returns an HTML string from the store
  (`S`). `render()` rebuilds the shell and keeps focus and scroll positions.
- **Interactions:** elements declare `data-a="actionName"`. `actions/events.js` delegates the
  click to `A.actionName`. Inputs use `data-in`, blur handlers use `data-blur`.
- **Layout:** every page edge uses `var(--gutter)`, so the breadcrumb, page header, toolbar,
  list, table and board start on the same line.
- **Dropdowns:** write a normal `<select class="select">`. `ui/select.js` renders it as the
  design-system menu and keeps the native element as the source of truth.
- **Icons:** call `ic('name')` with any [Lucide](https://lucide.dev) icon name. The
  `virtual:icons` plugin bundles only the icons the source uses.

## Continuous integration

Every push and pull request runs lint, the format check, the unit tests and a production build,
and the backend's PHPUnit suite against PostgreSQL (`.github/workflows/ci.yml`).

## Deploy

`vercel.json` builds with Vite and serves `dist/`.
