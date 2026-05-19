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

    const preferredResult = this.page
      .locator('.select2-results__option[role="option"]:not(.select2-results__option--disabled)')
      .first();

    if ((await preferredResult.count()) > 0) {
      await preferredResult.click();
      return true;
    }

    // Fallback: pick the first available result regardless of label.
    await openSearch.fill('');
    const firstAvailableResult = this.page
      .locator('.select2-results__option[role="option"]:not(.select2-results__option--disabled)')
      .first();
    if ((await firstAvailableResult.count()) > 0) {
      await firstAvailableResult.click();
      return true;
    }

    return false;
  }

  private async hasCategoryOrSeriesSelection(): Promise<boolean> {
    const nativeSelect = this.categorySelectField().first();
    if ((await nativeSelect.count()) > 0) {
      const selectedValue = await nativeSelect.inputValue();
      if (selectedValue && !/^(_none|none)?$/i.test(selectedValue)) {
        return true;
      }

      const selectedLabel = (await nativeSelect
        .locator('option:checked')
        .first()
        .innerText()
        .catch(() => ''))
        .trim();
      if (selectedLabel && !/^-\s*none\s*-$/i.test(selectedLabel)) {
        return true;
      }
    }

    const select2Choice = this.page
      .locator('.select2-selection__rendered, .select2-selection__choice')
      .first();
    if ((await select2Choice.count()) > 0) {
      const renderedText = (await select2Choice.innerText()).trim();
      if (renderedText && !/^-\s*none\s*-$/i.test(renderedText)) {
        return true;
      }
    }

    const autocomplete = this.categoryAutocompleteField().first();
    if ((await autocomplete.count()) > 0) {
      const value = (await autocomplete.inputValue()).trim();
      if (value && !/^-\s*none\s*-$/i.test(value)) {
        return true;
      }
    }

    return false;
  }

  async selectFirstCategory(preferredValue = 'Animated shorts'): Promise<void> {
    const categoryNativeSelect = this.categorySelectField();
    if ((await categoryNativeSelect.count()) > 0) {
      const options = categoryNativeSelect.first().locator('option');
      const optionsCount = await options.count();
      if (optionsCount > 0) {
        const candidateValues: string[] = [];
        for (let i = 0; i < optionsCount; i++) {
          const option = options.nth(i);
          const value = (await option.getAttribute('value')) ?? '';
          const label = (await option.innerText()).trim();
          if (!value || /^_none$/i.test(value) || /^-\s*none\s*-$/i.test(label)) {
            continue;
          }
          candidateValues.push(value);
        }

        if (candidateValues.length > 0) {
          await categoryNativeSelect.first().selectOption(candidateValues[0]);
          if (await this.hasCategoryOrSeriesSelection()) {
            return;
          }
        }
      }
    }

    const categorySelect2Input = this.categoryAutocompleteField();
    if ((await categorySelect2Input.count()) > 0 && (await categorySelect2Input.first().isVisible())) {
      await categorySelect2Input.first().click();
      await categorySelect2Input.first().fill(preferredValue);
      await categorySelect2Input.first().press('Enter');
      if (await this.hasCategoryOrSeriesSelection()) {
        return;
      }
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
        if (await this.hasCategoryOrSeriesSelection()) {
          return;
        }
      }

      if (await this.selectFromSelect2(preferredValue)) {
        if (await this.hasCategoryOrSeriesSelection()) {
          return;
        }
      }
    }

    const categorySearch = this.page.getByRole('searchbox', { name: /category|series/i }).first();
    if ((await categorySearch.count()) > 0) {
      await categorySearch.click();
      await categorySearch.fill(preferredValue);
      await categorySearch.press('Enter');
      if (await this.hasCategoryOrSeriesSelection()) {
        return;
      }
    }

    const mainText = (await this.page.locator('main').innerText()).replace(/\s+/g, ' ').trim();
    throw new Error(
      'Unable to select a category or series on the create form. ' +
      `Main text snapshot: ${mainText}`
    );
  }
}
