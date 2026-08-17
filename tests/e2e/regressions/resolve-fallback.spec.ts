import { test, expect } from '@playwright/test'

/**
 * Chantier 6 Phase 0 fixed a bug in resources/js/app.js's Inertia resolve():
 * it had no fallback for the `Pages/Foo/Foo.vue` naming pattern, so any page
 * whose real component lived at that nested path 404'd. These 5 pages were
 * the ones directly affected (confirmed live during Phase 0/1 investigation).
 * This is the one test class that actually exercises resolve() end-to-end —
 * Pest/Vitest can't, since neither runs through the real Vite module graph.
 */
test.describe('resolve() fallback regression', () => {
  const assertNotNotFound = async (page: import('@playwright/test').Page) => {
    await expect(page.locator('body')).not.toContainText('Page not found')
    await expect(page.locator('body')).not.toContainText('404')
  }

  test('/bi loads', async ({ page }) => {
    await page.goto('/bi')
    await assertNotNotFound(page)
  })

  test('/bi/sql-editor loads', async ({ page }) => {
    await page.goto('/bi/sql-editor')
    await assertNotNotFound(page)
  })

  test('/projects loads, and the first project detail page loads if one exists', async ({ page }) => {
    await page.goto('/projects')
    await assertNotNotFound(page)

    const firstRow = page.locator('a[href^="/projects/"]').first()
    if (await firstRow.count()) {
      await firstRow.click()
      await assertNotNotFound(page)
    }
  })

  test('/helpdesk/tickets loads, and the first ticket detail page loads if one exists', async ({ page }) => {
    await page.goto('/helpdesk/tickets')
    await assertNotNotFound(page)

    const firstRow = page.locator('tr.wh-dt-row').first()
    if (await firstRow.count()) {
      await firstRow.click()
      await page.waitForURL(/\/helpdesk\/tickets\/\d+/)
      await assertNotNotFound(page)
    }
  })
})
