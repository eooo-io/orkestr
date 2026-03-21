# Settings

Orkestr settings are stored in the `app_settings` table as key-value pairs. Configure them through the Settings page in the React SPA.

## Where to Configure

### React SPA Settings Page

The React SPA includes a Settings page (accessible from the sidebar at http://localhost:5173) that shows API key status and provider configuration.

### Environment Variables

Some settings can also be set via `.env` file. Database values take precedence over environment variables when both are set.

## API Keys

### Anthropic API Key

- **Setting key:** `anthropic_api_key`
- **Env variable:** `ANTHROPIC_API_KEY`
- **Required for:** Claude models (claude-sonnet, claude-opus, claude-haiku)
- **Get one at:** https://console.anthropic.com/

This is the primary API key. You need it for the test runner and playground to work with Claude models.

### OpenAI API Key

- **Setting key:** `openai_api_key`
- **Env variable:** `OPENAI_API_KEY`
- **Required for:** GPT-4o, o3, and other OpenAI models
- **Get one at:** https://platform.openai.com/api-keys

### Google Gemini API Key

- **Setting key:** `gemini_api_key`
- **Env variable:** `GEMINI_API_KEY`
- **Required for:** Gemini models
- **Get one at:** https://aistudio.google.com/apikey

### Ollama URL

- **Setting key:** `ollama_url`
- **Env variable:** `OLLAMA_URL`
- **Default:** `http://localhost:11434`
- **Required for:** Local Ollama models

No API key needed -- just a running Ollama instance.

## Model Configuration

### Default Model

- **Setting key:** `default_model`
- **Default:** `claude-sonnet-4-6`

Used when a skill does not specify a model in its frontmatter. Also used as the initial selection in the Playground model dropdown.

## Project Configuration

### Allowed Project Paths

Projects are registered with an absolute filesystem path. In Docker, the `PROJECTS_HOST_PATH` environment variable controls which host directory is mounted into the container.

::: warning
Only directories within the configured filesystem disk are accessible. Attempting to register a project outside the allowed paths will fail.
:::

## Settings API

### Read All Settings

```
GET /api/settings
```

Returns a JSON object with all setting keys and values. API keys are returned as boolean `configured` flags (not the actual key values) for security.

### Update Settings

```
PUT /api/settings
```

```json
{
  "anthropic_api_key": "sk-ant-...",
  "default_model": "claude-sonnet-4-6",
  "ollama_url": "http://localhost:11434"
}
```

## Environment Variables Summary

All API keys can be set via `.env` or through the Settings UI. Database values take precedence.

```env
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=AIza...
OLLAMA_URL=http://localhost:11434
PROJECTS_HOST_PATH=/path/to/your/dev/directory
PROJECTS_BASE_PATH=/projects
```

## Internal Storage

Settings use the `AppSetting` model with static helpers:

```php
// Read a setting
$key = AppSetting::get('anthropic_api_key');

// Write a setting
AppSetting::set('default_model', 'claude-sonnet-4-6');
```

The `key` column has a unique constraint. `get()` returns `null` for missing keys. `set()` performs an upsert.
