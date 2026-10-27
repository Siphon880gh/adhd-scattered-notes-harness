---
name: scattered-notes
description: >-
  Process a folder of scattered ADHD notes under inputs/ into Phase 1 clarity
  marks, Phase 2 organized tasks/reference with per-line fidelity, and Phase 3
  area tags. Use when the user wants to organize braindump .md/.txt notes
  (including .url.txt link captures), run the scattered-notes harness, update
  shorthands, enrich YouTube/web links (or collect browser pastes for blocked
  URLs), redraw Phase 1 note regions (split / merge; read phase1-regions.md),
  or advance phase1/phase2/phase3 artifacts for index.php.
---

# Scattered Notes Harness

This skill runs in **Cursor or Claude Code**. The PHP app (`index.php`) is an artifact container: write artifacts here, tell the user to review in the browser, then continue in chat. Do not switch folders inside the app—only via `app.config.json`.

## Product framing (verbatim)

This is primarily used under Cursor or Claude Code but we open the php file as a container of artifacts. We look at artifacts to know how to prompt subsequently.

## When to use

- User has scattered notes in `inputs/{folder}/` (`.md` / `.txt`)
- User asks to process, organize, clarify, or continue a scattered-notes batch
- User says **“start phase 1”**, **“start phase 2”**, **“start phase 3”**, or **“let’s continue”** (any phase — treat as resume/advance for the active batch)
- User explains shorthand / symbols after reviewing Phase 1 in the app
- User redraws Phase 1 note regions in the app (split one note, or merge two) and later asks about those boundaries
- User confirms Phase 1 is done and wants Phase 2
- User confirms Phase 2 is done and wants Phase 3 (auto-tagging)
- User says **“Revert to Phase 2”** while in Phase 3 (unlock Suggest / green-check again)

## File contracts

Per batch folder `inputs/{folder}/`:

| File | Role |
|------|------|
| `*.md`, `*.txt` | Raw scattered notes (includes `*.url.txt` — URL capture files) |
| `phase1.json` | Uncertain marks + YouTube + link enrichments (incl. blocked URLs awaiting user paste) |
| `phase1-suggestions.md` | User feedback for Phase 1 — shared shorthand notes + per-enrichment notes; **read before rewriting Phase 1 / advancing** |
| `phase1-regions.md` | User corrections to note boundaries (split / merge). **Read before Phase 2 grouping.** Do not treat it as a note. |
| `phase2.json` | Organized notes + per-line blame map + item tags (+ optional `filterLayout`) |
| `phase2-suggestions.md` | User feedback for Phase 2 — per-item and/or whole-section panels (Scattered / Reference / Articles); **read before writing/revising Phase 2** |

Project root:

| File | Role |
|------|------|
| `app.config.json` | `{ "inputFolder": "{folder}" \| "", "phase": 1 \| 2 \| 3 }` — skill owns this; empty `inputFolder` means no project selected |
| `shorthands.md` | App-wide glossary — **always read before marking uncertainty**; shared by every batch |

Skip when reading notes: `phase1.json`, `phase1-suggestions.md`, `phase1-regions.md`, `phase2.json`, `phase2-suggestions.md`, any leftover `shorthands.md` inside a batch folder, and any non-`.md`/`.txt` files.

A scattered note may be a line, a group of lines, or a whole file. Within a file, `---` separates notes, unless `phase1-regions.md` overrides that boundary. The batch is one broad category (coding, linux, etc.).

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
        },
        {
          "n": 3,
          "text": "https://example.com/article",
          "marks": [],
          "link": {
            "url": "https://example.com/article",
            "intent": "bubble vs merge sort complexity",
            "title": "...",
            "about": "...",
            "added": true,
            "blocked": false,
            "userPaste": "",
            "userNote": ""
          }
        }
      ]
    },
    "b.url.txt": {
      "lines": [
        {
          "n": 1,
          "text": "http://localhost:59999/sorting-notes",
          "marks": [],
          "link": {
            "url": "http://localhost:59999/sorting-notes",
            "intent": "",
            "title": "",
            "about": "",
            "added": false,
            "blocked": true,
            "userPaste": "",
            "userNote": ""
          }
        }
      ]
    }
  }
}
```

- Include **every** line of every note file (`n` is 1-based; `text` is the original line). `*.url.txt` files are note files too — include them.
- Mark unsure tokens especially symbols / shorthand that do not make sense after reading `shorthands.md`.
- For every YouTube / `youtu.be` URL: **fetch real metadata** (do not invent titles from the URL alone). Prefer YouTube oEmbed `https://www.youtube.com/oembed?url={URL}&format=json`, or fetch the watch page. Then set `youtube` with:
  - `title` — exact video title from the fetch
  - `about` — 1–2 sentences from title + description (and comments if useful)
  - `added: true`
  Do **not** watch the video. The app renders this like a git-diff green added line.
