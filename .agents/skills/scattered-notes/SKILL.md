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
| `*.md`, `*.txt` | Raw scattered notes |
| `phase1.json` | Uncertain marks + YouTube enrichments |
| `phase2.json` | Organized notes + per-line blame map |

Project root:

| File | Role |
|------|------|
| `app.config.json` | `{ "inputFolder": "{folder}" \| "", "phase": 1 \| 2 }` — skill owns this; empty `inputFolder` means no project selected |
| `shorthands.md` | App-wide glossary — **always read before marking uncertainty**; shared by every batch |

Skip when reading notes: `phase1.json`, `phase2.json`, any leftover `shorthands.md` inside a batch folder, and any non-`.md`/`.txt` files.

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
- For every YouTube / `youtu.be` URL: **fetch real metadata** (do not invent titles from the URL alone). Prefer YouTube oEmbed `https://www.youtube.com/oembed?url={URL}&format=json`, or fetch the watch page. Then set `youtube` with:
  - `title` — exact video title from the fetch
  - `about` — 1–2 sentences from title + description (and comments if useful)
  - `added: true`
  Do **not** watch the video. The app renders this like a git-diff green added line.
- **Forbidden** YouTube titles/abouts: placeholders such as “YouTube link”, “metadata placeholder”, “unknown video”, empty `about` when the URL is reachable, or any invented stub. If fetch fails after trying: `title` = `YouTube (lookup failed)` and put the failure reason in `about`.

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

Phase 2 items that come from a YouTube line **must reuse** `youtube.title` and `youtube.about` from `phase1.json` (e.g. reference title = that title; body includes the URL plus `about`). Do **not** invent a new title or write a metadata placeholder in Phase 2.

### `shorthands.md` (project root)

App-wide markdown glossary at the project root (not inside a batch folder). Example:

```markdown
# Shorthands

| Token | Meaning |
|-------|---------|
| wtf | write the function |
```

When the user explains a symbol or shorthand in chat, update this root file, then re-run Phase 1 marks so resolved tokens are no longer flagged. Tokens apply across all batches.

## Strict workflow

### 0. Sample offer, then pick folder

On **first start** and whenever **restarting** (re-picking a folder, re-running the skill, or starting over): list directories under `inputs/` (ignore files like `.gitkeep`), then offer these options:

1. **Reset sample and select it** — wipe/recreate `inputs/sample/`, set it as the active project, continue Phase 1.
2. **Reset sample only** — wipe/recreate `inputs/sample/`, reset `app.config.json` so `inputFolder` is empty (no project selected); stop and wait for the next pick.
3. **Use folder `{name}`** — one option per non-sample directory under `inputs/` (skip selecting stale sample unless they pick option 1).
4. **None / use my notes** — if they need a new batch: tell them to create `inputs/{name}/`, dump `.md`/`.txt` notes (separate files or one file with `---` — both fine), then re-run.

Selecting sample is never “open whatever is already there” — options 1 and 2 always run the reset below.

- **If they choose “Reset sample and select it”:**
  1. Delete the entire `inputs/sample/` directory if it exists.
  2. Recreate `inputs/sample/` with only the three note files below (exact contents). Do **not** write `phase1.json` or `phase2.json`.
  3. Write `app.config.json` with `"inputFolder": "sample"` and `"phase": 1`.
  4. Continue with Phase 1 on `sample`.

- **If they choose “Reset sample only”:**
  1. Delete the entire `inputs/sample/` directory if it exists.
  2. Recreate `inputs/sample/` with only the three note files below (exact contents). Do **not** write `phase1.json` or `phase2.json`.
  3. Write `app.config.json` with `"inputFolder": ""` and `"phase": 1` (no project selected).
  4. Stop and re-offer folder options (without auto-continuing into Phase 1).

- **If they choose another folder under `inputs/`:**
  1. Write `app.config.json` with that folder and `"phase": 1`.
  2. Continue with Phase 1 on that folder.

- **If they choose “None / use my notes” and no suitable folder exists:**
  1. Tell them to create `inputs/{name}/` and add notes, then re-run.

#### Sample note templates (recreate exactly; no phase JSON)

`inputs/sample/braindump.md`:

