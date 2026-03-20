# What is the Marketplace?

## The One-Sentence Answer

The Marketplace is a community hub where you can publish, discover, install, and vote on skills and agent configurations.

## The Analogy: An App Store

The App Store and Google Play let developers publish apps and users discover them. You browse, read reviews, install what you need, and rate what you use.

The Orkestr Marketplace works the same way — but for AI skills instead of apps.

## What You Can Do

### Browse and Discover

- **Search** by keyword, tag, or category
- **Filter** by model, provider compatibility, popularity
- **Sort** by votes, install count, or recency
- **Preview** the full skill content before installing

### Install

One click to add a marketplace skill to your project. The skill file is created in your `.agentis/skills/` directory and imported into the database.

### Publish

Share your skills with the community:

1. Open a skill in the editor
2. Click "Publish to Marketplace"
3. Fill in the listing details (description, category, tags)
4. The skill goes through a security scan
5. It's published and available to the community

### Vote

Upvote skills that are useful. Votes help the best skills surface to the top.

## Security Scanning

Before any skill is published to the marketplace, Orkestr runs a security scan:

- **Prompt injection detection** — Instructions that try to override system prompts
- **Data exfiltration patterns** — Attempts to send data to external endpoints
- **Credential harvesting** — Requests for passwords, API keys, or tokens
- **Obfuscated content** — Hidden instructions in encoded formats

Skills that fail the scan are blocked from publishing.

## Marketplace in Air-Gap Mode

The marketplace requires network connectivity to the Orkestr community server. In air-gap mode, the marketplace is disabled — but you can still share skills using **bundles** (ZIP/JSON exports) transferred manually between instances.

## Key Takeaway

The Marketplace is the community layer of Orkestr. Publish skills to share your expertise, install skills from others to bootstrap your setup, and vote to surface the best content.

---

You've completed Orkestr 101! Here's where to go next:

- **[Getting Started](/guide/getting-started)** — Install Orkestr and create your first project
- **[Deep Dives](/deep-dive/)** — Technical architecture of each subsystem
- **[Cookbook](/cookbook/)** — Step-by-step tutorials for common tasks