- **Forbidden** YouTube titles/abouts: placeholders such as “YouTube link”, “metadata placeholder”, “unknown video”, empty `about` when the URL is reachable, or any invented stub. If fetch fails after trying: treat like any other blocked URL — use `link` with `blocked: true` (see below), **or** for YouTube-only fallback: `title` = `YouTube (lookup failed)` and put the failure reason in `about`. Prefer the shared `link` blocked panel so the user can paste from their browser.
- **Non-YouTube links** (any `http://` / `https://` URL in any note, plus the whole contents of `*.url.txt` files): visit the page and enrich with a `link` object on the line that holds the URL (for multi-line `.url.txt` notes, put `link` on the URL line; use surrounding lines as `intent`).
  - `url` — canonical URL string
  - `intent` — what to look up, taken from surrounding text in the same note (lines before/after the URL within the `---` / blank-line note boundary, or other lines in a `.url.txt` file). Empty string if the note is URL-only.
  - When the page **opens**: set `title` / `about` from the real page. If `intent` is set, focus `about` on that request (e.g. extract the complexity comparison they asked for); otherwise summarize the page in 1–3 sentences. Set `added: true`, `blocked: false`, leave `userPaste` / `userNote` empty (or preserve any prior user paste).
  - When the page **fails to open** (timeout, connection refused, HTTP error, bot wall, empty body — **any** fetch failure): set `blocked: true`, `added: false`, empty `title`/`about` (unless already filled from a prior user paste). Do **not** invent page content. The app shows a **blocked-URL panel** so the user can open the URL in their browser, describe what they need, and paste relevant text. Re-read `phase1.json` after they paste — the app writes `userPaste` / `userNote` via `index.php?action=save-url-paste`.
  - After the user pastes: derive `title` / `about` from `userPaste` (+ `userNote` / `intent`), set `added: true`, keep `blocked: true` only if you still lack enough text (otherwise `blocked: false`). Never wipe `userPaste` / `userNote`.
  - **Forbidden** link stubs: invented titles, “could not fetch”, or fake summaries when `blocked` is true and `userPaste` is empty.

### `phase1-suggestions.md`

User-written feedback saved by the app **during Phase 1** (batch folder). May be missing — treat as empty. **Always read this file (if present) before rewriting `phase1.json`, before updating `shorthands.md` from app notes, and before advancing to Phase 2.**

Format (app-owned; preserve structure when the user edits via the app):

```markdown
# Phase 1 suggestions

## Shorthand: wtf

means write the function

## Enrichment: a.url.txt:1

about should focus on bubble vs merge complexity, not a generic overview
```

- `## Shorthand: {token}` — one note for that shorthand **type**. The app shows the **same** comment on every uncertain flag with that token (case-insensitive). Applying it once is enough for all occurrences — update root `shorthands.md`, then clear those uncertain marks on rewrite.
- `## Enrichment: {file}:{line}` — feedback on a YouTube / fetched-link green added line (`file` is the note filename, `line` is 1-based). Revise that line’s `youtube` or `link` title/about accordingly.
- Omit empty sections. The app POSTs to `index.php?action=save-phase1-suggestions`. Do not wipe this file when rewriting `phase1.json` unless the user asked to clear suggestions.

### `phase1-regions.md`

