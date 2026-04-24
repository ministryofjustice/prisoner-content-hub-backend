import { expect, Page } from '@playwright/test';

export class LoginPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/user/login');
  }

  async submitCredentials(username: string, password: string): Promise<void> {
    const usernameInput = this.page.locator('#edit-name').or(this.page.getByLabel(/username|email/i));
    const passwordInput = this.page.locator('#edit-pass').or(this.page.getByLabel(/password/i));

    await usernameInput.fill(username);
    await passwordInput.fill(password);
    await this.page.getByRole('button', { name: /log in/i }).click();
  }

  async expectLoginSucceeded(): Promise<void> {
    await expect(this.page).not.toHaveURL(/\/user\/login/);
  }
}
