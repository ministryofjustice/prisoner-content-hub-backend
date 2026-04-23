import { expect, test } from '@playwright/test';
import { createStepRunner } from './helpers/stepScreenshots';

test('home page responds', async ({ page }, testInfo) => {
  const runStep = createStepRunner(page, testInfo);

  await runStep('open home page', async () => {
    await page.goto('/');
  });

  await runStep('verify body is visible', async () => {
    await expect(page.locator('body')).toBeVisible();
  });

  await runStep('verify page has title', async () => {
    await expect(page).toHaveTitle(/.+/);
  });
});
