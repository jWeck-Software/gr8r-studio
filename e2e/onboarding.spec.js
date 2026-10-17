/* Onboarding of a verified User without a Workspace (actions.js onbExit, onbFinish): leaving it signs
   out, finishing it starts the Workspace on the server. */
import { test, expect } from '@playwright/test';
import { API, freshUser } from './support.js';

const step = n => `Step ${n} of 6`;

/* From step 1 to the last one, with the Workspace's name and URL and no invites. */
async function walkOnboarding(page, name, url) {
  await expect(page.getByText(step(1))).toBeVisible();
  await page.locator('#onb-next').click();
  await page.locator('#o-ws').fill(name);
  await page.locator('#o-url').fill(url);
  await page.locator('#onb-next').click();
  await expect(page.getByText(step(3))).toBeVisible();
  await page.locator('#onb-next').click();
  await expect(page.getByText(step(4))).toBeVisible();
  await page.locator('#onb-next').click();
  await expect(page.getByText(step(5))).toBeVisible();
  await page.getByRole('button', { name: 'Skip for now' }).click();
  await expect(page.getByRole('heading', { name: 'Your workspace is ready' })).toBeVisible();
}

test('exiting setup signs out and shows the sign-in page', async ({ page }) => {
  await freshUser(page.request, 'onbexit', { workspace: false });
  await page.goto('./');
  await expect(page.getByText(step(1))).toBeVisible();
  await page.getByRole('button', { name: 'Exit setup' }).click();
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  expect((await page.request.get(API + 'me')).status()).toBe(401);
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
});

test('finishing onboarding starts the Workspace on the server', async ({ page }) => {
  await freshUser(page.request, 'onbfinish', { workspace: false });
  const name = `E2E Finished ${Date.now() % 1e6}`;
  const url = `e2e-finished-${Date.now()}`;
  await page.goto('./');
  await walkOnboarding(page, name, url);
  const started = page.waitForResponse(r => r.url().endsWith('/api/workspaces'));
  await page.locator('#onb-finish').click();
  expect((await started).ok()).toBe(true);
  await expect(page.locator('.shell')).toBeVisible();
  await expect(page.getByText(name).first()).toBeVisible();

  expect((await page.request.get(API + 'me')).status()).toBe(200);
  await page.reload();
  await expect(page.locator('.shell')).toBeVisible();
  await expect(page.getByText(name).first()).toBeVisible();
});

test('finishing onboarding with a URL that is taken goes back to the Workspace step', async ({ page }) => {
  await freshUser(page.request, 'onbtaken', { workspace: false });
  await page.goto('./');
  await walkOnboarding(page, 'E2E Taken', 'gr8rstudio'); // the demo Workspace's URL
  const started = page.waitForResponse(r => r.url().endsWith('/api/workspaces'));
  await page.locator('#onb-finish').click();
  expect((await started).status()).toBe(422);
  await expect(page.getByText(step(2))).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Create your workspace' })).toBeVisible();
  await expect(page.locator('#o-url').locator('xpath=../..').locator('.err')).toContainText('URL');
  expect((await (await page.request.get(API + 'me')).json()).error).toBe('no_workspace');
});
