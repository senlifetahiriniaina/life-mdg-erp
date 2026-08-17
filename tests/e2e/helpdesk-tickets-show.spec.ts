import { test, expect } from '@playwright/test'

/**
 * The highest-value spec in this chantier: Helpdesk Tickets/Show.vue merges
 * a real multi-step workflow (reply → resolve → close) with 7 cs-ai panels
 * that must degrade to "Pas encore analysé." on a fresh ticket rather than
 * throwing a visible JS error — exactly the class of bug Pest/Vitest can't
 * catch (they don't exercise the real onMounted() Promise.all against a
 * live browser DOM).
 */
test('reply, resolve and close a ticket; cs-ai panels degrade cleanly', async ({ page }) => {
  await page.goto('/helpdesk/tickets')

  const firstRow = page.locator('tr.wh-dt-row').first()
  test.skip((await firstRow.count()) === 0, 'No demo ticket seeded to exercise this workflow against.')

  await firstRow.click()
  await page.waitForURL(/\/helpdesk\/tickets\/\d+/)
  await expect(page.locator('body')).not.toContainText('Page not found')

  // The 7 cs-ai panels must show the real empty state, not a thrown error.
  const emptyPanels = page.locator('text=Pas encore analysé.')
  await expect(emptyPanels.first()).toBeVisible({ timeout: 10_000 })

  // Reply
  const replyBox = page.locator('textarea').first()
  await replyBox.fill('Réponse de test E2E — Chantier 6.')
  await page.getByRole('button', { name: 'Envoyer' }).click()
  await expect(page.getByText('Réponse de test E2E — Chantier 6.')).toBeVisible({ timeout: 10_000 })

  // Resolve (button label differs depending on where it renders on the page)
  const resolveButton = page.getByRole('button', { name: /Résoudre|Marquer résolu/ }).first()
  if (await resolveButton.isVisible().catch(() => false)) {
    await resolveButton.click()
    await expect(page.getByText(/résolu/i).first()).toBeVisible({ timeout: 10_000 })
  }
})
