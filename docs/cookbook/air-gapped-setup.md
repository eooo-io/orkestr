# Air-Gapped Local Setup

**Goal:** Run Orkestr completely offline with local models via Ollama — zero external network calls.

**Time:** 30 minutes

## Ingredients

- A machine with at least 16GB RAM (32GB recommended for larger models)
- Docker and Docker Compose installed
- Ollama installed (or will install during this recipe)

## Steps

### 1. Install Ollama

```bash
curl -fsSL https://ollama.com/install.sh | sh
```

### 2. Pull Local Models

Download the models you'll need:

```bash
# General-purpose reasoning (recommended starting point)
ollama pull llama3.2

# Code-specialized model
ollama pull codellama

# Smaller, faster model for simple tasks
ollama pull phi3

# (Optional) Larger model for complex reasoning
ollama pull llama3.1:70b   # Requires 48GB+ RAM
```

### 3. Install Orkestr

```bash
git clone https://github.com/eooo-io/orkestr.git
cd orkestr
cp .env.example .env
```

### 4. Configure for Air-Gap

Edit `.env`:

```env
# Database
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=orkestr
DB_USERNAME=root
DB_PASSWORD=your-secure-password

# Local Models ONLY — no cloud API keys
OLLAMA_URL=http://host.docker.internal:11434

# Leave these empty or remove them entirely
# ANTHROPIC_API_KEY=
# OPENAI_API_KEY=
# GEMINI_API_KEY=

# Project path
PROJECTS_HOST_PATH=/path/to/your/projects
```

### 5. Build and Start

```bash
make build
make up
make migrate
```

Start the frontend:
```bash
cd ui && npm install && npm run dev
```

### 6. Enable Air-Gap Mode

1. Log in at `http://localhost:8000/admin` (admin@admin.com / password)
2. Go to the React SPA at `http://localhost:5173`
3. Navigate to **Settings → Infrastructure → Air-Gap Mode**
4. Toggle **Air-Gap Mode** to ON

Orkestr will verify:
- At least one local model is reachable
- No cloud API keys are configured
- All MCP servers are local
- Network enforcement is active

### 7. Configure Default Model

Go to **Settings → General** and set the default model to a local model:

- **Default model:** `llama3.2`

### 8. Create Your First Project and Skills

Everything works exactly the same as with cloud models — you just use local model names:

```yaml
---
name: Code Review Standards
model: llama3.2
---
```

Or use `codellama` for code-focused tasks:

```yaml
---
name: API Design Guide
model: codellama
---
```

### 9. Test It

Open the Playground, select `llama3.2`, and try a prompt. Responses come from your local Ollama instance — nothing leaves your machine.

### 10. Verify Air-Gap

Check the Air-Gap status panel in Settings. It should confirm:
- Network enforcement: **Active**
- External calls: **0**
- All models: **Local**
- All MCP servers: **Local**

## Result

You have a fully air-gapped Orkestr instance that:
- Runs entirely on your local hardware
- Uses Ollama for all AI model calls
- Makes zero external network requests
- Supports all platform features (agents, workflows, canvas, etc.)
- Is suitable for classified, regulated, or sensitive environments

## Performance Tips

| Model | RAM Required | Speed | Quality |
|---|---|---|---|
| `phi3` | 4GB | Very fast | Good for simple tasks |
| `llama3.2` | 8GB | Fast | Good general-purpose |
| `codellama` | 8GB | Fast | Best for code tasks |
| `llama3.1:70b` | 48GB | Slower | Best quality (needs big machine) |

- Use smaller models for simple tasks (triage, classification)
- Reserve larger models for complex reasoning (architecture review, security audit)
- Configure fallback chains: `llama3.1:70b` → `llama3.2` → `phi3`
