import { test } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import {
  loginViaUi,
  runWithTemporaryUser,
} from '../../../actions/authActions';
import { NodeCreationPage } from '../../../pages/NodeCreationPage';
import { appSettings } from '../../../config/appSettings';

const loginRole = appSettings.roles.lcmTest;

test.describe('basic page create', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('local content manager can access page create but not homepage create', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      const nodeCreationPage = new NodeCreationPage(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('verify page create route is accessible', async () => {
        await nodeCreationPage.expectCreatePageAccessible('page');
      });
    });
  });

  test('local content manager can create basic page content', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright basic page ${Date.now()}`;
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

      await runStep('verify created basic page content', async () => {
        await nodeCreationPage.expectNodeViewPage(uniqueTitle, uniqueBody);
      });
    });
  });
});
