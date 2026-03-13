# eooo.ai — QA Test Plan

> Systematic test coverage for pre-launch QA sweep.

## Structure

| File | Area | Test Cases |
|---|---|---|
| `01-auth-onboarding.md` | Auth, registration, first-run experience | 18 |
| `02-projects.md` | Project CRUD, scan, sync, providers, settings | 28 |
| `03-skills.md` | Skill CRUD, Monaco editor, frontmatter, versions, lint | 35 |
| `04-agents.md` | Agent builder, composition, export, project binding | 24 |
| `05-workflows.md` | Workflow builder, DAG validation, versioning, export | 26 |
| `06-execution.md` | Agent execution, workflow execution, traces, cost tracking | 30 |
| `07-integrations.md` | MCP, A2A, marketplace, library, bundles, webhooks, repos | 32 |
| `08-billing.md` | Plans, Stripe, usage tracking, tier enforcement | 16 |
| `09-ux-edge-cases.md` | Navigation, responsiveness, error states, performance | 22 |

**Total: ~231 test cases**

## Severity Levels

- **P0 — Blocker:** App crashes, data loss, security vulnerability. Must fix before launch.
- **P1 — Critical:** Core feature broken, no workaround. Must fix before launch.
- **P2 — Major:** Feature impaired but workaround exists. Should fix before launch.
- **P3 — Minor:** Cosmetic, non-blocking UX issue. Fix post-launch if needed.

## Test Case Format

Each test case follows this format:

```
### TC-XX-NNN: Short Title
**Priority:** P0/P1/P2/P3
**Preconditions:** What must be true before testing
**Steps:**
1. Step one
2. Step two
**Expected:** What should happen
**Notes:** Edge cases, things to watch for
```

## How to Use

1. Work through each file in order (01 → 09)
2. Mark each test case: PASS / FAIL / BLOCKED / SKIP
3. For FAILs, note the actual behavior and file a GitHub issue
4. Re-test FAILs after fixes
5. All P0/P1 must PASS before launch

## Environment

- **App URL:** http://localhost:8000 (Laravel + Filament)
- **SPA URL:** http://localhost:5173 (React + Vite)
- **Default login:** admin@admin.com / password
- **Docker:** `make up && make migrate`
- **Local dev:** `composer dev` + `cd ui && npm run dev`
