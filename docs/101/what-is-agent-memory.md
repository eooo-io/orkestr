# What is Agent Memory?

## The One-Sentence Answer

Agent memory lets agents remember information across sessions — past conversations, learned facts, and working state — so they get better over time instead of starting fresh every run.

## The Analogy: A Notebook

Imagine a consultant who visits your office every week. Without notes, they'd ask the same questions every visit: "What's your tech stack? What are your coding standards? What happened last sprint?"

Now give them a notebook. They write down key facts, decisions, and context. Each visit, they flip through their notes first, then pick up right where they left off. That notebook is agent memory.

## Why Memory Matters

Without memory, agents are **stateless**. Every execution starts from zero. The agent has its skills (training) and tools (abilities), but no memory of:

- What it did last time
- What it learned about your codebase
- Patterns it noticed across runs
- Decisions that were made previously
- Feedback it received

With memory, agents become **contextual**. They carry forward relevant information and avoid repeating work.

## Two Types of Memory

### Conversation Memory

A record of past interactions — what was asked, what the agent did, what the results were.

```
Memory: Conversation
├── Run #847 (2 days ago)
│   Input: "Review PR #42 for security"
│   Findings: SQL injection in auth.ts, XSS in api.ts
│   Outcome: PR blocked, 2 issues filed
│
├── Run #852 (yesterday)
│   Input: "Review PR #45 for security"
│   Findings: None — all checks passed
│   Outcome: PR approved
│
└── Run #856 (today)
    Input: "Review PR #48 for security"
    Context: Agent recalls auth.ts had issues recently,
             pays extra attention to auth-related changes
```

Conversation memory helps agents understand the history of their interactions and make better decisions based on patterns.

### Working Memory

Short-term state that persists across iterations within a single run and can optionally carry forward between runs.

```
Working Memory:
├── known_patterns:
│   - "auth.ts frequently has SQL injection issues"
│   - "API endpoints in this project don't validate input"
├── current_focus:
│   - "Reviewing database migration files"
├── unresolved_items:
│   - "Need to verify if the auth fix from PR #42 was merged"
```

Working memory is the agent's scratchpad — things it's keeping in mind while working.

## How Memory Integrates with the Agent Loop

```
Goal: "Review PR #48 for security"
  │
  ▼
Perceive:
  ├── Direct input: PR #48 diff
  ├── Memory recall: ◄── THIS IS WHERE MEMORY MATTERS
  │   ├── "auth.ts had SQL injection in PR #42"
  │   ├── "This project has a history of input validation issues"
  │   └── "Last review took 4 iterations, found issues in auth/ and api/"
  └── Tools: filesystem, github
  │
  ▼
Reason: "Given past patterns, I should prioritize auth-related
         changes and check input validation carefully"
  │
  ▼
Act: Read changed files, focusing on auth/ first...
```

Memory is injected into the **Perceive** stage. The agent retrieves relevant memories based on the current task and uses them to inform its reasoning.

## Memory Operations

### Remember

The agent (or the system) stores a fact for future use:

```
Remember: "auth.ts was refactored in PR #48 to use parameterized queries"
Type: fact
Scope: project (available to all agents in this project)
```

### Recall

The agent retrieves relevant memories based on the current context:

```
Recall: "What do I know about auth.ts and security?"
Results:
  1. "auth.ts had SQL injection in PR #42" (relevance: 0.95)
  2. "auth.ts was refactored in PR #48" (relevance: 0.90)
  3. "Project uses session-based auth" (relevance: 0.75)
```

### Forget

Memories can be explicitly removed when they're no longer relevant:

```
Forget: "auth.ts had SQL injection in PR #42"
Reason: Resolved in PR #48
```

## Memory Scopes

| Scope | Visibility | Example |
|---|---|---|
| **Run** | Single execution only | "Current files being reviewed" |
| **Agent** | All runs of this agent | "Patterns this agent has noticed" |
| **Project** | All agents in the project | "Project architecture decisions" |
| **Organization** | All projects in the org | "Company-wide coding standards" |

## Memory and Cost

Memory adds tokens to the agent's context, which increases cost. Orkestr manages this with:

- **Relevance scoring** — Only inject the most relevant memories
- **Summarization** — Compress old conversation memory into summaries
- **Limits** — Cap the number of memory tokens injected per run
- **Aging** — Older memories get lower relevance scores unless reinforced

## Key Takeaway

Memory transforms agents from stateless text generators into contextual entities that learn and improve. By remembering past interactions, learned patterns, and working state, agents make better decisions and avoid repeating work.

---

**Next:** [What are Guardrails?](./what-are-guardrails) →
