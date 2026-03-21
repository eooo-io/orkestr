# Open Source & Free Tier Strategy

> **Historical document.** Orkestr is now fully open source under the MIT license. All features are available without restrictions. The strategy below documents the original commercial positioning that was superseded by the decision to go fully open source (see NOTE.md).
>
> Created: 2026-03-12

## Original Decision

**Closed-source product with a generous free tier for open source projects.**

Not open-core. Not fully open source. A unified codebase with a free plan gated on project license detection.

## Why Not Open-Core

| Concern | Detail |
|---|---|
| Codebase fragmentation | Maintaining OSS + proprietary boundary is constant overhead |
| Feature gating complexity | Every feature decision becomes "is this free or paid?" at the code level |
| Community expectations | OSS communities expect governance, RFC processes, contributor docs — real cost |
| Distribution risk | An open-core model invites forks that strip out fees |
| Revenue dilution | Self-hosted OSS users never convert — they chose OSS to avoid paying |
| Speed | Solo/small team moves faster with one codebase, one build, one deploy target |

Open-core works for infrastructure (databases, observability) where enterprises need on-prem. Orkestr is a developer tool — cloud-first is natural.

## Why Free-for-Open-Source

### Strategic benefits

1. **Credibility without complexity** — Supporting OSS earns goodwill without maintaining a fork
2. **Proven playbook** — GitHub, JetBrains, GitKraken, Snyk, Sentry, Linear all do this
3. **Network effects** — OSS devs write about tools, recommend them in READMEs, create tutorials
4. **Pipeline to paid** — Devs use free tier on side projects, bring Orkestr to their company
5. **Community seeding** — OSS users create and share skills, building the skill library
6. **Low cost to serve** — OSS projects are typically smaller, lower usage

### What the OSS community actually wants

They don't want to self-host your SaaS. They want:
- Free access for their non-commercial work
- Assurance you won't rug-pull the free tier
- Transparency about what's free vs paid
- A way to contribute back (shared skills and bundles, not core code)

## Plan Architecture

### Tier: Free (Open Source)

**Eligibility:** Project has an OSI-approved license detected in `LICENSE` or `LICENSE.md` at the repo root, OR the project is hosted on a public GitHub/GitLab repository.

