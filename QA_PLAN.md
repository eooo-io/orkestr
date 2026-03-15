# QA Plan — Orkestr (eooo.ai)

Comprehensive quality assurance strategy covering automated testing, browser tests, and manual verification.

---

## 1. Test Coverage Map

| Layer | Framework | Count | Scope |
|---|---|---|---|
| Backend API + Services | Pest PHP | 619+ tests | API endpoints, services, models, middleware, jobs |
| Frontend E2E | Playwright | 9 spec files | Browser tests for all user flows |
| TypeScript | `tsc --noEmit` | Strict mode | Compile-time type safety across the React SPA |

### Coverage Goals

- **Backend:** Every API endpoint has at least one happy-path and one error-path test
- **Frontend:** Every primary user flow has an automated browser test
- **Types:** Zero TypeScript errors in strict mode at all times

---

## 2. Automated Browser Tests (Playwright)

All specs live in `ui/tests/e2e/`. Run with `npm run test:e2e` from `ui/`.

| Spec File | Area | What It Covers |
|---|---|---|
| `auth.setup.ts` | Setup | Logs in as admin, saves session storage state for all other tests |
| `auth.spec.ts` | Authentication | Login page render, register page render, authenticated redirect, unauthenticated redirect to login |
| `projects.spec.ts` | Projects | Project list page, create project, view detail, delete project, trigger provider sync |
| `skill-editor.spec.ts` | Skill Editor | Create skill, Monaco editor input, frontmatter panel, save + version creation, duplicate, lint results |
| `canvas.spec.ts` | Canvas / Agent Designer | ReactFlow canvas load, agent node rendering, node selection + detail panel, zoom/pan, compose preview |
| `settings.spec.ts` | Settings | Settings page load, API key update, default model change, API tokens section |
| `navigation.spec.ts` | Navigation | Sidebar rendering, section-to-section navigation, command palette (Cmd+K), breadcrumbs |
| `landing.spec.ts` | Landing Page | Hero section, auth links, feature sections (unauthenticated) |
| `responsive.spec.ts` | Responsive Layout | Mobile hamburger menu, tablet collapsed sidebar, desktop full sidebar |

### Playwright Configuration

- **Browser:** Chromium only (multi-browser deferred)
- **Base URL:** `http://localhost:5173`
- **Auth:** Session stored at `tests/e2e/.auth/user.json`, reused across all specs
- **Timeout:** 30 seconds per test
- **Retries:** 0 (fail fast during development)
- **Screenshots:** Captured on failure
- **Web server:** Vite dev server auto-started by Playwright

---

## 3. Automated API Tests (Pest PHP)

All specs live in `tests/`. Run with `php artisan test` or `composer test`.

| Test File | Area | What It Covers |
|---|---|---|
| `Feature/ProjectApiTest.php` | Projects API | CRUD, scan, sync, git-log, git-diff endpoints |
| `Feature/ProviderSyncTest.php` | Provider Sync | All 6 provider drivers (Claude, Cursor, Copilot, Windsurf, Cline, OpenAI) |
| `Feature/AgentisManifestServiceTest.php` | Manifest | `.agentis/` directory read/write, skill file parsing |
| `Feature/ProjectScanJobTest.php` | Scan Job | Filesystem scan, skill discovery, import |
| `Unit/SkillFileParserTest.php` | Parser | YAML frontmatter + Markdown body parsing |
| `Feature/AgentModelTest.php` | Agent Models | Agent Eloquent relationships, scopes |
| `Feature/AgentApiTest.php` | Agent API | Agent CRUD, toggle, instructions, skill assignment, compose |
| `Feature/AgentComposeServiceTest.php` | Agent Compose | Base + custom + skills merge logic |
| `Feature/WorkflowModelTest.php` | Workflow Models | Workflow, step, edge relationships |
| `Feature/WorkflowApiTest.php` | Workflow API | Workflow CRUD, step/edge management |
| `Feature/WorkflowServicesTest.php` | Workflow Services | Validation, topological sort, execution planning |
| `Feature/WorkflowExecutionTest.php` | Workflow Execution | End-to-end workflow run, step transitions |
| `Feature/ExecutionTest.php` | Execution Engine | Run creation, step execution, status tracking |
| `Feature/ExecutionReplayTest.php` | Execution Replay | Replay past runs, compare outputs |
| `Feature/McpClientTest.php` | MCP Client | MCP server communication, tool discovery |
| `Feature/A2aClientTest.php` | A2A Client | Agent-to-agent protocol, task delegation |
| `Feature/AgentMemoryTest.php` | Agent Memory | Memory CRUD, retrieval, context injection |
| `Feature/AgentScheduleTest.php` | Agent Schedules | Cron scheduling, trigger management |
| `Feature/AgentAutonomyTest.php` | Agent Autonomy | Autonomous execution, guardrail enforcement |
| `Feature/ModelRoutingTest.php` | Model Routing | LLMProviderFactory routing by model prefix |
| `Feature/ModelPullTest.php` | Model Pull | Ollama model pull progress tracking |
| `Feature/ObservabilityTest.php` | Observability | Logging, metrics, trace propagation |
| `Feature/OrganizationTest.php` | Organizations | Multi-tenant org CRUD, roles, invitations |
| `Feature/AdminSecurityTest.php` | Admin Security | Filament auth, permission gates |
| `Feature/DeploymentTest.php` | Deployment | Health check, environment validation |
| `Feature/GuardrailsTest.php` | Guardrails | Policy CRUD, profile assignment, cascading |
| `Feature/GuardrailsE3Test.php` | Guardrails E3 | Security scanner, content review, endpoint approvals, violation tracking |
| `Feature/LocalModelE4Test.php` | Local Models E4 | Ollama integration, custom endpoints, air-gap mode, model health |
| `Feature/DeveloperE5Test.php` | Developer E5 | API tokens, SDK generation, OpenAPI spec |
| `Feature/EnterpriseE6Test.php` | Enterprise E6 | Skill reviews, ownership, analytics, regression tests, inheritance, reports |
| `Feature/PerformanceDashboardTest.php` | Performance | Dashboard metrics, latency tracking |
| `Feature/CanvasLayoutTest.php` | Canvas Layout | Auto-layout algorithms, node positioning |
| `Feature/OpenRouterTest.php` | OpenRouter | OpenRouter provider integration |

