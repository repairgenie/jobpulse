# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests/e2e/03_api_auth.spec.js >> API Authentication >> unauthenticated request to /api/history.php returns 401
- Location: tests/e2e/03_api_auth.spec.js:15:5

# Error details

```
Error: expect(received).toBeGreaterThanOrEqual(expected)

Expected: >= 401
Received:    200
```

# Test source

```ts
  1  | const { test, expect } = require('@playwright/test');
  2  | const { interceptAlert } = require('./helpers/auth');
  3  | 
  4  | test.describe('API Authentication', () => {
  5  |   const apis = [
  6  |     '/api/optimize_resume.php',
  7  |     '/api/generalize_resume.php',
  8  |     '/api/resumes.php',
  9  |     '/api/history.php',
  10 |     '/api/ask_ai.php',
  11 |     '/api/analyze_job.php',
  12 |   ];
  13 | 
  14 |   for (const api of apis) {
  15 |     test(`unauthenticated request to ${api} returns 401`, async ({ page }) => {
  16 |       const response = await page.request.get('http://localhost:8000' + api);
  17 | // Should return 401 or 403 for unauthenticated requests
> 18 |     expect(response.status()).toBeGreaterThanOrEqual(401);
     |                               ^ Error: expect(received).toBeGreaterThanOrEqual(expected)
  19 |     });
  20 |   }
  21 | });
  22 | 
```