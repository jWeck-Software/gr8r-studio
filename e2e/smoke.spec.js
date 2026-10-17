import { test, expect } from '@playwright/test';
import { DEMO_PASSWORD, authFile, freshEmail } from './support.js';

test.describe('signed in as the Owner', () => {
  test.use({ storageState: authFile('owner') });

  test('the app opens the demo Workspace from the server', async ({ page }) => {
    const bootstrap = page.waitForResponse(r => r.url().endsWith('/api/bootstrap'));
    await page.goto('./');
    expect((await bootstrap).status()).toBe(200);
    await expect(page.getByText('Gr8r Studio').first()).toBeVisible();
  });
});

// A fresh address, so the demo accounts' sign-in limit (5 a minute) stays untouched.
test('an unknown address is refused on the sign-in page', async ({ page }) => {
  await page.goto('./');
  await page.locator('#l-email').fill(freshEmail('unknown'));
  await page.locator('#l-pw').fill(DEMO_PASSWORD);
  await page.locator('#l-pw').press('Enter');
  await expect(page.getByRole('alert')).toBeVisible();
});
