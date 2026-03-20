# What is Multi-Model?

## The One-Sentence Answer

Multi-model means Orkestr works with any AI model — cloud APIs, local models, or a mix of both — and can route different tasks to different models.

## The Analogy: A Multilingual Team

Imagine you have a team that speaks multiple languages. You assign the French client to the French speaker, the German client to the German speaker, and so on. Each person uses the language best suited to their task.

Multi-model works the same way. Different AI tasks have different requirements:
- Complex reasoning? Use a powerful model like Claude Opus or GPT-4
- Quick, simple tasks? Use a fast model like Claude Haiku or a local Llama model
- Sensitive data? Use a local model that never leaves your network
- Budget-constrained? Route to the cheapest model that's good enough

## Supported Models

### Cloud Providers

| Provider | Models | Prefix |
|---|---|---|
| **Anthropic** | Claude Opus, Sonnet, Haiku | `claude-` |
| **OpenAI** | GPT-4o, GPT-4, o3 | `gpt-` or `o3` |
| **Google** | Gemini Pro, Flash | `gemini-` |
| **xAI** | Grok | `grok-` |
| **OpenRouter** | 200+ models from any provider | `openrouter:` |

### Local Models

| Runtime | Models | Setup |
|---|---|---|
| **Ollama** | Llama, Mistral, CodeLlama, Phi, Qwen, and more | Point Orkestr at your Ollama URL |
| **vLLM** | Any HuggingFace model | OpenAI-compatible endpoint |
| **TGI** | Any HuggingFace model | OpenAI-compatible endpoint |
| **LM Studio** | Any GGUF model | OpenAI-compatible endpoint |

### Custom Endpoints

Any server that exposes an OpenAI-compatible API can be registered as a custom endpoint. This covers most local model servers and many commercial providers.

## Model Routing

Orkestr routes each request to the right provider based on the model name:

```
Model: "claude-sonnet-4-6"     → Anthropic API
Model: "gpt-4o"                → OpenAI API
Model: "gemini-pro"            → Google API
Model: "grok-2"                → xAI API
Model: "openrouter:anthropic/claude-3-opus" → OpenRouter
Model: "custom:my-server/llama3" → Custom endpoint
Model: "llama3.2"              → Ollama (default fallback)
```

## Model Fallback Chains

What if your primary model is down? Orkestr supports fallback chains:

```
Primary: claude-sonnet-4-6
  ↓ (if unavailable)
Fallback 1: gpt-4o
  ↓ (if unavailable)
Fallback 2: llama3.2 (local Ollama)
```

If the primary model fails, Orkestr automatically tries the next model in the chain. This keeps your agents running even during cloud outages.

## Per-Agent Model Assignment

Different agents can use different models:

| Agent | Model | Why |
|---|---|---|
| Orchestrator | claude-opus-4-6 | Needs maximum reasoning for coordination |
| Code Review | claude-sonnet-4-6 | Good balance of quality and cost |
| Security | claude-sonnet-4-6 | Needs careful analysis |
| QA | gpt-4o | Cross-model diversity |
| Quick Tasks | llama3.2 (local) | Free, fast, handles simple tasks |

## Per-Step Model Override

In workflows, each step can use a different model:

```
Step 1 (Triage): claude-haiku → fast, cheap classification
Step 2 (Analysis): claude-opus → deep reasoning
Step 3 (Summary): claude-sonnet → balanced output generation
```

## Model Health Dashboard

Orkestr monitors all configured models:

- **Status** — Online, degraded, offline
- **Latency** — Response time per model
- **Error rate** — Failures over time
- **Cost tracking** — Spending per model

## Cross-Model Benchmarking

Want to know which model performs best for a specific skill? Run a benchmark:

1. Select a skill
2. Choose the models to compare
3. Run the benchmark
4. See results side-by-side: quality, latency, cost

This helps you make informed decisions about model routing — use the most expensive model only where it matters.

## Key Takeaway

Multi-model means you're not locked into any single AI provider. Use cloud models for power, local models for privacy and cost savings, and intelligent routing to put the right model on the right task. Fallback chains keep your agents running even when a provider goes down.

---

**Next:** [What is Air-Gap Mode?](./what-is-air-gap) →
