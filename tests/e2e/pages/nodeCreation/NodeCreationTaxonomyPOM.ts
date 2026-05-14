import { Locator, Page } from '@playwright/test';

export class NodeCreationTaxonomyPOM {
  constructor(private readonly page: Page) {}

  private readonly categoryOrSeriesSelectors = [
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
    '[data-drupal-selector*="edit-field-moj-top-level-categories"]',
    '[id*="edit-field-moj-top-level-categories"]',
    '[data-drupal-selector*="edit-field-moj-series"]',
    '[id*="edit-field-moj-series"]',
  ];

  categoryField(): Locator {
    return this.page.locator(this.categoryOrSeriesSelectors.join(', ')).first();
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

  private categoryWrapperField(): Locator {
    return this.page.locator(
      [
        '[data-drupal-selector*="edit-field-moj-top-level-categories"]',
        '[id*="edit-field-moj-top-level-categories"]',
        '[data-drupal-selector*="edit-field-moj-series"]',
        '[id*="edit-field-moj-series"]',
      ].join(', ')
    );
  }

  private async selectFromSelect2(preferredValue: string): Promise<boolean> {
    const selectionTrigger = this.page
      .locator(
        [
          '.select2-selection',
          '.select2-container .selection',
          '[class*="select2"] [role="combobox"]',
        ].join(', ')
      )
      .first();

    if ((await selectionTrigger.count()) === 0) {
      return false;
    }

    await selectionTrigger.click();

    const openSearch = this.page
      .locator('.select2-container--open input.select2-search__field, .select2-dropdown input.select2-search__field')
      .first();

    if ((await openSearch.count()) === 0) {
      return false;
    }

    await openSearch.fill(preferredValue);
    await openSearch.press('Enter');
    return true;
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

    const categoryWrapper = this.categoryWrapperField();
    if ((await categoryWrapper.count()) > 0) {
      if (!(await categoryWrapper.first().isVisible())) {
        const openDetailsToggle = this.page
          .locator(
            [
              'summary:has-text("Category")',
              'summary:has-text("Series")',
              'button:has-text("Category")',
              'button:has-text("Series")',
            ].join(', ')
          )
          .first();

        if ((await openDetailsToggle.count()) > 0) {
          await openDetailsToggle.click();
        }
      }

      const selectedViaWrapperInput = categoryWrapper
        .first()
        .locator('input[type="search"], input[type="text"]')
        .first();

      if ((await selectedViaWrapperInput.count()) > 0 && (await selectedViaWrapperInput.isVisible())) {
        await selectedViaWrapperInput.click();
        await selectedViaWrapperInput.fill(preferredValue);
        await selectedViaWrapperInput.press('Enter');
        return;
      }

      if (await this.selectFromSelect2(preferredValue)) {
        return;
      }
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
