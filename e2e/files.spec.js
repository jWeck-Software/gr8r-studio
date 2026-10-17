/* A Project's Files in backend mode (files.js dropzone; modals.js filePreviewModal; actions.js delFile).
   Each test uploads its own image, so the demo Files stay as they are. */
import { test, expect } from '@playwright/test';
import { PNG, authFile } from './support.js';

test.use({ storageState: authFile('owner') });

async function openFiles(page) {
  await page.goto('./');
  await page.locator('.sitem[data-r="project"][data-id="p1"][data-tab="files"]').click();
  await expect(page.locator('.dropzone')).toBeVisible();
}

/* Uploads a PNG of that name and resolves with its card. */
async function upload(page, name) {
  const stored = page.waitForResponse(r => r.url().endsWith('/api/files') && r.request().method() === 'POST');
  await page.locator('input[data-in="uploadFiles"]').first().setInputFiles({ name, mimeType: 'image/png', buffer: PNG });
  expect((await stored).ok()).toBe(true);
  await expect(page.locator('.toast').filter({ hasText: `Uploaded ${name}` })).toBeVisible();
  const card = page.locator('.fcard').filter({ hasText: name });
  await expect(card).toBeVisible();
  return card;
}

test('the dropzone says files of up to 25 MB can be uploaded', async ({ page }) => {
  await openFiles(page);
  await expect(page.locator('.dropzone')).toContainText('up to 25 MB each');
});

test('previewing an uploaded image shows the image itself and a working Download link', async ({ page }) => {
  await openFiles(page);
  const name = `e2e-preview-${Date.now()}.png`;
  await (await upload(page, name)).click();

  const modal = page.locator('.modal');
  await expect(modal.getByRole('heading', { name })).toBeVisible();
  const img = modal.locator('img.fimg');
  await expect(img).toBeVisible();
  await expect(img).toHaveAttribute('src', /^api\/files\/[^/]+\/content$/);
  await expect.poll(() => img.evaluate(el => el.complete && el.naturalWidth)).toBe(1);

  const download = modal.getByRole('link', { name: 'Download' });
  await expect(download).toHaveAttribute('download', name);
  const href = await download.getAttribute('href');
  expect(href).toBe(await img.getAttribute('src'));
  const res = await page.request.get(new URL(href, page.url()).href);
  expect(res.status()).toBe(200);
  expect(Buffer.compare(await res.body(), PNG)).toBe(0);
});

test("deleting a File warns that it can't be undone and offers no Undo", async ({ page }) => {
  await openFiles(page);
  const name = `e2e-delete-${Date.now()}.png`;
  const card = await upload(page, name);
  await card.locator('[aria-label="File options"]').click();
  await page.locator('.pop [data-a="delFile"]').click();

  const modal = page.locator('.modal');
  await expect(modal).toContainText(`${name} will be removed from the project. It's deleted for everyone and can't be undone.`);
  const synced = page.waitForResponse(r => r.url().endsWith('/api/sync') && r.ok());
  await modal.getByRole('button', { name: 'Delete file' }).click();
  const toast = page.locator('.toast').filter({ hasText: `Deleted ${name}` });
  await expect(toast).toBeVisible();
  await expect(toast.locator('.ta')).toHaveCount(0);
  await expect(toast.getByRole('button', { name: 'Undo' })).toHaveCount(0);
  await synced;

  await page.reload();
  await page.locator('.sitem[data-r="project"][data-id="p1"][data-tab="files"]').click();
  await expect(page.locator('.fcard').first()).toBeVisible();
  await expect(page.locator('.fcard').filter({ hasText: name })).toHaveCount(0);
});
