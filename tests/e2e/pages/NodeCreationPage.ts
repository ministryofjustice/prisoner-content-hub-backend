import { Locator, Page, Response } from '@playwright/test';
import { NodeCreationFormPOM } from './nodeCreation/NodeCreationFormPOM';
import { NodeCreationNavigationPOM } from './nodeCreation/NodeCreationNavigationPOM';
import { NodeCreationTaxonomyPOM } from './nodeCreation/NodeCreationTaxonomyPOM';

export class NodeCreationPage {
  private readonly form: NodeCreationFormPOM;
  private readonly navigation: NodeCreationNavigationPOM;
  private readonly taxonomy: NodeCreationTaxonomyPOM;

  constructor(private readonly page: Page) {
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

  saveButton(): Locator {
    return this.form.saveButton();
  }

  async gotoCreatePage(bundle: string): Promise<Response | null> {
    return this.navigation.gotoCreatePage(bundle);
  }

  async expectCreatePageAccessible(bundle: string): Promise<void> {
    await this.navigation.expectCreatePageAccessible(bundle);
  }

  async expectCreatePageDenied(bundle: string): Promise<void> {
    await this.navigation.expectCreatePageDenied(bundle);
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

  async selectFirstCategory(): Promise<void> {
    await this.taxonomy.selectFirstCategory();
  }

  async save(): Promise<void> {
    await this.form.save();
  }

  async expectNodeViewPage(title: string, body?: string): Promise<void> {
    await this.navigation.expectNodeViewPage(title, body);
  }
}
