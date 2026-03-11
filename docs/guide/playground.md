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

## Differences from the Test Tab

| Feature | Test Tab | Playground |
|---|---|---|
| Turns | Single turn | Multi-turn |
| System prompt | Current skill only | Any skill, agent, or custom |
| Location | Inside Skill Editor | Standalone page |
| Model selection | From skill frontmatter | Dropdown with all available models |
| Conversation history | Not preserved | Maintained per session |
