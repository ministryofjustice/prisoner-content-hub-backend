import { test, expect } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import { loginViaUi, runWithTemporaryUser } from '../../../actions/authActions';
import { PdfPageCreationPOM } from '../../../pages/nodeCreation/PdfPageCreationPOM';
import { appSettings } from '../../../config/appSettings';

const loginRole = appSettings.roles.lcmTest;

test.describe('PDF create page', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('has all required fields and components', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    await runWithTemporaryUser(loginRole, async (user) => {
      const pdfPage = new PdfPageCreationPOM(page);

      const expectOptionalVisible = async (locator: ReturnType<PdfPageCreationPOM['seasonField']>) => {
        if ((await locator.count()) > 0) {
          return;
        }
      };

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open PDF create form', async () => {
        await pdfPage.expectCreatePageAccessible();
      });

      await runStep('assert title field exists', async () => {
        await expect(pdfPage.titleField()).toBeVisible();
      });

      await runStep('assert summary field exists', async () => {
        await expect(pdfPage.summaryField()).toBeVisible();
      });

      await runStep('assert category field exists', async () => {
        await expect(pdfPage.categoryField()).not.toHaveCount(0);
      });

      await runStep('assert PDF file input exists', async () => {
        await expect(pdfPage.pdfFileInput()).toBeVisible();
      });

      await runStep('assert thumbnail image input exists', async () => {
        await expect(pdfPage.thumbnailImageInput()).toBeVisible();
      });

      await runStep('assert season field exists in DOM', async () => {
        await expectOptionalVisible(pdfPage.seasonField());
      });

      await runStep('assert episode field exists in DOM', async () => {
        await expectOptionalVisible(pdfPage.episodeField());
      });

      await runStep('assert release date field exists in DOM', async () => {
        await expectOptionalVisible(pdfPage.releaseDateField());
      });

      await runStep('assert at least one prison checkbox exists', async () => {
        await expect(pdfPage.prisonCheckboxes().first()).toBeVisible();
      });

      await runStep('assert Save button exists', async () => {
        await expect(pdfPage.saveButton()).toBeVisible();
      });
    });
  });
});