```markdown
# linux / server scraps

wtf does nginx -t actually validate? reload vs restart again
---
ssh keys: copy pub to authorized_keys but ALSO check permissions 700/600 or it silently fails
---
journalctl -u nginx -f   // remember -u not --unit when tired
---
https://youtu.be/dQw4w9WgXcQ
---
ufw: allow 80/443 then enable; don't lock yourself out of 22
---
certbot certonly --nginx -d example.com
renewal is a cron/timer thing — check with systemctl list-timers | grep cert
```

`inputs/sample/half-thoughts.md`:

```markdown
rsync -avz --delete ./dist/ user@host:/var/www/app/
DRY RUN FIRST with -n

tmux: Ctrl-b d detach, Ctrl-b c new window, Ctrl-b % split

grep -R "TODO" --exclude-dir=node_modules .

article idea?: "permissions that look fine but break ssh/nginx" — 700 home, 700 .ssh, 600 keys, SELinux/AppArmor footnote

pg: dump with pg_dump -Fc, restore pg_restore -d ... don't use plain sql for big dbs
```

`inputs/sample/random.txt`:

```text
docker ps -a | grep dead
prune? careful volumes

---

compose: depends_on does NOT wait for healthy unless condition

---

asdf vs nvm vs mise — pick one for node on laptop, stop installing globally with brew every time

---

WTF is the difference between EXPOSE and publishing -p again
```

### 1. Phase 1 — mark uncertain + YouTube

1. Ensure project-root `shorthands.md` exists (create a stub with `# Shorthands` and an empty table if missing).
2. **Read root `shorthands.md` fully** before marking anything.
3. Read all note `.md`/`.txt` files in the folder.
4. For **each** YouTube / `youtu.be` URL found: run a metadata fetch (oEmbed or watch page) **before** writing `phase1.json`. Fill `youtube.title` / `youtube.about` from that response only.
5. Write/update `phase1.json` with line-level marks and YouTube descriptors (no placeholder titles).
6. Set `app.config.json` `"phase": 1`.
7. Tell the user to open `index.php` (e.g. `php -S localhost:8765` then browse), review marks, and **return to this chat** to:
   - explain any flagged shorthand/symbols, or
   - say Phase 1 looks good and advance to Phase 2.

Phase 1 may loop: user explains → update root `shorthands.md` → rewrite `phase1.json` → user refreshes the app.

### 2. Phase 2 — organize with fidelity

Only after the user confirms Phase 1 is done:

1. Set `app.config.json` `"phase": 2`.
2. Turn notes into grouped **tasks** and **reference** knowledge; recommend **article candidates**.
3. For YouTube source lines, copy `youtube.title` / `youtube.about` from `phase1.json` into the organized item — never replace with a stub.
4. Account for every line in `blame` — fidelity first; do not silently drop details.
5. Write `phase2.json`.
6. Tell the user to refresh `index.php`, review organized output and the hamburger originals/blame panel, then **return to this chat** to refine groupings or fix fidelity gaps.

## Agent checklist

1. On first start and on every restart/folder pick: offer (a) reset sample + select it, (b) reset sample only / no project, (c) each other `inputs/` folder, (d) none / use my notes.
2. If reset sample + select: delete `inputs/sample/`, recreate note files only (no `phase1.json` / `phase2.json`), set `app.config.json` to `sample` phase 1, continue Phase 1.
3. If reset sample only: same recreate, reset `app.config.json` to `"inputFolder": ""` and `"phase": 1`, re-offer folders.
4. If another folder: update `app.config.json` to that folder phase 1.
5. Read root `shorthands.md` before any uncertainty marks.
6. Never treat root `shorthands.md` / `phase1.json` / `phase2.json` as source notes.
7. Phase 1 artifacts must be visible in the app after refresh.
8. Every YouTube URL: metadata fetch first; non-placeholder `title`/`about`; `youtube.added: true`. On fetch failure only: `YouTube (lookup failed)`.
9. Phase 2 YouTube items reuse Phase 1 `youtube.title` / `youtube.about`.
10. Phase 2: every line in every note file appears in `blame`.
11. Do not advance to Phase 2 without explicit user confirmation.
12. After each write, remind the user how to return to Cursor / Claude Code to continue.

## Return-to-chat prompts (for the user)

After Phase 1 writes, tell the user they can say things like:

- “`wtf` means write the function”
- “Phase 1 looks good — start Phase 2”

After Phase 2 writes:

- “Merge t1 and t3”
- “Line 12 in braindump.md should stay as reference, not a task”
