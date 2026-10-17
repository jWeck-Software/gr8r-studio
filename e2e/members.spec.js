/* Members as the Owner sees them in backend mode (actions.js resendInvite, removeMember; modals.js inviteModal). */
import { test, expect } from '@playwright/test';
import { authFile, mailCount } from './support.js';

test.use({ storageState: authFile('owner') });

const memberOptions = (page, id) => page.locator(`tr[data-ctx="member"][data-id="${id}"] [aria-label="Member options"]`);

test('the invite dialog has no Workspace-wide invite link', async ({ page }) => {
  await page.goto('./#members');
  await page.getByRole('button', { name: 'Invite member' }).click();
  await expect(page.locator('#inv-emails')).toBeVisible();
  await expect(page.locator('#inv-submit')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Copy invite link' })).toHaveCount(0);
  await expect(page.locator('.modal [data-a="copyInviteLink"]')).toHaveCount(0);
});

test('resending an Invite mails a new join link and says so', async ({ page }) => {
  const diego = 'diego@freelance.io'; // the demo's invited Guest (m8)
  const before = mailCount(diego, 'join');
  await page.goto('./#members');
  await memberOptions(page, 'm8').click();
  await page.locator('.pop [data-a="resendInvite"]').click();
  await expect(page.locator('.toast').filter({ hasText: `Invite resent to ${diego}` })).toBeVisible();
  await expect.poll(() => mailCount(diego, 'join')).toBe(before + 1);
});

test('removing a Member has no Undo and lasts after a reload', async ({ page }) => {
  await page.goto('./#members');
  const marcus = page.locator('tr[data-ctx="member"][data-id="m6"]');
  await expect(marcus).toContainText('Marcus Lee');
  await memberOptions(page, 'm6').click();
  await page.locator('.pop [data-a="removeMember"]').click();
  await expect(page.locator('.modal')).toContainText('Remove Marcus Lee?');
  const synced = page.waitForResponse(r => r.url().endsWith('/api/sync') && r.ok());
  await page.getByRole('button', { name: 'Remove member' }).click();
  const toast = page.locator('.toast').filter({ hasText: 'Removed Marcus Lee' });
  await expect(toast).toBeVisible();
  await expect(toast.locator('.ta')).toHaveCount(0);
  await expect(toast.getByRole('button', { name: 'Undo' })).toHaveCount(0);
  await synced;

  await page.reload();
  await expect(page.locator('tr[data-ctx="member"]').first()).toBeVisible();
  await expect(marcus).toHaveCount(0);
  await expect(page.getByText('Marcus Lee')).toHaveCount(0);
});
