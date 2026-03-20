# CI/CD with GitHub Actions

**Goal:** Automate skill validation on PRs and provider sync on merge to main using the Orkestr GitHub Action.

**Time:** 15 minutes

## Ingredients

- A GitHub repository with an Orkestr project
- `.agentis/skills/` directory with skill files
- Orkestr API token (create one in Settings → Infrastructure → API Tokens)

## Steps

### 1. Create Your API Token

In Orkestr, go to **Settings → Infrastructure → API Tokens**:

1. Click **Create Token**
2. Name: "GitHub Actions"
3. Copy the generated token

### 2. Add the Token to GitHub Secrets

In your GitHub repository:

1. Go to Settings → Secrets and variables → Actions
2. Click **New repository secret**
3. Name: `ORKESTR_API_TOKEN`
4. Value: paste the token from step 1

### 3. Create the Validation Workflow

Create `.github/workflows/orkestr-validate.yml`:

```yaml
name: Validate Skills

on:
  pull_request:
    paths:
      - '.agentis/skills/**'

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Validate skill files
        uses: eooo-io/orkestr-action@v1
        with:
          command: validate
          path: .agentis/skills/
          api-token: ${{ secrets.ORKESTR_API_TOKEN }}
          orkestr-url: https://orkestr.yourcompany.com
```

This runs on every PR that changes skill files and validates:
- YAML frontmatter is valid
- Required fields are present (`name`)
- Includes reference existing skills
- Template variables are properly declared
- Lint rules pass

### 4. Create the Sync Workflow

Create `.github/workflows/orkestr-sync.yml`:

```yaml
name: Sync Skills

on:
  push:
    branches: [main]
    paths:
      - '.agentis/skills/**'

jobs:
  sync:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Sync to providers
        uses: eooo-io/orkestr-action@v1
        with:
          command: sync
          project-id: your-project-id
          api-token: ${{ secrets.ORKESTR_API_TOKEN }}
          orkestr-url: https://orkestr.yourcompany.com

      - name: Commit synced files
        run: |
          git config user.name "Orkestr Bot"
          git config user.email "orkestr@yourcompany.com"
          git add .claude/ .cursor/ .github/copilot-instructions.md .windsurf/ .clinerules .openai/
          git diff --staged --quiet || git commit -m "chore: sync provider configs from .agentis/"
          git push
```

This runs when skill files are merged to main:
1. Calls the Orkestr API to trigger provider sync
2. Commits the generated provider config files back to the repo

### 5. Add PR Status Checks

In GitHub, go to Settings → Branches → Branch protection rules:

1. Add rule for `main`
2. Enable "Require status checks to pass before merging"
3. Select "Validate Skills" as a required check

Now PRs with invalid skills can't be merged.

### 6. Test It

1. Create a branch and modify a skill file
2. Open a PR — the validation workflow runs automatically
3. If validation passes, merge the PR
4. The sync workflow runs and commits updated provider config files

## Result

Your CI/CD pipeline:
- Validates skill files on every PR (blocks merges if invalid)
- Syncs to all provider config formats on merge to main
- Commits generated files back to the repo automatically
- Keeps provider configs always in sync with `.agentis/skills/`

## Variations

- **Manual sync trigger:** Add `workflow_dispatch` to run sync on demand
- **PR comment with preview:** Add a step that posts the sync diff as a PR comment
- **Multi-project:** Run the sync action for multiple Orkestr projects in one workflow
- **Security scan:** Add an Orkestr security scan step before sync
