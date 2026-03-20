# Multi-Agent Architecture Review

**Goal:** Build a team of Architect, Infrastructure, and Security agents that collaboratively review system design documents and provide comprehensive feedback.

**Time:** 30 minutes

## Ingredients

- A running Orkestr instance with a project
- Architect, Infrastructure, and Security agents enabled
- Skills for each domain area
- MCP filesystem server (to read design documents)

## Steps

### 1. Create Domain-Specific Skills

#### architecture-principles.md

```yaml
---
name: Architecture Principles
description: System design evaluation criteria
tags: [architecture, design]
template_variables:
  - name: scale
    description: Expected system scale
    default: "10K concurrent users"
---
```

```markdown
When evaluating system architecture, assess:

## Scalability
- Can this design handle {{scale}}?
- Where are the bottlenecks?
- What breaks first under load?

## Maintainability
- Are services properly bounded (single responsibility)?
- Are interfaces well-defined between components?
- Can individual components be deployed independently?

## Resilience
- What happens when a dependency fails?
- Are there retry mechanisms and circuit breakers?
- Is there graceful degradation?
```

#### infrastructure-checklist.md

```yaml
---
name: Infrastructure Checklist
description: Production infrastructure review
tags: [infrastructure, ops]
---
```

```markdown
When reviewing infrastructure decisions:

- **Containerization:** Is the app containerized? Dockerfile quality?
- **Orchestration:** Kubernetes, ECS, or bare Docker Compose?
- **Networking:** Load balancer config, DNS, TLS termination
- **Storage:** Database scaling strategy, backup schedule
- **Monitoring:** Metrics, logging, alerting in place?
- **CI/CD:** Automated build, test, deploy pipeline?
- **Disaster recovery:** RTO and RPO defined? Tested?
```

### 2. Build the Workflow

Create a workflow called "Architecture Review":

```
┌─────────┐
│  Start  │
└────┬────┘
     │
     ▼
┌────────────────┐
│  Architect     │  ← Reads the design doc, provides structural feedback
│  Agent         │
└────────┬───────┘
         │
         ▼
┌────────────────┐
│ Parallel Split │
└──┬──────────┬──┘
   │          │
   ▼          ▼
┌──────────┐ ┌──────────┐
│Infra     │ │Security  │  ← Both review the architect's assessment
│Agent     │ │Agent     │     plus the original design
└────┬─────┘ └────┬─────┘
     │            │
     ▼            ▼
┌────────────────────┐
│   Parallel Join    │
└────────┬───────────┘
         │
         ▼
┌────────────────────┐
│   Architect Agent  │  ← Synthesizes all feedback into final report
│   (synthesis)      │
└────────┬───────────┘
         │
         ▼
┌────────────────────┐
│   Checkpoint:      │
│   "Review findings"│
└────────┬───────────┘
         │
         ▼
┌────────────────┐
│      End       │
└────────────────┘
```

### 3. Configure the Steps

**Step 1 — Architect (Initial Review):**
```
Input: { "document": "context.input.design_doc" }
Output key: "architect_review"
Instructions: "Analyze this system design for structural soundness,
scalability, and maintainability. Identify the top concerns."
```

**Step 2a — Infrastructure Agent:**
```
Input: {
  "document": "context.input.design_doc",
  "architect_feedback": "context.architect_review"
}
Output key: "infra_review"
```

**Step 2b — Security Agent:**
```
Input: {
  "document": "context.input.design_doc",
  "architect_feedback": "context.architect_review"
}
Output key: "security_review"
```

**Step 3 — Architect (Synthesis):**
```
Input: {
  "original": "context.input.design_doc",
  "my_initial_review": "context.architect_review",
  "infra_feedback": "context.infra_review",
  "security_feedback": "context.security_review"
}
Output key: "final_report"
Instructions: "Synthesize all feedback into a prioritized report:
1. Critical blockers (must fix before implementation)
2. Important recommendations (should fix)
3. Nice-to-haves (could improve)
Include specific, actionable suggestions for each item."
```

### 4. Run It

Trigger the workflow manually with a design document as input:

```json
{
  "design_doc": "# User Authentication System\n\nWe plan to use JWT tokens stored in localStorage..."
}
```

Watch as:
1. The Architect identifies structural concerns ("JWT in localStorage is an XSS risk")
2. Infrastructure and Security agents review in parallel
3. The Architect synthesizes everything into a prioritized report
4. You review the findings at the checkpoint

## Result

A multi-agent architecture review team that:
- Evaluates designs from three expert perspectives
- Runs specialized reviews in parallel (faster)
- Synthesizes findings into a single prioritized report
- Pauses for human review before finalizing
- Can be triggered manually or via webhook when design docs are committed
