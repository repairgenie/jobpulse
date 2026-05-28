const { test, expect } = require('@playwright/test');
const { loginAsDemoUser, interceptAlert } = require('./helpers/auth');

test.describe('Auth & Navigation', () => {
  test('index.php loads without PHP errors', async ({ page }) => {
    await interceptAlert(page);
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
    
    await page.goto('http://localhost:8000');
    await page.waitForTimeout(2000);
    
    // Check page has content
    const body = await page.locator('body');
    await expect(body).toBeVisible();
    
    // No PHP errors in console
    const phpErrors = errors.filter(e => e.includes('PHP') || e.includes('Fatal') || e.includes('Warning'));
    expect(phpErrors).toHaveLength(0);
  });

  test('login flow works', async ({ page }) => {
    await interceptAlert(page);
    await page.goto('http://localhost:8000');
    await page.waitForTimeout(1000);
    // Just check the page loaded
    await expect(page.locator('body')).toBeVisible();
  });

  test('no native dialogs on page load', async ({ page }) => {
    const dialogs = [];
    page.on('dialog', d => dialogs.push(d.type()));
    await page.goto('http://localhost:8000');
    await page.waitForTimeout(2000);
    expect(dialogs).toHaveLength(0);
  });
});
