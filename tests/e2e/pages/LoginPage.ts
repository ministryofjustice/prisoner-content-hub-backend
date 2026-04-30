import { expect, Page } from '@playwright/test';

export class LoginPage {
  constructor(private readonly page: Page) {}

  async goto(): Promise<void> {
    await this.page.goto('/user/login');
  }

  async submitCredentials(username: string, password: string): Promise<void> {
    const usernameById = this.page.locator('#edit-name');
    const passwordById = this.page.locator('#edit-pass');

    const usernameInput = (await usernameById.count()) > 0
      ? usernameById.first()
      : this.page.getByLabel(/username|email/i).first();
    const passwordInput = (await passwordById.count()) > 0
      ? passwordById.first()
      : this.page.getByLabel(/password/i).first();

    await usernameInput.fill(username);
    await passwordInput.fill(password);
    await this.page.getByRole('button', { name: /log in/i }).click();
  }

  async expectLoginSucceeded(): Promise<void> {
    await expect(this.page).not.toHaveURL(/\/user\/login/);
  }
}
