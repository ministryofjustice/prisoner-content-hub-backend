/// <reference types="node" />

import { expect, test } from '@playwright/test';
import { canManageDrupalUsersFromTests } from './helpers/drupalUser';
import { createStepRunner } from './helpers/stepScreenshots';
import {
  expectAuthenticatedSessionCookie,
  loginViaUi,
  runWithTemporaryUser,
} from './actions/authActions';
import { NodeCreationPage } from './pages/NodeCreationPage';

const canManageUsers = canManageDrupalUsersFromTests();
const loginRole = process.env.PLAYWRIGHT_LOGIN_ROLE ?? 'moj_local_content_manager';
const accessRole = process.env.PLAYWRIGHT_ACCESS_TEST_ROLE ?? 'moj_local_content_manager';

test.describe('authentication', () => {
  test.skip(!canManageUsers, 'Drush is not available. Set PLAYWRIGHT_DRUSH_COMMAND to enable runtime user creation.');

  test('user can log in from Drupal login page', async ({ context, page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      await loginViaUi(page, user.username, user.password, runStep);
      await expectAuthenticatedSessionCookie(context, runStep);
    });
  });

  test('local content manager can access page create but not homepage create', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(accessRole, async (user) => {
      const nodeCreationPage = new NodeCreationPage(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('verify page create route is accessible', async () => {
        await nodeCreationPage.expectBundleCreateAccessible('page');
      });

      await runStep('verify homepage create route is denied', async () => {
        await nodeCreationPage.expectBundleCreateDenied('homepage');
      });
    });
  });
});
