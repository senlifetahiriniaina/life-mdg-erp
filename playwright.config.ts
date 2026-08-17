import { defineConfig, devices } from '@playwright/test'

const BASE_URL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000'

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  globalSetup: './tests/e2e/fixtures/global-setup.ts',
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    storageState: './tests/e2e/.auth/admin.json',
    // Some sandboxed dev containers pre-ship a pinned Chromium at
    // $PLAYWRIGHT_BROWSERS_PATH/chromium rather than the revision the
    // installed @playwright/test version expects — point at it there instead
    // of `playwright install`-ing a second copy. CI/local dev (no override)
    // use Playwright's normal browser resolution.
    ...(process.env.PLAYWRIGHT_BROWSERS_PATH
      ? { launchOptions: { executablePath: `${process.env.PLAYWRIGHT_BROWSERS_PATH}/chromium` } }
      : {}),
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    // .env.testing sets SESSION_DRIVER=array (fine for Pest's single-process
    // in-memory runs) but that's fundamentally incompatible with a real
    // browser hitting `php artisan serve`, which forks a fresh PHP process
    // per request: the CSRF token issued on GET /login never survives to the
    // POST, so every real login 419s. Override to `file` for E2E only —
    // Pest's own test env is untouched.
    command: 'bash -c "APP_ENV=testing php artisan migrate:fresh --seed --force && SESSION_DRIVER=file APP_ENV=testing php artisan serve --port=8000"',
    url: BASE_URL,
    reuseExistingServer: !process.env.CI,
    timeout: 180_000,
  },
})