User corrections when an automatic note boundary is wrong. May be missing — treat as no corrections. **Read it whenever you group notes or answer a question about which lines are one idea**, including before Phase 2. The app writes it via `index.php?action=save-phase1-regions`. Do not wipe it. Do not re-split a file in a way that ignores these lines.

```markdown
# Phase 1 regions

## File: braindump.md

### Splits

12

### Joins

40
```

- `### Splits` — source line numbers that **start a new note** even though no `---` / blank line does.
- `### Joins` — source line numbers that **stay with the previous note** even though a `---` or blank line would split them.
- Everywhere else, keep the normal boundary rules below.
- Line numbers are 1-based and match the note file.
- The stored file is only the difference from automatic boundaries. Restoring a boundary in the app removes that line; if a file has no remaining splits or joins, the app deletes `phase1-regions.md`.

#### How the app draws a note

Use these same rules when you talk about regions or when you group notes. Do not invent a second segmentation.

- A line of only dashes, length 3 or more (`---`, `------`), is a hard break.
- A longer matching fence (length 6 or more, used in pairs) keeps shorter dash lines strictly inside that pair as content. Example: an article that already uses `---` is wrapped in `------`.
- If the file has no unprotected dash break, blank lines are the breaks instead.
- Leading and trailing blank lines inside a group are not part of the note. Internal blank lines stay.
- Empty groups between separators are not notes.
- Each note is one colored region in Phase 1. The map on the right is the whole file.
- Hues, in order: 199, 152, 42, 270, 330, 12, 88, 230. If the next hue would match the previous note, skip to the one after it. The user does not pick colors. After every split or merge the app reassigns hues so two notes in a row never share a color.

#### What the user can do in Phase 1

On each note header:

- **Split** — then **Split into a new note here** on the line that should start the second idea. **Cancel** or Escape leaves the note unchanged. A one-line note cannot split.
- **Merge up** — this note becomes part of the note above. The first note has no Merge up.
- **Merge down** — the next note becomes part of this one. The last note has no Merge down.

The app renumbers notes and rebuilds the map after either change, then saves. Tell the user they can fix a wrong boundary this way before they say Phase 1 looks good.

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
    { "type": "heading", "name": "Web" },
    { "type": "tag", "name": "linux" },
    { "type": "divider" },
    { "type": "heading", "name": "Proxy" },
    { "type": "tag", "name": "nginx" }
  ]
}
```

Every source line must have a `blame` entry. Use fates such as: `mapped`, `merged`, `reference`, `uncertain_carried`, `dropped_duplicate`, `dropped_noise`, `separator`.

Phase 2 items that come from a YouTube line **must reuse** `youtube.title` and `youtube.about` from `phase1.json` (e.g. reference title = that title; body includes the URL plus `about`). Do **not** invent a new title or write a metadata placeholder in Phase 2.

Phase 2 items that come from a non-YouTube link / `*.url.txt` line **must reuse** `link.title` and `link.about` from `phase1.json` when `link.added` is true (body includes the URL plus `about`; honor `intent` in the wording). If `link.blocked` is still true and `userPaste` is empty, keep the item honest (e.g. reference titled from the URL host + “blocked — paste pending”) — do **not** invent page content. If `userPaste` is present but Phase 1 was not re-run, you may derive title/about from the paste during Phase 2.

**Browser edits:** In Phase 2, the app POSTs to `index.php?action=save-phase2` and writes moves, tags, reorder, green-check (`checked`), and `filterLayout` into this same `phase2.json`. Phase 3 still saves moves/tags/reorder/`filterLayout`, but **Suggest** and green-check are locked off (review is finished). Always **re-read** `phase2.json` before Phase 3 or any fidelity fix so user edits are not overwritten blindly. Preserve each item’s `checked` boolean when rewriting organized items.

### `phase2-suggestions.md`

User-written feedback saved by the app **during Phase 2 only** (batch folder). May be missing — treat as empty. **Always read this file (if present) before the first Phase 2 write, before any Phase 2 revision, and before advancing to Phase 3.** Apply the feedback when grouping, moving buckets, merging, or fixing fidelity — that rewrite happens as a **Phase 2 pass**, not as Phase 3 tagging. For each item suggestion, you may rewrite the **heading (`title`) and/or the `body`** (either field alone, or both) to match what the user asked for; do not leave a stale title when they asked for a clearer heading, and do not leave a stale body when they asked for clearer wording. Do not invent suggestions; only use what the user wrote. Phase 3 does not open Suggest UI; do not expect new suggestion notes after lock-in.

Format (app-owned; preserve structure when the user edits via the app):

```markdown
# Phase 2 suggestions

