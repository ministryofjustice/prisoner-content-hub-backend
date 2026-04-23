import { Page, TestInfo, test } from '@playwright/test';

export type StepRunner = (stepName: string, action: () => Promise<void>) => Promise<void>;

function sanitizeForFileName(value: string): string {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

export function createStepRunner(page: Page, testInfo: TestInfo): StepRunner {
  let stepIndex = 0;

  return async (stepName: string, action: () => Promise<void>) => {
    await test.step(stepName, async () => {
      await action();
    });

    stepIndex += 1;
    const prefix = String(stepIndex).padStart(2, '0');
    const screenshotName = `${prefix}-${sanitizeForFileName(stepName)}.png`;
    const screenshotPath = testInfo.outputPath(screenshotName);

    await page.screenshot({ path: screenshotPath, fullPage: true });
    await testInfo.attach(`step-${prefix}-${stepName}`, {
      path: screenshotPath,
      contentType: 'image/png',
    });
  };
}
