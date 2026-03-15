import { test, expect } from '@playwright/test';

test.describe('Canvas (Agent Designer)', () => {
  test.skip('canvas page loads with ReactFlow', async ({ page }) => {
    // TODO: navigate to canvas, verify ReactFlow container renders
  });

  test.skip('agent nodes are visible on canvas', async ({ page }) => {
    // TODO: verify agent nodes render for a project with agents
  });

  test.skip('can select a node and see detail panel', async ({ page }) => {
    // TODO: click node, verify side panel opens
  });

  test.skip('can zoom and pan the canvas', async ({ page }) => {
    // TODO: use scroll/drag to zoom and pan
  });

  test.skip('compose preview generates output', async ({ page }) => {
    // TODO: trigger compose, verify preview panel shows content
  });
});
