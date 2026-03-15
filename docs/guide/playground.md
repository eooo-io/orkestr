# Playground

The Playground is a multi-turn chat interface for testing skills and agents in a conversational context. Unlike the Test tab (which is single-turn and tied to a specific skill), the Playground lets you choose any system prompt source and have an extended conversation.

## Accessing the Playground

Click **Playground** in the sidebar navigation, or press `Ctrl+K` and search for "Playground".

## Layout

The Playground has a split-pane layout:

- **Left sidebar** -- Configuration: project picker, system prompt source, model, max tokens
- **Right area** -- Chat interface with message history

## Choosing a System Prompt

Select a project first, then choose a system prompt source from three options:

### Skill

Pick any skill from the selected project. The resolved body (with includes and template variables) is used as the system prompt.

### Agent

Pick any enabled agent from the project. The composed output (base instructions + custom instructions + assigned skill bodies) is used as the system prompt.

### Custom

Write a freeform system prompt directly in the text area. Useful for quick experiments without creating a skill.

A **system prompt preview** shows the full text and a token estimate so you can verify what the model will receive.

## Model and Token Configuration

- **Model** -- Select from all available models across configured providers. The dropdown fetches available models dynamically from the `/api/models` endpoint.
- **Max tokens** -- Set the maximum output tokens per response.

## Conversation

Type a message and press **Send** or `Ctrl+Enter`. The response streams in via SSE with a cursor animation.

The full conversation history is maintained in the session. Each turn shows:

- **Your message** -- The user prompt you sent
- **Assistant response** -- The model's streamed reply
- **Per-turn stats** -- Elapsed time, input token count, output token count

All previous messages are sent with each request, so the model has full conversation context.

## Controls

- **Copy** -- Copy any individual message to clipboard
- **Clear** -- Reset the conversation and start fresh
- **Stop** -- Abort a streaming response mid-generation
- **Ctrl+Enter** -- Send the current message

The chat area auto-scrolls as new tokens arrive.

## Multi-Turn Chat

The Playground maintains full conversation history in the session. Each request sends all previous messages to the model, providing full context for multi-turn interactions. This is different from the Test Tab, which is single-turn only.

The conversation context grows with each turn. Monitor the token estimate in the sidebar to stay within the model's context window. If you approach the limit, clear the conversation and start fresh.

## SSE Streaming

Responses are delivered via Server-Sent Events (SSE). Tokens appear incrementally with a cursor animation as they arrive from the model. The SSE connection:

- Streams token-by-token for real-time feedback
- Sends a final event with usage statistics (input tokens, output tokens, latency)
- Can be cancelled mid-stream using the **Stop** button or `Escape`

The API endpoint behind the Playground:

```
POST /api/playground
```

```json
{
  "system_prompt": "You are a helpful assistant...",
  "messages": [
    { "role": "user", "content": "Hello" },
    { "role": "assistant", "content": "Hi there!" },
    { "role": "user", "content": "What can you do?" }
  ],
  "model": "claude-sonnet-4-6",
  "max_tokens": 4096
}
```

## Model Selection

The model dropdown dynamically fetches available models from all configured providers via `/api/models`. Models are grouped by provider:

- **Anthropic** -- Claude Opus 4.6, Sonnet 4.6, Haiku 4.5
- **OpenAI** -- GPT-5.4, o3
- **Gemini** -- Gemini 3.1 Pro, Gemini 3 Flash
- **Grok** -- Grok 3 family
- **OpenRouter** -- 200+ models via single API key
- **Ollama** -- Any locally running model
- **Custom** -- Any OpenAI-compatible endpoint

Only providers with valid API keys (or running local servers) appear in the dropdown.

## Differences from the Test Tab

| Feature | Test Tab | Playground |
|---|---|---|
| Turns | Single turn | Multi-turn |
| System prompt | Current skill only | Any skill, agent, or custom |
| Location | Inside Skill Editor | Standalone page |
| Model selection | From skill frontmatter | Dropdown with all available models |
| Conversation history | Not preserved | Maintained per session |