---

## 4. Manual Test Checklist

Items that require human judgment or are difficult to automate reliably.

### Canvas & Visual Editor
- [ ] Drag-and-drop precision for agent nodes on canvas
- [ ] A2A edge drawing (click-drag between agent nodes)
- [ ] Canvas auto-layout produces readable graph for 10+ nodes
- [ ] Node resize and reposition persists after page reload
- [ ] Minimap navigation accuracy

### Monaco Editor
- [ ] Syntax highlighting for YAML frontmatter + Markdown body
- [ ] Autocomplete for template variables (`{{variable}}`)
- [ ] Cursor position preserved after save
- [ ] Large skill content (5000+ lines) scrolls smoothly
- [ ] Find/replace works within editor
- [ ] Dark/light theme switches correctly in editor

### Visual Diff Viewer
- [ ] Side-by-side diff is readable for long skill content
- [ ] Additions/deletions are clearly color-coded
- [ ] Version restore produces correct diff preview

### Theme & UI Consistency
- [ ] Dark mode renders correctly on all pages
- [ ] Light mode renders correctly on all pages
- [ ] System theme detection works on first load
- [ ] No flash of wrong theme on page load
- [ ] All shadcn/ui components respect current theme
- [ ] Toast notifications are visible in both themes

### File Downloads & Exports
- [ ] Bundle export produces valid ZIP file
- [ ] ZIP contains correct `.agentis/` structure
- [ ] JSON bundle export is valid and importable
- [ ] PDF export (if applicable) renders correctly
- [ ] SDK download links return valid TypeScript/PHP packages

### LLM Provider Integration
- [ ] Anthropic API calls return streamed responses in playground
- [ ] OpenAI API calls stream correctly
- [ ] Gemini API calls stream correctly
- [ ] Ollama local model calls work end-to-end
- [ ] Model pull progress bar updates in real-time
- [ ] Error handling for invalid API keys shows clear message
- [ ] Rate limit errors are surfaced to user

### Mobile & Touch
- [ ] Touch scrolling works on skill list
- [ ] Sidebar swipe-to-open on mobile
- [ ] Canvas pinch-to-zoom on tablet
- [ ] Monaco editor is usable on tablet (keyboard appears)
- [ ] Modal dialogs are dismissible on mobile

### Cross-Browser
- [ ] Safari: all pages render, no layout breaks
- [ ] Firefox: all pages render, no layout breaks
- [ ] Safari: Monaco editor loads and accepts input
- [ ] Firefox: SSE streaming works in playground

### Performance
- [ ] Project with 100+ skills loads skill list in < 3 seconds
- [ ] Canvas with 20+ agent nodes renders without lag
- [ ] Search returns results in < 1 second
- [ ] Marketplace page with 50+ items scrolls smoothly
- [ ] Concurrent provider sync completes without timeout

---

## 5. CI/CD Integration

### On Every Push
```yaml
- php artisan test              # Pest PHP (all 619+ tests)
- cd ui && npx tsc --noEmit     # TypeScript strict check
```

### On Pull Request
```yaml
- php artisan test              # Pest PHP
- cd ui && npx tsc --noEmit     # TypeScript strict check
- cd ui && npx playwright test  # Playwright E2E (requires running backend)
```

### CI Environment Requirements
- PHP 8.4 with required extensions
- MariaDB 11.x (or SQLite for unit tests)
- Node.js 22+ with npm
- Chromium (installed via `npx playwright install chromium`)
- Laravel app seeded with test data (`php artisan migrate --seed`)
- Vite dev server running for Playwright tests (auto-started by config)

### CI Setup Commands
```bash
# Install Playwright browsers (one-time)
cd ui && npx playwright install --with-deps chromium

# Run E2E tests
cd ui && npm run test:e2e
```
