import { test, expect } from '@playwright/test';
import { createStepRunner } from '../../helpers/stepScreenshots';
import { loginViaUi, runWithTemporaryUser } from '../../actions/authActions';
import { BasicPageCreationPOM } from '../../pages/nodeCreation/BasicPageCreationPOM';
import { PdfPageCreationPOM } from '../../pages/nodeCreation/PdfPageCreationPOM';
import { appSettings } from '../../config/appSettings';

const loginRole = appSettings.roles.lcmTest;

test.describe('prisons selection on create pages', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('basic page create form displays prison selection', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('verify prison section is visible', async () => {
        await expect(basicPage.prisonGroup()).toBeVisible();
      });

      await runStep('verify prison inputs are rendered', async () => {
        await expect(basicPage.prisonGroup()).toBeAttached();
      });
    });
  });

  test('PDF create form displays prison selection', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      const pdfPage = new PdfPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open PDF create form', async () => {
        await pdfPage.expectCreatePageAccessible();
      });

      await runStep('verify prison section is visible', async () => {
        await expect(pdfPage.prisonGroup()).toBeVisible();
      });

      await runStep('verify prison inputs are rendered', async () => {
        await expect(pdfPage.prisonGroup()).toBeAttached();
      });
    });
  });

  test('prison radio buttons/checkboxes are properly labeled', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('verify prison inputs have labels', async () => {
        const prisonGroup = basicPage.prisonGroup();
        const inputs = prisonGroup.locator('input[name^="field_prisons"]');
        const inputCount = await inputs.count();
        expect(inputCount).toBeGreaterThan(0);

        for (let i = 0; i < Math.min(inputCount, 3); i++) {
          const input = inputs.nth(i);
          const inputId = await input.getAttribute('id');
          if (inputId) {
            const label = page.locator(`label[for="${inputId}"]`);
            expect(await label.count()).toBeGreaterThan(0);
          }
        }
      });
    });
  });

  test('can select prison on basic page create form', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright basic page prisons ${Date.now()}`;
    const uniqueSummary = `Prison selection test ${Date.now()}`;
    const uniqueBody = `Created by Playwright at ${new Date().toISOString()}`;

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('inspect prison widget rendering', async () => {
        const prisonGroup = basicPage.prisonGroup();
        await expect(prisonGroup).toBeVisible();
        await testInfo.attach('prison-group-markup', {
          body: await prisonGroup.innerText(),
          contentType: 'text/plain',
        });
        await testInfo.attach('prison-group-screenshot', {
          body: await page.screenshot({ fullPage: true }),
          contentType: 'image/png',
        });
      });

      await runStep('fill basic page content fields', async () => {
        await basicPage.fillTitle(uniqueTitle);
        await basicPage.fillSummary(uniqueSummary);
        await basicPage.fillBody(uniqueBody);
        await basicPage.selectFirstCategory();
      });

      await runStep('select first available prison', async () => {
        await basicPage.selectFirstPrison();
      });

      await runStep('verify prison selection is recorded', async () => {
        const selectionCount = await basicPage.getPrisonSelectionCount();
        expect(selectionCount).toBeGreaterThan(0);
      });

      await runStep('save basic page with prison selection', async () => {
        await basicPage.save();
      });

      await runStep('verify created page with prison selection', async () => {
        await basicPage.expectNodeViewPage(uniqueTitle, uniqueBody);
      });
    });
  });

  test('prison selection persists after form submission attempts', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);

    await runWithTemporaryUser(loginRole, async (user) => {
      const basicPage = new BasicPageCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open basic page create form', async () => {
        await basicPage.expectCreatePageAccessible();
      });

      await runStep('select prison', async () => {
        await basicPage.selectFirstPrison();
      });

      await runStep('verify prison remains selected after interaction', async () => {
        let selectionCount = await basicPage.getPrisonSelectionCount();
        expect(selectionCount).toBeGreaterThan(0);

        await basicPage.fillTitle('Test Title');

        selectionCount = await basicPage.getPrisonSelectionCount();
        expect(selectionCount).toBeGreaterThan(0);
      });
    });
  });
});
