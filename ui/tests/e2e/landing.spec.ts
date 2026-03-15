import { test, expect } from '@playwright/test';

test.describe('Landing Page', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test.skip('landing page renders hero section', async ({ page }) => {
    // TODO: visit /, verify hero headline and CTA
  });

  test.skip('landing page has sign up / login links', async ({ page }) => {
    // TODO: verify auth links are visible
  });

  test.skip('landing page feature sections render', async ({ page }) => {
    // TODO: scroll and verify feature blocks
  });
});
