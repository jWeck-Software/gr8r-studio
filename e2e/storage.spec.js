/* What backend mode keeps in this browser's localStorage (store.js STORE_KEY, load(), S.data, prefsOwner,
   save()): under its own key, the Member's prefs and views and whose they are, never the Workspace. */
import { test, expect } from '@playwright/test';
import { API, authFile, freshUser } from './support.js';

const KEY = 'gr8r.v2.backend';
const stored = page => page.evaluate(k => JSON.parse(localStorage.getItem(k) || 'null'), KEY);
/* Puts `value` under the key before the app first starts in this tab (not again on a reload). */
const seedStorage = (page, value) =>
  page.addInitScript(
    ([k, v]) => {
      if (!sessionStorage.getItem('e2e-seeded')) {
        sessionStorage.setItem('e2e-seeded', '1');
        localStorage.setItem(k, v);
      }
    },
    [KEY, JSON.stringify(value)],
  );
const themeIs = async (page, v) => {
  await page.getByLabel('Account menu').click();
  await expect(page.locator(`.pop [data-a="setTheme"][data-v="${v}"]`)).toHaveClass(/\bon\b/);
  await page.keyboard.press('Escape');
};

test.describe('signed in as a Member', () => {
  test.use({ storageState: authFile('member') });

  test('the browser keeps prefs and views under its own key, without the Workspace data', async ({ page }) => {
    await page.goto('./');
    await expect(page.locator('.shell')).toBeVisible();
    await page.getByRole('button', { name: 'Collapse sidebar' }).click();
    await expect(page.locator('.shell.collapsed')).toBeVisible();

    await expect.poll(() => stored(page)).not.toBeNull();
    const saved = await stored(page);
    expect(saved).not.toHaveProperty('data');
    expect(JSON.stringify(saved)).not.toContain('"tasks"');
    expect(saved.prefs).toEqual(expect.objectContaining({ theme: expect.any(String), home: expect.any(String) }));
    expect(saved).toHaveProperty('views');
    expect(saved.member).toBe('m3'); // John Carter, the Member signed in
    expect(saved.collapsed).toBe(true);
    expect(await page.evaluate(() => localStorage.getItem('gr8r.v2'))).toBeNull();
  });

  test('saved Workspace data in the browser is ignored, while the rest of what was saved is used', async ({ page }) => {
    // A save of the demo's shape, without tasks: backend mode still reads it, but not its data
    await seedStorage(page, { data: { ws: { name: 'Local Only Workspace' } }, member: 'm3', prefs: {}, views: {}, collapsed: true });
    await page.goto('./');
    await expect(page.locator('.shell.collapsed')).toBeVisible();
    await expect(page.getByText('Gr8r Studio').first()).toBeAttached();
    await expect(page.getByText('Local Only Workspace')).toHaveCount(0);

    // The next save drops the data
    await page.getByRole('button', { name: 'Expand sidebar' }).click();
    await expect(page.locator('.shell.collapsed')).toHaveCount(0);
    await expect.poll(async () => (await stored(page))?.collapsed).toBe(false);
    expect(await stored(page)).not.toHaveProperty('data');
  });

  test("a Member's prefs stay theirs after a reload", async ({ page }) => {
    await page.goto('./');
    await expect(page.locator('.shell')).toBeVisible();
    const setTheme = async v => {
      await page.getByLabel('Account menu').click();
      const synced = page.waitForResponse(r => r.url().endsWith('/api/sync') && r.ok());
      await page.locator(`.pop [data-a="setTheme"][data-v="${v}"]`).click();
      await synced;
      await expect.poll(async () => (await stored(page))?.prefs.theme).toBe(v);
    };

    await setTheme('dark');
    await page.reload();
    await expect(page.locator('.shell')).toBeVisible();
    await themeIs(page, 'dark');
    const saved = await stored(page);
    expect(saved.member).toBe('m3');
    expect(saved.prefs.theme).toBe('dark');

    await setTheme('system'); // as seeded
  });
});

// Signed out, the sign-in page names the Workspace of the placeholder data, not of a save in this browser.
test('the sign-in page ignores Workspace data saved in the browser', async ({ page }) => {
  await seedStorage(page, { data: { tasks: [], ws: { name: 'Local Only Workspace' } }, prefs: {}, views: {} });
  await page.goto('./');
  await expect(page.locator('.auth-card .sub')).toHaveText('Sign in to Gr8r Studio');
  await expect(page.getByText('Local Only Workspace')).toHaveCount(0);
});

/* A new Member has no prefs on the server yet: the browser's saved prefs are used only when they were saved for that Member. */
test.describe('a new Member without prefs on the server', () => {
  for (const [owner, theme] of [
    ['the same Member', 'dark'],
    ['another Member', 'system'],
  ])
    test(`takes the prefs saved in this browser for ${owner}: theme ${theme}`, async ({ page }) => {
      await freshUser(page.request, 'prefs');
      const { member } = await (await page.request.get(API + 'me')).json();
      await seedStorage(page, { member: owner === 'the same Member' ? member : 'm-someone-else', prefs: { theme: 'dark' }, views: {} });
      await page.goto('./');
      await expect(page.locator('.shell')).toBeVisible();
      await themeIs(page, theme);
      // The next save keeps whose the prefs are now
      await page.getByRole('button', { name: 'Collapse sidebar' }).click();
      await expect.poll(async () => (await stored(page))?.member).toBe(member);
      expect((await stored(page)).prefs.theme).toBe(theme);
    });
});
