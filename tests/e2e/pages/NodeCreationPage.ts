import { expect, Locator, Page, Response } from '@playwright/test';
import { NodeCreationFormPOM } from './nodeCreation/NodeCreationFormPOM';
import { NodeCreationNavigationPOM } from './nodeCreation/NodeCreationNavigationPOM';
import { NodeCreationTaxonomyPOM } from './nodeCreation/NodeCreationTaxonomyPOM';

export class NodeCreationPage {
  private readonly form: NodeCreationFormPOM;
  private readonly navigation: NodeCreationNavigationPOM;
  private readonly taxonomy: NodeCreationTaxonomyPOM;

  constructor(private readonly page: Page, private readonly bundle = 'page') {
    this.form = new NodeCreationFormPOM(page);
    this.navigation = new NodeCreationNavigationPOM(page);
    this.taxonomy = new NodeCreationTaxonomyPOM(page);
  }

  titleField(): Locator {
    return this.form.titleField();
  }

  summaryField(): Locator {
    return this.form.summaryField();
  }

  categoryField(): Locator {
    return this.taxonomy.categoryField();
  }

  categoryGroup(): Locator {
    return this.taxonomy.categoryGroup();
  }

  seriesField(): Locator {
    return this.taxonomy.seriesField();
  }

  pdfFileInput(): Locator {
    return this.form.pdfFileInput();
  }

  thumbnailImageInput(): Locator {
    return this.form.thumbnailImageInput();
  }

  seasonField(): Locator {
    return this.form.seasonField();
  }

  episodeField(): Locator {
    return this.form.episodeField();
  }

  releaseDateField(): Locator {
    return this.form.releaseDateField();
  }

  prisonCheckboxes(): Locator {
    return this.form.prisonCheckboxes();
  }

  prisonGroup(): Locator {
    return this.form.prisonGroup();
  }

  saveButton(): Locator {
    return this.form.saveButton();
  }

  async gotoCreatePage(bundle: string): Promise<Response | null> {
    return this.navigation.gotoCreatePage(bundle);
  }

  async expectCreatePageAccessible(bundle = this.bundle): Promise<void> {
    await this.navigation.expectCreatePageAccessible(bundle);
  }

  async expectCreatePageDenied(bundle = this.bundle): Promise<void> {
    await this.navigation.expectCreatePageDenied(bundle);
  }

  async openEditForm(nodeId: number): Promise<void> {
    const response = await this.page.goto(`/node/${nodeId}/edit`);
    expect(response?.status()).toBe(200);
  }

  prisonOwnerSelect(): Locator {
    return this.page.locator('select[name="field_prison_owner[]"]');
  }

  publicationControl(): Locator {
    return this.page
      .locator('select[name^="moderation_state["], input[name="status[value]"], select[name="status[value]"]')
      .first();
  }

  prisonCheckbox(fieldName: string, prisonName: string): Locator {
    const fieldId = fieldName.replaceAll('_', '-');
    return this.page.locator(`#edit-${fieldId}`).getByLabel(prisonName, { exact: true }).first();
  }

  async expectDefaultPrisonOwner(prisonId: number): Promise<void> {
    await expect(this.prisonOwnerSelect().locator('option:checked')).toHaveAttribute('value', String(prisonId));
  }

  async expectPrisonCheckboxEnabled(fieldName: string, prisonName: string): Promise<void> {
    await expect(this.prisonCheckbox(fieldName, prisonName)).toBeEnabled();
  }

  async expectPrisonCheckboxDisabled(fieldName: string, prisonName: string): Promise<void> {
    await expect(this.prisonCheckbox(fieldName, prisonName)).toBeDisabled();
  }

  async expectPrisonCheckboxChecked(fieldName: string, prisonName: string): Promise<void> {
    await expect(this.prisonCheckbox(fieldName, prisonName)).toBeChecked();
  }

  async selectPrison(fieldName: string, prisonName: string): Promise<void> {
    await this.prisonCheckbox(fieldName, prisonName).check();
  }

  async expectContentEditable(): Promise<void> {
    await expect(this.titleField()).toBeEnabled();
    await expect(this.publicationControl()).toBeEnabled();
  }

  async expectContentRestricted(): Promise<void> {
    await expect(this.titleField()).toBeDisabled();
    await expect(this.publicationControl()).toBeDisabled();
  }

  async expectSeriesSortingFormStates(episodeSeriesId: number, releaseDateSeriesId: number): Promise<void> {
    await this.expectCreatePageAccessible();
    await expect(this.seasonField()).toBeHidden();
    await expect(this.episodeField()).toBeHidden();
    await expect(this.releaseDateField()).toBeHidden();

    await this.selectSeriesById(episodeSeriesId);
    await expect(this.seasonField()).toBeVisible();
    await expect(this.episodeField()).toBeVisible();
    await expect(this.releaseDateField()).toBeHidden();

    await this.selectSeriesById(releaseDateSeriesId);
    await expect(this.seasonField()).toBeHidden();
    await expect(this.episodeField()).toBeHidden();
    await expect(this.releaseDateField()).toBeVisible();
  }

  async expectCategoryGroupVisible(): Promise<void> {
    await this.expectCreatePageAccessible();
    await expect(this.categoryGroup()).toBeVisible();
  }

  async fillTitle(title: string): Promise<void> {
    await this.form.fillTitle(title);
  }

  async fillSummary(summary: string): Promise<void> {
    await this.form.fillSummary(summary);
  }

  async fillBody(body: string): Promise<void> {
    await this.form.fillBody(body);
  }

  async uploadPdfFile(filePath: string): Promise<void> {
    await this.form.uploadPdfFile(filePath);
  }

  async selectFirstCategory(preferredValue?: string): Promise<void> {
    await this.taxonomy.selectFirstCategory(preferredValue);
  }

  async selectSeriesById(termId: number): Promise<void> {
    await this.taxonomy.selectSeriesById(termId);
  }

  async selectPrisonOwner(preferredValue?: string): Promise<void> {
    await this.taxonomy.selectPrisonOwner(preferredValue);
  }

  async selectFirstPrison(): Promise<void> {
    await this.form.selectFirstPrison();
  }

  async selectPrisonByLabel(prisonLabel: string): Promise<void> {
    await this.form.selectPrisonByLabel(prisonLabel);
  }

  async getPrisonSelectionCount(): Promise<number> {
    return this.form.getPrisonSelectionCount();
  }

  async fillUnpublishOnDate(date: string): Promise<void> {
    await this.form.fillUnpublishOnDate(date);
  }

  revisionLogMessageField(): Locator {
    return this.form.revisionLogMessageField();
  }

  detailsSummary(label: string): Locator {
    return this.form.detailsSummary(label);
  }

  async save(): Promise<void> {
    await this.form.save();
  }

  async saveAndExpectUpdated(): Promise<void> {
    await this.form.save();
    await expect(this.page.locator('body')).toContainText('has been updated');
  }

  async expectNodeViewPage(title: string, body?: string): Promise<void> {
    await this.navigation.expectNodeViewPage(title, body);
  }
}
