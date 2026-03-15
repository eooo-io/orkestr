import { test, expect } from '@playwright/test';

test.describe('Navigation', () => {
  test('sidebar renders with navigation links', async ({ page }) => {
    await page.goto('/');
    // Verify key navigation items exist
    await expect(page.locator('nav, [role="navigation"], aside')).not.toHaveCount(0);
  });

  test.skip('can navigate between all main sections', async ({ page }) => {
    // TODO: click through Projects, Library, Marketplace, Search, Settings
  });

  test.skip('command palette opens with keyboard shortcut', async ({ page }) => {
    // TODO: press Cmd+K / Ctrl+K, verify palette appears
  });

  test.skip('breadcrumbs update on navigation', async ({ page }) => {
    // TODO: navigate into project, verify breadcrumbs
  });
});
