# Multi-Model Setup

Agentis Studio supports testing skills against models from multiple providers: Anthropic (Claude), OpenAI, Google Gemini, and local Ollama models.

## Configuring API Keys

Set up API keys in the Filament Admin panel at http://localhost:8000/admin under **Settings**, or use the Settings page in the React SPA.

### Anthropic (Claude)

Required for Claude models (claude-sonnet, claude-opus, claude-haiku, etc.).

Set the `ANTHROPIC_API_KEY` in your `.env` file or enter it in the Settings page. This is the primary provider and the only one required for basic functionality.

### OpenAI

Required for GPT-5.4, GPT-5 Mini, o3, and other OpenAI models.

Set `OPENAI_API_KEY` in `.env` or enter it in Settings. Models prefixed with `gpt-` or `o` are routed to the OpenAI provider.

### Google Gemini

Required for Gemini models.

Set `GEMINI_API_KEY` in `.env` or enter it in Settings. Models prefixed with `gemini-` are routed to the Gemini provider.

### Ollama (Local)

For running open-source models locally. No API key needed -- just a running Ollama instance.

Set `OLLAMA_URL` in `.env` or Settings (defaults to `http://localhost:11434`). Models prefixed with `ollama/` are routed to your local Ollama server.

```env
# .env example
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=AIza...
OLLAMA_URL=http://localhost:11434
```

## Model Routing

The `LLMProviderFactory` routes requests to the correct provider based on the model name prefix:

| Prefix | Provider | Examples |
|---|---|---|
| `claude-` | Anthropic | `claude-sonnet-4-6`, `claude-opus-4-6` |
| `gpt-` or `o` | OpenAI | `gpt-5.4`, `gpt-5-mini`, `o3` |
| `gemini-` | Google Gemini | `gemini-3.1-pro-preview`, `gemini-3-flash-preview` |
| `ollama/` | Ollama | `ollama/llama3`, `ollama/codellama` |

## Checking Available Models

The Settings page shows a provider status card for each configured provider:

- A green indicator means the API key is set and valid
- A list of available models fetched from the provider's API

You can also fetch available models via the API:

```
GET /api/models
```

Returns a grouped list:

```json
{
  "anthropic": {
    "configured": true,
    "models": ["claude-opus-4-6", "claude-sonnet-4-6", "claude-haiku-4-5-20251001"]
  },
  "openai": {
    "configured": true,
    "models": ["gpt-5.4", "gpt-5-mini", "o3", "o4-mini"]
  },
  "gemini": {
    "configured": false,
    "models": []
  },
  "ollama": {
    "configured": true,
    "models": ["llama3", "codellama"]
  }
}
```

## Using Multiple Models

### In Skills

Set the `model` field in a skill's frontmatter to any supported model. The [Test Runner](./test-runner) uses this model when you test the skill.

```yaml
---
name: Code Review
model: gpt-5.4
---
```

### In the Playground

The [Playground](./playground) has a model dropdown that lists all available models from all configured providers. You can switch models between turns to compare responses.

### Default Model

Set a default model in Settings. This is used when a skill does not specify a model in its frontmatter.

::: tip
A good workflow is to write skills without a model field (using the default) and only set a specific model when the skill is optimized for a particular model's strengths.
:::
