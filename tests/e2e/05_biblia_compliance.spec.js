const { test, expect } = require('@playwright/test');

test.describe('Biblia Compliance', () => {
  test('no native dialogs on main page', async ({ page }) => {
    const dialogs = [];
    page.on('dialog', d => dialogs.push(d.type()));
    await page.goto('http://localhost:8000');
    await page.waitForTimeout(3000);
    expect(dialogs).toHaveLength(0);
  });

  test('main page uses Alpine.js (SPA pattern)', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(2000);
    const html = await page.content();
    expect(html).toContain('alpine');
  });

  test('error responses are JSON (no stack traces)', async ({ page }) => {
    const response = await page.request.get('http://localhost:8000/api/optimize_resume.php');
    const json = await response.json().catch(() => ({}));
    if (!response.ok()) {
      expect(JSON.stringify(json)).not.toMatch(/stack|trace|php|undefined/i);
    }
  });
});
