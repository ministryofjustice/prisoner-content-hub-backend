import { expect, Page, Response } from '@playwright/test';

export class NodeCreationNavigationPOM {
  constructor(private readonly page: Page) {}

  private isVisibleSelector(selector: string): Promise<boolean> {
    return this.page
      .locator(selector)
      .first()
      .isVisible()
      .catch(() => false);
  }

  private taxonomyControls() {
    return this.page.locator(
      [
        'select[name*="top_level_categories"]',
        'select[name*="moj_series"]',
        '[data-drupal-selector*="edit-field-moj-top-level-categories"]',
        '[data-drupal-selector*="edit-field-moj-series"]',
      ].join(', ')
    );
  }

  private async isBundleFullyRendered(bundle: string): Promise<boolean> {
    const summaryVisible = await this.isVisibleSelector(
      [
        '#edit-field-summary-0-value',
        'textarea[name="field_summary[0][value]"]',
        '#edit-field-moj-short-summary-0-value',
        'textarea[name="field_moj_short_summary[0][value]"]',
      ].join(', ')
    );

    const saveVisible = await this.page.getByRole('button', { name: /^Save$/ }).isVisible().catch(() => false);
    if (!summaryVisible || !saveVisible) {
      return false;
    }

    if ((await this.taxonomyControls().count()) === 0) {
      return false;
    }

    if (bundle === 'page') {
      return await this.page.evaluate(() => {
        const selectors = [
          '.ck-editor__editable[role="textbox"]',
          'textarea[name="body[0][value]"]',
          'textarea[name="field_main_body_content[0][value]"]',
          '#edit-field-main-body-content-0-value',
          '#edit-body-0-value',
        ];

        const isVisible = (el: Element): boolean => {
          if (!(el instanceof HTMLElement)) {
            return false;
          }

          const style = window.getComputedStyle(el);
          const rect = el.getBoundingClientRect();
          return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
        };

        return selectors.some((selector) =>
          Array.from(document.querySelectorAll(selector)).some((el) => isVisible(el))
        );
      });
    }

    if (bundle === 'moj_pdf_item') {
      const pdfVisible = await this.isVisibleSelector('input[type="file"][name="files[field_moj_pdf_0]"]');
      const thumbnailVisible = await this.isVisibleSelector('input[type="file"][name="files[field_moj_thumbnail_image_0]"]');
      return pdfVisible && thumbnailVisible;
    }

    return true;
  }

  private async isCreateFormInteractive(bundle: string): Promise<boolean> {
    const heading = this.page.getByRole('heading', { level: 1, name: new RegExp(`create\\s+${bundle === 'page' ? 'basic page' : 'pdf'}`, 'i') });
    if ((await heading.count()) === 0) {
      return false;
    }

    const titleField = this.page.locator('#edit-title-0-value, input[name="title[0][value]"]').first();
    if ((await titleField.count()) === 0 || !(await titleField.isVisible().catch(() => false))) {
      return false;
    }

    const taxonomyControls = this.taxonomyControls();
    if ((await taxonomyControls.count()) === 0) {
      return false;
    }

    return this.isBundleFullyRendered(bundle);
  }

  private async waitForInteractiveCreateForm(bundle: string, timeoutMs = 12000): Promise<boolean> {
    const start = Date.now();
    while (Date.now() - start < timeoutMs) {
      if (await this.isCreateFormInteractive(bundle)) {
        return true;
      }
      await this.page.waitForTimeout(250);
    }
    return false;
  }

  async gotoCreatePage(bundle: string): Promise<Response | null> {
    return this.page.goto(`/node/add/${bundle}`, { waitUntil: 'load' });
  }

  async expectCreatePageAccessible(bundle: string): Promise<void> {
    const response = await this.gotoCreatePage(bundle);
    expect(response?.status()).toBe(200);
    await expect(this.page).toHaveURL(new RegExp(`/node/add/${bundle}$`));

    const interactiveOnFirstLoad = await this.waitForInteractiveCreateForm(bundle, 8000);
    if (interactiveOnFirstLoad) {
      return;
    }

    // CI can occasionally return a partially initialized form; one reload usually resolves it.
    await this.page.reload({ waitUntil: 'load' });
    const interactiveAfterReload = await this.waitForInteractiveCreateForm(bundle, 8000);
    expect(
      interactiveAfterReload,
      `Create form for ${bundle} did not reach interactive state after reload at ${this.page.url()}`
    ).toBeTruthy();
  }

  async expectCreatePageDenied(bundle: string): Promise<void> {
    const response = await this.gotoCreatePage(bundle);
    const deniedStatus = response?.status();
    const hasAccessDeniedText = /access denied/i.test(await this.page.locator('body').innerText());
    expect(deniedStatus === 403 || hasAccessDeniedText).toBeTruthy();
  }

  async expectNodeViewPage(title: string, body?: string): Promise<void> {
    await expect(this.page).toHaveURL(/(?:\/node\/add\/page$)|(?:\/(?:[a-z]{2}\/)?node\/\d+(?:\/edit)?$)/);

    const currentUrl = this.page.url();
    if (/\/node\/add\/page$/.test(currentUrl)) {
      const mainText = (await this.page.locator('main').innerText()).replace(/\s+/g, ' ').trim();
      throw new Error(
        `Expected to be redirected to created node view, but stayed on add page (${currentUrl}). ` +
        `This usually means form validation blocked save. Main text: ${mainText}`
      );
    }

    const nodeMatch = currentUrl.match(/\/(?:[a-z]{2}\/)?node\/(\d+)(?:\/edit)?$/);
    expect(nodeMatch).toBeTruthy();

    if (currentUrl.endsWith('/edit') && nodeMatch?.[1]) {
      await this.page.goto(`/node/${nodeMatch[1]}`);
    }

    await expect(this.page.getByRole('heading', { level: 1, name: title })).toBeVisible();

    if (body) {
      await expect(this.page.locator('main')).toContainText(body);
    }
  }

  // Assert that a main body content validation error is present in the <main> region.
  async assertMainBodyContentValidationError(): Promise<void> {
    const main = this.page.locator('main');
    const mainText = (await main.innerText()).replace(/\s+/g, ' ').toLowerCase();
    const errorPatterns = [
      /main body content.*required/,
      /required.*main body content/,
      /main body content.*is required/,
      /main body content.*must not be empty/,
      /main body content.*field is required/,
      /main body content.*please enter/,
      /main body content.*mandatory/,
      /main body content.*missing/,
      /main body content.*required field/,
      /main body content.*required\s*\*/
    ];
    const matched = errorPatterns.some((pattern) => pattern.test(mainText));
    expect(
      matched,
      `Expected a main body content validation error in <main>, but got:\n${mainText}`
    ).toBe(true);
  }
}
