import { expect, test } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import {
  loginViaUi,
  runWithTemporaryUser,
} from '../../../actions/authActions';
import { BasicPageCreationPOM } from '../../../pages/nodeCreation/BasicPageCreationPOM';
import { appSettings } from '../../../config/appSettings';

const loginRole = appSettings.roles.lcmTest;

test.describe('create page validation warnings', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('local content manager sees validation warnings for missing title and summary', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('submit empty basic page form', async () => {
        await basicPage.save();
      });

      await runStep('verify title and summary validation warnings are shown', async () => {
        await page.waitForURL(/\/node\/add\/page$/);
        await testInfo.attach('validation-errors-page-text', {
          body: await page.locator('main').innerText(),
          contentType: 'text/plain',
        });

        const requiredWarnings = await page.evaluate(() => {
          const titleField = document.querySelector('#edit-title-0-value, input[name="title[0][value]"]');
          const summaryField = document.querySelector('#edit-field-summary-0-value, textarea[name="field_summary[0][value]"]');

          function warningFor(element: Element | null): string {
            if (!element) {
              return '';
            }

            if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement) {
              return element.validationMessage;
            }

            return '';
          }

          return {
            title: warningFor(titleField),
            summary: warningFor(summaryField),
          };
        });

        expect(requiredWarnings.title).not.toEqual('');
        expect(requiredWarnings.summary).not.toEqual('');
        expect(requiredWarnings.title).toMatch(/please fill out this field\.?/i);
        expect(requiredWarnings.summary).toMatch(/please fill out this field\.?/i);
      });
    });
  });

  test('local content manager sees category or series warning when taxonomy is missing', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright warning check ${Date.now()}`;
    const uniqueSummary = `Playwright warning summary ${Date.now()}`;
    const uniqueBody = `Warning validation body ${new Date().toISOString()}`;

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('fill required text fields without category or series', async () => {
        await basicPage.fillTitle(uniqueTitle);
        await basicPage.fillSummary(uniqueSummary);
        await basicPage.fillBody(uniqueBody);
      });

      await runStep('submit form without taxonomy selection', async () => {
        await basicPage.save();
      });

      await runStep('verify category or series validation warning appears', async () => {
        await page.waitForURL(/\/node\/add\/page$/);
        await expect(page.locator('main')).toContainText('This content must be placed in a category or series.');
        await expect(page.locator('main')).toContainText(/must be placed in a category or series/i);
      });
    });
  });

  test('local content manager sees main body content warning when body is missing', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright body warning ${Date.now()}`;
    const uniqueSummary = `Playwright body summary ${Date.now()}`;

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('fill title summary and category without body', async () => {
        await basicPage.fillTitle(uniqueTitle);
        await basicPage.fillSummary(uniqueSummary);
        await basicPage.selectFirstCategory();
      });

      await runStep('submit form without body content', async () => {
        await basicPage.save();
      });

      await runStep('verify main body content warning appears', async () => {
        await page.waitForURL(/\/node\/add\/page$/);
        await expect(page.getByRole('textbox', { name: /Rich Text Editor/i }).first()).toBeVisible();
      });
    });
  });
});
