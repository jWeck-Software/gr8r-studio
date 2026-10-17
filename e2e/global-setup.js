/* Before the browser tests: the tests' database gets the current schema and a fresh demo Workspace,
   and each demo role has a signed-in session in e2e/.auth/. A saved session that still opens the
   Workspace is kept; only a missing or expired one signs in again (sign-ins are rate limited).
   Seeding replaces the demo Members, which ends their sessions, so a run that seeds signs in anew. */
import { request } from '@playwright/test';
import { existsSync, mkdirSync } from 'node:fs';
import { API, DEMO_PASSWORD, ROLES, ROOT, authFile, console_ } from './support.js';

export default async function globalSetup() {
  console_('doctrine:migrations:migrate', '--allow-no-migration');
  console_('app:demo:seed');
  mkdirSync(`${ROOT}e2e/.auth`, { recursive: true });
  for (const [role, email] of Object.entries(ROLES)) {
    if (existsSync(authFile(role))) {
      const saved = await request.newContext({ storageState: authFile(role) });
      const ok = (await saved.get(API + 'bootstrap')).ok();
      await saved.dispose();
      if (ok) continue;
    }
    const ctx = await request.newContext();
    const res = await ctx.post(API + 'login', { data: { email, password: DEMO_PASSWORD } });
    if (!res.ok()) throw new Error(`Signing in as ${role} (${email}) answered ${res.status()}`);
    await ctx.storageState({ path: authFile(role) });
    await ctx.dispose();
  }
}
