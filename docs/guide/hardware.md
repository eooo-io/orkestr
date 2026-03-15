# Hardware Recommendations

What you need to run Orkestr on your own infrastructure. Requirements depend on whether you use cloud APIs or run local model inference.

## Quick Guide

| Deployment | Hardware | Budget |
|-----------|----------|--------|
| Cloud APIs only | Mac Mini M4 or any 4-core Linux box | $0-600 |
| Small local models (7-13B) | Mac Mini M4 Pro (24-48GB) or Linux + RTX 4060 Ti | $1,200-2,000 |
| Large local models (30-70B) | Mac Studio M4 Ultra (192GB) or Linux + 2x RTX 4090 | $4,000-6,000 |
| Enterprise fleet | Multi-GPU server or Kubernetes cluster | $8,000+ |

## Tier 1: Cloud API Only

The Orkestr application itself is lightweight — PHP, MariaDB, and a static React build. All inference happens on provider infrastructure.

**Minimum specs:** 4 cores, 8GB RAM, 50GB SSD.

Recommended options:
- **Mac Mini M4** (16GB, ~$599) — handles 10+ concurrent users
- **Hetzner CX32** (~$15/mo) — best value cloud VPS
- **AWS t3.medium** (~$30/mo) — standard cloud option

::: tip
This is the right starting point for most teams. Zero hardware investment beyond a basic server.
:::

## Tier 2: Small Local Models (7B-13B)

For running Llama 3 8B, Mistral 7B, Phi-3, or Gemma 2 9B via Ollama.

**Minimum specs:** 16GB unified memory (Apple Silicon) or 12GB+ VRAM (NVIDIA).

Recommended options:
- **Mac Mini M4 Pro** (24GB, ~$1,599) — ~30 tok/s on 8B models, silent
- **Mac Mini M4 Pro** (48GB, ~$1,999) — ~25 tok/s on 13B models
- **Linux + RTX 4060 Ti 16GB** (~$1,200-1,500) — ~40 tok/s on 8B models

Good for reducing API costs, air-gapped environments, and running routine agent tasks locally.

## Tier 3: Medium Local Models (30B-70B)

For production-grade local inference with Llama 3 70B, Mixtral 8x7B, or DeepSeek 67B.

Recommended options:
- **Mac Studio M4 Ultra** (192GB, ~$5,999) — ~20 tok/s on 70B Q5, multiple models loaded simultaneously
- **Linux + 2x RTX 4090** (48GB VRAM, ~$4,500-5,500) — ~20 tok/s on 70B Q4
- **Linux + A6000** (48GB VRAM, ~$5,000-6,000) — enterprise-grade

**Apple Silicon vs. NVIDIA:**
- Apple Silicon: unified memory (no VRAM limits), silent, low power, simpler setup
- NVIDIA: faster per-token throughput, multi-GPU scaling, CUDA ecosystem

## Tier 4: Enterprise Fleet

Multiple models running concurrently with dozens of agents.

- **Multi-GPU Linux server** (4x RTX 4090) — ~$8,000-12,000
- **NVIDIA A100 server** (2x A100 80GB) — ~$15,000-25,000
- **Kubernetes cluster** with dedicated GPU nodes — use the [Orkestr Helm chart](/guide/self-hosted-deployment)
- **Mac Studio fleet** (2-3x M4 Ultra 192GB) — ~$12,000-18,000, silent

At this scale, separate the Orkestr app server from inference server(s). Use vLLM or TGI on GPU servers, connected as custom endpoints.

## Hybrid Strategy (Recommended)

Most teams should start hybrid and scale local inference as needed:

1. **Start with Tier 1** — cloud APIs, minimal hardware
2. **Add a Tier 2 box** when you want to cut costs or need air-gap
3. **Route per-agent:** Claude/GPT for complex reasoning, local Llama for routine tasks
4. **Scale to Tier 3+** when volume justifies the hardware

Orkestr's per-agent model routing makes this seamless — reassign models in the UI, no code changes.

## Power and Noise

| Hardware | Load Power | Noise |
|----------|-----------|-------|
| Mac Mini M4 | ~25W | Silent |
| Mac Mini M4 Pro | ~40W | Silent |
| Mac Studio M4 Ultra | ~120W | Quiet |
| Linux + RTX 4060 Ti | ~200W | Moderate |
| Linux + RTX 4090 | ~450W | Loud |

For office use, Apple Silicon is ideal — silent and low power. For server rooms, NVIDIA for raw throughput.

## Storage

| Component | Size |
|-----------|------|
| Orkestr application | ~500MB |
| 7B model (Q4) | ~4GB |
| 13B model (Q4) | ~7GB |
| 70B model (Q4) | ~35GB |

256GB SSD minimum for Tier 1, 512GB+ for Tier 2, 1TB+ for Tier 3+.
