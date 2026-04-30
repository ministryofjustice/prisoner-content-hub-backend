import { test } from '@playwright/test';
import { createStepRunner } from '../../e2e/helpers/stepScreenshots';
import {
  expectAuthenticatedSessionCookie,
  loginViaUi,
  runWithTemporaryUser,
} from '../../e2e/actions/authActions';
import { appSettings } from '../../e2e/config/appSettings';

test.describe('Drupal Login Page', () => {
  test.describe.configure({ mode: 'serial' });

  for (const loginRole of appSettings.roles.all) {
    test(`role ${loginRole} can log in from Drupal login page`, async ({ context, page }, testInfo) => {
      const runStep = createStepRunner(page, testInfo);

      await runWithTemporaryUser(loginRole, async (user) => {
        await loginViaUi(page, user.username, user.password, runStep);
        await expectAuthenticatedSessionCookie(context, runStep);
      });
    });
  }
});
