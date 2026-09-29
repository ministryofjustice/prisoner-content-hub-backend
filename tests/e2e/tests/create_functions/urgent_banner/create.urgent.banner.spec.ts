import { test, expect } from '@playwright/test';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import { loginViaUi, runWithTemporaryUser } from '../../../actions/authActions';
import { UrgentBannerCreationPOM } from '../../../pages/nodeCreation/UrgentBannerCreationPOM';
import { appSettings } from '../../../config/appSettings';

const loginRole = appSettings.roles.lcmTest;

test.describe('urgent banner create', () => {
  test.describe.configure({ mode: 'serial', timeout: 120000 });

  test('local content manager can create urgent banner content', async ({ page }, testInfo) => {
    const runStep = createStepRunner(page, testInfo);
    const uniqueTitle = `Playwright urgent banner ${Date.now()}`;
    const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

    await runWithTemporaryUser(loginRole, async (user) => {
      const urgentBanner = new UrgentBannerCreationPOM(page);

      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open urgent banner create form', async () => {
        await urgentBanner.expectCreatePageAccessible();
      });

      await runStep('verify prison list renders', async () => {
        await expect(urgentBanner.prisonGroup()).toBeVisible();
        const prisonInputs = urgentBanner.prisonGroup().locator('input[name^="field_prisons"]');
        expect(await prisonInputs.count()).toBeGreaterThan(0);
      });

      await runStep('verify details sections and revision fields render', async () => {
        await expect(urgentBanner.detailsSummary('Prison owner')).toBeVisible();
        await expect(urgentBanner.detailsSummary('Published date')).toBeVisible();
        await expect(urgentBanner.detailsSummary('Authoring information')).toBeVisible();
        await expect(urgentBanner.revisionLogMessageField()).toBeVisible();
      });

      await runStep('fill urgent banner content fields', async () => {
        await urgentBanner.fillTitle(uniqueTitle);
        await urgentBanner.fillUnpublishOnDate(tomorrow);
      });

      await runStep('select an urgent banner prison', async () => {
        await urgentBanner.selectFirstPrison();
        expect(await urgentBanner.getPrisonSelectionCount()).toBeGreaterThan(0);
      });

      await runStep('save urgent banner content', async () => {
        await urgentBanner.save();
      });

      await runStep('verify created urgent banner content', async () => {
        await urgentBanner.expectNodeViewPage(uniqueTitle);
      });
    });
  });
});