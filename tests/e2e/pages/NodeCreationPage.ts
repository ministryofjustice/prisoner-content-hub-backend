import { expect, Page, Response } from '@playwright/test';

export class NodeCreationPage {
  constructor(private readonly page: Page) {}

  async gotoCreatePage(bundle: string): Promise<Response | null> {
    return this.page.goto(`/node/add/${bundle}`);
  }

  async expectBundleCreateAccessible(bundle: string): Promise<void> {
    const response = await this.gotoCreatePage(bundle);
    expect(response?.status()).toBe(200);
    await expect(this.page).toHaveURL(new RegExp(`/node/add/${bundle}$`));
  }

  async expectBundleCreateDenied(bundle: string): Promise<void> {
    const response = await this.gotoCreatePage(bundle);
    const deniedStatus = response?.status();
    const hasAccessDeniedText = /access denied/i.test(await this.page.locator('body').innerText());
    expect(deniedStatus === 403 || hasAccessDeniedText).toBeTruthy();
  }
}
