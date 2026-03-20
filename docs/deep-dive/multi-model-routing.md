# Multi-Model Routing

This deep dive covers how Orkestr routes LLM requests across providers, manages fallback chains, monitors model health, and optimizes costs.

## LLMProviderFactory

The central routing layer. Located at `app/Services/LLM/LLMProviderFactory.php`.

### Prefix-Based Routing

```
Model name → Provider mapping:
├── "claude-*"         → AnthropicProvider
├── "gpt-*" | "o3*"   → OpenAIProvider
├── "gemini-*"         → GeminiProvider
├── "grok-*"           → GrokProvider (xAI, OpenAI-compatible)
├── "openrouter:*"     → OpenRouterProvider
├── "custom:*"         → Custom endpoint (OpenAI-compatible)
└── (default)          → OllamaProvider (local models)
```

### Provider Interface

All providers implement `LLMProviderInterface`:

```php
interface LLMProviderInterface
{
    public function chat(string $model, array $messages, array $options = []): array;
    public function stream(string $model, array $messages, array $options = []): Generator;
    public function listModels(): array;
    public function healthCheck(): array;
}
```

### Provider Implementations

| Provider | API | Authentication | Features |
|---|---|---|---|
| **AnthropicProvider** | Messages API | `ANTHROPIC_API_KEY` | Streaming, tools, vision |
| **OpenAIProvider** | Chat Completions | `OPENAI_API_KEY` | Streaming, tools, vision |
| **GeminiProvider** | Generate Content | `GEMINI_API_KEY` | Streaming, tools |
| **GrokProvider** | OpenAI-compatible | `GROK_API_KEY` | Streaming, tools |
| **OpenRouterProvider** | OpenAI-compatible | `OPENROUTER_API_KEY` | 200+ models |
| **OllamaProvider** | Ollama API | None (local) | Streaming, tools |
| **CustomEndpoint** | OpenAI-compatible | Configurable | Per-endpoint auth |

## Fallback Chains

Each agent can define a fallback chain — an ordered list of models to try:

```
Fallback chain for "Security Auditor":
  1. claude-sonnet-4-6 (primary)
  2. gpt-4o (first fallback)
  3. llama3.2 (local, last resort)
```

### Fallback Logic

```
function callWithFallback(chain, messages, options):
  for each model in chain:
    try:
      provider = resolveProvider(model)
      response = provider.chat(model, messages, options)
      return response
    catch ProviderError:
      log("Model {model} failed: {error}, trying next")
      continue
    catch RateLimitError:
      log("Model {model} rate limited, trying next")
      continue

  throw AllProvidersFailed("No model in fallback chain is available")
```

Fallback is triggered by:
- API errors (500, 503, connection timeout)
- Rate limiting (429)
- Model unavailable

It is NOT triggered by:
- Content policy rejections (the issue would repeat)
- Invalid request errors (4xx other than 429)

## Per-Step Model Override

In workflow steps, each step can override the agent's default model:

```
workflow_steps.config:
{
  "model_override": "claude-opus-4-6",
  "fallback_chain": ["claude-opus-4-6", "claude-sonnet-4-6"]
}
```

This lets you use expensive models for critical steps and cheaper models for simple ones within the same workflow.

## Model Health Monitoring

### Health Checks

Each provider implements `healthCheck()`:

```
HealthCheck response:
{
  "provider": "anthropic",
  "status": "healthy" | "degraded" | "down",
  "latency_ms": 245,
  "models": [
    { "id": "claude-sonnet-4-6", "status": "available" },
    { "id": "claude-opus-4-6", "status": "available" }
  ],
  "last_checked": "2026-03-20T10:30:00Z"
}
```

Health checks run periodically and on demand. Results are cached and displayed on the Model Health dashboard.

### Latency Benchmarking

The benchmarking system measures model performance:

```
POST /api/model-health/benchmark
{
  "models": ["claude-sonnet-4-6", "gpt-4o", "llama3.2"],
  "prompt": "Explain the concept of dependency injection in 3 sentences.",
  "iterations": 5
}

Response:
{
  "results": [
    {
      "model": "claude-sonnet-4-6",
      "avg_latency_ms": 1250,
      "avg_tokens_per_second": 85,
      "avg_total_tokens": 120,
      "avg_cost": 0.0004,
      "success_rate": 1.0
    },
    { ... }
  ]
}
```

### Cost Optimization

The routing layer tracks per-model costs:

```
Model pricing (per 1M tokens):
├── claude-opus-4-6:   $15 input / $75 output
├── claude-sonnet-4-6: $3 input / $15 output
├── claude-haiku-4-5:  $0.80 input / $4 output
├── gpt-4o:            $2.50 input / $10 output
├── gemini-pro:        $1.25 input / $5 output
├── grok-2:            $2 input / $10 output
├── llama3.2 (local):  $0 (hardware cost only)
└── openrouter:        varies by model
```

Cost data feeds into:
- Per-run cost tracking in execution traces
- Budget guard enforcement
- Analytics dashboards
- Model recommendation engine

## Custom Endpoints

Any OpenAI-compatible server can be registered:

```
custom_endpoints
├── id (UUID)
├── name: "Internal vLLM Server"
├── base_url: "https://vllm.internal:8000/v1"
├── api_key: "..."
├── models (JSON): discovered or manual list
├── health_status: "healthy" | "degraded" | "unknown"
└── last_health_check

Usage: model name "custom:internal-vllm/llama3-70b"
  → routes to CustomEndpoint with id matching "internal-vllm"
  → calls base_url/chat/completions with model "llama3-70b"
```

### Model Discovery

Custom endpoints support model discovery:

```
POST /api/custom-endpoints/{id}/discover
  → calls GET {base_url}/models
  → parses response for available model IDs
  → stores in endpoint's models field
```

## OpenRouter Integration

OpenRouter provides access to 200+ models through a single API key:

```
Model name format: "openrouter:{provider}/{model}"
Examples:
├── openrouter:anthropic/claude-3-opus
├── openrouter:openai/gpt-4-turbo
├── openrouter:meta-llama/llama-3-70b
├── openrouter:google/gemini-pro
└── openrouter:mistralai/mixtral-8x7b
```

OpenRouter handles routing, rate limiting, and billing. Orkestr simply calls the OpenRouter API with the model name.

## Air-Gap Mode Enforcement

When air-gap mode is enabled:

```
Provider availability:
├── AnthropicProvider  → DISABLED
├── OpenAIProvider     → DISABLED
├── GeminiProvider     → DISABLED
├── GrokProvider       → DISABLED
├── OpenRouterProvider → DISABLED
├── OllamaProvider     → ENABLED (must be local)
├── CustomEndpoint     → ENABLED (must be local IP/hostname)
```

The network enforcement guard validates that all endpoint URLs resolve to local/private addresses (127.0.0.1, 10.x.x.x, 172.16-31.x.x, 192.168.x.x).
