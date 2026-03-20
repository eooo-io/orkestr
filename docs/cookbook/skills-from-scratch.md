# Skills from Scratch

**Goal:** Build a composable skill library using includes, template variables, and inheritance to maximize reuse.

**Time:** 20 minutes

## Ingredients

- A running Orkestr instance with a project created
- An understanding of your team's coding conventions

## Steps

### 1. Design the Skill Hierarchy

Before writing anything, plan how your skills will compose:

```
Base skills (shared across all agents):
├── coding-standards       ← fundamental rules
├── error-handling         ← how to handle errors
└── naming-conventions     ← variable/function naming

Domain skills (build on base):
├── api-design            ← includes: coding-standards
├── database-patterns     ← includes: coding-standards, error-handling
└── frontend-guide        ← includes: coding-standards, naming-conventions

Project-specific skills (use template variables):
├── project-context       ← {{framework}}, {{language}}, {{db_type}}
└── deployment-rules      ← {{environment}}, {{cloud_provider}}
```

### 2. Create the Base Skills

These are your foundation — small, focused, reusable.

#### coding-standards.md

```yaml
---
name: Coding Standards
description: Universal coding conventions
tags: [standards, base]
---
```

```markdown
## Core Principles

- Write code for humans first, machines second
- Keep functions short and focused (single responsibility)
- Use meaningful names that describe intent
- Prefer composition over inheritance
- Make invalid states unrepresentable
```

#### error-handling.md

```yaml
---
name: Error Handling
description: How to handle errors consistently
tags: [errors, base]
---
```

```markdown
## Error Handling Rules

- Never swallow errors silently
- Use custom error classes for domain-specific failures
- Include context in error messages (what failed, with what input)
- Log errors with structured data (not just strings)
- Distinguish between expected errors (user input) and unexpected (bugs)
```

#### naming-conventions.md

```yaml
---
name: Naming Conventions
description: Variable, function, and file naming rules
tags: [naming, base]
---
```

```markdown
## Naming Rules

- Variables: camelCase, descriptive (`userEmail` not `ue`)
- Functions: verb + noun (`fetchUser`, `validateInput`, `calculateTotal`)
- Booleans: start with `is`, `has`, `can` (`isActive`, `hasPermission`)
- Constants: SCREAMING_SNAKE_CASE (`MAX_RETRIES`, `API_BASE_URL`)
- Files: kebab-case (`user-service.ts`, `auth-middleware.ts`)
- Classes: PascalCase (`UserService`, `AuthMiddleware`)
```

### 3. Create Domain Skills with Includes

These build on the base skills:

#### api-design.md

```yaml
---
name: API Design Guide
description: RESTful API conventions
tags: [api, design]
includes: [coding-standards, error-handling]
---
```

```markdown
## API Design Rules

- Use RESTful resource naming: `/users`, `/users/{id}`, `/users/{id}/orders`
- Use HTTP methods correctly: GET (read), POST (create), PUT (replace), PATCH (update), DELETE (remove)
- Return appropriate status codes: 200, 201, 204, 400, 401, 403, 404, 422, 500
- Paginate list endpoints: `?page=1&per_page=25`
- Version APIs in the URL: `/api/v1/users`
- Always return JSON with consistent envelope: `{ data, meta, errors }`
```

When resolved, this skill's body will be:
1. Coding Standards body (from include)
2. Error Handling body (from include)
3. API Design Rules body (its own content)

### 4. Create Template-Driven Skills

These adapt to different projects:

#### project-context.md

```yaml
---
name: Project Context
description: Project-specific configuration
tags: [context, meta]
template_variables:
  - name: framework
    description: Backend framework
    default: Laravel
  - name: language
    description: Programming language
    default: PHP
  - name: db_type
    description: Database system
    default: MariaDB
  - name: test_framework
    description: Testing framework
    default: Pest
---
```

```markdown
## Project Context

This project uses:
- **Language:** {{language}}
- **Framework:** {{framework}}
- **Database:** {{db_type}}
- **Testing:** {{test_framework}}

All code must follow {{framework}}'s conventions and idioms.
Use {{test_framework}} for all test files.
```

### 5. Set Per-Project Variable Values

For each project, override the template variables:

**Backend API project:**
- `framework` → "Laravel"
- `language` → "PHP"
- `db_type` → "MariaDB"
- `test_framework` → "Pest"

**Frontend SPA project:**
- `framework` → "React"
- `language` → "TypeScript"
- `db_type` → "N/A"
- `test_framework` → "Vitest"

Navigate to the skill's Variables tab and set the values per project.

### 6. Create a Meta-Skill That Composes Everything

#### full-review.md

```yaml
---
name: Full Code Review
description: Complete review checklist
tags: [review, meta]
includes: [project-context, api-design, naming-conventions]
---
```

```markdown
## Review Process

Using all the standards above, review the code in this order:

1. **Naming** — Are names consistent and descriptive?
2. **Structure** — Are functions focused? Is code well-organized?
3. **API Design** — Do endpoints follow RESTful conventions?
4. **Error Handling** — Are errors handled consistently?
5. **Framework Conventions** — Does the code follow {{framework}} patterns?

For each issue found, provide:
- File and line number
- What's wrong
- A concrete fix (with code)
```

### 7. Test the Composition

Open the "Full Code Review" skill in the editor. Click the **Lint** tab to check for issues. Then click **Test** to try it with an AI model.

The resolved body will contain all included skills in order, with template variables filled in from the project's values.

### 8. Assign to Agents

Assign the meta-skill "Full Code Review" to your Code Review Agent. It will get the entire composed body — coding standards, error handling, naming conventions, API design, project context — all in one.

## Result

You now have:
- 3 base skills (reusable atoms)
- 1 domain skill with includes (builds on base)
- 1 template skill (adapts per project)
- 1 meta skill that composes everything
- A pattern you can repeat for any domain

## Design Principles

1. **Keep base skills small and focused** — Each should cover one topic
2. **Use includes for composition** — Don't copy-paste content between skills
3. **Use template variables for project differences** — Same skill, different contexts
4. **Test composition frequently** — Use the lint and test tools to verify
5. **Start simple, compose later** — Write individual skills first, then combine
