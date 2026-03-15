# Analytics & Testing

Orkestr tracks skill usage and provides tools for benchmarking and regression testing so you can measure prompt quality over time.

## Skill Analytics Dashboard

Navigate to **Analytics** from the sidebar to view usage metrics across your skills.

### Top Skills

The top skills table shows the most-used skills ranked by usage count:

```
GET /api/analytics/top-skills?period=30d&limit=20
```

Each row displays the skill name, project, usage count, average token consumption, and last-used timestamp.

### Usage Trends

The trends chart visualizes usage over time with configurable periods:

```
GET /api/analytics/trends?period=7d
```

Available periods: `7d`, `30d`, `90d`, `all`.

### Per-Skill Analytics

Click any skill to see its individual analytics:

```
GET /api/skills/{id}/analytics
```

Returns:

| Metric | Description |
|---|---|
| `total_uses` | Total number of times the skill was used in tests or playground |
| `avg_input_tokens` | Average input tokens per use |
| `avg_output_tokens` | Average output tokens per use |
| `avg_latency_ms` | Average response time in milliseconds |
| `uses_by_model` | Breakdown by model used |
| `uses_by_day` | Daily usage over the selected period |

## CSV Reports

Orkestr generates three types of downloadable CSV reports.

### Skills Report

```
GET /api/reports/skills
```

Exports a full inventory: skill name, project, model, tags, token estimate, version count, last updated.

### Usage Report

```
GET /api/reports/usage
```

Exports token consumption data: skill, model, total input tokens, total output tokens, estimated cost, period.

### Audit Report

```
GET /api/reports/audit
```

Exports the audit trail: timestamp, user, action, resource type, resource name, details.

::: tip
Reports respect the current organization context. Set the `X-Organization-Id` header or rely on your `current_organization_id` to scope the data.
:::

## Cross-Model Benchmarking

Run the same skill against multiple models simultaneously to compare output quality, speed, and cost.

### Running a Benchmark

```
POST /api/skills/{id}/benchmark
```

```json
{
  "models": ["claude-sonnet-4-6", "gpt-5.4", "gemini-3.1-pro"],
  "message": "Review this Python function for bugs...",
  "max_tokens": 2048
}
```

The response includes each model's output, latency, token counts, and estimated cost side by side.

### Model Health Benchmarking

For provider-level performance comparison:

```
POST /api/model-health/benchmark
```

This pings each configured provider and reports latency, availability, and throughput metrics.

## Regression Test Cases

Create test cases for a skill and run them to detect regressions after edits.

### Creating Test Cases

```
POST /api/skills/{id}/test-cases
```

```json
{
  "name": "Should list 5 bullet points",
  "input": "Summarize the key features of Rust.",
  "expected_contains": ["memory safety", "zero-cost abstractions"],
  "expected_not_contains": ["garbage collector"],
  "model": "claude-sonnet-4-6"
}
```

### Running All Tests

```
POST /api/skills/{id}/test-cases/run-all
```

Each test case is executed against the skill and evaluated:

| Result | Meaning |
|---|---|
| **Pass** | All `expected_contains` found, no `expected_not_contains` found |
| **Fail** | A required string was missing or a blocked string was present |
| **Error** | The model call failed (timeout, rate limit, etc.) |

### Managing Test Cases

```
GET    /api/skills/{id}/test-cases       # List all test cases for a skill
PUT    /api/skill-test-cases/{id}        # Update a test case
DELETE /api/skill-test-cases/{id}        # Delete a test case
```

::: warning
Regression tests call the LLM for each test case. Running a large suite across multiple models can consume significant tokens. Use the benchmark feature selectively.
:::
