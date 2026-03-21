# What is Provider Sync?

## The One-Sentence Answer

Provider sync takes your skills and composed agent instructions and writes them into the native config format that each AI coding tool expects.

## The Analogy: Translating a Cookbook

You wrote a cookbook in English. Now you want to publish it in French, Spanish, German, Japanese, and Korean. Each translation preserves the same recipes — but in the format each audience expects.

Provider sync is the translator. Your skills are the English cookbook. Each AI coding tool (Claude, Cursor, Copilot, etc.) is a different language with its own format.

## The Six Providers

| Provider | Output Path | Format |
|---|---|---|
| **Claude** | `.claude/CLAUDE.md` | All skills under H2 headings in one file |
| **Cursor** | `.cursor/rules/{slug}.mdc` | One MDC file per skill |
| **GitHub Copilot** | `.github/copilot-instructions.md` | All skills concatenated in one file |
| **Windsurf** | `.windsurf/rules/{slug}.md` | One Markdown file per skill |
| **Cline** | `.clinerules` | Single flat file with all skills |
| **OpenAI** | `.openai/instructions.md` | All skills concatenated in one file |

## How It Works

```
Your skills in .orkestr/skills/
    │
    │  + Composed agent instructions
    │    (base instructions + custom + assigned skills)
    │
    ▼
ProviderSyncService
    │
    ├──► ClaudeDriver     → .claude/CLAUDE.md
    ├──► CursorDriver     → .cursor/rules/*.mdc
    ├──► CopilotDriver    → .github/copilot-instructions.md
    ├──► WindsurfDriver   → .windsurf/rules/*.md
    ├──► ClineDriver      → .clinerules
    └──► OpenAIDriver     → .openai/instructions.md
```

Each provider driver knows the exact format its tool expects. Cursor needs `.mdc` files with frontmatter. Claude needs a single Markdown file with heading structure. Copilot needs concatenated text.

## Sync is Explicit

Provider sync **never** runs automatically when you save a skill. You click "Sync" (or call the API) when you're ready. This gives you full control over when changes propagate.

Before syncing, you can **preview the diff** — see exactly what will change in each provider's config files. This is the same side-by-side diff view you'd see in a code review tool.

## What Gets Synced

1. **Individual skills** — Each skill's resolved body (includes expanded, template variables filled in)
2. **Composed agents** — Each enabled agent's merged output (base instructions + custom instructions + assigned skill bodies)

## Provider Sync in the Agent OS Context

Provider sync is one feature of the platform — not the core purpose. Think of it as the **bridge** between Orkestr's agent system and your daily coding workflow:

- You design agents and skills in Orkestr
- Agents run in Orkestr's execution engine for complex tasks
- But your AI coding tools also benefit from those same skills
- Provider sync delivers your skills to those tools automatically

## Key Takeaway

Provider sync is the bridge between Orkestr and your AI coding tools. Write skills once, sync to Claude, Cursor, Copilot, Windsurf, Cline, and OpenAI — each in its native format. Preview diffs before committing. Always explicit, never automatic.

---

**Next:** [What is Multi-Model?](./what-is-multi-model) →
