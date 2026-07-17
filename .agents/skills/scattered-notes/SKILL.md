---
name: scattered-notes
description: >-
  Process a folder of scattered ADHD notes under inputs/ into Phase 1 clarity
  marks and Phase 2 organized tasks/reference with per-line fidelity. Use when
  the user wants to organize braindump .md/.txt notes, run the scattered-notes
  harness, update shorthands, or advance phase1/phase2 artifacts for index.php.
---

# Scattered Notes Harness

This skill runs in **Cursor or Claude Code**. The PHP app (`index.php`) is an artifact container: write artifacts here, tell the user to review in the browser, then continue in chat. Do not switch folders inside the app—only via `app.config.json`.

## Product framing (verbatim)

This is primarily used under Cursor or Claude Code but we open the php file as a container of artifacts. We look at artifacts to know how to prompt subsequently.

## When to use

- User has scattered notes in `inputs/{folder}/` (`.md` / `.txt`)
- User asks to process, organize, clarify, or continue a scattered-notes batch
- User explains shorthand / symbols after reviewing Phase 1 in the app
- User confirms Phase 1 is done and wants Phase 2

## File contracts

Per batch folder `inputs/{folder}/`:

| File | Role |
|------|------|
| `*.md`, `*.txt` (except `shorthands.md`) | Raw scattered notes |
| `shorthands.md` | Glossary for this batch — **always read before marking uncertainty** |
| `phase1.json` | Uncertain marks + YouTube enrichments |
| `phase2.json` | Organized notes + per-line blame map |

Project root:

| File | Role |
|------|------|
| `app.config.json` | `{ "inputFolder": "{folder}", "phase": 1 \| 2 }` — skill owns this |

Skip when reading notes: `shorthands.md`, `phase1.json`, `phase2.json`, and any non-`.md`/`.txt` files.

A scattered note may be a line, a group of lines, or a whole file. Within a file, `---` separates notes. The batch is one broad category (coding, linux, etc.).

### `phase1.json`

```json
{
  "files": {
    "braindump.md": {
      "lines": [
        {
          "n": 1,
          "text": "...",
          "marks": [{ "type": "uncertain", "token": "wtf", "note": "unknown shorthand" }]
        },
        {
          "n": 2,
          "text": "https://youtu.be/...",
          "marks": [],
          "youtube": { "title": "...", "about": "...", "added": true }
        }
      ]
    }
  }
}
```

- Include **every** line of every note file (`n` is 1-based; `text` is the original line).
- Mark unsure tokens especially symbols / shorthand that do not make sense after reading `shorthands.md`.
- For YouTube URLs: set `youtube` with title and a short “what this video is about”, inferred from YouTube title, description, and comments. Do **not** watch the video. The app renders this like a git-diff green added line.

### `phase2.json`

```json
{
  "organized": {
    "tasks": [{ "id": "t1", "title": "...", "body": "...", "sources": ["braindump.md:12"] }],
    "reference": [{ "id": "r1", "title": "...", "body": "...", "sources": ["notes.txt:3"] }],
    "articleCandidates": [{ "id": "a1", "title": "...", "why": "...", "sources": ["braindump.md:4"] }]
  },
  "blame": {
    "braindump.md": [
      { "n": 1, "text": "...", "fate": "mapped", "to": ["t1"], "comment": "merged into install task" }
    ]
  }
}
```

Every source line must have a `blame` entry. Use fates such as: `mapped`, `merged`, `reference`, `uncertain_carried`, `dropped_duplicate`, `dropped_noise`, `separator`.

### `shorthands.md`

Markdown glossary for the batch. Example:

```markdown
# Shorthands

| Token | Meaning |
|-------|---------|
| wtf | write the function |
```

When the user explains a symbol or shorthand in chat, update this file, then re-run Phase 1 marks so resolved tokens are no longer flagged.

## Strict workflow

### 0. Pick folder

1. List directories under `inputs/` (ignore files like `.gitkeep`).
2. Ask the user which folder to process.
3. If none exist: tell them to create `inputs/{name}/`, dump `.md`/`.txt` notes (separate files or one file with `---` — both fine), then re-run.
4. Write `app.config.json` with that folder and `"phase": 1`.

### 1. Phase 1 — mark uncertain + YouTube

1. Ensure `shorthands.md` exists (create a stub with `# Shorthands` and an empty table if missing).
2. **Read `shorthands.md` fully** before marking anything.
3. Read all note `.md`/`.txt` files in the folder.
4. Write/update `phase1.json` with line-level marks and YouTube descriptors.
5. Set `app.config.json` `"phase": 1`.
6. Tell the user to open `index.php` (e.g. `php -S localhost:8765` then browse), review marks, and **return to this chat** to:
   - explain any flagged shorthand/symbols, or
   - say Phase 1 looks good and advance to Phase 2.

Phase 1 may loop: user explains → update `shorthands.md` → rewrite `phase1.json` → user refreshes the app.

### 2. Phase 2 — organize with fidelity

Only after the user confirms Phase 1 is done:

1. Set `app.config.json` `"phase": 2`.
2. Turn notes into grouped **tasks** and **reference** knowledge; recommend **article candidates**.
3. Account for every line in `blame` — fidelity first; do not silently drop details.
4. Write `phase2.json`.
5. Tell the user to refresh `index.php`, review organized output and the hamburger originals/blame panel, then **return to this chat** to refine groupings or fix fidelity gaps.

## Agent checklist

1. List `inputs/` folders; ask which one; update `app.config.json`.
2. Read `shorthands.md` before any uncertainty marks.
3. Never treat `shorthands.md` / `phase1.json` / `phase2.json` as source notes.
4. Phase 1 artifacts must be visible in the app after refresh.
5. YouTube: title + about from metadata/comments only; `youtube.added: true`.
6. Phase 2: every line in every note file appears in `blame`.
7. Do not advance to Phase 2 without explicit user confirmation.
8. After each write, remind the user how to return to Cursor / Claude Code to continue.

## Return-to-chat prompts (for the user)

After Phase 1 writes, tell the user they can say things like:

- “`wtf` means write the function”
- “Phase 1 looks good — start Phase 2”

After Phase 2 writes:

- “Merge t1 and t3”
- “Line 12 in braindump.md should stay as reference, not a task”
