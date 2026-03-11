# Diff Preview

The Preview Sync feature lets you see exactly what changes a provider sync will make before writing anything to disk. This prevents surprises and gives you a chance to verify the output.

## Opening the Preview

On the project detail page, click **Preview Sync** (next to the Sync button). Agentis Studio performs a dry-run sync that:

1. Generates the output for each enabled provider
2. Reads the current content of each provider's config files from disk
3. Compares the generated output against the existing files
4. Returns a per-file diff

## The Diff Modal

The preview opens as a modal with tabs for each enabled provider. Each tab shows:

- A list of files that would be created, modified, or remain unchanged
- Status badges for each file
- A Monaco Diff Editor showing the side-by-side difference

### Status Badges

| Badge | Meaning |
|---|---|
| **Added** | File does not exist on disk yet and will be created |
| **Modified** | File exists and its content will change |
| **Deleted** | File exists on disk but is no longer in the generated output |
| **Unchanged** | File exists and already matches the generated output |

A summary count at the top of each tab shows how many files fall into each category.

### Reading the Diff

The Monaco Diff Editor displays the current file content on the left and the new content on the right:

- **Green highlighted lines** -- New content being added
- **Red highlighted lines** -- Content being removed
- **Inline change markers** -- Character-level changes within modified lines

The diff view is read-only.

## Confirming or Canceling

After reviewing the diffs:

- Click **Confirm Sync** to write all the changes to disk
- Click **Cancel** (or press Escape) to close the modal without writing anything

::: tip
If you see unexpected changes, cancel the preview and check your skills. A common cause is a skill with unresolved [template variables](./templates) or missing [includes](./includes) that produce placeholder comments in the output.
:::

## API Endpoint

```
POST /api/projects/{id}/sync/preview
```

Returns a JSON object with per-provider diff data, including file paths, status (added/modified/deleted/unchanged), current content, and generated content.
