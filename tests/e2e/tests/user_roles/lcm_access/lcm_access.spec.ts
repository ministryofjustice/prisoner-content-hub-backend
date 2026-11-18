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

type PrisonAccessJourney = (
  user: TemporaryUser,
  fixture: PrisonAccessFixture,
  contentPage: NodeCreationPage,
) => Promise<void>;

async function runPrisonAccessJourney(
  page: Parameters<typeof loginViaUi>[0],
  testInfo: Parameters<typeof createStepRunner>[1],
  journey: PrisonAccessJourney,
  roles: DrupalRoleInput = 'moj_local_content_manager',
): Promise<void> {
  await runWithPrisonAccessUser(async (user, fixture) => {
    await loginViaUi(page, user.username, user.password, createStepRunner(page, testInfo));
    await journey(user, fixture, new NodeCreationPage(page));
  }, roles);
}

async function expectEditableContentForEveryBundle(
  contentPage: NodeCreationPage,
  fixture: PrisonAccessFixture,
  ownerPrisonIds: number[],
): Promise<void> {
  for (const contentType of fixture.contentTypes) {
    const nodeId = createPrisonAccessNode(fixture, {
      title: `${fixture.prefix} ${contentType}`,
      contentType,
      ownerPrisonIds,
    });
    await contentPage.openEditForm(nodeId);
    await contentPage.expectContentEditable();
  }
}

test.describe('prison access CMS journeys', () => {
  test.describe.configure({ mode: 'parallel', timeout: 120000 });

  test('userCanCreateNewContent', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (_user, fixture) => {
      const contentTypes = fixture.contentTypes.filter((contentType) => contentType !== 'homepage');
      expect(contentTypes.length).toBeGreaterThan(0);

      for (const contentType of contentTypes) {
        const contentPage = new NodeCreationPage(page, contentType);
        await contentPage.expectCreatePageAccessible();
        await contentPage.expectDefaultPrisonOwner(fixture.terms.currentPrisonId);
        await contentPage.expectContentEditable();
        await contentPage.expectPrisonCheckboxEnabled(prisonFieldName, fixture.terms.currentPrisonName);
        await contentPage.expectPrisonCheckboxEnabled(prisonFieldName, fixture.terms.otherPrisonName);
      }
    });
  });

  // This covers both PHP cases: testUserCanEditOwnPrisonContent and
  // testUserCanEditContentAssignedToCategory use the same category-owned setup.
  test('userCanEditContentAssignedToCategory', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (_user, fixture, contentPage) => {
      await expectEditableContentForEveryBundle(contentPage, fixture, [fixture.terms.currentCategoryId]);
    });
  });

  test('userCanEditContentInPrisonCategory', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (user, fixture, contentPage) => {
      setUserPrisons(user.username, [fixture.terms.currentCategoryId]);
      await expectEditableContentForEveryBundle(contentPage, fixture, [fixture.terms.siblingPrisonId]);
    });
  });

  test('userWithMultiplePrisonsCanEditContent', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (user, fixture, contentPage) => {
      setUserPrisons(user.username, [fixture.terms.currentPrisonId, fixture.terms.extraPrisonId]);
      await expectEditableContentForEveryBundle(contentPage, fixture, [fixture.terms.extraPrisonId]);
    });
  });

  test('userCanEditContentInMultiplePrisons', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (_user, fixture, contentPage) => {
      await expectEditableContentForEveryBundle(contentPage, fixture, [
        fixture.terms.currentPrisonId,
        fixture.terms.otherPrisonId,
      ]);
    });
  });

  test('userWithByPassPermissionCanEditContent', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (_user, fixture, contentPage) => {
      await expectEditableContentForEveryBundle(contentPage, fixture, [fixture.terms.otherPrisonId]);
    }, 'local_administrator');
  });

  test('userCanEditOwnAuthoredContent', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (user, fixture, contentPage) => {

      for (const contentType of fixture.contentTypes) {
        const nodeId = createPrisonAccessNode(fixture, {
          title: `${fixture.prefix} own ${contentType}`,
          contentType,
          authorUserId: fixture.userId,
        });
        await contentPage.openEditForm(nodeId);
        await contentPage.expectContentEditable();
      }
    });
  });

  test('userCannotEditOtherUserPrisonContent', async ({ page }, testInfo) => {
    await runPrisonAccessJourney(page, testInfo, async (_user, fixture, contentPage) => {

      for (const contentType of fixture.contentTypes) {
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
      }
    });
  });

  test('otherPrisonsDoNotGetRemoved', async ({ page }, testInfo) => {
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

  test('userCannotEditUserPrisons', async ({ page }, testInfo) => {
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
