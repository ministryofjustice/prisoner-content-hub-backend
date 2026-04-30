import { test } from '@playwright/test';
import { createStepRunner } from '../../e2e/helpers/stepScreenshots';
import {
  expectAuthenticatedSessionCookie,
  loginViaUi,
  runWithTemporaryUser,
} from '../../e2e/actions/authActions';

const loginRole = process.env.PLAYWRIGHT_LOGIN_ROLE ?? 'moj_local_content_manager';
const accessRole = process.env.PLAYWRIGHT_ACCESS_TEST_ROLE ?? 'moj_local_content_manager';

test.describe('authentication', () => {
  test.describe.configure({ mode: 'serial' });
  
  test('user can log in from Drupal login page', async ({ context, page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      await loginViaUi(page, user.username, user.password, runStep);
      await expectAuthenticatedSessionCookie(context, runStep);
    });
  });
});
