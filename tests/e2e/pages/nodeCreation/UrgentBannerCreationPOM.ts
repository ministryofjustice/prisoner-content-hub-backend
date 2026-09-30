import { Page } from '@playwright/test';
import { NodeCreationFormPOM } from './NodeCreationFormPOM';
import { NodeCreationNavigationPOM } from './NodeCreationNavigationPOM';
import { NodeCreationTaxonomyPOM } from './NodeCreationTaxonomyPOM';

export class UrgentBannerCreationPOM {
  private readonly form: NodeCreationFormPOM;
  private readonly navigation: NodeCreationNavigationPOM;
  private readonly taxonomy: NodeCreationTaxonomyPOM;

  constructor(page: Page) {
    this.form = new NodeCreationFormPOM(page);
    this.navigation = new NodeCreationNavigationPOM(page);
    this.taxonomy = new NodeCreationTaxonomyPOM(page);
  }

  async expectCreatePageAccessible(): Promise<void> {
    await this.navigation.expectCreatePageAccessible('urgent_banner');
  }

  async fillTitle(title: string): Promise<void> {
    await this.form.fillTitle(title);
  }

  async fillUnpublishOnDate(date: string): Promise<void> {
    await this.form.fillUnpublishOnDate(date);
  }

  revisionLogMessageField() {
    return this.form.revisionLogMessageField();
  }

  detailsSummary(label: string) {
    return this.form.detailsSummary(label);
  }

  prisonGroup() {
    return this.form.prisonGroup();
  }

  async selectFirstPrison(): Promise<void> {
    await this.form.selectFirstPrison();
  }

  async getPrisonSelectionCount(): Promise<number> {
    return await this.form.getPrisonSelectionCount();
  }

  async selectPrisonOwner(preferredValue?: string): Promise<void> {
    await this.taxonomy.selectPrisonOwner(preferredValue);
  }

  async save(): Promise<void> {
    await this.form.save();
  }

  async expectNodeViewPage(title: string): Promise<void> {
    await this.navigation.expectNodeViewPage(title);
  }
}