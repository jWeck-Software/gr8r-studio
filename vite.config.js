import { defineConfig } from 'vite';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { icons as lucide } from 'lucide';

/* The frontend lives in assets/ (index.html, src/, public/); the repo root is the Symfony app, whose
   public/ the build writes into, next to index.php and .htaccess. */
const root = fileURLToPath(new URL('./assets', import.meta.url));

/* `virtual:icons` exports only the Lucide icons the source actually names, as inline SVG markup,
   so the bundle carries ~170 icons instead of the full set. Any quoted kebab-case token in
   assets/src/ that matches a Lucide icon name is included. */
function lucideSubset() {
  const id = 'virtual:icons',
    resolved = '\0' + id;
  const walk = dir =>
    fs.readdirSync(dir, { withFileTypes: true }).flatMap(e => {
      const p = path.join(dir, e.name);
      return e.isDirectory() ? walk(p) : /\.js$/.test(e.name) ? [p] : [];
    });
  const pascal = n =>
    n
      .split('-')
      .map(p => p[0].toUpperCase() + p.slice(1))
      .join('');
  return {
    name: 'lucide-subset',
    resolveId: s => (s === id ? resolved : null),
    load(s) {
      if (s !== resolved) return null;
      const text = walk(path.join(root, 'src'))
        .map(f => fs.readFileSync(f, 'utf8'))
        .join('\n');
      const names = [...new Set([...text.matchAll(/['"`]([a-z][a-z0-9]*(?:-[a-z0-9]+)*)['"`]/g)].map(m => m[1]))].filter(n => lucide[pascal(n)]).sort();
      const out = {};
      for (const n of names) {
        let node = lucide[pascal(n)];
        if (node[0] === 'svg') node = node[2];
        out[n] = node
          .map(
            ([t, a]) =>
              `<${t} ${Object.entries(a)
                .filter(([k]) => k !== 'key')
                .map(([k, v]) => `${k}="${v}"`)
                .join(' ')}/>`,
          )
          .join('');
      }
      return `export const ICONS = ${JSON.stringify(out)};`;
    },
  };
}

/* Two builds (see package.json):
   - `npm run build`: the app in backend mode (VITE_API_URL=api) into public/. `api` is relative, so
     the app reaches the API next to itself, under http://localhost/repos/gr8r-studio/public/ as at a
     vhost's root. Never empty public/: index.php and .htaccess live there.
   - `npm run build:demo` (--mode demo): the localStorage demo without VITE_API_URL into dist/, for Vercel.
   assets/public/ (the favicon) is copied into either. */
export default defineConfig(({ mode }) => ({
  root,
  base: './',
  plugins: [lucideSubset()],
  build:
    mode === 'demo'
      ? { outDir: fileURLToPath(new URL('./dist', import.meta.url)), emptyOutDir: true }
      : { outDir: fileURLToPath(new URL('./public', import.meta.url)), emptyOutDir: false },
  // Backend mode in development (VITE_API_URL=/api npm run dev): the dev server forwards /api to the
  // app under Apache, so the session cookie stays same-origin (ADR 0004).
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
        rewrite: p => '/repos/gr8r-studio/public' + p,
      },
    },
  },
}));
