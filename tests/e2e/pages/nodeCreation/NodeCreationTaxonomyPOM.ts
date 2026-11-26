import { Locator, Page } from '@playwright/test';

const defaultPreferredCategory = process.env.PLAYWRIGHT_E2E_CATEGORY_TERM ?? 'Animated shorts';
const defaultPreferredPrisonOwner = process.env.PLAYWRIGHT_E2E_PRISON_OWNER_TERM ?? 'Bedford';

export class NodeCreationTaxonomyPOM {
  constructor(private readonly page: Page) {}

  categoryGroup(): Locator {
    return this.page.getByRole('group', { name: /^Category$/i }).first();
  }

  categoryField(): Locator {
    return this.categoryGroup().locator([
      'select[name="field_moj_top_level_categories[]"]',
      'input[id*="field-moj-top-level-categories"][type="search"]',
      'input[id*="field-moj-top-level-categories"][type="text"]',
      'input[data-drupal-selector*="edit-field-moj-top-level-categories"][type="text"]',
      'input[name^="field_moj_top_level_categories"][name*="[target_id]"]',
      'tags.tagify.field_moj_top_level_categories .tagify__input[contenteditable]',
      '[role="combobox"]',
    ].join(', ')).first();
  }

  seriesField(): Locator {
    return this.page.getByLabel(/^Series$/i).first();
  }

  async selectSeriesById(termId: number): Promise<void> {
    await this.seriesField().selectOption(String(termId));
  }

  private async hasSelectionInGroup(group: Locator): Promise<boolean> {
    if ((await group.locator('.select2-selection__choice').count()) > 0) {
      return true;
    }

    const renderedSelection = group.locator('.select2-selection__rendered').first();
    if ((await renderedSelection.count()) > 0) {
      const text = (await renderedSelection.innerText()).replace(/\s+/g, ' ').trim();
      if (text && !/^(-\s*none\s*-|select|search)/i.test(text)) {
        return true;
      }
    }

    const selectedOption = group.locator('select option:checked').first();
    if ((await selectedOption.count()) > 0) {
      const text = (await selectedOption.innerText()).trim();
      return Boolean(text && !/^(-\s*none\s*-|select)/i.test(text));
    }

    return false;
  }

  private async selectFromTaxonomyGroup(groupName: RegExp, preferredValue: string): Promise<boolean> {
    const group = this.page.getByRole('group', { name: groupName }).first();
    if ((await group.count()) === 0) {
      return false;
    }

    const nativeSelect = group.locator('select').first();
    if ((await nativeSelect.count()) > 0) {
      const options = await nativeSelect.locator('option').evaluateAll((elements) =>
        elements.map((element) => ({
          value: (element as HTMLOptionElement).value,
          label: (element as HTMLOptionElement).label.trim(),
        })).filter(({ value, label }) => value && !/^_none$/i.test(value) && !/^-\s*none\s*-$/i.test(label)),
      );
      const option = options.find(({ label }) => label.toLowerCase() === preferredValue.toLowerCase())
        ?? options.find(({ label }) => label.toLowerCase().includes(preferredValue.toLowerCase()))
        ?? options[0];
      if (option) {
        await nativeSelect.selectOption(option.value);
        return this.hasSelectionInGroup(group);
      }
    }

    const combo = group.locator('[role="combobox"], .select2-selection').first();
    if ((await combo.count()) === 0) {
      return false;
    }
    await combo.click();

    const openSearch = this.page
      .locator('.select2-container--open input.select2-search__field, .select2-dropdown input.select2-search__field')
      .first();
    if ((await openSearch.count()) > 0) {
      await openSearch.fill(preferredValue);
    }

    const availableOptions = this.page.locator(
      '.select2-container--open .select2-results__option[role="option"]:not(.select2-results__option--disabled):not(:has-text("No results found")), .select2-dropdown .select2-results__option[role="option"]:not(.select2-results__option--disabled):not(:has-text("No results found"))',
    );
    const escapedValue = preferredValue.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const exactOption = availableOptions
      .filter({ hasText: new RegExp(`^\\s*${escapedValue}\\s*$`, 'i') })
      .first();

    if ((await exactOption.count()) > 0) {
      await exactOption.click();
    } else {
      if ((await openSearch.count()) > 0) {
        await openSearch.fill('');
      }

      const firstAvailableOption = availableOptions.first();
      if ((await firstAvailableOption.count()) > 0) {
        await firstAvailableOption.click();
      } else if ((await openSearch.count()) > 0) {
        await openSearch.press('ArrowDown');
        await openSearch.press('Enter');
      }
    }

    return this.hasSelectionInGroup(group);
  }

  async selectFirstCategory(preferredValue = defaultPreferredCategory): Promise<void> {
    if (!(await this.selectFromTaxonomyGroup(/^Category$/i, preferredValue))) {
      throw new Error(`Unable to select a category on the create form: "${preferredValue}".`);
    }
  }

  async selectPrisonOwner(preferredValue = defaultPreferredPrisonOwner): Promise<void> {
    if (!(await this.selectFromTaxonomyGroup(/^Prison owner$/i, preferredValue))) {
      throw new Error(`Unable to select prison owner "${preferredValue}" on the create form.`);
    }
  }
}
