import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
  test('authenticated user can access the app', async ({ page }) => {
    await page.goto('/');
    await expect(page).not.toHaveURL(/\/login/);
  });

  test.describe('unauthenticated', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('unauthenticated user is redirected to login', async ({ page }) => {
      await page.goto('/projects');
      await expect(page).toHaveURL(/\/login/);
    });

    test('login page renders', async ({ page }) => {
      await page.goto('/login');
      await expect(page.getByLabel(/email/i)).toBeVisible();
      await expect(page.getByLabel(/password/i)).toBeVisible();
    });

    test('register page renders', async ({ page }) => {
      await page.goto('/register');
      await expect(page.getByLabel(/email/i)).toBeVisible();
    });
  });
});
