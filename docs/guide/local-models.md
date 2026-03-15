# Local Models

Orkestr supports running LLMs locally for privacy, cost control, or air-gapped environments. You can use Ollama out of the box and add custom endpoints for other inference servers.

## Ollama Integration

[Ollama](https://ollama.ai) is the simplest way to run local models with Orkestr.

### Setup

1. Install Ollama on your host machine
2. Pull a model: `ollama pull llama3.1` or `ollama pull codellama`
3. Set the Ollama URL in your `.env`:

```env
OLLAMA_URL=http://localhost:11434
```

When running Orkestr in Docker, use `http://host.docker.internal:11434` to reach the host's Ollama instance.

4. Verify the connection in the Orkestr UI at **Settings > Models** or via the API:

```
GET /api/local-models
```

### Using Local Models

Once connected, Ollama models appear in every model selector throughout the application -- skill editor, test runner, playground, and agent configuration. Any model name that does not match a cloud provider prefix (`claude-`, `gpt-`, `o`, `gemini-`) is routed to Ollama automatically.

## Custom Endpoints

For inference servers beyond Ollama -- such as vLLM, TGI (Text Generation Inference), or LM Studio -- use custom endpoints.

### Configuration

Create custom endpoints via the API or the React SPA at **Settings > Custom Endpoints**:

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

Supported API formats:
- **openai** -- OpenAI-compatible `/v1/chat/completions` (vLLM, LM Studio, llama.cpp server)
- **tgi** -- Hugging Face Text Generation Inference format

### vLLM Example

```bash
# Start vLLM server
python -m vllm.entrypoints.openai.api_server \
  --model codellama/CodeLlama-70b-Instruct-hf \
  --port 8000
```

Then add a custom endpoint in Orkestr with format `openai` and URL `http://your-server:8000/v1/chat/completions`.

### LM Studio Example

LM Studio exposes an OpenAI-compatible API on port 1234 by default:

```env
# Custom endpoint URL
http://localhost:1234/v1/chat/completions
```

## Air-Gap Mode

Air-gap mode ensures Orkestr makes zero outbound network calls. Enable it when operating in restricted environments.

```
GET /api/air-gap          # Check air-gap status
POST /api/air-gap         # Enable/disable air-gap mode
```

```json
{
  "enabled": true
}
```

When air-gap mode is active:

- All cloud provider API calls are blocked (Anthropic, OpenAI, Gemini, Grok)
- Only local models (Ollama, custom endpoints on the local network) are available
- Marketplace, skills.sh import, and other external features are disabled
- License validation switches to offline mode

Air-gap mode is indicated in the UI header and enforced at the service layer.

## Model Health & Benchmarking

Monitor your model infrastructure and compare performance.

### Health Checks

```
GET /api/model-health
```

Returns connectivity status, response times, and availability for all configured providers and endpoints.

### Benchmarking

Run standardized benchmarks against any model:

```
POST /api/model-health/benchmark
```

```json
{
  "models": ["llama3.1", "codellama-70b"],
  "benchmark": "code-completion",
  "iterations": 5
}
```

### Model Comparison

Compare two or more models side-by-side:

```
GET /api/model-health/compare?models=llama3.1,codellama-70b
```

Returns latency, throughput (tokens/sec), and quality scores from recent benchmarks.
