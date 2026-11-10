import { defineConfig, devices } from '@playwright/test';

const isCI = !!process.env.CI;
const isMergingReports = process.env.PLAYWRIGHT_MERGE_REPORTS === 'true';

export default defineConfig({
  testDir: './tests',
  timeout: 3 * 60 * 1000,
  globalTimeout: 60 * 60 * 1000,
  fullyParallel: isCI,
  workers: 1,
  forbidOnly: isCI,
  retries: isCI ? 1 : 0,
  reporter: isCI && !isMergingReports
    ? [
      [
        'blob',
        { outputDir: process.env.PLAYWRIGHT_BLOB_OUTPUT_DIR || '../../test-results/playwright/blob-report' },
      ],
      [
        'junit',
        { outputFile: process.env.PLAYWRIGHT_JUNIT_OUTPUT_FILE || '../../test-results/playwright/junit.xml' },
      ],
    ]
    : [
      ['list'],
      [
        'html',
        {
          outputFolder: process.env.PLAYWRIGHT_HTML_OUTPUT_DIR || '../../test-results/playwright/report',
          open: isCI ? 'never' : 'on-failure',
        },
      ],
      [
        'junit',
        { outputFile: process.env.PLAYWRIGHT_JUNIT_OUTPUT_FILE || '../../test-results/playwright/junit.xml' },
      ],
    ],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080',
    trace: 'on',
    screenshot: 'on',
    video: 'on',
  },
  outputDir: '../../test-results/playwright/test-output',
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
