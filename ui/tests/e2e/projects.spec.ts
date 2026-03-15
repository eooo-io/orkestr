import { test, expect } from '@playwright/test';

test.describe('Projects', () => {
  test('projects page loads', async ({ page }) => {
    await page.goto('/projects');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test.skip('can create a new project', async ({ page }) => {
    // TODO: fill in project creation flow
  });

  test.skip('can view project detail', async ({ page }) => {
    // TODO: navigate to a project and verify detail page
  });

  test.skip('can delete a project', async ({ page }) => {
    // TODO: delete project and verify removal
  });

  test.skip('can trigger provider sync', async ({ page }) => {
    // TODO: test sync button and success feedback
  });
});
