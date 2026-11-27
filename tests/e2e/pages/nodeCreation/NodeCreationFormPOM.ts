import { expect, Locator, Page } from '@playwright/test';
import { NodeCreationNavigationPOM } from './NodeCreationNavigationPOM';
import { NodeCreationTaxonomyPOM } from './NodeCreationTaxonomyPOM';

export class NodeCreationFormPOM {
  private readonly navigation: NodeCreationNavigationPOM;
  private readonly taxonomy: NodeCreationTaxonomyPOM;

  constructor(private readonly page: Page) {
    this.navigation = new NodeCreationNavigationPOM(page);
    this.taxonomy = new NodeCreationTaxonomyPOM(page);
  }

  async expectCreatePageAccessible(bundle = 'page'): Promise<void> {
    await this.navigation.expectCreatePageAccessible(bundle);
  }

  async expectNodeViewPage(title: string, body?: string): Promise<void> {
    await this.navigation.expectNodeViewPage(title, body);
  }

  titleField(): Locator {
    return this.page.locator('#edit-title-0-value').first();
  }

  summaryField(): Locator {
    return this.page.locator('#edit-field-summary-0-value');
  }

  pdfFileInput(): Locator {
    return this.page.locator('#edit-field-moj-pdf-0-upload');
  }

  thumbnailImageInput(): Locator {
    return this.page.locator('#edit-field-moj-thumbnail-image-0-upload');
  }

  seasonField(): Locator {
    return this.page.locator('#edit-field-moj-season-0-value');
  }

  episodeField(): Locator {
    return this.page.locator('#edit-field-moj-episode-0-value');
  }

  releaseDateField(): Locator {
    return this.page.locator('#edit-field-release-date-0-value-date');
  }

  unpublishOnDateField(): Locator {
    return this.page.locator('#edit-unpublish-on-0-value-date');
  }

  revisionLogMessageField(): Locator {
    return this.page.locator('#edit-revision-log-0-value');
  }

  categoryField(): Locator {
    return this.taxonomy.categoryField();
  }

  prisonOwnerField(): Locator {
    return this.taxonomy.prisonOwnerField();
  }

  topicField(): Locator {
    return this.taxonomy.topicField();
  }

  seriesField(): Locator {
    return this.taxonomy.seriesField();
  }

  async selectFirstCategory(preferredValue?: string): Promise<void> {
    await this.taxonomy.selectFirstCategory(preferredValue);
  }

  async selectPrisonOwner(preferredValue?: string): Promise<void> {
    await this.taxonomy.selectPrisonOwner(preferredValue);
  }

  async selectTopic(preferredValue: string): Promise<void> {
    await this.taxonomy.selectTopic(preferredValue);
  }

  async selectSeries(preferredValue: string): Promise<void> {
    await this.taxonomy.selectSeries(preferredValue);
  }

  async selectFirstCategoryRemoveButton(): Promise<void> {
    await this.taxonomy.selectFirstCategoryRemoveButton();
  }

  async selectPrisonOwnerRemoveButton(): Promise<void> {
    await this.taxonomy.selectPrisonOwnerRemoveButton();
  }

  async selectTopicRemoveButton(): Promise<void> {
    await this.taxonomy.selectTopicRemoveButton();
  }

  async selectSeriesRemoveButton(): Promise<void> {
    await this.taxonomy.selectSeriesRemoveButton();
  }

  detailsSummary(label: string): Locator {
    const sectionSelectors: Record<string, string> = {
      'Prison owner': '#edit-group-prison-owner > summary',
      'Published date': '#edit-group-published-date > summary',
      'Authoring information': '#edit-author > summary',
    };
    const selector = sectionSelectors[label];
    if (!selector) {
      throw new Error(`Unknown form section "${label}".`);
    }
    return this.page.locator(selector);
  }

  prisonCheckboxes(): Locator {
    return this.prisonGroup().locator('input[type="checkbox"][id^="edit-field-prisons-"]');
  }

  prisonRadioButtons(): Locator {
    return this.prisonGroup().locator('input[type="radio"][id^="edit-field-prisons-"]');
  }

  prisonGroup(): Locator {
    return this.page.locator('#edit-field-prisons--wrapper');
  }

  async selectPrisonByLabel(prisonLabel: string): Promise<void> {
    const escapedLabel = prisonLabel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const label = this.prisonGroup()
      .locator('label[for]')
      .filter({ hasText: new RegExp(`^\\s*${escapedLabel}\\s*$`, 'i') })
      .first();
    await expect(label).toBeVisible();

    const inputId = await label.getAttribute('for');
    if (!inputId) {
      throw new Error(`Prison option "${prisonLabel}" has no associated input ID.`);
    }

    const input = this.page.locator(`#${inputId}`);
    await label.click();
    await expect(input).toBeChecked();
  }

  async selectFirstPrison(): Promise<void> {
    const selectablePrisons = this.prisonGroup().locator('input[id^="edit-field-prisons-"]');

    const firstPrison = selectablePrisons.first();
    await expect(firstPrison).toBeVisible({ timeout: 30000 });
    const firstPrisonId = await firstPrison.getAttribute('id');

    if (firstPrisonId) {
      const firstPrisonLabel = this.page.locator(`label[for="${firstPrisonId}"]`).first();

      if ((await firstPrisonLabel.count()) > 0) {
        await firstPrisonLabel.scrollIntoViewIfNeeded();
        await firstPrisonLabel.click();
        await expect(firstPrison).toBeChecked();
        return;
      }
    }

    await firstPrison.scrollIntoViewIfNeeded();
    await firstPrison.check({ force: true });
    await expect(firstPrison).toBeChecked();
  }

  async getPrisonSelectionCount(): Promise<number> {
    return await this.prisonGroup().locator('input[id^="edit-field-prisons-"]:checked').count();
  }

  saveButton(): Locator {
    return this.page.locator('#edit-submit');
  }

  async fillTitle(title: string): Promise<void> {
    await this.titleField().fill(title);
  }

  async fillSummary(summary: string): Promise<void> {
    await this.summaryField().fill(summary);
  }

  async fillBody(body: string): Promise<void> {
    const bodyField = this.page.locator('#edit-field-main-body-content-0-value, #edit-body-0-value').first();

    if (await bodyField.isVisible()) {
      await bodyField.fill(body);
      return;
    }

    const richTextEditor = bodyField
      .locator('xpath=following-sibling::div[contains(@class, "ck-editor")]')
      .locator('.ck-editor__editable');
    await expect(richTextEditor).toBeVisible();
    await richTextEditor.fill(body);
  }

  async fillUnpublishOnDate(date: string): Promise<void> {
    const unpublishOnDateField = this.unpublishOnDateField();
    if ((await unpublishOnDateField.count()) > 0) {
      await unpublishOnDateField.fill(date);
    }
  }

  async uploadPdfFile(filePath: string): Promise<void> {
    const pdfFileInput = this.pdfFileInput();
    await expect(pdfFileInput).toHaveCount(1);
    await pdfFileInput.setInputFiles(filePath);

    const managedFile = this.page
      .locator('[data-drupal-selector="edit-field-moj-pdf-0-fids"]')
      .locator('xpath=ancestor::div[contains(@class, "js-form-managed-file")]');
    await expect(managedFile.locator('.file a')).toBeVisible({ timeout: 30000 });
  }

  async save(): Promise<void> {
    await this.saveButton().click();
  }
}
