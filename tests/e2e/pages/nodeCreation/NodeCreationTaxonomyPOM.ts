import { Locator, Page } from '@playwright/test';

const defaultPreferredCategory = process.env.PLAYWRIGHT_E2E_CATEGORY_TERM ?? 'Animated shorts';

export class NodeCreationTaxonomyPOM {
  constructor(private readonly page: Page) {}

  private escapeRegex(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

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
        'select[name*="top_level_categories"]',
        'select[id*="top-level-categories"]',
        'select[name="field_moj_series[]"]',
        'select[name*="moj_series"]',
        'select[id*="moj-series"]',
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

  private async selectFromSelect2(root: Locator, preferredValue: string): Promise<boolean> {
    const selectionTrigger = root
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
    const selects = this.categorySelectField();
    const selectCount = await selects.count();
    for (let i = 0; i < selectCount; i++) {
      const nativeSelect = selects.nth(i);
      const selectedLabel = (await nativeSelect
        .locator('option:checked')
        .first()
        .innerText()
        .catch(() => ''))
        .trim();
      if (selectedLabel && !/^\-\s*none\s*\-$/i.test(selectedLabel)) {
        return true;
      }
    }

    return false;
  }

  private async hasSelectionInGroup(group: Locator, preferredValue?: string): Promise<boolean> {
    const selectedChoices = group.locator('.select2-selection__choice');
    if ((await selectedChoices.count()) > 0) {
      return true;
    }

    const renderedSelection = group.locator('.select2-selection__rendered').first();
    if ((await renderedSelection.count()) > 0) {
      const text = (await renderedSelection.innerText()).replace(/\s+/g, ' ').trim();
      if (text && !/^(-\s*none\s*-|select|search)/i.test(text)) {
        if (!preferredValue || new RegExp(`\\b${preferredValue.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'i').test(text)) {
          return true;
        }
        return true;
      }
    }

    if (preferredValue) {
      const selectedText = group.getByText(new RegExp(`^\\s*${preferredValue.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*$`, 'i'));
      if ((await selectedText.count()) > 0) {
        return true;
      }
    }

    const nativeSelect = group.locator('select').first();
    if ((await nativeSelect.count()) > 0) {
      const selectedLabel = (await nativeSelect
        .locator('option:checked')
        .first()
        .innerText()
        .catch(() => ''))
        .trim();
      if (selectedLabel && !/^\-\s*none\s*\-$/i.test(selectedLabel)) {
        return true;
      }
    }

    return false;
  }

  private async waitForTaxonomyControls(timeoutMs = 15000): Promise<void> {
    const start = Date.now();

    while (Date.now() - start < timeoutMs) {
      if ((await this.categorySelectField().count()) > 0) {
        return;
      }

      const autocomplete = this.categoryAutocompleteField().first();
      if ((await autocomplete.count()) > 0 && (await autocomplete.isVisible().catch(() => false))) {
        return;
      }

      const categoryGroupCombo = this.page.getByRole('group', { name: /^Category$/i }).first().locator('[role="combobox"]').first();
      if ((await categoryGroupCombo.count()) > 0) {
        return;
      }

      const seriesGroupCombo = this.page.getByRole('group', { name: /Series/i }).first().locator('[role="combobox"]').first();
      if ((await seriesGroupCombo.count()) > 0) {
        return;
      }

      await this.page.waitForTimeout(250);
    }
  }

  private async selectViaDomSelects(preferredValue: string): Promise<boolean> {
    return this.page.evaluate((preferred) => {
      const selectors = [
        'select[name="field_moj_top_level_categories[]"]',
        'select[name*="top_level_categories"]',
        'select[id*="top-level-categories"]',
        'select[name="field_moj_series[]"]',
        'select[name*="moj_series"]',
        'select[id*="moj-series"]',
      ];

      const selects = selectors
        .flatMap((selector) => Array.from(document.querySelectorAll(selector)))
        .filter((el): el is HTMLSelectElement => el instanceof HTMLSelectElement);

      for (const select of selects) {
        const options = Array.from(select.options);
        if (options.length === 0) {
          continue;
        }

        const exact = options.find((option) => option.text.trim().toLowerCase() === preferred.trim().toLowerCase());
        const fallback = options.find((option) => {
          const value = (option.value || '').trim();
          const label = option.text.trim();
          return value !== '' && !/^(_none|none)$/i.test(value) && !/^\-\s*none\s*\-$/i.test(label);
        });

        const chosen = exact ?? fallback;
        if (!chosen) {
          continue;
        }

        for (const option of options) {
          option.selected = false;
        }
        chosen.selected = true;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
      }

      return false;
    }, preferredValue);
  }

  private async selectFromTaxonomyGroup(groupName: RegExp, preferredValue: string): Promise<boolean> {
    const group = this.page.getByRole('group', { name: groupName }).first();
    if ((await group.count()) === 0) {
      return false;
    }

    const combo = group.locator('[role="combobox"]').first();
    if ((await combo.count()) === 0) {
      return false;
    }

    try {
      await combo.click({ timeout: 2000 });
    } catch {
      return false;
    }

    const comboInput = this.page
      .locator('.select2-container--open input.select2-search__field, .select2-dropdown input.select2-search__field')
      .first();
    if ((await comboInput.count()) > 0) {
      await comboInput.fill(preferredValue, { timeout: 2000 });

      const exactOption = this.page
        .locator('.select2-container--open .select2-results__option[role="option"], .select2-dropdown .select2-results__option[role="option"]')
        .filter({ hasText: new RegExp(`^\\s*${preferredValue.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*$`, 'i') })
        .first();

      if ((await exactOption.count()) > 0) {
        await exactOption.click({ timeout: 2000 });
        return true;
      } else {
        await comboInput.press('Enter', { timeout: 2000 });
      }

      const noResultsAlert = this.page
        .locator('.select2-container--open .select2-results__option, .select2-dropdown .select2-results__option')
        .filter({ hasText: /no results found/i })
        .first();
      if ((await noResultsAlert.count()) > 0) {
        await comboInput.fill('', { timeout: 2000 });
        await comboInput.press('ArrowDown', { timeout: 2000 });
        await comboInput.press('Enter', { timeout: 2000 });
        return true;
      }
    }

    const firstOption = this.page
      .locator('.select2-container--open .select2-results__option[role="option"]:not(.select2-results__option--disabled):not(:has-text("No results found")), .select2-dropdown .select2-results__option[role="option"]:not(.select2-results__option--disabled):not(:has-text("No results found"))')
      .first();
    if ((await firstOption.count()) > 0) {
      await firstOption.click({ timeout: 2000 });
      return true;
    }

    await combo.press('ArrowDown', { timeout: 2000 });
    await combo.press('Enter', { timeout: 2000 });
    return true;
  }

  private async selectFromCategoryListbox(preferredValue: string): Promise<boolean> {
    const listboxCandidates = this.page
      .locator([
        '#edit-field-moj-top-level-categories',
        '[data-drupal-selector*="edit-field-moj-top-level-categories"]',
        'select[name*="top_level_categories"]',
        'main [role="listbox"]',
      ].join(', '));

    const listbox = listboxCandidates.first();
    if ((await listbox.count()) === 0) {
      return false;
    }

    const listboxTag = (await listbox.evaluate((el) => el.tagName).catch(() => '')).toLowerCase();
    if (listboxTag === 'select') {
      const selectedByLabel = await listbox.selectOption({ label: preferredValue }).catch(() => []);
      if (selectedByLabel.length > 0) {
        return true;
      }

      const options = listbox.locator('option');
      const count = await options.count();
      for (let i = 0; i < count; i++) {
        const option = options.nth(i);
        const value = (await option.getAttribute('value')) ?? '';
        const label = (await option.innerText()).trim();
        if (!value || /^(_none|none)$/i.test(value) || /^\-\s*none\s*\-$/i.test(label)) {
          continue;
        }
        const selected = await listbox.selectOption(value).catch(() => []);
        if (selected.length > 0) {
          return true;
        }
      }
    }

    const preferredOption = listbox
      .getByRole('option')
      .filter({ hasText: new RegExp(`^\\s*${this.escapeRegex(preferredValue)}\\s*$`, 'i') })
      .first();
    if ((await preferredOption.count()) > 0) {
      await preferredOption.click({ timeout: 2000 });
      return true;
    }

    const firstOption = listbox.getByRole('option').first();
    if ((await firstOption.count()) > 0) {
      await firstOption.click({ timeout: 2000 });
      return true;
    }

    return false;
  }

  async selectFirstCategory(preferredValue = defaultPreferredCategory): Promise<void> {
    await this.waitForTaxonomyControls();

    if (await this.selectFromCategoryListbox(preferredValue)) {
      return;
    }

    if (await this.selectViaDomSelects(preferredValue)) {
      return;
    }

    if (await this.hasCategoryOrSeriesSelection()) {
      return;
    }

    const categoryNativeSelect = this.categorySelectField().first();
    if ((await categoryNativeSelect.count()) > 0) {
      const selectedByLabel = await categoryNativeSelect.selectOption({ label: preferredValue }).catch(() => []);
      if (selectedByLabel.length > 0 && (await this.hasCategoryOrSeriesSelection())) {
        return;
      }
    }

    const categorySelect2Input = this.categoryAutocompleteField();
    if ((await categorySelect2Input.count()) > 0 && (await categorySelect2Input.first().isVisible())) {
      await categorySelect2Input.first().click({ timeout: 2000 });
      await categorySelect2Input.first().fill(preferredValue, { timeout: 2000 });
      await categorySelect2Input.first().press('Enter', { timeout: 2000 });
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

      if (await this.selectFromSelect2(categoryWrapper.first(), preferredValue)) {
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

    if (await this.selectFromTaxonomyGroup(/^Category$/i, preferredValue)) {
      return;
    }

    if (await this.selectFromTaxonomyGroup(/Series/i, preferredValue)) {
      return;
    }

    const mainText = (await this.page.locator('main').innerText()).replace(/\s+/g, ' ').trim();

    throw new Error(
      'Unable to select a category or series on the create form. ' +
      `Main text snapshot: ${mainText}`
    );
  }
}
