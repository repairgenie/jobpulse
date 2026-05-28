# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests/e2e/02_cover_letter_resume.spec.js >> Cover Letter & Resume Generation (Issue #54) >> optimize_resume API endpoint responds
- Location: tests/e2e/02_cover_letter_resume.spec.js:5:3

# Error details

```
Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
Call log:
  - navigating to "/", waiting until "load"

```

# Test source

```ts
  1  | const { test, expect } = require('@playwright/test');
  2  | 
  3  | async function loginAsDemoUser(page) {
  4  |   // JobPulse demo user flow — fill login form if present
> 5  |   await page.goto('/');
     |              ^ Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
  6  |   // If login form visible, try demo credentials
  7  |   const emailInput = page.locator('input[type="email"], input[name="email"]');
  8  |   if (await emailInput.isVisible({ timeout: 3000 })) {
  9  |     await emailInput.fill('demo@jobpulse.local');
  10 |     const passInput = page.locator('input[type="password"], input[name="password"]');
  11 |     await passInput.fill('demo123');
  12 |     const submitBtn = page.locator('button[type="submit"], button:has-text("Log in"), button:has-text("Sign in")');
  13 |     await submitBtn.click();
  14 |     await page.waitForTimeout(2000);
  15 |   }
  16 | }
  17 | 
  18 | async function interceptAlert(page) {
  19 |   page.on('dialog', async dialog => {
  20 |     console.log('Native dialog intercepted:', dialog.message());
  21 |     await dialog.dismiss();
  22 |   });
  23 | }
  24 | 
  25 | module.exports = { loginAsDemoUser, interceptAlert };
  26 | 
```