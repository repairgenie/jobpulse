const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  retries: 2,
  repeatEach: parseInt(process.env.REPEAT_EACH || '1'),
  workers: 1,
  timeout: 30000,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.TEST_BASE_URL || 'http://localhost:8000',
    headless: true,
  },
  projects: [
    { name: 'chromium', use: { ...require('@playwright/test').devices['Desktop Chrome'] } },
  ],
});
