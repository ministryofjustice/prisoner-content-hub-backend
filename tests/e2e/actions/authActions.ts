import { BrowserContext, expect, Page } from '@playwright/test';
import {
  TemporaryUser,
  createTemporaryDrupalUser,
  deleteTemporaryDrupalUser,
} from '../helpers/drupalUser';
import { StepRunner } from '../helpers/stepScreenshots';
import { LoginPage } from '../pages/LoginPage';

export async function runWithTemporaryUser<T>(
  role: string,
  action: (user: TemporaryUser) => Promise<T>,
): Promise<T> {
  const user = createTemporaryDrupalUser(role);
  try {
    return await action(user);
  } finally {
    deleteTemporaryDrupalUser(user.username);
  }
}

export async function loginViaUi(
  page: Page,
  username: string,
  password: string,
  runStep: StepRunner,
): Promise<void> {
  const loginPage = new LoginPage(page);

  await runStep('open login page', async () => {
    await loginPage.goto();
  });

  await runStep('submit credentials', async () => {
    await loginPage.submitCredentials(username, password);
  });

  await runStep('verify login succeeded', async () => {
    await loginPage.expectLoginSucceeded();
  });
}

export async function expectAuthenticatedSessionCookie(
  context: BrowserContext,
  runStep: StepRunner,
): Promise<void> {
  await runStep('verify authenticated session cookie exists', async () => {
    const cookies = await context.cookies();
    const hasSessionCookie = cookies.some((cookie) => cookie.name.startsWith('SESS') || cookie.name.startsWith('SSESS'));
    expect(hasSessionCookie).toBeTruthy();
  });
}
