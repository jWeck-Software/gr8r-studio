# The Symfony app at the repo root, the frontend in `assets/`

The repository root is the Symfony app (`src/`, `config/`, `migrations/`, `tests/`, `public/`), and the frontend's sources move to `assets/` (this replaces the `backend/` layout of ADR 0001). `npm run build` writes the frontend into `public/` next to `index.php` and `.htaccess`, so the local Apache serves the app and its API from one directory, `http://localhost/repos/gr8r-studio/public/`, with no dev server in front of it and with relative API paths. The demo build that keeps all data in the browser still goes to `dist/`.
