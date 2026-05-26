import path from 'path';
import { test } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import {
  loginViaUi,
  runWithTemporaryUser,
} from '../../../actions/authActions';
import { PdfPageCreationPOM } from '../../../pages/nodeCreation/PdfPageCreationPOM';
import { appSettings } from '../../../config/appSettings';

const loginRole = appSettings.roles.lcmTest;

test.describe('PDF create page', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test.skip('local content manager can create PDF content', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright PDF ${Date.now()}`;
    const uniqueSummary = `Playwright PDF summary ${Date.now()}`;

    await runWithTemporaryUser(loginRole, async (user) => {
      const pdfPage = new PdfPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open PDF create form', async () => {
        await pdfPage.expectCreatePageAccessible();
      });

      await runStep('fill PDF content fields', async () => {
        await pdfPage.fillTitle(uniqueTitle);
        await pdfPage.fillSummary(uniqueSummary);
        await pdfPage.selectFirstCategory();
      });

      await runStep('upload PDF file', async () => {
        const testFilePath = path.resolve(__dirname, '../../fixtures/test-file.pdf');
        await pdfPage.uploadPdfFile(testFilePath);
      });

      await runStep('save PDF content', async () => {
        const saveButton = page.getByRole('button', { name: /^Save$/ });
        const isDisabled = await saveButton.isDisabled();
        console.log('Save button disabled?', isDisabled);
        
        await pdfPage.save();
        await page.waitForTimeout(3000);

        const currentUrl = page.url();
        console.log('Current URL after save:', currentUrl);
        
        const bodyText = await page.locator('body').innerText();
        const errorMessages = await page.locator('[data-drupal-message-type="error"]').count();
        console.log('Error message count:', errorMessages);
        console.log('Page title:', await page.title());
      });

      await runStep('verify created PDF content', async () => {
        await pdfPage.expectNodeViewPage(uniqueTitle);
      });
    });
  });
});
