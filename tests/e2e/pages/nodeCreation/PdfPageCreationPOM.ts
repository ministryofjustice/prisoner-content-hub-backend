import { Locator, Page } from '@playwright/test';
import { NodeCreationFormPOM } from './NodeCreationFormPOM';
import { NodeCreationNavigationPOM } from './NodeCreationNavigationPOM';
import { NodeCreationTaxonomyPOM } from './NodeCreationTaxonomyPOM';

export class PdfPageCreationPOM {
  private readonly form: NodeCreationFormPOM;
  private readonly navigation: NodeCreationNavigationPOM;
  private readonly taxonomy: NodeCreationTaxonomyPOM;

  constructor(page: Page) {
    this.form = new NodeCreationFormPOM(page);
    this.navigation = new NodeCreationNavigationPOM(page);
    this.taxonomy = new NodeCreationTaxonomyPOM(page);
  }

  async expectCreatePageAccessible(): Promise<void> {
    await this.navigation.expectCreatePageAccessible('moj_pdf_item');
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

  async fillTitle(title: string): Promise<void> {
    await this.form.fillTitle(title);
  }

  async fillSummary(summary: string): Promise<void> {
    await this.form.fillSummary(summary);
  }

  async selectFirstCategory(preferredValue?: string): Promise<void> {
    await this.taxonomy.selectFirstCategory(preferredValue);
  }

  async uploadPdfFile(filePath: string): Promise<void> {
    await this.form.uploadPdfFile(filePath);
  }

  async save(): Promise<void> {
    await this.form.save();
  }

  async expectNodeViewPage(title: string): Promise<void> {
    await this.navigation.expectNodeViewPage(title);
  }
}
