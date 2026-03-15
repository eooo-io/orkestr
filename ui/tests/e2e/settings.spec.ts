import { test, expect } from '@playwright/test';

test.describe('Settings', () => {
  test.skip('settings page loads', async ({ page }) => {
    // TODO: navigate to settings, verify page renders
  });

  test.skip('can update API keys', async ({ page }) => {
    // TODO: enter API key, save, verify success toast
  });

  test.skip('can change default model', async ({ page }) => {
    // TODO: select model from dropdown, save
  });

  test.skip('API tokens section renders', async ({ page }) => {
    // TODO: verify API token list is visible
  });
});
