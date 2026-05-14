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
      await loginViaUi(page, user.username, user.password, runStep);

      await runStep('open PDF create form', async () => {
        await page.goto('/node/add/moj_pdf_item');
        await expect(page).toHaveURL(/\/node\/add\/moj_pdf_item$/);
      });

      await runStep('assert title field exists', async () => {
        await expect(page.locator('#edit-title-0-value')).toBeVisible();
      });
      await runStep('assert summary field exists', async () => {
        await expect(page.locator('#edit-field-summary-0-value')).toBeVisible();
      });
      await runStep('assert category field exists', async () => {
        const category = page.locator('select[name="field_moj_top_level_categories[]"], input[id*="field-moj-top-level-categories"][type="search"]');
        await expect(category.first()).toBeVisible();
      });
      await runStep('assert PDF file input exists', async () => {
        await expect(page.locator('input[type="file"][name="files[field_moj_pdf_0]"]')).toBeVisible();
      });
      await runStep('assert thumbnail image input exists', async () => {
        await expect(page.locator('input[type="file"][name="files[field_moj_thumbnail_image_0]"]')).toBeVisible();
      });
      await runStep('assert season field exists in DOM', async () => {
        await expect(page.locator('#edit-field-moj-season-0-value')).toHaveCount(1);
      });
      await runStep('assert episode field exists in DOM', async () => {
        await expect(page.locator('#edit-field-moj-episode-0-value')).toHaveCount(1);
      });
      await runStep('assert release date field exists in DOM', async () => {
        await expect(page.locator('#edit-field-release-date-0-value-date')).toHaveCount(1);
      });
      await runStep('assert at least one prison checkbox exists', async () => {
        const prisonCheckbox = page.locator('input[type="checkbox"][name^="field_prisons"]');
        await expect(prisonCheckbox.first()).toBeVisible();
      });
      await runStep('assert Save button exists', async () => {
        await expect(page.getByRole('button', { name: /^Save$/ })).toBeVisible();
      });
    });
  });
});
