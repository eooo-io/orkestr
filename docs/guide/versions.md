# Version History

Every time you save a skill, Orkestr creates an automatic version snapshot. This gives you a full history of changes with the ability to compare and restore any previous version.

## How Versioning Works

Versions are numbered sequentially starting at 1. Each version stores:

- The complete frontmatter (as a JSON snapshot)
- The full skill body
- A timestamp

There is no manual "create version" step -- versions are created automatically on every save.

## Viewing Version History

In the Skill Editor, click the **Versions** tab in the right panel. The version list shows all snapshots ordered by version number (newest first), with timestamps.

## Comparing Versions

Select any two versions using the checkboxes in the version list. A **Compare** button appears at the top. Clicking it opens the Monaco Diff Editor showing a side-by-side comparison of the two versions.

The diff viewer highlights:
- Added lines in green
- Removed lines in red
- Modified sections with inline change markers

The diff is read-only -- you cannot edit directly in the diff view.

## Restoring a Version

Each version has a **Restore** button. Clicking it opens a confirmation dialog. Confirming the restore:

1. Sets the skill's body and frontmatter to match the selected version
2. Writes the restored content to the `.agentis/skills/{slug}.md` file
3. Creates a **new** version snapshot (the restore itself becomes a new version)
4. Reloads the skill and version list in the editor

::: info
Restoring does not delete any versions. The restored state becomes the latest version, and the full history remains intact.
:::

## Versions and Provider Sync

Versions are internal to Orkestr. Provider sync always uses the current (latest) version of each skill. If you need to sync an older version, restore it first, then run the sync.

## API Endpoints

```
GET  /api/skills/{id}/versions              # List all versions
GET  /api/skills/{id}/versions/{number}     # Get a specific version
POST /api/skills/{id}/versions/{number}/restore  # Restore a version
```

The version response includes `version_number`, `frontmatter` (JSON), `body`, and `created_at`.
