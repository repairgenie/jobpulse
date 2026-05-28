# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: tests/e2e/04_security.spec.js >> Security Checks >> API returns generic errors (no info disclosure)
- Location: tests/e2e/04_security.spec.js:21:3

# Error details

```
SyntaxError: Unexpected token '<', "    <!DOCTYPE "... is not valid JSON
```

# Test source

```ts
  1  | const { test, expect } = require('@playwright/test');
  2  | 
  3  | test.describe('Security Checks', () => {
  4  |   test('no SQL injection in job search API', async ({ page }) => {
  5  |     const response = await page.request.post('http://localhost:8000/api/search_jobs.php', {
  6  |       headers: { 'Content-Type': 'application/json' },
  7  |       data: JSON.stringify({ search: "'; DROP TABLE users; --" }),
  8  |     });
  9  |     // Should not crash, should return valid response
  10 |     const contentType = response.headers()['content-type'] || '';
  11 |     expect(contentType).toContain('application/json');
  12 |   });
  13 | 
  14 |   test('no reflected XSS in job search', async ({ page }) => {
  15 |     await page.goto('http://localhost:8000');
  16 |     // Check for any script injection reflected back
  17 |     const body = await page.content();
  18 |     expect(body).not.toContain("DROP TABLE");
  19 |   });
  20 | 
  21 |   test('API returns generic errors (no info disclosure)', async ({ page }) => {
  22 |     const response = await page.request.post('http://localhost:8000/api/optimize_resume.php', {
  23 |       headers: { 'Content-Type': 'application/json' },
  24 |       data: JSON.stringify({}),
  25 |     });
> 26 |     const json = await response.json();
     |                  ^ SyntaxError: Unexpected token '<', "    <!DOCTYPE "... is not valid JSON
  27 |     // Error messages should not expose internal paths or stack traces
  28 |     if (!json.success) {
  29 |       expect(json.error).not.toMatch(/stack|trace|php|undefined|warning/i);
  30 |     }
  31 |   });
  32 | });
```