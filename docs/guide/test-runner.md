# Test Runner

The test runner lets you send your skill's prompt to an LLM and see the streaming response in real time. Use it to iterate on prompts without leaving the editor.

## Using the Test Tab

In the Skill Editor, click the **Test** tab in the right panel. You will see:

- A text input for your test message (the user message to send)
- A **Send** button (or press `Ctrl+Enter`)
- A streaming response area

## Running a Test

1. Type a test message that exercises your skill (e.g., "Review this function for bugs" followed by a code sample)
2. Press **Send** or `Ctrl+Enter`
3. The response streams in via Server-Sent Events (SSE)

The test uses:
- **System prompt** -- The skill's resolved body (with [includes](./includes) and [template variables](./templates) expanded)
- **Model** -- The model specified in the skill's frontmatter (falls back to the default model in [settings](/reference/settings))
- **Max tokens** -- The `max_tokens` value from frontmatter (or the system default)

## Streaming

Responses stream token-by-token using SSE. You see the text appear progressively as the model generates it, just like in a chat interface.

## Stats

After the response completes, the panel shows:

- **Elapsed time** -- How long the response took
- **Token count** -- Input and output token counts (as reported by the model)

## Stopping Mid-Stream

Click the **Stop** button that appears during streaming to abort the response. The partial output remains visible.

## Copying the Result

Click the **Copy** button to copy the full response text to your clipboard.

## Multi-Model Support

The test runner supports multiple LLM providers. The model used is determined by the skill's `model` field. See [Multi-Model Setup](./multi-model) for how to configure API keys for OpenAI, Gemini, and Ollama.

::: tip
For longer, multi-turn conversations with more control over model selection and system prompt source, use the [Playground](./playground).
:::