## Panel: tasks

Optional notes about the whole Scattered section (tagging, heading style, ordering, what belongs here).

## Panel: reference

Optional notes about the whole Reference section (grouping, tone, what belongs here).

## Panel: articleCandidates

Optional notes about the whole Articles section (grouping, heading style, weak candidates to drop).

## Item: t1

Optional notes about organized item id `t1` (wrong bucket, merge, rewrite heading, rewrite body, keep detail, etc.).

## Item: r2

…
```

- `## Panel: tasks` / `## Panel: reference` / `## Panel: articleCandidates` — guidance for an entire section (tagging preferences, heading/formatting style, ordering, what belongs in that bucket).
- `## Item: {id}` — guidance for one organized item (`t1`, `r1`, `a1`, …). Match by `id` from `phase2.json`.
- When applying an item suggestion: rewrite `title` (heading), `body`, or **both**, as needed. Leave a field unchanged only when the suggestion does not call for changing it.
- When applying a panel suggestion: apply across items in that section (retitle style, reorder, retag, move misfits out, etc.) without inventing requests the user did not write.
- Omit empty sections. Ignore unknown item ids (item may have been removed).
- The app POSTs to `index.php?action=save-suggestions` to write this file. Do not wipe it when rewriting `phase2.json`.

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
   - **Phase 2:** Continue `{folder}` at phase 2 — open the app and review whether the AI parsed your notes correctly: nothing important dropped, cards read as intended; use **Suggest** on any note that should change.
2. **Reset sample and select it** — same as A.1 (starts a fresh sample session at phase 1).
3. **See the app with no folder selected** — same as A.2 (empty-state preview only; you still must pick a folder or select sample afterward).
4. **Switch to folder `{name}`** — one option per other non-sample directory under `inputs/` (sets that folder, `"phase": 1`, then Phase 1).
5. **None / use my notes** — same as A.4.

**C. Final session (phase 3)** — offer:

1. **Continue `{folder}` (phase 3, locked)** — keep config; remind them the app is for **filter + copy** into their permanent notes app. Re-read `phase2.json` and refine tags only if they ask. No Suggest / green-check loop. Also remind: they can always say **“Revert to Phase 2”** in chat, then refresh the app, to unlock Suggest / green-check again.
2. **Revert to Phase 2** — set `app.config.json` `"phase": 2` (keep `inputFolder` and `phase2.json`); tell them to refresh the app so Suggest / green-check return.
3. **Start a new batch** — then offer A’s folder picks (reset sample + select, other folders, or create new notes). Do not wipe the finished folder unless they explicitly ask.
4. **Reset sample and select it** — same as A.1.
5. **See the app with no folder selected** — same as A.2 (empty-state preview only; you still must pick a folder or select sample afterward).
6. **None / use my notes** — same as A.4.

Selecting sample is never “open whatever is already there” — **Reset sample and select it** and **See the app with no folder selected** always run the reset below.

- **If they choose “Reset sample and select it”:**
  1. Delete the entire `inputs/sample/` directory if it exists.
  2. Recreate `inputs/sample/` with only the six note files below (exact contents). Do **not** write `phase1.json` or `phase2.json`.
  3. Write `app.config.json` with `"inputFolder": "sample"` and `"phase": 1`.
  4. Continue with Phase 1 on `sample`.

