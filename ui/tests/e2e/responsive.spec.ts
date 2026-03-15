import { test, expect, devices } from '@playwright/test';

test.describe('Responsive Layout', () => {
  test.skip('mobile viewport shows hamburger menu', async ({ browser }) => {
    const context = await browser.newContext({
      ...devices['iPhone 14'],
    });
    const page = await context.newPage();
    // TODO: verify mobile nav / hamburger renders
    await context.close();
  });

  test.skip('tablet viewport renders sidebar collapsed', async ({ browser }) => {
    const context = await browser.newContext({
      ...devices['iPad Mini'],
    });
    const page = await context.newPage();
    // TODO: verify sidebar collapses on tablet
    await context.close();
  });

  test.skip('desktop viewport renders full sidebar', async ({ page }) => {
    // TODO: verify sidebar is fully expanded at default desktop size
  });
});
