# Keyboard Shortcuts

Orkestr supports keyboard shortcuts throughout the React SPA for common actions.

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

### Monaco Editor Shortcuts

The embedded Monaco editor supports all standard editing shortcuts:

| Shortcut | Action |
|---|---|
| `Ctrl+Z` / `Cmd+Z` | Undo |
| `Ctrl+Shift+Z` / `Cmd+Shift+Z` | Redo |
| `Ctrl+F` / `Cmd+F` | Find |
| `Ctrl+H` / `Cmd+H` | Find and Replace |
| `Ctrl+D` / `Cmd+D` | Select next occurrence (multi-cursor) |
| `Alt+Up` / `Option+Up` | Move line up |
| `Alt+Down` / `Option+Down` | Move line down |
| `Ctrl+Shift+K` / `Cmd+Shift+K` | Delete line |
| `Ctrl+/` / `Cmd+/` | Toggle line comment |
| `Ctrl+L` / `Cmd+L` | Select entire line |
| `Ctrl+Shift+L` / `Cmd+Shift+L` | Select all occurrences of selection |

## Canvas Controls

The visual workflow builder and agent canvas support these controls:

| Input | Action |
|---|---|
| `Scroll wheel` | Zoom in/out |
| `Ctrl++` / `Cmd++` | Zoom in |
| `Ctrl+-` / `Cmd+-` | Zoom out |
| `Ctrl+0` / `Cmd+0` | Fit view to content |
| `F11` or fullscreen button | Toggle fullscreen mode |
| `Escape` | Exit fullscreen mode |
| `Click + Drag` on background | Pan the canvas |
| `Click + Drag` on node | Move the node |

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
On macOS, all `Ctrl` shortcuts also work with `Cmd`. The shortcut hints in the UI automatically adapt to your operating system.
:::
