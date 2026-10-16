import AxeBuilder from '@axe-core/playwright'
import { expect, peran, test, tungguHalaman, butuhEnvUat } from './support/uat.js'

butuhEnvUat()

test('@a11y halaman admin SOP bebas dari violation critical/serious', async ({ page, masuk }) => {
  await masuk(peran.admin)
  await page.goto('/sop/admin/dokumen')
  await tungguHalaman(page)

  await expect(page.locator('#app')).not.toBeEmpty()

  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa'])
    .analyze()

  const blocking = results.violations.filter((violation) =>
    ['critical', 'serious'].includes(violation.impact),
  )

  if (blocking.length) {
    await test.info().attach('accessibility-violations.json', {
      body: JSON.stringify({
        url: page.url(),
        blocking: blocking.map(({ id, impact, help, helpUrl, nodes }) => ({
          id,
          impact,
          help,
          helpUrl,
          nodes: nodes.map(({ html, target }) => ({ html, target })),
        })),
        allViolations: results.violations.length,
      }, null, 2),
      contentType: 'application/json',
    })
  }

  const summary = blocking.map((violation) =>
    `${violation.id} [${violation.impact}] (${violation.nodes.length} node)`,
  ).join('\n')

  expect(blocking, summary || undefined).toEqual([])
})
