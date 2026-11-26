import { expect, test } from '@playwright/test';
import { loginViaUi } from '../../../actions/authActions';
import {
  createPrisonAccessNode,
  PrisonAccessFixture,
  runWithPrisonAccessUser,
  setUserPrisons,
} from '../../../fixtures/prison-access';
import { createStepRunner } from '../../../helpers/stepScreenshots';
import { DrupalRoleInput, TemporaryUser } from '../../../helpers/drupalUser';
import { NodeCreationPage } from '../../../pages/NodeCreationPage';

const prisonFieldName = 'field_prisons';
const excludeFromPrisonFieldName = 'field_exclude_from_prison';
const prisonAccessContentTypes = [
  'page',
  'moj_video_item',
  'moj_radio_item',
  'moj_pdf_item',
  'homepage',
  'urgent_banner',
];
const creatableContentTypes = prisonAccessContentTypes.filter((contentType) => contentType !== 'homepage');

type PrisonAccessJourney = (
  user: TemporaryUser,
  fixture: PrisonAccessFixture,
  contentPage: NodeCreationPage,
) => Promise<void>;
type ContentTypeJourney = PrisonAccessJourney extends (...args: infer Args) => Promise<void>
  ? (...args: [...Args, contentType: string]) => Promise<void>
  : never;

async function runPrisonAccessJourney(
  page: Parameters<typeof loginViaUi>[0],
  testInfo: Parameters<typeof createStepRunner>[1],
  journey: PrisonAccessJourney,
  roles: DrupalRoleInput = 'moj_local_content_manager',
  contentType = 'page',
): Promise<void> {
  await runWithPrisonAccessUser(async (user, fixture) => {
    await loginViaUi(page, user.username, user.password, createStepRunner(page, testInfo));
    await journey(user, fixture, new NodeCreationPage(page, contentType));
  }, roles);
}

function testForEachContentType(
  title: string,
  contentTypes: readonly string[],
  journey: ContentTypeJourney,
  roles: DrupalRoleInput = 'moj_local_content_manager',
): void {
  for (const contentType of contentTypes) {
    test(`${title} (${contentType})`, async ({ page }, testInfo) => {
      await runPrisonAccessJourney(page, testInfo, async (user, fixture, contentPage) => {
        expect([...fixture.contentTypes].sort()).toEqual([...prisonAccessContentTypes].sort());
        await journey(user, fixture, contentPage, contentType);
      }, roles, contentType);
    });
  }
}

