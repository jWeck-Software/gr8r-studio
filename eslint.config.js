import js from '@eslint/js';
import globals from 'globals';

export default [
  { ignores: ['dist/', 'public/', 'e2e/www/', 'test-results/', 'playwright-report/', 'vendor/', 'var/', 'bin/', 'config/', 'migrations/', 'src/', 'tests/'] },
  js.configs.recommended,
  {
    files: ['**/*.js'],
    languageOptions: { ecmaVersion: 'latest', sourceType: 'module', globals: { ...globals.browser } },
  },
  {
    files: ['vite.config.js', 'eslint.config.js', 'playwright.config.js', '**/*.test.js', 'e2e/**/*.js'],
    languageOptions: { globals: { ...globals.node } },
  },
];
