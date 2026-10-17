/* What a signed-in User does for themselves: change their password, sign out, delete their Workspace
   (actions.js savePassword, signOut, delWorkspace; settings.js password). Each test has its own account,
   so the demo sessions stay valid. */
import { test, expect } from '@playwright/test';
import { API, FRESH_PASSWORD, freshUser } from './support.js';

test('changing the password checks the current one and says the other sessions were signed out', async ({ page, playwright }) => {
  const { email } = await freshUser(page.request, 'password');
  // A second session of the same User, elsewhere
  const other = await playwright.request.newContext();
  expect((await other.post(API + 'login', { data: { email, password: FRESH_PASSWORD } })).ok()).toBe(true);

  await page.goto('./#settings');
  await page.locator('.sitem[data-sec="password"]').click();
  const next = 'Changed-pass-2026!';

  await page.locator('#pw-cur').fill('Not-the-password-1');
  await page.locator('#pw-new').fill(next);
  await page.locator('#pw-conf').fill(next);
  await page.locator('#pw-save').click();
  await expect(page.locator('#pw-cur')).toHaveClass(/is-error/);
  await expect(page.locator('#pw-cur ~ .err')).toBeVisible();
  await expect(page.locator('.alert.ok')).toHaveCount(0);

  await page.locator('#pw-cur').fill(FRESH_PASSWORD);
  await page.locator('#pw-new').fill(next);
  await page.locator('#pw-conf').fill(next);
  await page.locator('#pw-save').click();
  await expect(page.locator('.alert.ok')).toHaveText('Password updated. Your other sessions were signed out.');
  await expect(page.locator('.toast').filter({ hasText: 'Password updated' })).toBeVisible();

  // This session goes on, the other one ended
  expect((await page.request.get(API + 'bootstrap')).status()).toBe(200);
  expect((await other.get(API + 'bootstrap')).status()).toBe(401);
  await other.dispose();
});

test('signing out ends the session on the server', async ({ page }) => {
  await freshUser(page.request, 'signout');
  await page.goto('./');
  await expect(page.locator('.shell')).toBeVisible();

  await page.getByLabel('Account menu').click();
  const logout = page.waitForResponse(r => r.url().endsWith('/api/logout'));
  await page.locator('.pop [data-a="signOut"]').click();
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await logout;

  expect((await page.request.get(API + 'bootstrap')).status()).toBe(401);
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
});

test('deleting the Workspace leaves its Owner without one, so signing in again opens onboarding', async ({ page }) => {
  const { email, workspace } = await freshUser(page.request, 'delws');
  await page.goto('./#settings');
  await page.locator('.sitem[data-sec="workspace"]').click();
  await page.locator('[data-a="delWorkspace"]').click();
  await page.locator('#confirm-in').fill(workspace);
  const deleted = page.waitForResponse(r => r.url().endsWith('/api/workspace') && r.request().method() === 'DELETE');
  await page.getByRole('button', { name: 'Delete workspace' }).last().click();
  expect((await deleted).status()).toBeLessThan(300);
  await expect(page.locator('.toast').filter({ hasText: 'Workspace deleted' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'What are you working on?' })).toBeVisible();

  const me = await page.request.get(API + 'me');
  expect(me.status()).not.toBe(200);
  expect((await me.json()).error).toBe('no_workspace');

  await page.request.post(API + 'logout');
  await page.goto('./');
  await page.locator('#l-email').fill(email);
  await page.locator('#l-pw').fill(FRESH_PASSWORD);
  await page.locator('#login-btn').click();
  await expect(page.getByRole('heading', { name: 'What are you working on?' })).toBeVisible();
});