test.describe('LCM prison access CMS journeys', () => {
  test.describe.configure({ mode: 'parallel', timeout: 120000 });

  testForEachContentType('LCMUserCanCreateNewContent', creatableContentTypes, async (_user, fixture, contentPage) => {
    await contentPage.expectCreatePageAccessible();
    await contentPage.expectDefaultPrisonOwner(fixture.terms.currentPrisonId);
    await contentPage.expectContentEditable();
    await contentPage.expectPrisonCheckboxEnabled(prisonFieldName, fixture.terms.currentPrisonName);
    await contentPage.expectPrisonCheckboxEnabled(prisonFieldName, fixture.terms.otherPrisonName);
  });

  // This covers both PHP cases: testUserCanEditOwnPrisonContent and
  // testUserCanEditContentAssignedToCategory use the same category-owned setup.
  testForEachContentType('LCMUserCanEditContentAssignedToCategory', prisonAccessContentTypes, async (_user, fixture, contentPage, contentType) => {
    const nodeId = createPrisonAccessNode(fixture, {
      title: `${fixture.prefix} ${contentType}`,
      contentType,
      ownerPrisonIds: [fixture.terms.currentCategoryId],
    });
    await contentPage.openEditForm(nodeId);
    await contentPage.expectContentEditable();
  });

  testForEachContentType('LCMUserCanEditContentInPrisonCategory', prisonAccessContentTypes, async (user, fixture, contentPage, contentType) => {
    setUserPrisons(user.username, [fixture.terms.currentCategoryId]);
    const nodeId = createPrisonAccessNode(fixture, {
      title: `${fixture.prefix} ${contentType}`,
      contentType,
      ownerPrisonIds: [fixture.terms.siblingPrisonId],
    });
    await contentPage.openEditForm(nodeId);
    await contentPage.expectContentEditable();
  });

  testForEachContentType('LCMUserWithMultiplePrisonsCanEditContent', prisonAccessContentTypes, async (user, fixture, contentPage, contentType) => {
    setUserPrisons(user.username, [fixture.terms.currentPrisonId, fixture.terms.extraPrisonId]);
    const nodeId = createPrisonAccessNode(fixture, {
      title: `${fixture.prefix} ${contentType}`,
      contentType,
      ownerPrisonIds: [fixture.terms.extraPrisonId],
    });
    await contentPage.openEditForm(nodeId);
    await contentPage.expectContentEditable();
  });

  testForEachContentType('LCMUserCanEditContentInMultiplePrisons', prisonAccessContentTypes, async (_user, fixture, contentPage, contentType) => {
    const nodeId = createPrisonAccessNode(fixture, {
      title: `${fixture.prefix} ${contentType}`,
      contentType,
      ownerPrisonIds: [fixture.terms.currentPrisonId, fixture.terms.otherPrisonId],
    });
    await contentPage.openEditForm(nodeId);
    await contentPage.expectContentEditable();
  });

  testForEachContentType('LCMUserWithByPassPermissionCanEditContent', prisonAccessContentTypes, async (_user, fixture, contentPage, contentType) => {
    const nodeId = createPrisonAccessNode(fixture, {
      title: `${fixture.prefix} ${contentType}`,
      contentType,
      ownerPrisonIds: [fixture.terms.otherPrisonId],
    });
    await contentPage.openEditForm(nodeId);
    await contentPage.expectContentEditable();
  }, 'local_administrator');

  testForEachContentType('LCMUserCanEditOwnAuthoredContent', prisonAccessContentTypes, async (_user, fixture, contentPage, contentType) => {
    const nodeId = createPrisonAccessNode(fixture, {
      title: `${fixture.prefix} own ${contentType}`,
      contentType,
      authorUserId: fixture.userId,
    });
    await contentPage.openEditForm(nodeId);
    await contentPage.expectContentEditable();
  });

  testForEachContentType('LCMUserCannotEditOtherUserPrisonContent', prisonAccessContentTypes, async (_user, fixture, contentPage, contentType) => {
    const nodeId = createPrisonAccessNode(fixture, {
      title: `${fixture.prefix} restricted ${contentType}`,
      contentType,
      ownerPrisonIds: [fixture.terms.otherPrisonId],
      includedPrisonIds: [fixture.terms.otherPrisonId],
      excludedPrisonIds: [fixture.terms.otherPrisonId],
    });
    await contentPage.openEditForm(nodeId);
    await contentPage.expectContentRestricted();
    await contentPage.expectPrisonCheckboxEnabled(prisonFieldName, fixture.terms.currentPrisonName);
    await contentPage.expectPrisonCheckboxDisabled(prisonFieldName, fixture.terms.otherPrisonName);
    await contentPage.expectPrisonCheckboxEnabled(excludeFromPrisonFieldName, fixture.terms.currentPrisonName);
    await contentPage.expectPrisonCheckboxDisabled(excludeFromPrisonFieldName, fixture.terms.otherPrisonName);
  });

  test('LCMOtherPrisonsDoNotGetRemoved', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (_user, fixture, contentPage) => {
      const nodeId = createPrisonAccessNode(fixture, {
        title: `${fixture.prefix} preserve prison values`,
        ownerPrisonIds: [fixture.terms.currentPrisonId],
        includedPrisonIds: [fixture.terms.otherPrisonId],
        excludedPrisonIds: [fixture.terms.otherPrisonId],
      });
      await contentPage.openEditForm(nodeId);
      await contentPage.selectPrison(prisonFieldName, fixture.terms.currentPrisonName);
      await contentPage.selectPrison(excludeFromPrisonFieldName, fixture.terms.currentPrisonName);
      await contentPage.saveAndExpectUpdated();
      await contentPage.openEditForm(nodeId);
      await contentPage.expectPrisonCheckboxChecked(prisonFieldName, fixture.terms.currentPrisonName);
      await contentPage.expectPrisonCheckboxChecked(prisonFieldName, fixture.terms.otherPrisonName);
      await contentPage.expectPrisonCheckboxChecked(excludeFromPrisonFieldName, fixture.terms.currentPrisonName);
      await contentPage.expectPrisonCheckboxChecked(excludeFromPrisonFieldName, fixture.terms.otherPrisonName);
    });
  });

  test('LCMUserCannotEditUserPrisons', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (_user, fixture) => {
      const response = await page.goto(`/user/${fixture.userId}/edit`);
      expect(response?.status()).toBe(200);
      const prisonCheckboxes = page.locator('#edit-field-user-prisons input[type="checkbox"]');
      expect(await prisonCheckboxes.count()).toBeGreaterThan(0);
      for (let index = 0; index < await prisonCheckboxes.count(); index++) {
        await expect(prisonCheckboxes.nth(index)).toBeDisabled();
      }
    });
  });
});
