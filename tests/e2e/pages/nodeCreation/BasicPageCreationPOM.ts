import { Page } from '@playwright/test';
import { NodeCreationFormPOM } from './NodeCreationFormPOM';
import { NodeCreationNavigationPOM } from './NodeCreationNavigationPOM';
import { NodeCreationTaxonomyPOM } from './NodeCreationTaxonomyPOM';

export class BasicPageCreationPOM {
  private readonly form: NodeCreationFormPOM;
  private readonly navigation: NodeCreationNavigationPOM;
  private readonly taxonomy: NodeCreationTaxonomyPOM;

  constructor(page: Page) {
    this.form = new NodeCreationFormPOM(page);
    this.navigation = new NodeCreationNavigationPOM(page);
    this.taxonomy = new NodeCreationTaxonomyPOM(page);
  }

  async expectCreatePageAccessible(): Promise<void> {
    await this.navigation.expectCreatePageAccessible('page');
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

  async selectFirstCategory(preferredValue?: string): Promise<void> {
    await this.taxonomy.selectFirstCategory(preferredValue);
  }

  async save(): Promise<void> {
    await this.form.save();
  }

  async expectNodeViewPage(title: string, body?: string): Promise<void> {
    await this.navigation.expectNodeViewPage(title, body);
  }
}
