import { chromium, type FullConfig } from '@playwright/test'
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

/**
 * Runs once, after the webServer (which already ran migrate:fresh --seed
 * against the .env.testing sqlite DB) is confirmed up. Logs in through the
 * real /login form as the seeded super-admin (DatabaseSeeder's
 * admin@widehalo.com) and saves the resulting session storageState so
 * individual specs don't each have to re-authenticate.
 */
export default async function globalSetup(config: FullConfig) {
  const baseURL = config.projects[0].use.baseURL ?? 'http://127.0.0.1:8000'
  const authDir = path.resolve(__dirname, '../.auth')
  fs.mkdirSync(authDir, { recursive: true })

  const browser = await chromium.launch(
    process.env.PLAYWRIGHT_BROWSERS_PATH
      ? { executablePath: `${process.env.PLAYWRIGHT_BROWSERS_PATH}/chromium` }
      : {}
  )
  const page = await browser.newPage({ baseURL })

  await page.goto('/login')
  await page.getByLabel('Adresse email').fill('admin@widehalo.com')
  await page.getByLabel('Mot de passe').fill('Admin#Wh2025!')
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await page.waitForURL(/\/dashboard/, { timeout: 30_000 })

  await page.context().storageState({ path: path.join(authDir, 'admin.json') })
  await browser.close()
}