- **If they choose “See the app with no folder selected”:**
  1. Delete the entire `inputs/sample/` directory if it exists.
  2. Recreate `inputs/sample/` with only the six note files below (exact contents). Do **not** write `phase1.json` or `phase2.json`.
  3. Write `app.config.json` with `"inputFolder": ""` and `"phase": 1` (no project selected).
  4. Tell them to open `index.php` to see the empty-state screen. Remind them this is only a preview — they still must pick a folder or say they want to select sample before the app can show notes/artifacts. Stop and re-offer folder options (without auto-continuing into Phase 1).

- **If they choose “Continue …” (mid or final):**
  1. Do **not** change `inputFolder` / `phase` unless the user asks to advance or switch.
  2. Resume at the current phase:
     - Phase 1: marks / shorthand loop.
     - Phase 2: tell them to review in the app whether the AI parsed their notes correctly — nothing dropped unnecessarily; use **Suggest** on any note that should change — then return to chat to apply those notes or advance.
     - Phase 3: locked — filter/copy exit; tag refine only if asked. Remind they can say **“Revert to Phase 2”** anytime, then refresh the app.

- **If they choose “Revert to Phase 2”** (from phase 3, or they say that phrase in chat):
  1. Keep `inputFolder` and do **not** wipe `phase1.json` / `phase2.json` / `phase2-suggestions.md`.
  2. Write `app.config.json` with `"phase": 2`.
  3. Tell them to **refresh the app** — Suggest / green-check unlock again; resume Phase 2 review (apply suggestions, fidelity, then they can re-advance to Phase 3 when ready).

- **If they choose another / switch folder under `inputs/`:**
  1. Write `app.config.json` with that folder and `"phase": 1`.
  2. Continue with Phase 1 on that folder.

- **If they choose “None / use my notes” and no suitable folder exists:**
  1. Tell them to create `inputs/{name}/` and add notes, then re-run.

#### Sample note templates (recreate exactly; no phase JSON)

`inputs/sample/0-preface.md`:

```markdown
Many of these notes were drafts for one of Weng's published note sites, such as codernotes.wengindustries.com.

They were captured in UpNote. Final writing was done in Obsidian, before the deployment scripts were run.
```

`inputs/sample/a.url.txt`:

```text
https://www.geeksforgeeks.org/dsa/introduction-to-sorting-algorithm/
```

`inputs/sample/b.url.txt`:

```text
Need bubble vs merge sort time complexity from this page
http://localhost:59999/sorting-notes
```

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

### 1. Phase 1 — mark uncertain + YouTube + links

1. Ensure project-root `shorthands.md` exists (create a stub with `# Shorthands` and an empty table if missing).
2. **Read root `shorthands.md` fully** before marking anything.
3. Read all note `.md`/`.txt` files in the folder (including `*.url.txt`).
4. For **each** YouTube / `youtu.be` URL found: run a metadata fetch (oEmbed or watch page) **before** writing `phase1.json`. Fill `youtube.title` / `youtube.about` from that response only. If the fetch fails, use the blocked-`link` path below so the app can collect a browser paste.
5. For **each** non-YouTube `http(s)` URL (inline in notes **or** in `*.url.txt` files): try to visit the page **before** writing `phase1.json`. Capture surrounding-note text as `link.intent`. On success: fill `link.title` / `link.about` (honor intent), `added: true`, `blocked: false`. On **any** failure to open/read the page: `blocked: true`, `added: false`, do not invent content.
6. **Preserve** any existing `link.userPaste` / `link.userNote` already saved by the app when rewriting `phase1.json`. If paste text is present for a blocked URL, derive `title`/`about` from it and set `added: true`.
7. Write/update `phase1.json` with line-level marks, YouTube descriptors, and link enrichments (no placeholder titles).
8. Set `app.config.json` `"phase": 1`.
9. Tell the user to open `index.php` (e.g. `php -S localhost:8765` then browse), review marks / green added descriptors / **blocked-URL paste panels** / **in-app Suggest** / **numbered note regions and the map**, and **return to this chat** to:
   - explain any flagged shorthand/symbols (chat **or** read `phase1-suggestions.md` shorthand sections — one note covers every flag of that token), or
   - apply enrichment Suggest notes from `phase1-suggestions.md`, or
   - say they pasted blocked-URL text (re-read `phase1.json` and enrich from `userPaste`), or
   - say a region is wrong — they fix that in the app with **Split** or **Merge up** / **Merge down** (saved to `phase1-regions.md`; colors update automatically and neighboring notes never share a color). Read that file before you group notes again, or
   - say Phase 1 looks good and advance to Phase 2.

