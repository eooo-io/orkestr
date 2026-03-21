# Skill Library

Orkestr ships with 25 pre-built skills across 6 categories. Import them into projects as standalone instructions for [Provider Sync](./provider-sync), assign them to [agents](./agents), or study them as examples of well-structured prompts.

## Categories

| Category | Skills | Focus |
|---|---|---|
| Laravel | 5 | API resources, Eloquent optimization, migrations, Pest tests, service classes |
| PHP | 4 | Type safety, design patterns, error handling, documentation |
| TypeScript | 4 | React components, type utilities, API clients, state management |
| FinTech | 4 | Transaction processing, compliance, risk assessment, reporting |
| DevOps | 4 | Docker optimization, CI/CD pipelines, monitoring, infrastructure |
| Writing | 4 | Technical writing, API docs, changelogs, user guides |

## Browsing the Library

Navigate to the **Library** page from the sidebar. The page has:

- A **category sidebar** on the left for filtering by category
- A **search bar** for full-text search across skill names and descriptions
- A **card grid** showing matching library skills

Each card displays the skill name, description, category, and tags.

## Importing a Library Skill

Click a library skill card to open the import modal:

1. **Select a target project** from the dropdown
2. Click **Import**

The import process:

- Creates a new skill in the target project with the library skill's name, description, tags, and body
- Writes the `.agentis/skills/{slug}.md` file to disk
- Creates a version 1 snapshot
- Syncs any tags that do not already exist in the system

### Slug Collision Handling

If the target project already has a skill with the same slug, the import appends a numeric suffix. For example, importing `pest-test-writer` into a project that already has that slug creates `pest-test-writer-1`.

## Library vs. Marketplace

The library is a local, pre-seeded collection that ships with every Orkestr installation. The [Marketplace](./marketplace) is a self-hosted discovery platform for publishing and installing community skills. Library skills are always available offline; marketplace skills require network access to browse and install.

## Managing Library Skills

Library skills are managed through the Filament Admin panel under **Library Skills**. You can:

- Add new library skills
- Edit existing entries
- Set categories and tags
- Delete skills from the library

Changes to the library do not affect skills already imported into projects.
