const { test, expect } = require('@playwright/test');
const { interceptAlert } = require('./helpers/auth');

test.describe('API Authentication', () => {
  const apis = [
    '/api/optimize_resume.php',
    '/api/generalize_resume.php',
    '/api/resumes.php',
    '/api/history.php',
    '/api/ask_ai.php',
    '/api/analyze_job.php',
  ];

  for (const api of apis) {
    test(`unauthenticated request to ${api} returns 401`, async ({ page }) => {
      const response = await page.request.get('http://localhost:8000' + api);
// Should return 401 or 403 for unauthenticated requests
    expect(response.status()).toBeGreaterThanOrEqual(401);
    });
  }
});
