import { expect, test } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import {
  loginViaUi,
  runWithTemporaryUser,
} from '../../../actions/authActions';
import { NodeCreationPage } from '../../../pages/NodeCreationPage';
import { appSettings } from '../../../config/appSettings';

const loginRole = appSettings.roles.lcmTest;

test.describe('content listing', () => {
    
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('newly created basic page appears in content listing', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright content list ${Date.now()}`;
    const uniqueSummary = `Playwright summary ${Date.now()}`;
    const uniqueBody = `Created by Playwright at ${new Date().toISOString()}`;

    await runWithTemporaryUser(loginRole, async (user) => {
      const nodeCreationPage = new NodeCreationPage(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open basic page create form', async () => {
        await nodeCreationPage.expectCreatePageAccessible('page');
      });

      await runStep('fill basic page content fields', async () => {
        await nodeCreationPage.fillTitle(uniqueTitle);
        await nodeCreationPage.fillSummary(uniqueSummary);
        await nodeCreationPage.fillBody(uniqueBody);
        await nodeCreationPage.selectFirstCategory();
      });

      await runStep('save basic page content', async () => {
        await nodeCreationPage.save();
      });

      await runStep('visit content listing', async () => {
        await page.goto('/admin/content');
        await expect(page).toHaveURL(/\/admin\/content/);
      });

      await runStep('verify new page appears in listing', async () => {
        await expect(page.locator('table')).toContainText(uniqueTitle);
      });

      await runStep('search for new page by title', async () => {
        await page.fill('input[name="title"]', uniqueTitle);
        await page.click('input[type="submit"][value="Filter"]');
        await expect(page.locator('table')).toContainText(uniqueTitle);
      });

      await runStep('filter by content type: Basic page', async () => {
        await page.selectOption('select[name="type"]', 'page');
        await page.click('input[type="submit"][value="Filter"]');
        await expect(page.locator('table')).toContainText(uniqueTitle);
      });

      await runStep('clear filters to reset view', async () => {
        await page.fill('input[name="title"]', '');
        await page.selectOption('select[name="type"]', 'All');
        await page.click('input[type="submit"][value="Filter"]');
        await expect(page.locator('table')).toContainText(uniqueTitle);
      });
    });
  });

  test.skip('newly created PDF appears in content listing', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright PDF listing ${Date.now()}`;
    const uniqueSummary = `Playwright PDF summary ${Date.now()}`;

    await runWithTemporaryUser(loginRole, async (user) => {
      const nodeCreationPage = new NodeCreationPage(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open PDF create form', async () => {
        await nodeCreationPage.expectCreatePageAccessible('moj_pdf_item');
      });

      await runStep('fill PDF content fields', async () => {
        await nodeCreationPage.fillTitle(uniqueTitle);
        await nodeCreationPage.fillSummary(uniqueSummary);
        await nodeCreationPage.selectFirstCategory();
      });

      await runStep('upload PDF file', async () => {
        const path = require('path');
        const testFilePath = path.resolve(__dirname, '../../fixtures/test-file.pdf');
        await nodeCreationPage.uploadPdfFile(testFilePath);
      });

      await runStep('save PDF content', async () => {
        await nodeCreationPage.save();
      });

      await runStep('visit content listing', async () => {
        await page.goto('/admin/content');
        await expect(page).toHaveURL(/\/admin\/content/);
      });

      await runStep('verify new PDF appears in listing', async () => {
        await expect(page.locator('table')).toContainText(uniqueTitle);
      });

      await runStep('search for new PDF by title', async () => {
        await page.fill('input[name="title"]', uniqueTitle);
        await page.click('input[type="submit"][value="Filter"]');
        await expect(page.locator('table')).toContainText(uniqueTitle);
      });

      await runStep('filter by content type: PDF', async () => {
        await page.selectOption('select[name="type"]', 'moj_pdf_item');
        await page.click('input[type="submit"][value="Filter"]');
        await expect(page.locator('table')).toContainText(uniqueTitle);
      });
    });
  });
});