**Before rewriting Phase 1 artifacts:** always read `phase1-suggestions.md` if present. For each `## Shorthand:` note, update root `shorthands.md` and remove matching uncertain marks. For each `## Enrichment:` note, revise that line’s `youtube` / `link` fields. Do not wipe `phase1-suggestions.md` unless the user cleared Suggest in the app.

Phase 1 may loop: user explains (chat or Suggest) → update root `shorthands.md` / enrichments → rewrite `phase1.json`; or user pastes blocked URL text in the app → re-read pastes → rewrite link enrichments → user refreshes the app.

### 2. Phase 2 — organize with fidelity

Only after the user confirms Phase 1 is done:

1. Set `app.config.json` `"phase": 2`.
2. **Read** `inputs/{folder}/phase2-suggestions.md` if it exists (per-item and/or `## Panel: tasks|reference|articleCandidates` feedback from a prior review). Apply it — including rewriting each suggested item’s **heading (`title`) and/or `body`**, and applying whole-section guidance (tagging / heading style / ordering) as needed.
3. Read `phase1-regions.md` if it exists. A listed split starts a new note at that line; a listed join keeps that line with the previous note. Apply `---` / blank-line boundaries everywhere else. Do not wipe `phase1-regions.md`.
4. Turn notes into grouped **tasks** and **reference** knowledge; recommend **article candidates**.
5. For YouTube source lines, copy `youtube.title` / `youtube.about` from `phase1.json` into the organized item — never replace with a stub.
6. For link / `*.url.txt` source lines, copy `link.title` / `link.about` when `added` is true; if still blocked with empty paste, do not invent page content.
7. Account for every line in `blame` — fidelity first; do not silently drop details.
8. Write `phase2.json` (items may omit `tags` for now, or use `[]`). Do **not** delete `phase2-suggestions.md` or `phase1-regions.md`.
9. Tell the user to **visit the web app** (`index.php`), refresh, and review whether the AI parsed their notes correctly: that it did **not** drop lines unnecessarily, and that each card reads the way they meant. **Actively invite Suggest notes for changing a note** — if a heading, body, bucket, merge, or detail is wrong, they should open **Suggest** on that card (or a section header Suggest for whole-section feedback: tagging, heading style, ordering) and write what to change; those notes save to `phase2-suggestions.md` for the next AI pass, and the AI may rewrite the **heading and/or body** accordingly. Also remind them they can:
   - **green-check** items as they review;
   - **add tags** or **move items** between Scattered / Reference / Articles (and rearrange within a panel);
   - those panel edits **save into `phase2.json`** via the PHP endpoint so this chat can see them later;
   - **Phase 3 will ultimately tag the items for you** by area — manual tags in the app are optional;
   - then **return to this chat** so you can apply Suggest notes (re-read `phase2-suggestions.md` + `phase2.json`), or say Phase 2 looks good and start Phase 3.

### 3. Phase 3 — auto-tag by area (then lock)

Only after the user confirms Phase 2 is done (or explicitly asks for Phase 3):

