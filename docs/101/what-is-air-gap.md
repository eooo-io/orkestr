# What is Air-Gap Mode?

## The One-Sentence Answer

Air-gap mode runs Orkestr with **zero external network calls** — all AI models, tools, and data stay entirely on your infrastructure.

## The Analogy: A Submarine

A submarine operates completely self-contained underwater. It carries its own power, food, air supply, and communication equipment. It doesn't need to surface to function.

Air-gap mode makes Orkestr a submarine. Everything it needs is on board — local AI models, local tool servers, local data. No internet required. Nothing leaves your network.

## Why Air-Gap?

Some environments can't — or shouldn't — send data to external APIs:

| Scenario | Why Air-Gap |
|---|---|
| Government / defense | Classified data cannot leave secure networks |
| Healthcare | Patient data (HIPAA) must stay on-premises |
| Financial services | Trading algorithms and financial models are proprietary |
| Air-gapped networks | Some facilities literally have no internet connection |
| Competitive advantage | Source code and business logic are trade secrets |
| Compliance | Regulations require data residency |

## What Happens in Air-Gap Mode

When you enable air-gap mode:

1. **All cloud API calls are blocked** — Anthropic, OpenAI, Google, xAI, OpenRouter are disabled
2. **Only local models are available** — Ollama, vLLM, TGI, LM Studio
3. **MCP servers must be local** — Remote MCP endpoints are blocked
4. **Network enforcement** — Orkestr validates that no outbound HTTP requests escape the local network

## Setting Up Air-Gap

### 1. Install Local Models

Use Ollama (simplest) or any OpenAI-compatible server:

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Pull models
ollama pull llama3.2
ollama pull codellama
ollama pull mistral
```

### 2. Configure Orkestr

Point Orkestr at your local model server:

```env
OLLAMA_URL=http://localhost:11434
```

### 3. Enable Air-Gap Mode

Toggle it in Settings → Infrastructure → Air-Gap Mode. Orkestr verifies that:
- At least one local model is reachable
- All configured MCP servers are local
- All configured A2A endpoints are local
- No cloud API keys are being used

## What Still Works

Everything in the platform works in air-gap mode — just with local models instead of cloud APIs:

- Agent design and execution
- Workflows and orchestration
- MCP tool calls (local servers only)
- A2A delegation (local agents only)
- Guardrails and safety policies
- Version history and diff viewer
- Provider sync (generates config files locally)
- Skill editor, canvas, and all UI features

## Key Takeaway

Air-gap mode is Orkestr running as a fully self-contained system. Zero external network calls. All AI processing happens on your hardware with your models. The full platform works — design, execute, manage — just without cloud dependencies.

---

Return to the [Orkestr 101 index](/101/) →
