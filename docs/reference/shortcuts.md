# Keyboard Shortcuts

Agentis Studio supports keyboard shortcuts throughout the React SPA for common actions.

## Global Shortcuts

| Shortcut | Action |
|---|---|
| `Ctrl+K` / `Cmd+K` | Open the command palette |
| `Escape` | Close modal, blur input, or navigate back |

## Command Palette

| Shortcut | Action |
|---|---|
| `Arrow Up` / `Arrow Down` | Navigate through results |
| `Enter` | Select the highlighted result |
| `Escape` | Close the palette |

The command palette supports fuzzy search across:
- Skills (by name, across all projects)
- Projects (by name)
- Pages (Projects, Library, Search, Playground, Settings)
- Actions (Add Skill, Sync, Scan)

Recent selections are saved in localStorage and shown when you open the palette with an empty query.

## Skill Editor

| Shortcut | Action |
|---|---|
| `Ctrl+S` / `Cmd+S` | Save the current skill |
| `Ctrl+Enter` / `Cmd+Enter` | Send a test message (when the Test tab is active) |

The editor also respects standard Monaco Editor shortcuts for text editing (undo, redo, find, replace, multi-cursor, etc.).

## Playground

| Shortcut | Action |
|---|---|
| `Ctrl+Enter` / `Cmd+Enter` | Send the current message |
| `Escape` | Stop streaming response |

## Navigation

| Shortcut | Action |
|---|---|
| `Escape` | Close the current modal or navigate back from the skill editor |

::: tip
On macOS, all `Ctrl` shortcuts also work with `Cmd`.
:::