| Feature | Limit |
|---|---|
| Projects | Up to 5 |
| Skills per project | Up to 25 |
| Provider sync | All 7 providers |
| Skill versions | Last 10 per skill |
| Skill library | Full access |
| Bundle import | Yes |
| Test runner | 50 runs/day |
| Team members | 1 (solo) |
| MCP server configs | Up to 3 per project |
| A2A agent configs | Up to 3 per project |
| Agent Compose | Up to 3 workflows |
| CLI | Full access |
| Skill bundles | Import only (can't create) |

### Tier: Pro ($12/month)

For individual developers on private/commercial projects.

| Feature | Limit |
|---|---|
| Projects | Unlimited |
| Skills per project | Unlimited |
| Provider sync | All 7 providers |
| Skill versions | Unlimited history |
| Skill library | Full access |
| Bundle export/import | Create + share |
| Test runner | Unlimited (fair use) |
| Team members | 1 |
| MCP / A2A configs | Unlimited |
| Agent Compose | Unlimited workflows |
| CLI | Full access |
| Skill bundles | Create + share |
| Priority support | Email |

### Tier: Team ($29/seat/month)

For teams and organizations.

| Feature | Limit |
|---|---|
| Everything in Pro | Yes |
| Team members | Unlimited |
| Shared skill library | Cross-project, org-wide |
| Role-based access | Admin, Editor, Viewer |
| SSO / SAML | Yes |
| Audit log | Full history |
| Org-wide provider config | Centralized API keys & defaults |
| Usage analytics | Per-member, per-project |
| Invoice billing | Yes |

## OSS License Detection

### How it works

1. On project creation/scan, read `LICENSE`, `LICENSE.md`, `LICENSE.txt`, or `COPYING` from project root
2. Match content against SPDX license list using simple header detection
3. Alternatively, check `license` field in `package.json`, `composer.json`, `Cargo.toml`, `pyproject.toml`
4. For GitHub-hosted projects, query the GitHub API license endpoint as a fallback
5. Cache the result on the `projects` table; re-check on each scan

### Supported OSI licenses (non-exhaustive)

MIT, Apache-2.0, GPL-2.0, GPL-3.0, LGPL-2.1, LGPL-3.0, BSD-2-Clause, BSD-3-Clause, MPL-2.0, ISC, AGPL-3.0, Unlicense, CC0-1.0, Artistic-2.0, Zlib, BSL-1.0

### Edge cases

- **Dual-licensed projects**: If any license is OSI-approved, qualifies as free
- **No license file**: Does not qualify (public repo without a license is not OSS)
- **Custom/proprietary license**: Does not qualify
- **License changes**: Re-evaluated on each project scan; if license removed, grace period of 30 days before downgrade

## Anti-Abuse Measures

- Rate limit project creation on free tier (5 projects max, not 5 at a time)
- License file must be present in the actual project directory (not faked in a wrapper repo)
- Periodic re-validation of OSS status on active projects
- Free tier users are limited to the defined project and skill caps
- GitHub API cross-reference: if project claims OSS but repo is private, doesn't qualify

## Migration Path

Users can upgrade/downgrade freely:

- **Free → Pro**: Instant, all data preserved, limits lifted
- **Pro → Free**: Graceful — excess projects/skills become read-only (not deleted), user chooses which to keep active within free limits
- **Pro → Team**: Instant, invite team members
- **Team → Pro**: Team members lose access, data preserved under the owner

## What We Communicate

### Messaging to OSS community

> "Orkestr is free for open source. If your project has an OSI-approved license, you get full access to design agents, build workflows, and manage AI skills — no credit card, no trial expiry. We believe agent infrastructure should be accessible to everyone building in the open."

### Messaging to commercial users

> "For teams and commercial projects, Orkestr Pro gives you unlimited agents, workflows, skills, and full version history starting at $12/month."

### What we DON'T say

- "Open source" when referring to Orkestr itself (it's not)
- "Free forever" (free tier terms can evolve, but OSS commitment is durable)
- "Community edition" (implies an open-core split we don't have)

## Comparison to Alternatives

| Approach | Example | Pros | Cons |
|---|---|---|---|
| **Free-for-OSS** (ours) | GitHub, JetBrains, Sentry | Simple, proven, earns goodwill | No community code contributions |
| Open-core | GitLab, Supabase | Community contributions, self-host option | Codebase split, governance overhead |
| Fully open source | VS Code, Zed | Maximum trust and adoption | Hard to monetize, forks compete |
| Freemium (no OSS angle) | Most SaaS | Simple business model | No developer community credibility |
| Source-available | Elastic, HashiCorp | Transparency without full OSS | Community backlash, license confusion |

## Implementation Notes

### Database changes

```sql
-- Add to projects table
ALTER TABLE projects ADD COLUMN detected_license VARCHAR(50) NULL;
ALTER TABLE projects ADD COLUMN is_oss BOOLEAN DEFAULT FALSE;
ALTER TABLE projects ADD COLUMN license_checked_at TIMESTAMP NULL;

-- Add to organizations/users (depending on auth model)
ALTER TABLE users ADD COLUMN plan ENUM('free', 'pro', 'team') DEFAULT 'free';
ALTER TABLE users ADD COLUMN plan_expires_at TIMESTAMP NULL;
```

### Service changes

- New `LicenseDetectionService` — reads license files, matches against SPDX list
- Extend `ProjectScanJob` to run license detection on each scan
- New `PlanEnforcementMiddleware` — checks limits on API endpoints
- Extend Stripe integration to manage Pro/Team subscriptions

### UI changes

- Plan badge in React SPA sidebar and header
- Upgrade prompts when hitting limits (non-blocking, informational)
- Project settings show detected license and OSS status
- Billing page in React SPA settings

## Revenue Projections (Conservative)

Assumes subscriptions as the primary revenue stream:

| Metric | Year 1 | Year 2 |
|---|---|---|
| Free (OSS) users | 2,000 | 8,000 |
| Pro subscribers | 200 | 1,000 |
| Team seats | 50 | 300 |
| Pro MRR | $2,400 | $12,000 |
| Team MRR | $1,450 | $8,700 |
| **Total MRR** | **$3,850** | **$20,700** |

The free OSS tier is a marketing channel. Its ROI is measured in awareness, community content, and conversion to Pro — not direct revenue.
