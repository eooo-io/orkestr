import { test as setup, expect } from '@playwright/test';

const authFile = 'tests/e2e/.auth/user.json';

setup('authenticate as admin', async ({ page }) => {
  await page.goto('/login');

  await page.getByLabel(/email/i).fill('admin@admin.com');
  await page.getByLabel(/password/i).fill('password');
  await page.getByRole('button', { name: /log\s*in|sign\s*in/i }).click();

  // Wait for redirect to dashboard / projects page after login
  await expect(page).not.toHaveURL(/\/login/);

  await page.context().storageState({ path: authFile });
});
