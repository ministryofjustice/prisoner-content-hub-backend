import { expect, Page } from '@playwright/test';

export class LoginPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/user/login');
  }

  async submitCredentials(username: string, password: string): Promise<void> {
    await this.page.locator('#edit-name').fill(username);
    await this.page.locator('#edit-pass').fill(password);
    await this.page.locator('#edit-submit').click();
  }

  async expectLoginSucceeded(): Promise<void> {
    await expect(this.page).not.toHaveURL(/\/user\/login/);
  }
}
