import { test } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import {
  loginViaUi,
  runWithTemporaryUser,
} from '../../../actions/authActions';
import { BasicPageCreationPOM } from '../../../pages/nodeCreation/BasicPageCreationPOM';
import { appSettings } from '../../../config/appSettings';

const loginRole = appSettings.roles.lcmTest;

test.describe('basic page create', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('local content manager can access page create but not homepage create', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('verify page create route is accessible', async () => {
        await basicPage.expectCreatePageAccessible();
      });
    });
  });

  test('local content manager can create basic page content', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright basic page ${Date.now()}`;
    const uniqueSummary = `Playwright summary ${Date.now()}`;
    const uniqueBody = `Created by Playwright at ${new Date().toISOString()}`;

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('fill basic page content fields', async () => {
        await basicPage.fillTitle(uniqueTitle);
        await basicPage.fillSummary(uniqueSummary);
        await basicPage.fillBody(uniqueBody);
        await basicPage.selectFirstCategory();
      });

      await runStep('save basic page content', async () => {
        await basicPage.save();
      });

      await runStep('verify created basic page content', async () => {
        await basicPage.expectNodeViewPage(uniqueTitle, uniqueBody);
      });
    });
  });
});
