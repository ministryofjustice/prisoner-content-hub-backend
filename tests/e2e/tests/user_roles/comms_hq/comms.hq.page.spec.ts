import { test } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import {
  loginViaUi,
  runWithTemporaryUser,
} from '../../../actions/authActions';
import { BasicPageCreationPOM } from '../../../pages/nodeCreation/BasicPageCreationPOM';
import { appSettings } from '../../../config/appSettings';

const commsHqRole = appSettings.roles.commsLiveServiceHq;
const accessRole = appSettings.roles.accessTest;
const commsHqCombinedRoles = [commsHqRole, accessRole];

test.describe('comms live service HQ role', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('comms HQ user can access page create form', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(commsHqCombinedRoles, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('verify comms HQ user can access page create route', async () => {
        await basicPage.expectCreatePageAccessible();
      });
    });
  });

  test('comms HQ user can create basic page content', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright comms HQ page ${Date.now()}`;
    const uniqueSummary = `Playwright comms HQ summary ${Date.now()}`;
    const uniqueBody = `Created by comms HQ at ${new Date().toISOString()}`;

    await runWithTemporaryUser(commsHqCombinedRoles, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open comms HQ basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('fill comms HQ basic page content fields', async () => {
        await basicPage.fillTitle(uniqueTitle);
        await basicPage.fillSummary(uniqueSummary);
        await basicPage.fillBody(uniqueBody);
        await basicPage.selectFirstCategory();
      });

      await runStep('save comms HQ basic page content', async () => {
        await basicPage.save();
      });

      await runStep('verify comms HQ created basic page content', async () => {
        await basicPage.expectNodeViewPage(uniqueTitle, uniqueBody);
      });
    });
  });
});
