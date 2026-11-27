import { expect, Locator, Page } from '@playwright/test';

const defaultPreferredCategory = process.env.PLAYWRIGHT_E2E_CATEGORY_TERM ?? 'Animated shorts';
const defaultPreferredPrisonOwner = process.env.PLAYWRIGHT_E2E_PRISON_OWNER_TERM ?? 'Bedford';
const defaultPreferredTopic = process.env.PLAYWRIGHT_E2E_TOPIC_TERM ?? 'Appeals';
const defaultPreferredSeries = process.env.PLAYWRIGHT_E2E_SERIES_TERM ?? defaultPreferredCategory;
const categoryFieldId = '#edit-field-moj-top-level-categories';
const topicFieldId = '#edit-field-topics';
const seriesFieldId = '#edit-field-moj-series';
const prisonOwnerFieldId = '#edit-field-prison-owner';

export class NodeCreationTaxonomyPOM {
  constructor(private readonly page: Page) {}

  categoryField(): Locator {
    return this.page.locator(categoryFieldId);
  }

  prisonOwnerField(): Locator {
    return this.page.locator(prisonOwnerFieldId);
  }

  topicField(): Locator {
    return this.page.locator(topicFieldId);
  }

  seriesField(): Locator {
    return this.page.locator(seriesFieldId);
  }

  categoryRemoveButton(preferredValue = defaultPreferredCategory): Locator {
    return this.tagifyRemoveButton(categoryFieldId, preferredValue);
  }

  prisonOwnerRemoveButton(preferredValue = defaultPreferredPrisonOwner): Locator {
    return this.tagifyRemoveButton(prisonOwnerFieldId, preferredValue);
  }

  topicRemoveButton(preferredValue = defaultPreferredTopic): Locator {
    return this.tagifyRemoveButton(topicFieldId, preferredValue);
  }

  seriesRemoveButton(preferredValue = defaultPreferredSeries): Locator {
    return this.tagifyRemoveButton(seriesFieldId, preferredValue);
  }

  private tagifyWrapper(fieldId: string): Locator {
    return this.page.locator(fieldId).locator('xpath=preceding-sibling::tags[1]');
  }

  private tagifyRemoveButton(fieldId: string, preferredValue: string): Locator {
    return this.tagifyWrapper(fieldId)
      .locator('tag')
      .filter({ hasText: preferredValue })
      .locator('.tagify__tag__removeBtn');
  }

  private async selectTagifyValue(fieldId: string, preferredValue: string): Promise<void> {
    const wrapper = this.tagifyWrapper(fieldId);
    const editor = wrapper.locator('.tagify__input[contenteditable]');
    await expect(editor).toBeVisible();
    await editor.fill(preferredValue);

    const suggestion = this.page.locator('.tagify__dropdown__item').filter({ hasText: preferredValue }).first();
    await expect(suggestion).toBeVisible();
    await suggestion.click();
    await expect(wrapper.locator('tag').filter({ hasText: preferredValue })).toBeVisible();
    await expect.poll(async () => {
      const selectedValues = await this.page.locator(fieldId).inputValue();
      try {
        const values = JSON.parse(selectedValues) as Array<{ entity_id?: string | number; label?: string }>;
        return values.some((value) => value.label === preferredValue && value.entity_id != null);
      } catch {
        return false;
      }
    }, {
      timeout: 30000,
      intervals: [100, 250, 500],
      message: `Tagify field ${fieldId} did not serialize the selected entity "${preferredValue}".`,
    }).toBe(true);
  }

  async selectPrisonOwner(preferredValue = defaultPreferredPrisonOwner): Promise<void> {
    await this.selectTagifyValue(prisonOwnerFieldId, preferredValue);
  }

  async selectFirstCategory(preferredValue = defaultPreferredCategory): Promise<void> {
    await this.selectTagifyValue(categoryFieldId, preferredValue);
  }

  async selectTopic(preferredValue: string): Promise<void> {
    await this.selectTagifyValue(topicFieldId, preferredValue);
  }

  async selectSeries(preferredValue: string): Promise<void> {
    await this.selectTagifyValue(seriesFieldId, preferredValue);
  }

  async selectFirstCategoryRemoveButton(preferredValue = defaultPreferredCategory): Promise<void> {
    await this.removeTagifyValue(this.categoryRemoveButton(preferredValue));
  }

  async selectPrisonOwnerRemoveButton(preferredValue = defaultPreferredPrisonOwner): Promise<void> {
    await this.removeTagifyValue(this.prisonOwnerRemoveButton(preferredValue));
  }

  async selectTopicRemoveButton(preferredValue = defaultPreferredTopic): Promise<void> {
    await this.removeTagifyValue(this.topicRemoveButton(preferredValue));
  }

  async selectSeriesRemoveButton(preferredValue = defaultPreferredSeries): Promise<void> {
    await this.removeTagifyValue(this.seriesRemoveButton(preferredValue));
  }

  private async removeTagifyValue(removeButton: Locator): Promise<void> {
    await expect(removeButton).toBeVisible();
    await removeButton.click();
  }
}
