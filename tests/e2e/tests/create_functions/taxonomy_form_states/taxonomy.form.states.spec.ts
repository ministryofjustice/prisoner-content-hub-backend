import { expect, Page, test } from '@playwright/test';
import { loginViaUi } from '../../../actions/authActions';
import { appSettings } from '../../../config/appSettings';
import { TaxonomySortingFixture, withTaxonomySortingFixture } from '../../../fixtures/taxonomy-form-states';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import { NodeCreationPage } from '../../../pages/NodeCreationPage';

const studioAdminContentTypes = ['moj_radio_item', 'page', 'moj_video_item', 'moj_pdf_item', 'link'];
const localContentManagerContentTypes = ['moj_radio_item', 'page', 'moj_video_item', 'moj_pdf_item'];

type TestInfo = Parameters<typeof createStepRunner>[1];
type FormStateJourney = (fixture: TaxonomySortingFixture) => Promise<void>;

async function runAsRole(
  role: string,
  page: Page,
  testInfo: TestInfo,
  journey: FormStateJourney,
): Promise<void> {
  await withTaxonomySortingFixture(role, async (user, fixture) => {
    await loginViaUi(page, user.username, user.password, createStepRunner(page, testInfo));
    await journey(fixture);
  });
}

async function expectSeriesSortingStates(
  page: Page,
  fixture: TaxonomySortingFixture,
  contentTypes: string[],
): Promise<void> {
  for (const contentType of contentTypes) {
    const contentPage = new NodeCreationPage(page, contentType);
    await contentPage.expectSeriesSortingFormStates(fixture.episodeSeriesId, fixture.releaseDateSeriesId);
  }
}

async function expectCategoryFieldUsable(page: Page, contentTypes: string[]): Promise<void> {
  for (const contentType of contentTypes) {
    const contentPage = new NodeCreationPage(page, contentType);
    await contentPage.expectCategoryGroupVisible();
    await contentPage.selectFirstCategory();
  }
}

test.describe('taxonomy form states', () => {
  test.describe.configure({ mode: 'serial', timeout: 180000 });

  test('seriesSortingFieldsStudioAdmin', async ({ page }, testInfo) => {
    await runAsRole(appSettings.roles.studioAdminTest, page, testInfo, async (fixture) => {
      await expectSeriesSortingStates(page, fixture, studioAdminContentTypes);
    });
  });

  test('seriesSortingFieldsLocalContentManager', async ({ page }, testInfo) => {
    await runAsRole(appSettings.roles.lcmTest, page, testInfo, async (fixture) => {
      await expectSeriesSortingStates(page, fixture, localContentManagerContentTypes);
    });
  });

  test('categoryFieldStudioAdmin', async ({ page }, testInfo) => {
    await runAsRole(appSettings.roles.studioAdminTest, page, testInfo, async () => {
      await expectCategoryFieldUsable(page, studioAdminContentTypes);
    });
  });

  test('categoryFieldLocalContentManager', async ({ page }, testInfo) => {
    await runAsRole(appSettings.roles.lcmTest, page, testInfo, async () => {
      await expectCategoryFieldUsable(page, localContentManagerContentTypes);
    });
  });
});
