import { test, expect } from '@playwright/test';

test.describe('Skill Editor', () => {
  test.skip('can create a new skill', async ({ page }) => {
    // TODO: navigate to project, create skill, verify in list
  });

  test.skip('Monaco editor loads and accepts input', async ({ page }) => {
    // TODO: open skill editor, type in Monaco, verify content
  });

  test.skip('frontmatter panel reflects skill metadata', async ({ page }) => {
    // TODO: check name, description, model, tags are displayed
  });

  test.skip('can save a skill and see version created', async ({ page }) => {
    // TODO: edit skill, save, check version history
  });

  test.skip('can duplicate a skill', async ({ page }) => {
    // TODO: duplicate and verify new skill appears
  });

  test.skip('lint results display on save', async ({ page }) => {
    // TODO: save skill, verify lint panel shows results
  });
});
