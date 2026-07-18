---
name: scattered-notes
description: >-
  Process a folder of scattered ADHD notes under inputs/ into Phase 1 clarity
  marks, Phase 2 organized tasks/reference with per-line fidelity, and Phase 3
  area tags. Use when the user wants to organize braindump .md/.txt notes, run
  the scattered-notes harness, update shorthands, or advance phase1/phase2/phase3
  artifacts for index.php.
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
- User confirms Phase 2 is done and wants Phase 3 (auto-tagging)

## File contracts

Per batch folder `inputs/{folder}/`:

| File | Role |
|------|------|
| `*.md`, `*.txt` | Raw scattered notes |
| `phase1.json` | Uncertain marks + YouTube enrichments |
| `phase2.json` | Organized notes + per-line blame map + item tags (+ optional `filterLayout`) |

Project root:

| File | Role |
|------|------|
| `app.config.json` | `{ "inputFolder": "{folder}" \| "", "phase": 1 \| 2 \| 3 }` — skill owns this; empty `inputFolder` means no project selected |
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
    "tasks": [{ "id": "t1", "title": "...", "body": "...", "sources": ["braindump.md:12"], "tags": ["linux", "nginx"], "checked": false }],
    "reference": [{ "id": "r1", "title": "...", "body": "...", "sources": ["notes.txt:3"], "tags": ["tmux"], "checked": false }],
    "articleCandidates": [{ "id": "a1", "title": "...", "why": "...", "sources": ["braindump.md:4"], "tags": ["ssh", "permissions"], "checked": false }]
  },
  "blame": {
    "braindump.md": [
      { "n": 1, "text": "...", "fate": "mapped", "to": ["t1"], "comment": "merged into install task" }
    ]
  },
  "filterLayout": [
    { "type": "tag", "name": "linux" },
    { "type": "divider" },
    { "type": "tag", "name": "nginx" }
  ]
}
```

Every source line must have a `blame` entry. Use fates such as: `mapped`, `merged`, `reference`, `uncertain_carried`, `dropped_duplicate`, `dropped_noise`, `separator`.

Phase 2 items that come from a YouTube line **must reuse** `youtube.title` and `youtube.about` from `phase1.json` (e.g. reference title = that title; body includes the URL plus `about`). Do **not** invent a new title or write a metadata placeholder in Phase 2.

**Browser edits:** In Phase 2 and Phase 3, the app POSTs to `index.php?action=save-phase2` and writes moves, tags, reorder, green-check (`checked`), and `filterLayout` into this same `phase2.json`. Always **re-read** `phase2.json` before Phase 3 or any fidelity fix so user edits are not overwritten blindly. Preserve each item’s `checked` boolean when rewriting organized items.

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

### 0. Session check, then offer options

On **every start** (first run, re-run, or restart): **read `app.config.json` first**, list directories under `inputs/` (ignore files like `.gitkeep`), and detect session state before offering options.

#### Detect session state

| State | When |
|-------|------|
| **No folder pointed** | `inputFolder` is `""`, or the folder under `inputs/` does not exist |
| **Mid-session** | `inputFolder` points at an existing folder and `phase` is `1` or `2` |
| **Final session** | `inputFolder` points at an existing folder and `phase` is `3` |

Announce the detected state briefly (e.g. “Active: `inputs/sample` · phase 2 (mid-session)” or “No folder pointed”).

#### Options by state

**A. No folder pointed** — offer:

1. **Reset sample and select it** — wipe/recreate `inputs/sample/`, set it as the active project, continue Phase 1.
2. **See the app with no folder selected** — recreate the sample notes on disk, but leave `inputFolder` empty so the app shows its empty state. This is only a preview of that screen. You still have to pick a folder afterward (or say you want to select sample) before the app can show notes or artifacts.
3. **Use folder `{name}`** — one option per non-sample directory under `inputs/` (do not offer stale `sample` as “use as-is”; use option 1 to reset + select sample).
4. **None / use my notes** — if they need a new batch: tell them to create `inputs/{name}/`, dump `.md`/`.txt` notes (separate files or one file with `---` — both fine), then re-run.

**B. Mid-session** — offer continue first, with phase-specific wording:

1. **Continue `{folder}` …** — keep `app.config.json` as-is; do not wipe artifacts. Word the option by phase:
   - **Phase 1:** Continue `{folder}` at phase 1 — resume reviewing uncertain marks / shorthand in the app.
   - **Phase 2:** Continue `{folder}` at phase 2 — open the app and review whether the AI parsed your notes correctly: nothing important dropped, and the suggestions for your lines make sense.
2. **Reset sample and select it** — same as A.1 (starts a fresh sample session at phase 1).
3. **See the app with no folder selected** — same as A.2 (empty-state preview only; you still must pick a folder or select sample afterward).
4. **Switch to folder `{name}`** — one option per other non-sample directory under `inputs/` (sets that folder, `"phase": 1`, then Phase 1).
5. **None / use my notes** — same as A.4.

**C. Final session (phase 3)** — offer:

1. **Continue refining `{folder}` (phase 3)** — keep config; re-read `phase2.json` and refine tags / fidelity as the user asks.
2. **Start a new batch** — then offer A’s folder picks (reset sample + select, other folders, or create new notes). Do not wipe the finished folder unless they explicitly ask.
3. **Reset sample and select it** — same as A.1.
4. **See the app with no folder selected** — same as A.2 (empty-state preview only; you still must pick a folder or select sample afterward).
5. **None / use my notes** — same as A.4.

Selecting sample is never “open whatever is already there” — **Reset sample and select it** and **See the app with no folder selected** always run the reset below.

- **If they choose “Reset sample and select it”:**
  1. Delete the entire `inputs/sample/` directory if it exists.
  2. Recreate `inputs/sample/` with only the three note files below (exact contents). Do **not** write `phase1.json` or `phase2.json`.
  3. Write `app.config.json` with `"inputFolder": "sample"` and `"phase": 1`.
  4. Continue with Phase 1 on `sample`.

- **If they choose “See the app with no folder selected”:**
  1. Delete the entire `inputs/sample/` directory if it exists.
  2. Recreate `inputs/sample/` with only the three note files below (exact contents). Do **not** write `phase1.json` or `phase2.json`.
  3. Write `app.config.json` with `"inputFolder": ""` and `"phase": 1` (no project selected).
  4. Tell them to open `index.php` to see the empty-state screen. Remind them this is only a preview — they still must pick a folder or say they want to select sample before the app can show notes/artifacts. Stop and re-offer folder options (without auto-continuing into Phase 1).

- **If they choose “Continue …” (mid or final):**
  1. Do **not** change `inputFolder` / `phase` unless the user asks to advance or switch.
  2. Resume at the current phase:
     - Phase 1: marks / shorthand loop.
     - Phase 2: tell them to review in the app whether the AI parsed their notes correctly — that it did not drop lines unnecessarily, and that the suggestions for their lines make sense — then return to chat to refine or advance.
     - Phase 3: tag refine.

- **If they choose another / switch folder under `inputs/`:**
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
5. Write `phase2.json` (items may omit `tags` for now, or use `[]`).
6. Tell the user to **visit the web app** (`index.php`), refresh, and review whether the AI parsed their notes correctly: that it did **not** drop lines unnecessarily, and that the suggestions for their lines make sense. Remind them they can:
   - **add tags** or **move items** between Scattered / Reference / Articles (and rearrange within a panel);
   - those edits **save into `phase2.json`** via the PHP endpoint so this chat can see them later;
   - **Phase 3 will ultimately tag the items for you** by area — manual tags in the app are optional;
   - then **return to this chat** to refine groupings/fidelity, or say Phase 2 looks good and start Phase 3.

### 3. Phase 3 — auto-tag by area

Only after the user confirms Phase 2 is done (or explicitly asks for Phase 3):

1. **Re-read** `inputs/{folder}/phase2.json` so any browser moves/tags/reorder are included.
2. Set `app.config.json` `"phase": 3`.
3. Tag every organized item (`tasks`, `reference`, `articleCandidates`) so related notes share tags.
4. Write the updated `tags` (and keep `blame` / bucket placement / user edits) back to `phase2.json`.
5. Tell the user to refresh `index.php`, review tags/filters, edit further if they want (still saves to `phase2.json`), then **return to this chat** to refine tags.

#### Tagging rules

- Prefer a small hierarchy you can **chunk up or down**: e.g. supercategory → category → subcategory (`linux` → `nginx` → `certs`), or broader/narrower area labels that cluster the same idea.
- An item may have **more than one tag**.
- Tags may contain **spaces**, but prefer short phrases (roughly a few words). Avoid long sentence-tags.
- Prefer reuse of the same tag string across items in the same area (consistent casing).
- **Preserve** any tags the user already added in the app; merge AI tags with theirs (dedupe case-insensitively).
- Do not invent tags that contradict the item’s content; when unsure, use a broader category tag.
- Optionally refresh `filterLayout` so new tags appear in a sensible order (group related tags; dividers between major areas are fine). If unsure, omit `filterLayout` and let the app append missing tags.

## Agent checklist

1. On every start: read `app.config.json`, list `inputs/` folders, detect **no folder** / **mid-session** (phase 1–2) / **final session** (phase 3), announce state, then offer the matching option set from §0.
2. Mid-session: offer **Continue** first (phase 2 wording: review parse fidelity — nothing dropped, suggestions make sense); Final: offer **Continue refining** first. Do not wipe an in-progress or finished batch unless they choose a reset/switch.
3. If reset sample + select: delete `inputs/sample/`, recreate note files only (no `phase1.json` / `phase2.json`), set `app.config.json` to `sample` phase 1, continue Phase 1.
4. If “See the app with no folder selected”: same recreate, reset `app.config.json` to `"inputFolder": ""` and `"phase": 1`; empty-state preview only — they still must pick a folder or select sample afterward; re-offer folders.
5. If another / switch folder: update `app.config.json` to that folder phase 1.
6. Read root `shorthands.md` before any uncertainty marks.
7. Never treat root `shorthands.md` / `phase1.json` / `phase2.json` as source notes.
8. Phase 1 artifacts must be visible in the app after refresh.
9. Every YouTube URL: metadata fetch first; non-placeholder `title`/`about`; `youtube.added: true`. On fetch failure only: `YouTube (lookup failed)`.
10. Phase 2 YouTube items reuse Phase 1 `youtube.title` / `youtube.about`.
11. Phase 2: every line in every note file appears in `blame`.
12. Do not advance to Phase 2 without explicit user confirmation.
13. After Phase 2 (and when continuing a phase-2 session): remind the user to review in the app whether the AI parsed their notes correctly — nothing dropped unnecessarily, suggestions make sense; they may tag/move (writes `phase2.json`); Phase 3 will auto-tag.
14. Before Phase 3 (and before fidelity rewrites): re-read `phase2.json` from disk.
15. Do not advance to Phase 3 without explicit user confirmation.
16. Phase 3: tag all organized items; preserve user tags; write back to `phase2.json`; set phase 3.
17. After each write, remind the user how to return to Cursor / Claude Code to continue.

## Return-to-chat prompts (for the user)

After Phase 1 writes, tell the user they can say things like:

- “`wtf` means write the function”
- “Phase 1 looks good — start Phase 2”

After Phase 2 writes:

- “Merge t1 and t3”
- “Line 12 in braindump.md should stay as reference, not a task”
- “Phase 2 looks good — start Phase 3”

After Phase 3 writes:

- “Retag t8 under docker networking”
- “Add a supercategory tag devops to t4 and t6”
