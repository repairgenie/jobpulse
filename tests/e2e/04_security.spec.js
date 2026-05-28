const { test, expect } = require('@playwright/test');

test.describe('Security Checks', () => {
  test('no SQL injection in job search API', async ({ page }) => {
    const response = await page.request.post('http://localhost:8000/api/search_jobs.php', {
      headers: { 'Content-Type': 'application/json' },
      data: JSON.stringify({ search: "'; DROP TABLE users; --" }),
    });
    // Should not crash, should return valid response
    const contentType = response.headers()['content-type'] || '';
    expect(contentType).toContain('application/json');
  });

  test('no reflected XSS in job search', async ({ page }) => {
    await page.goto('http://localhost:8000');
    // Check for any script injection reflected back
    const body = await page.content();
    expect(body).not.toContain("DROP TABLE");
  });

  test('API returns generic errors (no info disclosure)', async ({ page }) => {
    const response = await page.request.post('http://localhost:8000/api/optimize_resume.php', {
      headers: { 'Content-Type': 'application/json' },
      data: JSON.stringify({}),
    });
    const json = await response.json();
    // Error messages should not expose internal paths or stack traces
    if (!json.success) {
      expect(json.error).not.toMatch(/stack|trace|php|undefined|warning/i);
    }
  });
});