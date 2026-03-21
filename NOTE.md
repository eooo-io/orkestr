# Decision Log — 2026-03-16

## 1. Open Source Under MIT License

**Decision:** Release Orkestr as a free, open-source project under the MIT license.

**Reasoning:**
- The project was built in ~4 days with heavy AI assistance. The scope (agent runtime, workflow DAGs, MCP/A2A clients, guardrails, SSO, Helm charts, VS Code extension) would normally take a funded team 12-18 months. The code is real and structured, but it's scaffolding-depth — not battle-tested for paying customers.
- The competitive landscape is brutal: Dify, FlowiseAI, CrewAI, LangGraph, Langfuse — all funded, all with communities. A solo developer selling self-hosted enterprise software against funded teams is not viable.
- Enterprise buyers require support SLAs, SOC2, and a team behind the product. None of that exists.
- As an open-source project, this is a strong portfolio piece and credibility builder. As a paid product, it's a liability.

**What changed:**
- Added MIT `LICENSE` file
- Rewrote `README.md` for open-source positioning
- Removed billing routes (Stripe subscriptions, license keys) from `routes/api.php`
- Removed Stripe env vars from `.env.example`
- Updated landing page: replaced pricing tiers with open-source deployment section, removed fake testimonials, rebranded from "Orkestr by eooo.ai" to "Orkestr", swapped CTAs to GitHub links
- Removed `TRADEMARK_GUIDE.md`
- Stripped billing/license references from `CLAUDE.md`

**What was kept:**
- Billing controllers, middleware classes, and Stripe services still exist as dead code (no routes point to them). Can be cleaned up later or left as reference.
- The `licenses` and billing-related DB migrations remain. They don't hurt anything and removing them risks breaking the migration chain.

## 2. Canvas Composer as Pre-Release Priority (Phase L)

**Decision:** The interactive canvas must be the primary composition surface before public release. Phase L: Canvas Composer — 5 milestones, 37 issues.

**Problem:** The canvas (Phase I) was built as a visualization tool with shallow editing. The detail panel only edits agent `custom_instructions`. You can't create entities, draw connections, or configure MCP/A2A/skills from the canvas. For a product whose pitch is "visual agent orchestration," this is the gap that matters most.

**Plan:**
- **L.1 (10 issues):** Detail panel overhaul — full entity editors in the right flyout for agents (tabbed), skills (embedded Monaco), MCP servers, A2A agents, and delegation edge configs
- **L.2 (8 issues):** Canvas CRUD — create and delete agents, skills, MCP, A2A directly from the canvas palette and flyout
- **L.3 (7 issues):** Connection drawing — drag-to-connect edges between nodes with validation and visual feedback
- **L.4 (7 issues):** UX polish — multi-select, undo/redo, context menus, keyboard shortcuts, auto-save
- **L.5 (5 issues):** Backend — `delegation_configs` table, graph endpoint expansion, optimistic refresh, agent quick-create API

**Build order:** L.1 → L.2 → L.3 → L.5 (parallel) → L.4

**GitHub issues:** Run `gh auth login && bash scripts/create-phase-l-issues.sh` to create all milestones and issues.
