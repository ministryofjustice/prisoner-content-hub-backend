import { test } from '@playwright/test';
import { createStepRunner } from '../../e2e/helpers/stepScreenshots';
import {
  loginViaUi,
  runWithTemporaryUser,
} from '../../e2e/actions/authActions';
import { NodeCreationPage } from '../../e2e/pages/NodeCreationPage';

const accessRole = process.env.PLAYWRIGHT_ACCESS_TEST_ROLE ?? 'moj_local_content_manager';

test.describe('create page function', () => {
  test.describe.configure({ mode: 'serial' });

  test('local content manager can access page create but not homepage create', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(accessRole, async (user) => {
      const nodeCreationPage = new NodeCreationPage(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('verify page create route is accessible', async () => {
        await nodeCreationPage.expectCreatePageAccessible('page');
      });
    });
  });
});
