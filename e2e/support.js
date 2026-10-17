/* What the browser tests share: the app's addresses, the demo accounts, signed-in sessions per role,
   the links the app mails, and new accounts for flows that change or end a session. */
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

export const ROOT = fileURLToPath(new URL('..', import.meta.url));
export const APP = 'http://localhost/repos/gr8r-studio/e2e/www/';
export const API = APP + 'api/';
export const DEMO_PASSWORD = 'gr8r-studio-demo'; // App\Demo\DemoSeed::PASSWORD
export const ROLES = {
  owner: 'hello@gr8rstudio.com',
  admin: 'sarah@gr8rstudio.com',
  member: 'john@gr8rstudio.com',
};
/* Signed-in sessions (live cookies), git-ignored. */
export const authFile = role => `${ROOT}e2e/.auth/${role}.json`;

/* Runs bin/console on the tests' database: the variables of .env.e2e.local win over .env.local. */
export function console_(...args) {
  const env = { ...process.env };
  for (const line of readFileSync(`${ROOT}.env.e2e.local`, 'utf8').split('\n')) {
    const m = line.match(/^([A-Z_]+)=(.*)$/);
    if (m) env[m[1]] = m[2].replace(/^"(.*)"$/, '$1');
  }
  return execFileSync('php8.4', ['bin/console', ...args, '--no-interaction'], { cwd: ROOT, env, encoding: 'utf8' });
}

/* The last link the app mailed to an address (var/log/mail.log, the dev mail transport's log), of one
   kind: 'verify', 'reset' or 'join'. Waits for it, since the app sends after answering. */
export async function mailedLink(to, kind, { after = 0, timeout = 5000 } = {}) {
  const end = Date.now() + timeout;
  for (;;) {
    const lines = readFileSync(`${ROOT}var/log/mail.log`, 'utf8').trim().split('\n');
    for (const line of lines.reverse()) {
      const at = Date.parse(line.slice(0, 25));
      if (at < after) break;
      if (!line.includes(` to=${to} `)) continue;
      const link = line.match(new RegExp(`links=\\S*?(https?://\\S+#${kind}/\\S+)`))?.[1];
      if (link) return link;
    }
    if (Date.now() > end) throw new Error(`No ${kind} link mailed to ${to}`);
    await new Promise(r => setTimeout(r, 200));
  }
}

/* A fresh address for flows that sign out, change a password or delete a Workspace, so the shared
   sessions of the demo accounts stay valid. */
export const freshEmail = tag => `e2e-${tag}-${Date.now()}-${Math.floor(Math.random() * 1e4)}@example.test`;

/* The password of the fresh accounts (the policy: at least 10 characters with a number or symbol). */
export const FRESH_PASSWORD = 'Fresh-pass-2026!';

/* How many links of one kind the app has mailed to an address so far (var/log/mail.log). */
export function mailCount(to, kind) {
  return readFileSync(`${ROOT}var/log/mail.log`, 'utf8')
    .split('\n')
    .filter(line => line.includes(` to=${to} `) && line.includes(`#${kind}/`)).length;
}

/* A new, verified User, signed in on `request` (a page's request shares the browser's cookies), with
   a Workspace of its own unless `workspace` is false. Resolves with the address and the Workspace. */
export async function freshUser(request, tag, { workspace = true } = {}) {
  const email = freshEmail(tag);
  const after = Date.now() - 2000;
  const signup = await request.post(API + 'signup', { data: { name: `E2E ${tag}`, email, password: FRESH_PASSWORD } });
  if (!signup.ok()) throw new Error(`Signing up ${email} answered ${signup.status()}`);
  const token = (await mailedLink(email, 'verify', { after })).split('#verify/')[1];
  const verify = await request.post(API + 'verify-email', { data: { token } });
  if (!verify.ok()) throw new Error(`Verifying ${email} answered ${verify.status()}`);
  if (!workspace) return { email };
  const name = `E2E ${tag} ${Date.now() % 1e6}`;
  const url = `e2e-${tag}-${Date.now()}-${Math.floor(Math.random() * 1e4)}`;
  const started = await request.post(API + 'workspaces', { data: { name, url, c: '#5A67D8' } });
  if (!started.ok()) throw new Error(`Starting a Workspace for ${email} answered ${started.status()}`);
  return { email, workspace: name, url };
}

/* A 1×1 PNG, for uploads. */
export const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
