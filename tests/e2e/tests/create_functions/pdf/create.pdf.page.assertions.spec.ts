import { test, expect } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import { loginViaUi, runWithTemporaryUser } from '../../../actions/authActions';
import { NodeCreationPage } from '../../../pages/NodeCreationPage';
import { appSettings } from '../../../config/appSettings';

const loginRole = appSettings.roles.lcmTest;

test.describe('PDF create page', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('has all required fields and components', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    await runWithTemporaryUser(loginRole, async (user) => {
      const nodeCreationPage = new NodeCreationPage(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open PDF create form', async () => {
        await nodeCreationPage.expectCreatePageAccessible('moj_pdf_item');
      });

      await runStep('assert title field exists', async () => {
        await expect(nodeCreationPage.titleField()).toBeVisible();
      });

      await runStep('assert summary field exists', async () => {
        await expect(nodeCreationPage.summaryField()).toBeVisible();
      });

      await runStep('assert category field exists', async () => {
        await expect(nodeCreationPage.categoryField()).toBeVisible();
      });

      await runStep('assert PDF file input exists', async () => {
        await expect(nodeCreationPage.pdfFileInput()).toBeVisible();
      });

      await runStep('assert thumbnail image input exists', async () => {
        await expect(nodeCreationPage.thumbnailImageInput()).toBeVisible();
      });

      await runStep('assert season field exists in DOM', async () => {
        await expect(nodeCreationPage.seasonField()).toHaveCount(1);
      });

      await runStep('assert episode field exists in DOM', async () => {
        await expect(nodeCreationPage.episodeField()).toHaveCount(1);
      });

      await runStep('assert release date field exists in DOM', async () => {
        await expect(nodeCreationPage.releaseDateField()).toHaveCount(1);
      });

      await runStep('assert at least one prison checkbox exists', async () => {
        await expect(nodeCreationPage.prisonCheckboxes().first()).toBeVisible();
      });

      await runStep('assert Save button exists', async () => {
        await expect(nodeCreationPage.saveButton()).toBeVisible();
      });
    });
  });
});
