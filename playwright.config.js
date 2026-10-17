import { defineConfig, devices } from '@playwright/test';

/* Browser tests of the backend-mode app (npm run test:e2e), the way a user drives it: Apache serves
   the build in e2e/www/ and its own front controller, which runs the app on the tests' database from
   .env.e2e.local (never the developer's). No server is started here; Apache is always running.
   One worker: every test shares that database, the mail log and the rate limits. */
export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.js',
  workers: 1,
  fullyParallel: false,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://localhost/repos/gr8r-studio/e2e/www/',
    trace: 'retain-on-failure',
    ...devices['Desktop Chrome'],
  },
});
