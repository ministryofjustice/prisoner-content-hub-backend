import { expect, test } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import {
  loginViaUi,
  runWithTemporaryUser,
} from '../../../actions/authActions';
import { NodeCreationFormPOM } from '../../../pages/nodeCreation/NodeCreationFormPOM';
import { appSettings } from '../../../config/appSettings';
import { taxonomyFixtures } from '../../../fixtures/taxonomyFixures';

const loginRole = appSettings.roles.lcmTest;

test.describe('basic page create', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('local content manager can access page create but not homepage create', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new NodeCreationFormPOM(page);

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
      const basicPage = new NodeCreationFormPOM(page);

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

  for (const taxonomyFixture of taxonomyFixtures) {
    test(`local content manager can select and remove ${taxonomyFixture.name}`, async ({ page }, testInfo) => {
      const runStep = createStepRunner(page, testInfo);
      await runWithTemporaryUser(loginRole, async (user) => {
        const basicPage = new NodeCreationFormPOM(page);
        await loginViaUi(page, user.username, user.password, runStep);
        await basicPage.expectCreatePageAccessible();

        const field = taxonomyFixture.field(basicPage);
        await expect(field).toHaveCount(1);
        const wrapper = field.locator('xpath=preceding-sibling::tags[1]');
        await taxonomyFixture.select(basicPage);
        await expect(wrapper.locator('tag').filter({ hasText: taxonomyFixture.term })).toBeVisible();

        await taxonomyFixture.remove(basicPage);
        await expect(wrapper.locator('tag').filter({ hasText: taxonomyFixture.term })).toHaveCount(0);
      });
    });
  }
});
