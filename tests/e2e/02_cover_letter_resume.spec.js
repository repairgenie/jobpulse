const { test, expect } = require('@playwright/test');
const { loginAsDemoUser, interceptAlert } = require('./helpers/auth');

test.describe('Cover Letter & Resume Generation (Issue #54)', () => {
  test('optimize_resume API endpoint responds', async ({ page }) => {
    await loginAsDemoUser(page);
    
    const response = await page.request.post('http://localhost:8000/api/optimize_resume.php', {
      headers: { 'Content-Type': 'application/json' },
      data: JSON.stringify({ job_description: 'Software Engineer\nPHP, JavaScript, MySQL\nRemote', resume_id: null }),
    });
    
    // Should get JSON response (401 if not logged in, 400 if missing data, or 200 with result)
    const contentType = response.headers()['content-type'] || '';
    expect(contentType).toContain('application/json');
  });

  test('generalize_resume API endpoint responds', async ({ page }) => {
    await loginAsDemoUser(page);
    
    const response = await page.request.post('http://localhost:8000/api/generalize_resume.php', {
      headers: { 'Content-Type': 'application/json' },
      data: JSON.stringify({ resume_id: 'test', direction: 'backend developer', label: 'Test' }),
    });
    
    const contentType = response.headers()['content-type'] || '';
    expect(contentType).toContain('application/json');
  });

  test('resume upload and management UI loads', async ({ page }) => {
    await loginAsDemoUser(page);
    await page.goto('http://localhost:8000');
    await page.waitForTimeout(2000);
    
    // Check for resume-related UI elements
    const pageContent = await page.content();
    expect(pageContent).toContain('resume');
  });
});