1. **Re-read** `inputs/{folder}/phase2-suggestions.md` (if present) **and** `phase2.json`. If suggestion notes are still pending, **apply them first** as a Phase 2 content rewrite (grouping / heading (`title`) and/or `body` / buckets / fidelity), then continue. Phase 3 itself does not keep a Suggest loop.
2. **Re-read** `phase2.json` again so any browser moves/tags/reorder are included.
3. Set `app.config.json` `"phase": 3`.
4. Tag every organized item (`tasks`, `reference`, `articleCandidates`) so related notes share tags.
5. **Always write `filterLayout`** in `phase2.json` (required, not optional): order all tags into related groups and insert `{ "type": "divider" }` **between major areas** so the Filter bar is scannable. Prefer a `{ "type": "heading", "name": "…" }` before each major group when the area label is clear. Include every tag used on any item. Do not leave a flat ungrouped tag list. If the user already ordered tags/dividers/headings in the app, keep their order and headings where it still makes sense and only insert missing tags (with dividers for new major areas).
6. Write the updated `tags` + `filterLayout` (and keep `blame` / bucket placement / user edits / `checked`) back to `phase2.json`. Do **not** wipe `phase2-suggestions.md`.
7. Tell the user to refresh `index.php`: Phase 3 is **locked in** — no Suggest buttons, no green-check marking. The app’s job now is to **filter by tag and Copy** cards (or whole panels) into their permanent notes app. Tag/move tweaks still save to `phase2.json`; return to chat only to refine tags if needed. **Always mention** they can return to Phase 2 anytime by saying **“Revert to Phase 2”** in this chat, then refreshing the app (Suggest / green-check unlock; organized notes stay).

#### Tagging rules

- Prefer a small hierarchy you can **chunk up or down**: e.g. supercategory → category → subcategory (`linux` → `nginx` → `certs`), or broader/narrower area labels that cluster the same idea.
- An item may have **more than one tag**.
- Tags may contain **spaces**, but prefer short phrases (roughly a few words). Avoid long sentence-tags.
- Prefer reuse of the same tag string across items in the same area (consistent casing).
- **Preserve** any tags the user already added in the app; merge AI tags with theirs (dedupe case-insensitively).
- Do not invent tags that contradict the item’s content; when unsure, use a broader category tag.

#### `filterLayout` rules (Phase 3 — always)

- **Always** emit `filterLayout` when writing Phase 3 `phase2.json`.
- Group related tags together (same area / hierarchy), then put a divider between groups.
- Optionally name each group with `{ "type": "heading", "name": "…" }` immediately before that group’s tags (after a divider, or at the start for the first group). Headings are labels only — not filterable tags.
- Example shape: heading `Web` → `nginx`, `certs` → divider → heading `Containers` → `docker`, `compose` → divider → `node`, `tooling`.
- No leading/trailing divider required; never stack two dividers in a row; every tag name in `filterLayout` must exist on at least one item (and every item tag must appear once in the layout).
- If the user already named groups in the app, **preserve those headings** when refining tags (only add headings for new major areas when helpful).
- When refining tags later in a phase-3 session, refresh `filterLayout` the same way. Note: if the user removes a divider in the app, the two group headings merge as `Heading A + Heading B`.

## Agent checklist

