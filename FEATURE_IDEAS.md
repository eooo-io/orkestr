# Orkestr — Feature Ideas

> Updated 2026-03-15 after Phase E completion and self-hosted pivot.
> Items marked ✅ are already built. Remaining items are prioritized for the self-hosted product.

## Already Shipped (Phase A–E)

These were on the original brainstorm and have since been implemented:

- ✅ **Skill Analytics Dashboard** — Per-skill usage, pass rates, token trends, cost per run (#225)
- ✅ **Automated Regression Testing** — Saved test cases per skill with 4 assertion types (#227)
- ✅ **Review & Approval Workflow** — Submit → review → approve/reject with notifications (#219)
- ✅ **Skill Ownership & CODEOWNERS** — Owner assignment, auto-review-request (#220)
- ✅ **Skill Inheritance** — `extends_skill_id` with merge resolution, max depth 5 (#231)
- ✅ **Bulk Import from GitHub Repos** — Scan GitHub org for .agentis/ directories (#241)
- ✅ **REST API SDK** — TypeScript and PHP clients auto-generated from OpenAPI spec (#213, #214)
- ✅ **OpenAPI Spec Generation** — OpenAPI 3.1 with Swagger UI at /api/docs (#212)
- ✅ **Export Reports (CSV)** — Skill inventory, usage, audit log exports (#240)
- ✅ **Secret Scanning** — OutputGuard detects API keys, private keys, PII in agent output
- ✅ **Audit Log** — GuardrailViolation tracking with dismissal workflow (#267)
- ✅ **Skill Content Policies** — Org-level guardrail policies with cascading config (#259)
- ✅ **SSO (SAML/OIDC)** — SsoProvider model with SAML + OIDC support (#204)
- ✅ **Cross-Model Benchmarking** — Cloud vs local side-by-side comparison (#230, #257)
- ✅ **Activity Feed / Notifications** — In-app notification system (#223)

---

## Self-Hosted Infrastructure Priorities

These matter most for the self-hosted product — they make Orkestr indispensable on customer infra.

### Deployment & Operations

1. **One-Line Install Script** — `curl -sSL https://get.orkestr.dev | bash` that bootstraps Docker Compose, configures .env, runs setup wizard. The #1 adoption gate for self-hosted.
2. **Helm Chart / Kubernetes Operator** — Enterprise customers run K8s. Helm chart with configurable replicas, PVC, ingress, secrets.
3. **Automatic Updates** — `orkestr:upgrade` already exists, but needs a channel check (stable/nightly), changelog display, and rollback on failure.
4. **Monitoring & Alerting** — Prometheus metrics endpoint (`/metrics`) for agent execution counts, latency percentiles, error rates, token usage. Grafana dashboard template.
5. **Log Aggregation** — Structured JSON logging with correlation IDs for agent execution traces. Compatible with ELK/Loki.

### Local-First Model Experience

6. **Model Pull UI** — One-click Ollama model download from the local model browser (wraps `ollama pull`). Show download progress.
7. **Model Performance Profiles** — Auto-benchmark local models on first connect, store results, recommend best model per task type (coding, summarization, chat).
8. **GPU Utilization Dashboard** — Show GPU memory, VRAM usage, and inference load when running local models. Critical for air-gapped deployments.
9. **Model Routing Rules** — Route by task type: "use local llama3 for code review, use Claude for complex reasoning." Per-project or per-agent override.

### Agent Orchestration Depth

10. **Agent Execution Replay** — Record full agent execution traces (every tool call, LLM response, decision point). Replay in UI for debugging and auditing.
11. **Skill Chains / Pipelines** — Define multi-step skill sequences where output of one feeds into the next. Visual pipeline builder.
12. **Agent Scheduling** — Cron-style agent execution: "run this agent every morning at 9am against this project." Already have `ProcessAgentSchedules` command.
13. **Webhook-Triggered Agents** — Inbound webhook fires an agent run. Git push → code review agent. Jira ticket → analysis agent.
14. **Agent Memory / Context Persistence** — Agents remember across runs. Vector store or structured memory per agent per project.

### Developer Experience

15. **VS Code Extension** — Browse/edit/test skills from VS Code. Sync status indicator. This is the highest-leverage DX feature for adoption.
16. **GitHub Action for CI/CD** — Validate skill format in PRs, auto-sync on merge to main. `.github/workflows/orkestr-sync.yml` template.
17. **Hot Reload Sync** — File watcher that auto-syncs `.agentis/` changes to provider configs on save. `orkestr:watch` command.
18. **Skill Scaffolding** — `orkestr:new` interactive command with category-specific starter templates.

### Enterprise & Compliance

19. **RBAC Audit Report** — Exportable report: who has access to what, when roles changed, who approved. Compliance teams ask for this.
20. **Data Residency Controls** — Explicit config for where LLM calls go. "This project's data never leaves EU endpoints." Enforced at network guard level.
21. **Backup Encryption** — Encrypt backup ZIPs at rest with a customer-provided key. Required for regulated industries.
22. **LDAP/Active Directory Sync** — Beyond SSO — sync user groups and roles from AD. Auto-provision/deprovision.

---

## Growth & Community (Post-Launch)

Lower priority until there are paying self-hosted customers.

### Skill Intelligence

23. **Skill A/B Testing** — Run two skill versions against the same prompt set, compare output quality side-by-side.
24. **Skill Similarity Detection** — Flag near-duplicates across projects using embedding-based similarity scoring. Helps large orgs consolidate.
25. **Dynamic Skill Activation** — Activate skills based on project language, framework detection, or custom expressions.
26. **Prompt Cost Estimator** — Estimated token cost per skill across different models. Useful for budget planning.

### Ecosystem

27. **Private Marketplace** — Self-hosted orgs share skills internally without publishing to the public marketplace. Think "private npm registry" for skills.
28. **Skill Collections** — Curated bundles: "Laravel Best Practices", "Security Review Kit", "React Patterns".
29. **n8n / Temporal Integration** — Trigger agent runs from workflow automation tools. More natural fit than Zapier for self-hosted customers.

### Collaboration

30. **Real-Time Collaborative Editing** — WebSocket-based multi-cursor Monaco editing. High effort, nice-to-have.
31. **Three-Way Merge UI** — When two users edit the same skill, show a merge interface. Rare edge case for small teams.
32. **Inline Commenting on Skills** — Comment on specific lines of a skill prompt, like GitHub PR reviews.

---

## Removed Ideas (No Longer Relevant)

These were on the original list but don't fit the self-hosted pivot:

- ~~Zapier integration~~ → Self-hosted customers use n8n/Temporal, not Zapier
- ~~Slack/Discord bot~~ → Low priority; self-hosted teams use their own comms. Could revisit as a plugin.
- ~~Revenue Analytics for Sellers~~ → Marketplace is a community feature, not core product
- ~~Verified Publisher Badges~~ → Same; community/marketplace concern
- ~~Marketplace Reviews & Ratings~~ → Written reviews add moderation burden; upvotes suffice
- ~~Marketplace Skill Versioning~~ → Complex; bundles + version history already cover this
- ~~Skill Dependency Graph in CLI~~ → The visualization API (`/api/projects/{id}/graph`) already exists in the UI
- ~~Context-Aware Variable Resolution~~ → Over-engineered; template variables with manual defaults work fine
- ~~Skill Permissions / Scoping~~ → Org-level guardrail policies + RBAC already handle this
