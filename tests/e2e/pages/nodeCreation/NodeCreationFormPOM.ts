import { Locator, Page } from '@playwright/test';

export class NodeCreationFormPOM {
  constructor(private readonly page: Page) {}

  titleField(): Locator {
    return this.page.locator('#edit-title-0-value').first();
  }

  summaryField(): Locator {
    return this.page
      .locator(
        [
          '#edit-field-summary-0-value',
          'textarea[name="field_summary[0][value]"]',
          '#edit-field-moj-short-summary-0-value',
          'textarea[name="field_moj_short_summary[0][value]"]',
        ].join(', ')
      )
      .first();
  }

  pdfFileInput(): Locator {
    return this.page
      .locator('input[type="file"][name="files[field_moj_pdf_0]"]')
      .first();
  }

  thumbnailImageInput(): Locator {
    return this.page
      .locator('input[type="file"][name="files[field_moj_thumbnail_image_0]"]')
      .first();
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

  prisonCheckboxes(): Locator {
    return this.page.locator('input[type="checkbox"][name^="field_prisons"]');
  }

  saveButton(): Locator {
    return this.page.getByRole('button', { name: /^Save$/ });
  }

  async fillTitle(title: string): Promise<void> {
    await this.titleField().fill(title);
  }

  async fillSummary(summary: string): Promise<void> {
    const summaryField = this.summaryField();
    if ((await summaryField.count()) > 0) {
      await summaryField.fill(summary);
      return;
    }

    await this.page.getByRole('textbox', { name: /summary/i }).first().fill(summary);
  }

  async fillBody(body: string): Promise<void> {
    const bodyTextarea = this.page.locator('textarea[name="body[0][value]"]');

    if ((await bodyTextarea.count()) > 0 && (await bodyTextarea.first().isVisible())) {
      await bodyTextarea.first().fill(body);
      return;
    }

    const richTextEditor = this.page.locator('.ck-editor__editable[role="textbox"]');
    if ((await richTextEditor.count()) > 0) {
      await richTextEditor.first().click();
      await richTextEditor.first().fill(body);
    }
  }

  async uploadPdfFile(filePath: string): Promise<void> {
    const pdfFileInput = this.pdfFileInput();

    if ((await pdfFileInput.count()) > 0) {
      await pdfFileInput.setInputFiles(filePath);

      const saveButton = this.saveButton();
      const startTime = Date.now();
      const timeout = 30000;
      let isEnabled = await saveButton.isEnabled();

      while (!isEnabled && Date.now() - startTime < timeout) {
        await this.page.waitForTimeout(500);
        isEnabled = await saveButton.isEnabled();
      }
    }
  }

  async save(): Promise<void> {
    await this.saveButton().click();
  }
}
