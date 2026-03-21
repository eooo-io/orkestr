# Models

Orkestr supports 7 LLM providers through a unified interface. Every model selector in the application -- skill editor, agent configuration, test runner, playground -- draws from the same pool of available models.

## Provider Overview

| Provider | Prefix | Example Models | API Key Env Var |
|---|---|---|---|
| Anthropic | `claude-` | Claude Opus 4.6, Sonnet 4.6, Haiku 4.5 | `ANTHROPIC_API_KEY` |
| OpenAI | `gpt-`, `o3` | GPT-5.4, GPT-5 Mini, o3 | `OPENAI_API_KEY` |
| Google Gemini | `gemini-` | Gemini 3.1 Pro, Gemini 3 Flash | `GEMINI_API_KEY` |
| Grok (xAI) | `grok-` | Grok 3, Grok 3 Fast, Grok 3 Mini | `GROK_API_KEY` |
| OpenRouter | `openrouter:` | 200+ models via single key | `OPENROUTER_API_KEY` |
| Ollama | _(default)_ | Any locally pulled model | `OLLAMA_URL` |
| Custom endpoints | `custom:` | vLLM, TGI, LM Studio | _(per endpoint)_ |

## Model Prefix Routing

The `LLMProviderFactory` examines the model name and routes to the correct provider:

```
claude-sonnet-4-6     → Anthropic
gpt-5.4               → OpenAI
o3                     → OpenAI
gemini-3.1-pro        → Google Gemini
grok-3                → Grok (xAI)
openrouter:meta/llama → OpenRouter
custom:vllm:codellama → Custom endpoint "vllm"
llama3.1              → Ollama (default fallback)
```

Any model name that does not match a known prefix falls through to Ollama. This means local models "just work" without any prefix.

::: tip
You can check which provider a model will route to by looking at its prefix. No configuration is needed beyond setting the API key -- routing is automatic.
:::

## Configuring Providers

### API Keys

Set API keys in one of three places (checked in this order):

1. **Settings UI** -- navigate to **Settings > General** and enter keys in the API Keys section
2. **Database** -- stored via `AppSetting::set()` in the `app_settings` table
3. **Environment** -- `.env` file variables

```env
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...
GEMINI_API_KEY=AIza...
GROK_API_KEY=xai-...
OPENROUTER_API_KEY=sk-or-...
OLLAMA_URL=http://localhost:11434
```

### Checking Provider Status

```
GET /api/models
```

Returns all providers with their configured status and available models:

```json
[
  {
    "provider": "anthropic",
    "label": "Anthropic",
    "configured": true,
    "models": [
      { "id": "claude-opus-4-6", "name": "Claude Opus 4.6", "context_window": 200000 },
      { "id": "claude-sonnet-4-6", "name": "Claude Sonnet 4.6", "context_window": 200000 },
      { "id": "claude-haiku-4-5-20251001", "name": "Claude Haiku 4.5", "context_window": 200000 }
    ]
  }
]
```

## OpenRouter

OpenRouter provides access to 200+ models from dozens of providers through a single API key. This is the fastest way to get access to a wide range of models without managing multiple provider accounts.

### Setup

1. Get an API key from [openrouter.ai](https://openrouter.ai)
2. Set `OPENROUTER_API_KEY` in your `.env` or in **Settings > General**
3. Models become available with the `openrouter:` prefix

### Usage

Prefix any OpenRouter model ID with `openrouter:`:

```yaml
---
name: Research Summary
model: openrouter:meta-llama/llama-3.1-405b-instruct
---
```

Orkestr fetches the full model catalog from OpenRouter's API (cached) including pricing and context window information.

::: tip
OpenRouter is ideal for experimentation. Try dozens of models without signing up for each provider individually.
:::

## Custom Endpoints

For self-hosted inference servers -- vLLM, Text Generation Inference (TGI), LM Studio, or any OpenAI-compatible API -- use custom endpoints.

### Adding a Custom Endpoint

Navigate to **Settings > Infrastructure > Custom Endpoints** or use the API:

```
POST /api/custom-endpoints
```

```json
{
  "name": "vLLM - CodeLlama 70B",
  "url": "http://gpu-server:8000/v1/chat/completions",
  "api_format": "openai",
  "auth_header": "Bearer your-token",
  "models": ["codellama-70b"],
  "timeout": 120
}
```

Once created, the endpoint's models are available with the `custom:` prefix:

```
custom:vllm:codellama-70b
```

The format is `custom:{endpoint-slug}:{model-name}`.

### Supported API Formats

- **openai** -- OpenAI-compatible `/v1/chat/completions` (vLLM, LM Studio, llama.cpp)
- **tgi** -- Hugging Face Text Generation Inference

### Health Checks

Test connectivity to a custom endpoint:

```
POST /api/custom-endpoints/{id}/health
```

### Model Discovery

Some endpoints support listing available models:

```
POST /api/custom-endpoints/{id}/discover
```

## Air-Gap Mode

Air-gap mode blocks all outbound network calls, restricting Orkestr to local models only. Enable it for classified environments or networks with no internet access.

```
POST /api/air-gap
{ "enabled": true }
```

When active:

- Cloud provider API calls are blocked (Anthropic, OpenAI, Gemini, Grok, OpenRouter)
- Only Ollama and custom endpoints on the local network are available
- Skills.sh import and other external features are disabled
- All features remain fully available offline

::: warning
Air-gap mode is a hard block at the service layer. Ensure you have local models configured before enabling it, or you will have no LLM access.
:::

## Model Health & Benchmarking

### Health Dashboard

Monitor all configured providers from **Settings > Infrastructure > Model Health**:

```
GET /api/model-health
GET /api/model-health/{provider}
```

The dashboard shows connectivity status, average response time, and availability for each provider.

### Benchmarking

Run standardized benchmarks against any model:

```
POST /api/model-health/benchmark
```

```json
{
  "models": ["claude-sonnet-4-6", "gpt-5.4", "llama3.1"],
  "benchmark": "code-completion",
  "iterations": 5
}
```

### Model Comparison

Compare performance across models:

```
POST /api/model-health/compare
```

Returns latency, throughput (tokens/sec), and quality scores from recent benchmarks.

## Model Recommendations

Orkestr suggests models based on the type of task an agent or skill performs:

| Task Type | Recommended Models |
|---|---|
| Code generation | Claude Sonnet 4.6, GPT-5.4, CodeLlama 70B |
| Research / analysis | Claude Opus 4.6, Gemini 3.1 Pro |
| Chat / conversation | Claude Haiku 4.5, GPT-5 Mini, Gemini 3 Flash |
| Summarization | Claude Sonnet 4.6, Gemini 3 Flash |
| Fast iteration | Claude Haiku 4.5, Grok 3 Fast, GPT-5.3 Instant |

::: tip
Recommendations are guidelines, not rules. Benchmark your specific use case with the [Cross-Model Benchmark](./execution) feature to find the best model for your workload.
:::

## Default Model

Set a project-wide or global default model in **Settings > General**. Skills and agents that do not specify a model use the default. The factory default is `claude-sonnet-4-6`.
