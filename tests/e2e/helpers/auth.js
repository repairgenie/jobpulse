const { test, expect } = require('@playwright/test');

async function loginAsDemoUser(page) {
  // JobPulse demo user flow — fill login form if present
  await page.goto(process.env.TEST_BASE_URL || 'http://localhost:8000');
  // If login form visible, try demo credentials
  const emailInput = page.locator('input[type="email"], input[name="email"]');
  if (await emailInput.isVisible({ timeout: 3000 })) {
    await emailInput.fill('demo@jobpulse.local');
    const passInput = page.locator('input[type="password"], input[name="password"]');
    await passInput.fill('demo123');
    const submitBtn = page.locator('button[type="submit"]').first();
    await submitBtn.click();
    await page.waitForTimeout(2000);
  }
}

async function interceptAlert(page) {
  page.on('dialog', async dialog => {
    console.log('Native dialog intercepted:', dialog.message());
    await dialog.dismiss();
  });
}

module.exports = { loginAsDemoUser, interceptAlert };
