import { expect, Page, Response } from '@playwright/test';

export class NodeCreationPage {
  constructor(private readonly page: Page) {}

  async gotoCreatePage(bundle: string): Promise<Response | null> {
    return this.page.goto(`/node/add/${bundle}`);
  }

  async expectCreatePageAccessible(bundle: string): Promise<void> {
    const response = await this.gotoCreatePage(bundle);
    expect(response?.status()).toBe(200);
    await expect(this.page).toHaveURL(new RegExp(`/node/add/${bundle}$`));
  }

  async expectCreatePageDenied(bundle: string): Promise<void> {
    const response = await this.gotoCreatePage(bundle);
    const deniedStatus = response?.status();
    const hasAccessDeniedText = /access denied/i.test(await this.page.locator('body').innerText());
    expect(deniedStatus === 403 || hasAccessDeniedText).toBeTruthy();
  }

  async fillTitle(title: string): Promise<void> {
    await this.page.locator('#edit-title-0-value').fill(title);
  }

  async fillSummary(summary: string): Promise<void> {
    const summaryById = this.page.locator('#edit-field-moj-short-summary-0-value');
    if ((await summaryById.count()) > 0) {
      await summaryById.first().fill(summary);
      return;
    }

    const summaryByName = this.page.locator('textarea[name="field_moj_short_summary[0][value]"]');
    if ((await summaryByName.count()) > 0) {
      await summaryByName.first().fill(summary);
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
      return;
    }
  }

  async uploadPdfFile(filePath: string): Promise<void> {
    const pdfFileInput = this.page.locator('input[name="files[field_moj_pdf_0]"]').first();
    
    if ((await pdfFileInput.count()) > 0) {
      await pdfFileInput.setInputFiles(filePath);

      const saveButton = this.page.getByRole('button', { name: /^Save$/ });
      
      try {
        let isEnabled = false;
        const startTime = Date.now();
        const timeout = 30000; // 30 seconds
        
        while (!isEnabled && (Date.now() - startTime) < timeout) {
          isEnabled = await saveButton.isEnabled();
          if (!isEnabled) {
            await this.page.waitForTimeout(500);
          }
        }
      } catch (e) {
      }
    }
  }

  async selectFirstCategory(): Promise<void> {
    const categoryNativeSelect = this.page.locator('select[name="field_moj_top_level_categories[]"]');
    if ((await categoryNativeSelect.count()) > 0) {
      const options = categoryNativeSelect.first().locator('option');
      const optionsCount = await options.count();
      if (optionsCount > 0) {
        const fallbackIndex = optionsCount > 1 ? 1 : 0;
        await categoryNativeSelect.first().selectOption({ index: fallbackIndex });
        return;
      }
    }

    const categorySelect2Input = this.page.locator('input[id*="field-moj-top-level-categories"][type="search"]');
    if ((await categorySelect2Input.count()) > 0) {
      await categorySelect2Input.first().click();
      await categorySelect2Input.first().fill('Animated shorts');
      await categorySelect2Input.first().press('Enter');
      return;
    }

    const categorySearch = this.page.getByRole('group', { name: 'Category' }).getByRole('searchbox').first();
    await categorySearch.click();
    await categorySearch.fill('Animated shorts');
    await categorySearch.press('Enter');
  }

  async save(): Promise<void> {
    await this.page.getByRole('button', { name: /^Save$/ }).click();
  }

  async expectNodeViewPage(title: string, body?: string): Promise<void> {
    await expect(this.page).toHaveURL(/\/node\/\d+(?:\/edit)?$/);

    const nodeMatch = this.page.url().match(/\/node\/(\d+)(?:\/edit)?$/);
    expect(nodeMatch).toBeTruthy();

    if (this.page.url().endsWith('/edit') && nodeMatch?.[1]) {
      await this.page.goto(`/node/${nodeMatch[1]}`);
    }

    await expect(this.page.getByRole('heading', { level: 1, name: title })).toBeVisible();

    if (body) {
      await expect(this.page.locator('main')).toContainText(body);
    }
  }
}