1. On every start: read `app.config.json`, list `inputs/` folders, detect **no folder** / **mid-session** (phase 1–2) / **final session** (phase 3), announce state, then offer the matching option set from §0.
2. Mid-session: offer **Continue** first (phase 2 wording: review parse fidelity — nothing dropped; invite **Suggest** for notes that should change); Final: offer **Continue (phase 3, locked)** first — filter/copy exit — and remind **“Revert to Phase 2”** then refresh unlocks Suggest again. Do not wipe an in-progress or finished batch unless they choose a reset/switch.
3. If reset sample + select: delete `inputs/sample/`, recreate note files only (six files including `0-preface.md`, `a.url.txt`, and `b.url.txt`; no `phase1.json` / `phase2.json`), set `app.config.json` to `sample` phase 1, continue Phase 1.
4. If “See the app with no folder selected”: same recreate, reset `app.config.json` to `"inputFolder": ""` and `"phase": 1`; empty-state preview only — they still must pick a folder or select sample afterward; re-offer folders.
5. If another / switch folder: update `app.config.json` to that folder phase 1.
6. Read root `shorthands.md` before any uncertainty marks.
7. Never treat root `shorthands.md` / `phase1.json` / `phase1-suggestions.md` / `phase1-regions.md` / `phase2.json` / `phase2-suggestions.md` as source notes.
8. Phase 1 artifacts must be visible in the app after refresh.
9. Every YouTube URL: metadata fetch first; non-placeholder `title`/`about`; `youtube.added: true`. On fetch failure: prefer blocked `link` panel (or `YouTube (lookup failed)` fallback).
10. Every non-YouTube URL and every `*.url.txt`: try fetch; set `link` with intent from surrounding text; on failure `blocked: true` and wait for app paste (`userPaste`/`userNote`). Preserve pastes across rewrites. After paste: derive `title`/`about`, set `added: true`.
11. Before rewriting Phase 1: read `phase1-suggestions.md` if present; apply shorthand notes to `shorthands.md` (one token note covers all flags of that token) and enrichment notes to `youtube`/`link` fields; do not wipe that file unless cleared in the app. Whenever grouping notes or answering which lines are one idea, read `phase1-regions.md` if present and honor its splits and joins (same rules as the Phase 1 app: dash breaks, `------` fences, blank lines only when there is no dash break, automatic recolor so neighbors differ). Do not wipe it.
12. Before any Phase 2 write or revision: read `phase2-suggestions.md` if present; apply panel/item feedback (rewrite item `title` and/or `body` as the suggestion requires); do not wipe that file.
13. Phase 2 YouTube items reuse Phase 1 `youtube.title` / `youtube.about`. Link items reuse Phase 1 `link.title` / `link.about` when added.
14. Phase 2: every line in every note file appears in `blame`.
15. Do not advance to Phase 2 without explicit user confirmation.
16. After Phase 2 (and when continuing a phase-2 session): remind the user to review in the app whether the AI parsed their notes correctly — nothing dropped unnecessarily; **invite Suggest on any note that should change** (heading and/or body, bucket, merge, detail → `phase2-suggestions.md`; next pass may rewrite `title` and/or `body`); they may also tag/move (writes `phase2.json`); Phase 3 will auto-tag.
17. Before Phase 3 (and before fidelity rewrites): re-read `phase2.json` and `phase2-suggestions.md` from disk; apply any pending suggestions as a Phase 2 rewrite before tagging.
18. Do not advance to Phase 3 without explicit user confirmation.
19. Phase 3: tag all organized items; preserve user tags; **always write `filterLayout` with dividers between major tag groups**; write back to `phase2.json`; set phase 3. Remind: locked — filter + Copy into their notes app; no Suggest / green-check; they can always say **“Revert to Phase 2”** then refresh the app.
20. On **“Revert to Phase 2”**: set `"phase": 2` (keep folder + artifacts); tell them to refresh the app; resume Phase 2.
21. After each write, remind the user how to return to Cursor / Claude Code to continue (Phase 3: optional tag refine, or **“Revert to Phase 2”**).

## Return-to-chat prompts (for the user)

Across **all phases**, the user can drive the harness with short phrases:

- **“start phase 1”** / **“start phase 2”** / **“start phase 3”** — begin or jump to that phase for the active folder (respect advance gates: don’t run Phase 2/3 until the prior phase is confirmed done, unless they explicitly insist).
- **“let’s continue”** (or “continue”) — resume the current mid/final session at whatever phase `app.config.json` already has; same as choosing **Continue** in §0.
- **“Revert to Phase 2”** — from Phase 3 only: set `"phase": 2`, keep artifacts; user refreshes the app to unlock Suggest / green-check.

After Phase 1 writes, also tell them they can say things like:

- “`wtf` means write the function”
- “I added Phase 1 Suggest notes in the app — continue”
- “I pasted the blocked URL text in the app — continue”
- “Phase 1 looks good — start Phase 2”

After Phase 2 writes:

- “Merge t1 and t3”
- “Rewrite the heading on t2 to …” / “Rewrite the body on r1 to …”
- “Line 12 in braindump.md should stay as reference, not a task”
- “Phase 2 looks good — start Phase 3”

After Phase 3 writes:

- “Retag t8 under docker networking”
- “Add a supercategory tag devops to t4 and t6”
- **“Revert to Phase 2”** — then refresh the app (Suggest / green-check unlock again)

Also remind them Phase 3 is locked for review: filter tags and Copy into their permanent notes app — and that they can always **Revert to Phase 2** in chat, then refresh.
