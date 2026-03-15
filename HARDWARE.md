# Hardware Recommendations — Orkestr Self-Hosted

> What you need to run Orkestr on your own infrastructure.

The hardware requirements depend on whether you use cloud APIs (Anthropic, OpenAI, etc.) or run local model inference via Ollama/vLLM.

---

## Tier 1: Cloud API Only

**Use case:** Orkestr + MariaDB, all inference via cloud providers (Anthropic, OpenAI, Gemini, Grok, OpenRouter).

The application itself is lightweight — PHP, MariaDB, and a static React build.

| Option | Specs | Price | Notes |
|--------|-------|-------|-------|
| **Mac Mini M4** | 10-core CPU, 16GB RAM, 256GB SSD | ~$599 | Handles 10+ concurrent users comfortably |
| **Any Linux box** | 4 cores, 8GB RAM, 50GB SSD | ~$300-500 | Ubuntu 22.04+ or Debian 12+ |
| **Hetzner CX32** | 4 vCPU, 8GB RAM, 80GB SSD | ~$15/mo | Best value cloud VPS |
| **AWS t3.medium** | 2 vCPU, 4GB RAM, EBS | ~$30/mo | Standard cloud option |
| **DigitalOcean Droplet** | 4 vCPU, 8GB RAM, 160GB SSD | ~$48/mo | Simple setup |

**This is the right starting point for most teams.** You're paying per-token to cloud providers, but zero hardware investment beyond a basic server.

---

## Tier 2: Small Local Models (7B-13B parameters)

**Use case:** Running Llama 3 8B, Mistral 7B, Phi-3, Gemma 2 9B, or similar quantized models locally via Ollama.

| Option | Specs | Price | Performance |
|--------|-------|-------|-------------|
| **Mac Mini M4 Pro** | 14-core CPU, 24GB unified memory, 512GB SSD | ~$1,599 | ~30 tok/s on 8B models |
| **Mac Mini M4 Pro** | 14-core CPU, 48GB unified memory, 512GB SSD | ~$1,999 | ~25 tok/s on 13B models, room for multiple |
| **Linux + RTX 4060 Ti 16GB** | 6-core CPU, 32GB RAM, RTX 4060 Ti (16GB VRAM) | ~$1,200-1,500 | ~40 tok/s on 8B models |
| **Linux + RTX 4070 Ti Super** | 6-core CPU, 32GB RAM, RTX 4070 Ti Super (16GB VRAM) | ~$1,500-1,800 | ~50 tok/s on 8B models |

**Minimum requirements:** 16GB unified memory (Apple Silicon) or 12GB+ VRAM (NVIDIA GPU) for 7B quantized models.

**Good for:** Reducing API costs, air-gapped environments, data sovereignty requirements, running routine/simple agent tasks locally while reserving cloud APIs for complex reasoning.

---

## Tier 3: Medium Local Models (30B-70B parameters)

**Use case:** Running Llama 3 70B, Mixtral 8x7B, DeepSeek 67B, CodeLlama 34B, or similar models for production-grade local inference.

| Option | Specs | Price | Performance |
|--------|-------|-------|-------------|
| **Mac Studio M4 Ultra** | 32-core CPU, 128GB unified memory, 1TB SSD | ~$3,999 | ~15 tok/s on 70B Q4 |
| **Mac Studio M4 Ultra** | 32-core CPU, 192GB unified memory, 2TB SSD | ~$5,999 | ~20 tok/s on 70B Q5, multiple models loaded |
| **Linux + RTX 4090** | 8-core CPU, 64GB RAM, RTX 4090 (24GB VRAM) | ~$2,500-3,000 | 30B Q4 models, ~25 tok/s |
| **Linux + 2x RTX 4090** | 8-core CPU, 64GB RAM, 2x RTX 4090 (48GB VRAM) | ~$4,500-5,500 | 70B Q4 models, ~20 tok/s |
| **Linux + A6000** | 8-core CPU, 64GB RAM, NVIDIA A6000 (48GB VRAM) | ~$5,000-6,000 | 70B Q4 models, enterprise-grade |

**Apple Silicon advantage:** Unified memory means a 192GB Mac Studio can load models that would require multiple GPUs on Linux. Slower per-token than NVIDIA, but simpler setup and no VRAM fragmentation.

**NVIDIA advantage:** Faster raw inference speed. Better for high-throughput scenarios. CUDA ecosystem support. Multiple GPUs scale linearly with vLLM/TGI.

---

## Tier 4: Enterprise Fleet

**Use case:** Multiple models running concurrently, dozens of agents, high-throughput production workloads.

| Option | Specs | Price | Notes |
|--------|-------|-------|-------|
| **Multi-GPU Linux server** | 4x RTX 4090, 128GB RAM, 2TB NVMe | ~$8,000-12,000 | Run multiple 70B models simultaneously |
| **NVIDIA A100 server** | 2x A100 (80GB each), 256GB RAM | ~$15,000-25,000 | Enterprise inference, FP16 performance |
| **Kubernetes cluster** | 3+ nodes, dedicated GPU node(s) | Varies | Use the Orkestr Helm chart |
| **Mac Studio fleet** | 2-3x Mac Studio M4 Ultra (192GB each) | ~$12,000-18,000 | Simple, quiet, low power draw |

**Architecture at this scale:**
- Separate Orkestr app server (Tier 1 hardware) from inference server(s)
- Use vLLM or TGI on GPU servers, connect as custom endpoints in Orkestr
- Kubernetes with the Orkestr Helm chart for orchestration scaling
- Redis for session store and cache, separate MariaDB instance

---

## Hybrid Strategy (Recommended)

Most teams should start hybrid and scale local inference as needed:

1. **Start with Tier 1** — cloud APIs only, $0-600 hardware investment
2. **Add a Tier 2 box** when you want to reduce API costs or need air-gap — $1,500
3. **Route per-agent:** complex reasoning tasks to Claude/GPT via cloud, routine tasks to local Llama/Mistral
4. **Scale to Tier 3+** only when local inference volume justifies the hardware

Orkestr's per-agent model routing makes this seamless — no code changes, just reassign models in the UI.

---

## Power and Noise Considerations

| Hardware | Idle Power | Load Power | Noise |
|----------|-----------|------------|-------|
| Mac Mini M4 | ~5W | ~25W | Silent |
| Mac Mini M4 Pro | ~8W | ~40W | Silent |
| Mac Studio M4 Ultra | ~20W | ~120W | Quiet |
| Linux + RTX 4060 Ti | ~60W | ~200W | Moderate |
| Linux + RTX 4090 | ~80W | ~450W | Loud |
| Linux + 2x RTX 4090 | ~100W | ~800W | Very loud, needs cooling |

**For office environments:** Apple Silicon is unbeatable — silent, low power, no special cooling. A Mac Studio under a desk runs 70B models without anyone hearing it.

**For server rooms:** Linux + NVIDIA for raw throughput. Plan for cooling and power delivery.

---

## Storage Requirements

| Component | Size |
|-----------|------|
| Orkestr application | ~500MB |
| MariaDB data | ~1-5GB (depends on projects/versions) |
| 7B model (Q4 quantized) | ~4GB |
| 13B model (Q4 quantized) | ~7GB |
| 34B model (Q4 quantized) | ~18GB |
| 70B model (Q4 quantized) | ~35GB |
| Multiple models cached | 50-200GB |

**Recommendation:** 256GB SSD minimum for Tier 1, 512GB+ for Tier 2, 1TB+ for Tier 3+.
