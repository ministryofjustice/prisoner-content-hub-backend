import { Locator, Page } from '@playwright/test';

export class NodeCreationTaxonomyPOM {
  constructor(private readonly page: Page) {}

  categoryField(): Locator {
    return this.page
      .locator(
        [
          'select[name="field_moj_top_level_categories[]"]',
          'input[id*="field-moj-top-level-categories"][type="search"]',
          'input[id*="field-moj-top-level-categories"][type="text"]',
          'input[data-drupal-selector*="edit-field-moj-top-level-categories"][type="text"]',
          'input[name^="field_moj_top_level_categories"][name*="[target_id]"]',
          'input[name*="top_level_categories"][name*="[target_id]"]',
          'select[name="field_moj_series[]"]',
          'input[id*="field-moj-series"][type="search"]',
          'input[id*="field-moj-series"][type="text"]',
          'input[data-drupal-selector*="edit-field-moj-series"][type="text"]',
          'input[name^="field_moj_series"][name*="[target_id]"]',
        ].join(', ')
      )
      .first();
  }

  private categorySelectField(): Locator {
    return this.page.locator(
      [
        'select[name="field_moj_top_level_categories[]"]',
        'select[name="field_moj_series[]"]',
      ].join(', ')
    );
  }

  private categoryAutocompleteField(): Locator {
    return this.page.locator(
      [
        'input[id*="field-moj-top-level-categories"][type="search"]',
        'input[id*="field-moj-top-level-categories"][type="text"]',
        'input[data-drupal-selector*="edit-field-moj-top-level-categories"][type="text"]',
        'input[name^="field_moj_top_level_categories"][name*="[target_id]"]',
        'input[name*="top_level_categories"][name*="[target_id]"]',
        'input[id*="field-moj-series"][type="search"]',
        'input[id*="field-moj-series"][type="text"]',
        'input[data-drupal-selector*="edit-field-moj-series"][type="text"]',
        'input[name^="field_moj_series"][name*="[target_id]"]',
      ].join(', ')
    );
  }

  async selectFirstCategory(preferredValue = 'Animated shorts'): Promise<void> {
    const categoryNativeSelect = this.categorySelectField();
    if ((await categoryNativeSelect.count()) > 0 && (await categoryNativeSelect.first().isVisible())) {
      const options = categoryNativeSelect.first().locator('option');
      const optionsCount = await options.count();
      if (optionsCount > 0) {
        const fallbackIndex = optionsCount > 1 ? 1 : 0;
        await categoryNativeSelect.first().selectOption({ index: fallbackIndex });
        return;
      }
    }

    const categorySelect2Input = this.categoryAutocompleteField();
    if ((await categorySelect2Input.count()) > 0 && (await categorySelect2Input.first().isVisible())) {
      await categorySelect2Input.first().click();
      await categorySelect2Input.first().fill(preferredValue);
      await categorySelect2Input.first().press('Enter');
      return;
    }

    const categorySearch = this.page.getByRole('searchbox', { name: /category|series/i }).first();
    if ((await categorySearch.count()) > 0) {
      await categorySearch.click();
      await categorySearch.fill(preferredValue);
      await categorySearch.press('Enter');
      return;
    }

    throw new Error('Unable to find a category or series field on the create form.');
  }
}
