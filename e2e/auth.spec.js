/* Signing up, verifying, signing in and resetting a password, the way the backend-mode app does them
   (actions.js doSignup, resend, verified, doLogin, doForgot, doReset; auth.js forgot-sent and reset). */
import { test, expect } from '@playwright/test';
import { API, FRESH_PASSWORD, freshEmail, freshUser, mailCount, mailedLink } from './support.js';

const signIn = async (page, email, password = FRESH_PASSWORD) => {
  await page.locator('#l-email').fill(email);
  await page.locator('#l-pw').fill(password);
  await page.locator('#login-btn').click();
};

test('signing up shows the verify screen and mails a verification link', async ({ page }) => {
  const email = freshEmail('signup');
  const after = Date.now() - 2000;
  await page.goto('./#signup');
  await page.locator('#s-name').fill('E2E Signup');
  await page.locator('#s-email').fill(email);
  await page.locator('#s-pw').fill(FRESH_PASSWORD);
  await page.locator('#signup-btn').click();
  await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible();
  await expect(page.locator('.auth-card .sub')).toContainText(email);
  expect(await mailedLink(email, 'verify', { after })).toContain('#verify/');
});

test('the verify screen moves on to onboarding once the link was opened in this browser', async ({ page, context }) => {
  const email = freshEmail('verified');
  const after = Date.now() - 2000;
  await page.goto('./#signup');
  await page.locator('#s-name').fill('E2E Verified');
  await page.locator('#s-email').fill(email);
  await page.locator('#s-pw').fill(FRESH_PASSWORD);
  await page.locator('#signup-btn').click();
  await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible();

  // Not verified yet: the button says to open the link
  await page.getByRole('button', { name: "I've verified my email" }).click();
  await expect(page.locator('.toast').filter({ hasText: 'Open the link in the email we sent you to verify your address.' })).toBeVisible();

  // The link, opened in another tab of the same browser, verifies and signs in
  const link = await mailedLink(email, 'verify', { after });
  const tab = await context.newPage();
  await tab.goto(link);
  await expect(tab.getByRole('heading', { name: 'What are you working on?' })).toBeVisible();
  await tab.close();

  await page.getByRole('button', { name: "I've verified my email" }).click();
  await expect(page.getByRole('heading', { name: 'What are you working on?' })).toBeVisible();
});

test('an unverified account signing in gets the verify screen, which mails a new link', async ({ page }) => {
  const email = freshEmail('unverified');
  const res = await page.request.post(API + 'signup', { data: { name: 'E2E Unverified', email, password: FRESH_PASSWORD } });
  expect(res.ok()).toBe(true);
  await expect.poll(() => mailCount(email, 'verify')).toBe(1);

  await page.goto('./');
  await signIn(page, email);
  await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible();
  await expect(page.locator('.auth-card .alert[role="alert"]')).toBeVisible();

  await page.locator('#resend-btn').click();
  await expect(page.locator('.toast').filter({ hasText: `Verification email sent to ${email}` })).toBeVisible();
  await expect.poll(() => mailCount(email, 'verify')).toBe(2);
});

test('a wrong password is shown as an error on the sign-in page', async ({ page }) => {
  const { email } = await freshUser(page.request, 'wrongpw');
  await page.request.post(API + 'logout');
  await page.goto('./');
  await signIn(page, email, 'Not-the-password-1');
  await expect(page.getByRole('alert')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
});

test('signing in as a Member opens their Workspace', async ({ page }) => {
  const { email, workspace } = await freshUser(page.request, 'login');
  await page.request.post(API + 'logout');
  await page.goto('./');
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await signIn(page, email);
  await expect(page.locator('.toast').filter({ hasText: `Signed in as ${email}` })).toBeVisible();
  await expect(page.locator('.shell')).toBeVisible();
  await expect(page.getByText(workspace).first()).toBeVisible();
});

test('signing in without a Workspace opens onboarding', async ({ page }) => {
  const { email } = await freshUser(page.request, 'noworkspace', { workspace: false });
  await page.request.post(API + 'logout');
  await page.goto('./');
  await signIn(page, email);
  await expect(page.getByRole('heading', { name: 'What are you working on?' })).toBeVisible();
  await expect(page.getByText('Step 1 of 6')).toBeVisible();
});

test('asking for a reset link says it was sent if there is an account, and can send it again', async ({ page }) => {
  const { email } = await freshUser(page.request, 'forgot');
  await page.request.post(API + 'logout');
  await page.goto('./');
  await page.getByRole('button', { name: 'Forgot password?' }).click();
  await page.locator('#f-email').fill(email);
  await page.locator('#forgot-btn').click();

  await expect(page.getByRole('heading', { name: 'Check your email' })).toBeVisible();
  await expect(page.locator('.auth-card .sub')).toHaveText(`If there is an account for ${email}, we sent a reset link to it. It expires in 30 minutes.`);
  await expect(page.getByRole('button', { name: 'Open reset link' })).toHaveCount(0);
  await expect(page.getByText('In this prototype')).toHaveCount(0);
  await expect.poll(() => mailCount(email, 'reset')).toBe(1);

  await page.getByRole('button', { name: 'Resend email' }).click();
  await expect(page.locator('.toast').filter({ hasText: `Reset link sent to ${email}` })).toBeVisible();
  await expect.poll(() => mailCount(email, 'reset')).toBe(2);
});

test('the reset link opens the new-password page, which signs in', async ({ page }) => {
  const { email, workspace } = await freshUser(page.request, 'reset');
  await page.request.post(API + 'logout');
  const after = Date.now() - 2000;
  expect((await page.request.post(API + 'password/forgot', { data: { email } })).ok()).toBe(true);
  const link = await mailedLink(email, 'reset', { after });

  await page.goto(link);
  await expect(page.getByRole('heading', { name: 'Choose a new password' })).toBeVisible();
  await expect(page.locator('.auth-card .sub')).toHaveCount(0);
  await expect(page.getByText(`for ${email}`)).toHaveCount(0);
  await expect(page.locator('.auth-card .auth-foot')).toHaveText('Request a new link · Back to sign in');

  const next = 'Brand-new-pass-77';
  await page.locator('#r-pw').fill(next);
  await page.locator('#r-pw2').fill(next);
  await page.locator('#reset-btn').click();
  await expect(page.locator('.toast').filter({ hasText: 'Password updated. You are signed in.' })).toBeVisible();
  await expect(page.locator('.shell')).toBeVisible();
  await expect(page.getByText(workspace).first()).toBeVisible();
  expect((await page.request.get(API + 'bootstrap')).status()).toBe(200);
});

test('the new-password page opened without a reset link asks for the link', async ({ page }) => {
  await page.goto('./#reset');
  await expect(page.getByRole('heading', { name: 'Choose a new password' })).toBeVisible();
  await page.locator('#r-pw').fill('Brand-new-pass-77');
  await page.locator('#r-pw2').fill('Brand-new-pass-77');
  await page.locator('#reset-btn').click();
  await expect(page.getByRole('alert')).toHaveText('Open the link in the password reset email to choose a new password.');
});
