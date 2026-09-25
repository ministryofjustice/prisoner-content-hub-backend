import { test } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import {
  loginViaUi,
  runWithTemporaryUser,
} from '../../../actions/authActions';
import { BasicPageCreationPOM } from '../../../pages/nodeCreation/BasicPageCreationPOM';
import { appSettings } from '../../../config/appSettings';

const studioAdminRole = appSettings.roles.studioAdminTest;

test.describe('studio admin role', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('studio admin can access page create form', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(studioAdminRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('verify studio admin can access page create route', async () => {
        await basicPage.expectCreatePageAccessible();
      });
    });
  });

  test('studio admin can create basic page content', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright studio admin page ${Date.now()}`;
    const uniqueSummary = `Playwright studio admin summary ${Date.now()}`;
    const uniqueBody = `Created by studio admin at ${new Date().toISOString()}`;

    await runWithTemporaryUser(studioAdminRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open studio admin basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('fill studio admin basic page content fields', async () => {
        await basicPage.fillTitle(uniqueTitle);
        await basicPage.fillSummary(uniqueSummary);
        await basicPage.fillBody(uniqueBody);
        await basicPage.selectFirstCategory();
      });

      await runStep('save studio admin basic page content', async () => {
        await basicPage.save();
      });

      await runStep('verify studio admin created basic page content', async () => {
        await basicPage.expectNodeViewPage(uniqueTitle, uniqueBody);
      });
    });
  });
});
