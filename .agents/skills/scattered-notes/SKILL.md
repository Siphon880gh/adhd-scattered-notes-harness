---
name: scattered-notes
description: >-
  Process a folder of scattered ADHD notes under inputs/ into Phase 1 clarity
  marks, Phase 2 organized tasks/reference with per-line fidelity, a heading
  and a plain-language sentence on every card for the human reader, and area
  tags (shown on cards and in the filter bar), and a Phase 3 lock for filter and
  copy. Use when the user wants to organize braindump .md/.txt notes
  (including .url.txt link captures), paste a public Evernote or UpNote share
  URL (download its images into attachments/ and link them), read each
  embedded image for text or for what it shows and keep a summary or the
  important details, run the scattered-notes harness, update shorthands,
  enrich YouTube/web links (or
  collect browser pastes for blocked URLs), redraw Phase 1 note regions
  (split / merge / delete; read phase1-regions.md), or advance
  phase1/phase2/phase3 artifacts for index.php. If the inputs/ project is
  not clear, ask which folder before doing the work.
---

# Scattered Notes Harness

This skill runs in **Cursor or Claude Code**. The PHP app (`index.php`) is an artifact container: write artifacts here, tell the user to review in the browser, then continue in chat. Each folder under `inputs/` is its own project. The app’s **Projects** menu switches the live folder and restores that project’s phase by writing `app.config.json`. You may also switch by writing that file.

## Product framing (verbatim)

This is primarily used under Cursor or Claude Code but we open the php file as a container of artifacts. We look at artifacts to know how to prompt subsequently.

## When to use

- User has scattered notes in `inputs/{folder}/` (`.md` / `.txt`)
- User pastes a public Evernote or UpNote share URL, or asks how to get notes in
- User asks to process, organize, clarify, or continue a scattered-notes batch
- User says **“start phase 1”**, **“start phase 2”**, **“start phase 3”**, or **“let’s continue”** (any phase — resume or advance the batch once the project is known; if it is not, ask which `inputs/` folder first, then do what they asked)
- User explains shorthand / symbols after reviewing Phase 1 in the app
- User redraws Phase 1 note regions in the app (split one note, or merge two) and later asks about those boundaries
- User confirms Phase 1 is done and wants Phase 2
- User confirms Phase 2 is done and wants Phase 3 (lock review; refresh tags if any are still missing)
- User says **“Revert to Phase 2”** while in Phase 3 (unlock Suggest / green-check again)

## How the user gives you notes

Say **both** ways whenever you tell someone how to start, including **None / use my notes**. A public URL is a note source, the same as a file.

### 1. A folder under `inputs/`

They create `inputs/{name}/` and put `.md` / `.txt` notes in it. One thought per file, many thoughts in one file, or dividers — the rest of this skill decides the boundaries.

Photos in that folder: the image file goes in the **same** `inputs/{name}/` folder. They link it with either syntax.

Markdown:

```markdown
![sketch of the nginx error](nginx-error.png)
```

Obsidian:

```markdown
![[nginx-error.png]]
```

The path is relative to that inputs folder. `attachments/nginx-error.png` is the same idea when the file already lives in `inputs/{name}/attachments/`.

When you explain this folder method, add two sentences: if they want the pictures handled for them, share a public Evernote or UpNote URL instead — this skill downloads those images. Either way, each linked picture is read for text or for what it shows, and that reading stays with the photo.

Do not flag `![...](...)` or `![[...]]` as uncertain shorthand. Read each embedded image as in **Reading images**. In Phase 2, keep that image line in the card body so the photo stays on the card, and keep the reading with it.

### 2. A public Evernote or UpNote URL

They make the note viewable by link inside the app, copy that URL, and paste it in chat.

**Evernote.** Open the note. Share. Set general access to **Anyone with the link can view**. Copy link. It looks like `https://share.evernote.com/note/...`. Older public links (`https://www.evernote.com/shard/.../sh/...` or `https://www.evernote.com/l/...`) are fine. A link that still asks for their Evernote login is not public — tell them to switch access to anyone with the link.

**UpNote.** Open the note. Share in the top bar. **Copy Link** on desktop, or **Share Link** on the phone. Paste that https web link. `upnote://...` only opens the app. It is not the public page. Tell them to use Share → Copy Link.

If the message is that URL, import it. Do not answer with only the start menu.

1. Fetch the public page. If the note text is not in the HTML because the page is a client app shell, open the URL in the browser and read the rendered note and each image address.
2. Destination folder: if the project is already known, use it. If they named a folder, use that. If nothing is selected and they did not name one, create `inputs/{slug}/` from the note title (short, lowercase, hyphens) and tell them the name. If several folders already exist and they did not say which, ask once — list the folders and offer a new folder `{slug}` — then wait before writing files. You may fetch first so the slug is the real title.
3. Download every image, in reading order, into `inputs/{folder}/attachments/`. Name them `01-short-words.jpg` (safe filename, original extension, two-digit order). Skip a URL you already saved. Prefer jpg, png, gif, or webp when the page offers a choice.
4. Write `inputs/{folder}/{slug}.md`. Keep their wording. Where each image was, put a markdown image link:

```markdown
![caption from the note, or a short filename](attachments/01-short-words.jpg)
```

Obsidian `![[attachments/01-short-words.jpg]]` is also valid. Prefer the markdown form when you write the file so the caption is kept. Do not leave the remote image URL in the note.
5. If a download fails, write `[image could not be downloaded]` in that spot and say so. Do not invent a picture.
6. Then continue with the phase they asked for, or Phase 1 if they only handed you the URL. `attachments/` is not a note. Read each linked image as in **Reading images**. The image file is not its own note.

The app shows each linked photo at a size that fits the page and keeps its shape. Clicking it opens a larger view they can enlarge further.

## File contracts

Per batch folder `inputs/{folder}/`:

| File | Role |
|------|------|
| `*.md`, `*.txt` | Raw scattered notes (includes `*.url.txt` — URL capture files). Image lines use markdown `![caption](file.png)` or Obsidian `![[file.png]]`, with the file in this same folder |
| `attachments/` | Images downloaded from a public Evernote or UpNote note, or placed by the user. Not a note. Linked from a note with the syntax above |
| `phase1.json` | Uncertain marks + YouTube + link enrichments (incl. blocked URLs awaiting user paste) + a reading for each embedded image |
| `phase1-suggestions.md` | User feedback for Phase 1 — shared shorthand notes, per-enrichment notes, whole-idea notes (colored edge), and per-line notes; **read before rewriting Phase 1 / advancing** |
| `phase1-regions.md` | User corrections to note boundaries (split / merge) and deleted ideas. **Read before Phase 2 grouping.** Do not treat it as a note. Do not organize a deleted line. |
| `phase2.json` | Organized notes + per-line blame map + item tags + `filterLayout` |
| `phase2-suggestions.md` | User feedback for Phase 2 — per-item and/or whole-section panels (Scattered / Reference / Articles); **read before writing/revising Phase 2** |

Project root:

| File | Role |
|------|------|
| `app.config.json` | Live project plus a `projects` map of each folder’s phase. Empty `inputFolder` means no project selected. The app’s Projects menu and this skill both write it. Preserve `projects` when you update the file. |

When you write `app.config.json`:

- Set `inputFolder` and `phase` for the project you are opening or advancing.
- Keep the existing `projects` object. Set `projects["{inputFolder}"].phase` to that same phase. Do not drop other folders’ phases.
- The app’s **Projects** menu does this too. On the next start, read the file again — the user may have switched projects in the browser. Snapshots are not a project switch; they are saved Phase 3 views and do not change `inputFolder`.
| `shorthands.md` | App-wide glossary — **always read before marking uncertainty**; shared by every batch |

Skip when reading notes: `phase1.json`, `phase1-suggestions.md`, `phase1-regions.md`, `phase2.json`, `phase2-suggestions.md`, any leftover `shorthands.md` inside a batch folder, `attachments/`, and any non-`.md`/`.txt` files. Image files are not notes. An image embed line inside a note is a note line — keep it, do not mark the embed syntax as uncertain, and read the picture as in **Reading images**.

A scattered note may be a line, a group of lines, or a whole file. A divider line is only a candidate boundary. You decide whether it starts a new idea or is a section of the same idea or article. `phase1-regions.md` records that decision. The batch is one broad category (coding, linux, etc.).

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
  - `intent` — what to look up, taken from surrounding text in the same note (lines before/after the URL within the note you judged, or other lines in a `.url.txt` file). Empty string if the note is URL-only.
  - When the page **opens**: set `title` / `about` from the real page. If `intent` is set, focus `about` on that request (e.g. extract the complexity comparison they asked for); otherwise summarize the page in 1–3 sentences. Set `added: true`, `blocked: false`, leave `userPaste` / `userNote` empty (or preserve any prior user paste).
  - When the page **fails to open** (timeout, connection refused, HTTP error, bot wall, empty body — **any** fetch failure): set `blocked: true`, `added: false`, empty `title`/`about` (unless already filled from a prior user paste). Do **not** invent page content. The app shows a **blocked-URL panel** so the user can open the URL in their browser, describe what they need, and paste relevant text. Re-read `phase1.json` after they paste — the app writes `userPaste` / `userNote` via `index.php?action=save-url-paste`.
  - After the user pastes: derive `title` / `about` from `userPaste` (+ `userNote` / `intent`), set `added: true`, keep `blocked: true` only if you still lack enough text (otherwise `blocked: false`). Never wipe `userPaste` / `userNote`.
  - **Forbidden** link stubs: invented titles, “could not fetch”, or fake summaries when `blocked` is true and `userPaste` is empty.

### Reading images

When a note embeds a picture, read that picture while you take the notes in. Do this in Phase 1, before you write `phase1.json`, including right after an Evernote or UpNote import. If Phase 2 starts and an embed still has no reading, read it then.

An image file is not a note. A picture in the folder that no note embeds is not a note. Do not invent a note for it. Skip an embed on a line listed under `### Deletes` in `phase1-regions.md`. Skip an embed inside a fenced code block — that is an example of syntax, not a picture in the note.

An embed is markdown `![caption](path)`, Obsidian `![[path]]` (a `|width` or `|caption` after the path is still that file), or an HTML `<img src="path">`. The path is relative to `inputs/{folder}/`, including `attachments/` and `media/`. Use the path only: ignore a title in quotes after it, and ignore `<...>` around it. The same file linked more than once shares one reading — write that full reading on each line. A caption or a filename is a hint. Open the file anyway.

Open jpg, png, gif, and webp directly. For svg, avif, bmp, or another image type, try once. For an `http(s)` image URL, fetch it and look. A gif is the frame you can see. Do not describe motion you did not see.

Decide from the picture itself:

- **Text.** Screenshots, errors, terminals, whiteboards, handwriting, labels, or a photo of a page: pull out the important writing. Keep the exact errors, commands, numbers, names, and URLs. Leave out chrome that does not change the idea, such as a clock or a repeated window title, unless it matters here.
- **Visual meaning.** A diagram, sketch, layout, photo, or UI with little writing: say what it shows and what that means in this note. Name the parts a person would need later.
- **Both.** When the writing and the layout both matter, summarize what the picture is, then keep the important text.
- **Nothing extra.** A blank image, or a picture that only repeats the caption or the surrounding lines: say that in a few words in `about`, and leave `details` empty.
- **Cannot read.** Missing file, a format you cannot open, a failed fetch, or a picture too unclear to interpret: set `read` to false, put why in `reason`, and leave `about` and `details` empty. Do not invent text or a scene.

Shape the result to the picture. One or two sentences in `about` when that holds it. Add `details` as separate strings when there are several distinct facts — a dense screenshot, a list, or a page of text. Use a tighter list when it still keeps every fact the person would need to act on or look up later. Do not dump every word of a long page, and do not drop those facts. Do not put a bullet character on the strings. A summary may paraphrase the scene. Keep an error string, a command, or a label in the picture’s own words.

Use the surrounding note to judge what is important. Do not flag the embed syntax as uncertain shorthand.

On a line with one embed:

```json
{
  "n": 4,
  "text": "![nginx error](attachments/01-nginx-error.png)",
  "marks": [],
  "image": {
    "src": "attachments/01-nginx-error.png",
    "read": true,
    "about": "nginx -t fails on an unexpected } in nginx.conf line 12.",
    "details": [
      "nginx: [emerg] unexpected \"}\" in /etc/nginx/nginx.conf:12",
      "nginx: configuration file /etc/nginx/nginx.conf test failed"
    ]
  }
}
```

When the picture cannot be read:

```json
"image": {
  "src": "attachments/01-missing.png",
  "read": false,
  "about": "",
  "details": [],
  "reason": "file not found"
}
```

`read` is true only after you opened the image and could interpret it. `about` is one or two sentences. `details` is a list of strings, or empty. Omit `reason` when `read` is true.

A line with several embeds uses `images`, an array of that same object in reading order, and does not also set `image`.

**Forbidden:** a reading you did not get from looking, a placeholder such as “image” or “screenshot” when the picture had specifics, or `read: true` with empty `about` and empty `details` after you found something worth saying.

When you rewrite `phase1.json`, keep an existing `image` / `images` value. Re-open the file only when the reading is missing, the user says it is wrong, or a suggestion changes it. A `## Enrichment:` note on that line, a `## Line:` note, a `## Idea:` note that talks about the picture, or a chat correction revises `about` and `details`. When a Phase 2 suggestion rewrites the card text that came from the picture, update the stored reading too, so the next pass does not restore the old one.

The app shows the photo on the note line. When `read` is true and `about` or `details` is set, it shows a green added line above that photo: `about`, then each detail. Suggest on that green line is the enrichment note. When `read` is false, the app shows `Could not read {src} — {reason}` and does not show a green line.

In Phase 2, keep the embed in the card so the photo still shows, and put the reading in the card so Copy includes it.

- When the picture is the idea and the source has no sentence of its own, the heading names the idea and the opening sentence is `about`.
- When the source already explains the idea, keep that explanation as the opening. Put `about` under the embed only when it adds something the opening does not already say.
- Put `details` under that embed, one bullet each. Do not repeat `about` under the embed when the opening sentence already is `about`.
- The same reading twice in one card: keep the full bullets once, under the first embed, and under the later embed say it is the same picture. The stored objects stay complete.
- Do not replace the embed with the reading. When `read` is false, keep the embed and do not add a made-up description.

After Phase 1, say how many pictures you read. Name every file you could not read, with the reason. When there are five or fewer, include each `about` in that message. When there are more, say the readings are on the green lines and will be on the cards, and do not paste every bullet into chat.

### `phase1-suggestions.md`

User-written feedback saved by the app **during Phase 1** (batch folder). May be missing — treat as empty. **Always read this file (if present) before rewriting `phase1.json`, before updating `shorthands.md` from app notes, and before advancing to Phase 2.**

Format (app-owned; preserve structure when the user edits via the app):

```markdown
# Phase 1 suggestions

## Shorthand: wtf

means write the function

## Enrichment: a.url.txt:1

about should focus on bubble vs merge complexity, not a generic overview

## Idea: braindump.md:12-40

This whole region is one article. Keep the steps in order and don’t turn each line into its own task.

## Line: braindump.md:15

This line is the task. Keep the exact command.
```

- `## Shorthand: {token}` — one note for that shorthand **type**. The app shows the **same** comment on every uncertain flag with that token (case-insensitive). Applying it once is enough for all occurrences — update root `shorthands.md`, then clear those uncertain marks on rewrite.
- `## Enrichment: {file}:{line}` — feedback on a green added line: YouTube, a fetched link, or an image reading (`file` is the note filename, `line` is 1-based). Revise that line’s `youtube`, `link`, or `image` / `images` fields accordingly.
- `## Idea: {file}:{start}-{end}` — feedback on that **whole idea** (the colored region covering those 1-based source lines, inclusive). The user writes it by clicking the colored edge. Apply it when you group notes for Phase 2: wording, whether it stays one idea, and whether it is a task, reference, or article. Do not ignore it because the region was also split or merged; the line range is the idea they meant.
- `## Line: {file}:{line}` — feedback on **that source line’s text** only. The user writes it by clicking the line. Apply it to how that line is worded and classified when you organize Phase 2. It is not a comment on the rest of the idea.
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

### Deletes

15
16
```

- `### Splits` — source line numbers that **start a new note** even though no divider or blank line does.
- `### Joins` — source line numbers that **stay with the previous note** even though a divider or blank line would split them. This is how you record a divider that is only a section of the same idea or article.
- `### Deletes` — source line numbers the user removed with **Delete**. Leave them out of every phase. Do not turn them into tasks, reference, or articles. In `blame`, use fate `dropped_user` and comment `Deleted in the app.` The original note file still contains the lines; do not erase them from the `.md` / `.txt` file. **Restore** in Phase 1 removes those numbers. A card the user deleted in Phase 2 or 3 is already gone from `phase2.json`; restoring the lines does not put that card back unless they ask you to organize it again.
- Everywhere else, the app's candidate breaks apply, and you still judge every divider as below.
- Line numbers are 1-based and match the note file.
- The stored file is only the difference from automatic boundaries, plus any deletes. Restoring a boundary in the app removes that line. Restoring a deleted idea removes its lines from `### Deletes`. If a file has no remaining splits, joins, or deletes, the app deletes `phase1-regions.md`.

#### How a divider becomes a note

Use these same rules when you talk about regions or when you group notes. Do not invent a second segmentation. Dash length does not decide.

A divider is any of these:

- A line whose trimmed text is only dashes, short or long: `--`, `---`, `------`
- An HTML rule: `<hr>`, `<hr/>`, `<hr />`, including a tag with attributes
- A line of only asterisks: `**`, `***`, `****`
- Two or more blank lines in a row. One blank line is paragraph space, not a divider.

The app first draws a candidate note at every divider. If the file has none of these, single blank lines are the candidate breaks instead. That drawing is provisional. You decide what each candidate means:

- **New idea** — the text after the divider is a different idea, piece of knowledge, or task. Leave the break. Do not join it.
- **Section of the same idea or article** — the divider is a heading or rule inside one pasted article or one continuing thought. Join the first content line after the divider so it stays with the previous note. The divider stays inside that note.

A short dash line can be a new idea. A long dash line can be only a section. Two blank lines can be either. Do not wrap an article in a longer dash fence to protect shorter dashes. Do not treat `<hr>` or a line of asterisks as ordinary text. Do not treat a single blank line as a divider when the file already has one.

When a file has no divider at all, single blank lines are the candidates. Judge them the same way: paragraphs of one article stay one note; unrelated jots separated by a blank line are separate notes.

Leading and trailing blank lines inside a group are not part of the note. Internal blank lines stay. Empty groups between separators are not notes.

After you judge, write the joins and splits into `phase1-regions.md` so the app matches you. Honor splits and joins the user already saved; do not undo those to re-litigate a boundary they fixed. Then judge any divider they have not already joined or split.

- Each note is one colored region in Phase 1. The map on the right is the whole file.
- Hues, in order: 199, 152, 42, 270, 330, 12, 88, 230. If the next hue would match the previous note, skip to the one after it. The user does not pick colors. After every split or merge the app reassigns hues so two notes in a row never share a color.

#### What the user can do in Phase 1

On each note header:

- **Split** — then **Split into a new note here** on the line that should start the second idea. **Cancel** or Escape leaves the note unchanged. A one-line note cannot split.
- **Merge up** — this note becomes part of the note above. The first note has no Merge up.
- **Merge down** — the next note becomes part of this one. The last note has no Merge down.
- **Delete** — this idea leaves every phase. The original note file stays. Undo on this page brings it back. After a reload, **Restore** under Deleted puts it back in Phase 1. Delete works in Phase 2 and Phase 3 too, on each card and in Notebook, including while previewing another phase. Deleting a snapshot card removes it from that snapshot only.

The app renumbers notes and rebuilds the map after a boundary change or a delete, then saves. Tell the user they can fix a wrong boundary, or delete an idea they do not want, before they say Phase 1 looks good.

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

### Readable note — every organized item

A person reads these cards and copies them into their notes. Write each one so that person can use it later. Do this for **every** item in `tasks`, `reference`, and `articleCandidates`. Do not leave a raw jot with no heading and no explanation. Do not rewrite the original `.md` / `.txt` files. The heading and the explanation live on the card.

The card the person sees, and the text Copy puts on the clipboard, is:

```text
Short heading

One sentence, or a short paragraph, that says what this idea is.

The original lines, commands, links, and images.
```

**Heading (`title`).** The first line of the note. One line. Use a second line only when one line cannot name the idea — put that second line in `title` separated with a space, still a name, still scannable.

- The source already has a heading (`# …`, or a first line that is clearly a title): keep that meaning. Tighten words only so a person can scan it. Do not swap in a different idea.
- The source has no heading: write one. Name the idea in a few words (“Check nginx config before reload”). It is a name, not a paragraph, and not a tag.

**Explanation.** The first paragraph of the text the person reads. For `tasks` and `reference`, that is the start of `body`. For `articleCandidates`, the app shows `why` as the note text, so the explanation and the original detail both go in `why`.

- One sentence when that is enough. A short paragraph when one sentence would leave the idea unclear. Then a blank line, then the original detail.
- Say what the idea is, in words a person would keep. Use their terms. Where `shorthands.md` explains a token, the opening sentence may spell that token out so the note stands on its own. Leave their original line underneath.
- The source already opens with a sentence that explains the idea: keep that sentence as the opening. Do not stack a second summary on top of it.
- The original lines stay after the blank line: commands, paths, images (`![…](…)` / `![[…]]`), URLs, and the wording they wrote. The explanation is added in front. It does not replace the detail.
- An image embed stays in that original detail. Its **Reading images** result is part of the note a person copies: the opening sentence when the picture is the idea, and the important details under the embed. Do not replace the picture with the reading.
- A one-line jot still gets a heading and one sentence, then that original line.

YouTube and fetched links still follow the rules below. The card heading is `youtube.title` or `link.title`. The opening sentence is `youtube.about` or `link.about` when that text already explains the page or video. Then the URL and the rest. Do not invent a second title or a second summary on top of a real `about`.

On a later rewrite, keep a heading or an opening sentence the person already fixed in the app or in Suggest. Fill a heading or an explanation only where it is still missing.

Phase 2 items that come from a YouTube line **must reuse** `youtube.title` and `youtube.about` from `phase1.json` (e.g. reference title = that title; body includes the URL plus `about`). Do **not** invent a new title or write a metadata placeholder in Phase 2.

Phase 2 items that come from a non-YouTube link / `*.url.txt` line **must reuse** `link.title` and `link.about` from `phase1.json` when `link.added` is true (body includes the URL plus `about`; honor `intent` in the wording). If `link.blocked` is still true and `userPaste` is empty, keep the item honest (e.g. reference titled from the URL host + “blocked — paste pending”) — do **not** invent page content. If `userPaste` is present but Phase 1 was not re-run, you may derive title/about from the paste during Phase 2.

Phase 2 items that contain an image embed **must reuse** that line’s `image` / `images` reading from `phase1.json`, as in **Reading images**. If the reading is missing, open the picture before you write the card. Do not invent one.

**Browser edits:** In Phase 2, the app POSTs to `index.php?action=save-phase2` and writes moves, tags, reorder, green-check (`checked`), deletes, and `filterLayout` into this same `phase2.json`. **Delete** on a card removes that organized item and records its source lines under `### Deletes` in `phase1-regions.md`, with blame fate `dropped_user`. Phase 3 still saves moves/tags/reorder/deletes/`filterLayout`, but **Suggest** and green-check are locked off (review is finished). Delete stays available. Always **re-read** `phase2.json` and `phase1-regions.md` before Phase 3 or any fidelity fix so user edits and deletes are not overwritten blindly. Do not recreate a deleted item. Preserve each item’s `checked` boolean when rewriting organized items.

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
- When applying an item suggestion: rewrite `title` (heading), `body`, or **both**, as needed. Leave a field unchanged only when the suggestion does not call for changing it. If that rewrite changes what a picture says, update the stored `image` / `images` reading too (**Reading images**).
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

### Which project — ask when it is not clear

Each folder under `inputs/` is one project. On **every user message** about this harness, decide whether you know which folder the work is for **before** you read that folder’s notes, write artifacts, advance a phase, or apply a shorthand / suggestion / tag change.

Read `app.config.json` and list directories under `inputs/` (ignore files such as `.gitkeep`) before you decide.

**The project is known** only when one of these is true:

- This message names one existing folder (`sort-scattered`, `inputs/sample`, “use sample”).
- This same chat already confirmed a folder, you just re-read `app.config.json`, and `inputFolder` is still that folder. If the file no longer matches, they switched projects in the browser and you do not know.
- `inputFolder` is set, that directory exists, and it is the only directory under `inputs/`.

**Ask which project, then stop,** when you do not necessarily know. That includes:

- `inputFolder` is empty, or it is not a directory under `inputs/`.
- More than one directory exists under `inputs/`, this message does not name one, and this chat has not already confirmed the folder that `inputFolder` still points at. A value left in `app.config.json` at the start of a chat is not enough.
- Their words fit a different folder than `inputFolder` (a name, a topic, or notes that match another batch).

Do not guess from the only non-sample folder, from another chat, or from whichever folder looks mid-session.

**How to ask:** One short question. List every directory under `inputs/`, with the phase saved in `projects` (or “no phase yet”). If `inputFolder` is set and that folder exists, say the app currently has that one open. Wait. Do not start a phase, reset sample, or write files in that same turn.

Remember what they already asked (start a phase, continue, explain a shorthand, apply suggestions). After they name a folder, do that — do not make them repeat it. Set `inputFolder` to their answer, keep `projects`, and use that folder’s saved phase unless they asked to advance, reset, or start a specific phase.

If the name is not a directory under `inputs/`, say so and ask again. Create `inputs/{name}/` only after they say they want a new batch.

When the project is known, say which folder and phase in one short line, then continue.

### 0. Session check, then offer options

On **every start** (first run, re-run, or restart), run **Which project** first. Only after the project is known: detect session state before offering options. If you asked which project, do not also run this menu in that same turn.

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
4. **None / use my notes** — if they need a new batch, tell them both ways from **How the user gives you notes**. Folder: create `inputs/{name}/` and dump `.md`/`.txt` notes (separate files, or one file with divider lines such as `---`, `<hr>`, or `***`). Photos in that folder use markdown `![caption](file.png)` or Obsidian `![[file.png]]`, with the image file in the same folder. Then say that a public Evernote or UpNote URL is the automatic path: images are downloaded into `attachments/` and linked for them. Either way, each linked picture is read for text or for what it shows, and that reading stays with the photo. You decide which dividers are a new idea and which are a section of the same article. If they already pasted a public URL, import it instead of only repeating this menu.

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
2. **Revert to Phase 2** — set `app.config.json` `"phase": 2` and `projects["{inputFolder}"].phase` to `2` (keep `inputFolder`, the rest of `projects`, and `phase2.json`); tell them to refresh the app so Suggest / green-check return.
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
  1. Tell them both ways. Folder: create `inputs/{name}/`, add `.md`/`.txt` notes, and for a photo put the image in that same folder and link it with `![caption](file.png)` or `![[file.png]]`. Public URL: in Evernote set **Anyone with the link can view** and copy the link; in UpNote use Share → **Copy Link** (phone: **Share Link**). Paste that https URL here. You download images into `inputs/{name}/attachments/` and write the markdown image links. Each linked picture is read for text or for what it shows, and that reading stays with the photo. An `upnote://` link is not the public page. Then re-run once the notes are in the folder.

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

### 1. Phase 1 — mark uncertain + YouTube + links + images

1. Ensure project-root `shorthands.md` exists (create a stub with `# Shorthands` and an empty table if missing).
2. **Read root `shorthands.md` fully** before marking anything.
3. Read all note `.md`/`.txt` files in the folder (including `*.url.txt`). Read `phase1-regions.md` if it exists so you can skip embeds on deleted lines.
4. For **each** image embed that is not inside a fenced code block and not on a deleted line: open the picture and set `image` or `images` as in **Reading images**, **before** writing `phase1.json`. Do not invent text or a scene you did not see. Keep a reading already stored unless it is missing or a suggestion corrects it.
5. For **each** YouTube / `youtu.be` URL found: run a metadata fetch (oEmbed or watch page) **before** writing `phase1.json`. Fill `youtube.title` / `youtube.about` from that response only. If the fetch fails, use the blocked-`link` path below so the app can collect a browser paste.
6. For **each** non-YouTube `http(s)` URL (inline in notes **or** in `*.url.txt` files): try to visit the page **before** writing `phase1.json`. Capture surrounding-note text as `link.intent`. On success: fill `link.title` / `link.about` (honor intent), `added: true`, `blocked: false`. On **any** failure to open/read the page: `blocked: true`, `added: false`, do not invent content.
7. **Preserve** any existing `link.userPaste` / `link.userNote` already saved by the app when rewriting `phase1.json`. If paste text is present for a blocked URL, derive `title`/`about` from it and set `added: true`. Keep any existing `image` / `images` reading the same way, unless you re-read that picture or a suggestion changes it.
8. Write/update `phase1.json` with line-level marks, YouTube descriptors, link enrichments, and image readings (no placeholder titles, no invented picture text).
9. Set `app.config.json` `"phase": 1`.
10. Tell the user to open `index.php` (e.g. `php -S localhost:8765` then browse), review marks / green added descriptors (YouTube, links, and image readings) / **Could not read** lines / **blocked-URL paste panels** / **in-app Suggest** / **numbered note regions and the map**, and **return to this chat** to:
   - explain any flagged shorthand/symbols (chat **or** read `phase1-suggestions.md` shorthand sections — one note covers every flag of that token), or
   - apply enrichment Suggest notes from `phase1-suggestions.md`, including a correction to an image reading, or
   - apply whole-idea notes (`## Idea:`) and per-line notes (`## Line:`) from `phase1-suggestions.md` — the colored edge is the whole idea, a clicked line is that line’s text — or
   - say they pasted blocked-URL text (re-read `phase1.json` and enrich from `userPaste`), or
   - correct a picture — Suggest on its green line, or say in chat what it actually shows, or
   - say a region is wrong — they fix that in the app with **Split** or **Merge up** / **Merge down** (saved to `phase1-regions.md`; colors update automatically and neighboring notes never share a color). Read that file before you group notes again, or
   - say Phase 1 looks good and advance to Phase 2.

In that same message, report image readings as in **Reading images** (how many you read, each `about` when there are five or fewer, and every file you could not read).

**Before rewriting Phase 1 artifacts, and again before Phase 2:** always read `phase1-suggestions.md` if present. For each `## Shorthand:` note, update root `shorthands.md` and remove matching uncertain marks. For each `## Enrichment:` note, revise that line’s `youtube`, `link`, or `image` / `images` fields. For each `## Idea:` note, treat that source range as the user described when you group and word the next phase. For each `## Line:` note, apply that comment to that source line’s text, and to its image reading when the comment is about the picture. Do not wipe `phase1-suggestions.md` unless the user cleared Suggest in the app.

Phase 1 may loop: user explains (chat or Suggest) → update root `shorthands.md` / enrichments / image readings → rewrite `phase1.json`; or user pastes blocked URL text in the app → re-read pastes → rewrite link enrichments → user refreshes the app.

### 2. Phase 2 — organize with fidelity

Only after the user confirms Phase 1 is done:

1. Set `app.config.json` `"phase": 2`.
2. **Read** `inputs/{folder}/phase2-suggestions.md` if it exists (per-item and/or `## Panel: tasks|reference|articleCandidates` feedback from a prior review). Apply it — including rewriting each suggested item’s **heading (`title`) and/or `body`**, and applying whole-section guidance (tagging / heading style / ordering) as needed.
3. Read `phase1-regions.md` if it exists. A listed split starts a new note at that line; a listed join keeps that line with the previous note. A listed delete stays out of the organized notes (`blame` fate `dropped_user`). Judge every divider (`--` / `---` / `------`, `<hr>`, `**`, or two or more blank lines) as a new idea or as a section of the same article, and write joins for the sections. One blank line is paragraph space when any divider exists. Do not wipe `phase1-regions.md`. Do not drop `### Deletes`.
4. Read `phase1-suggestions.md` if it exists. Apply every `## Idea:` note to that whole source range and every `## Line:` note to that source line’s text while you group and word the cards. Do not wipe `phase1-suggestions.md`.
5. Turn notes into grouped **tasks** and **reference** knowledge; recommend **article candidates**. Enrich **every** item as in **Readable note**: a heading in `title` when the source has none, then one sentence or a short paragraph that says what the idea is, then a blank line, then the original detail. When a source line is a markdown, Obsidian, or HTML image embed, keep that embed in the item body so the photo still shows. Do not replace the picture with a description only. Put the **Reading images** result on the card with that embed. If the reading is missing, open the picture before you write the card.
6. For YouTube source lines, copy `youtube.title` / `youtube.about` from `phase1.json` into the organized item — never replace with a stub.
7. For link / `*.url.txt` source lines, copy `link.title` / `link.about` when `added` is true; if still blocked with empty paste, do not invent page content.
8. Account for every line in `blame` — fidelity first; do not silently drop details.
9. Tag every organized item (`tasks`, `reference`, `articleCandidates`) and **always write `filterLayout`**, using the tagging and `filterLayout` rules below. Related notes share tags. On a rewrite, keep every tag currently on the item and add any AI tag that is not already there (dedupe case-insensitively). Do not leave an item untagged, and do not delete a tag that is still on the item. Do **not** delete `phase2-suggestions.md`, `phase1-suggestions.md`, or `phase1-regions.md`.
10. Tell the user to **visit the web app** (`index.php`), refresh, and review whether the AI parsed their notes correctly: that it did **not** drop lines unnecessarily, and that each card reads the way they meant. Each card opens with a heading and a sentence that says what the idea is; their original lines follow. **Actively invite Suggest notes for changing a note** — if a heading, that opening sentence, the body, a bucket, a merge, a detail, or a tag is wrong, they should open **Suggest** on that card (or a section header Suggest for whole-section feedback: tagging, heading style, ordering) and write what to change; those notes save to `phase2-suggestions.md` for the next AI pass, and the AI may rewrite the **heading and/or body** and retag accordingly. Also remind them they can:
   - **green-check** items as they review;
   - see the tags already on each card and in the **Filter** bar, and add, remove, or rename them if one is wrong;
   - **move items** between Scattered / Reference / Articles (and rearrange within a panel);
   - those panel edits **save into `phase2.json`** via the PHP endpoint so this chat can see them later;
   - Phase 3 locks Suggest / green-check and only fills tags that are still missing — tags first appear here in Phase 2;
   - then **return to this chat** so you can apply Suggest notes (re-read `phase2-suggestions.md` + `phase2.json`), or say Phase 2 looks good and start Phase 3.

### 3. Phase 3 — refresh tags, then lock

Tags are already on the cards from Phase 2. Phase 3 does not wait to invent them. Only after the user confirms Phase 2 is done (or explicitly asks for Phase 3):

1. **Re-read** `inputs/{folder}/phase2-suggestions.md` (if present) **and** `phase2.json`. If suggestion notes are still pending, **apply them first** as a Phase 2 content rewrite (grouping / heading (`title`) and/or `body` / buckets / fidelity / tags), then continue. Phase 3 itself does not keep a Suggest loop.
2. **Re-read** `phase2.json` again so any browser moves/tags/reorder are included.
3. Set `app.config.json` `"phase": 3`.
4. Fill any organized item that is still missing tags, using the tagging rules below. **Preserve** tags the user already set; merge, do not replace their list.
5. **Refresh `filterLayout`** with the same rules as Phase 2: keep the user’s order, headings, and dividers where they still make sense, and only insert missing tags (with dividers for new major areas).
6. Write the updated `tags` + `filterLayout` (and keep `blame` / bucket placement / user edits / `checked`) back to `phase2.json`. Do **not** wipe `phase2-suggestions.md`.
7. Tell the user to refresh `index.php`: Phase 3 is **locked in** — no Suggest buttons, no green-check marking. The app’s job now is to **filter by tag and Copy** cards (or whole panels) into their permanent notes app. Tag/move tweaks still save to `phase2.json`. **Delete** still removes an idea. Return to chat only to refine tags if needed. **Always mention** they can return to Phase 2 anytime by saying **“Revert to Phase 2”** in this chat, then refreshing the app (Suggest / green-check unlock; organized notes stay). To bring a deleted idea back, they open Phase 1 and choose **Restore**.

#### Tagging rules (Phase 2 first write; Phase 3 only fills gaps)

- Prefer a small hierarchy you can **chunk up or down**: e.g. supercategory → category → subcategory (`linux` → `nginx` → `certs`), or broader/narrower area labels that cluster the same idea.
- An item may have **more than one tag**.
- Tags may contain **spaces**, but prefer short phrases (roughly a few words). Avoid long sentence-tags.
- Prefer reuse of the same tag string across items in the same area (consistent casing).
- **Preserve** any tags the user already added in the app; merge AI tags with theirs (dedupe case-insensitively).
- Do not invent tags that contradict the item’s content; when unsure, use a broader category tag.

#### `filterLayout` rules (Phase 2 and Phase 3 — always)

- **Always** emit `filterLayout` when writing Phase 2 or Phase 3 `phase2.json`. The app shows these groups in the Filter bar as soon as Phase 2 is on screen.
- Group related tags together (same area / hierarchy), then put a divider between groups.
- Optionally name each group with `{ "type": "heading", "name": "…" }` immediately before that group’s tags (after a divider, or at the start for the first group). Headings are labels only — not filterable tags.
- Example shape: heading `Web` → `nginx`, `certs` → divider → heading `Containers` → `docker`, `compose` → divider → `node`, `tooling`.
- No leading/trailing divider required; never stack two dividers in a row; every tag name in `filterLayout` must exist on at least one item (and every item tag must appear once in the layout).
- If the user already named groups in the app, **preserve those headings** when refining tags (only add headings for new major areas when helpful).
- When refining tags later in a phase-3 session, refresh `filterLayout` the same way. Note: if the user removes a divider in the app, the two group headings merge as `Heading A + Heading B`.

## Agent checklist

1. On every user message: read `app.config.json` and list `inputs/` folders. If you do not necessarily know which project (**Which project**), ask which folder and stop. When you know, announce folder + phase. On a start, then detect **no folder** / **mid-session** (phase 1–2) / **final session** (phase 3) and offer the matching option set from §0. A public Evernote or UpNote URL is an import, not only a menu: follow **How the user gives you notes** (download images into `attachments/`, link them with markdown image syntax, then continue). When you explain the folder method, also mention markdown `![caption](file.png)` and Obsidian `![[file.png]]` with the image in that same inputs folder, and that a public Evernote or UpNote URL downloads the images for them. Say that each linked picture is read for text or for what it shows, and that reading stays with the photo.
2. Mid-session: offer **Continue** first (phase 2 wording: review parse fidelity — nothing dropped; invite **Suggest** for notes that should change); Final: offer **Continue (phase 3, locked)** first — filter/copy exit — and remind **“Revert to Phase 2”** then refresh unlocks Suggest again. Do not wipe an in-progress or finished batch unless they choose a reset/switch.
3. If reset sample + select: delete `inputs/sample/`, recreate note files only (six files including `0-preface.md`, `a.url.txt`, and `b.url.txt`; no `phase1.json` / `phase2.json`), set `app.config.json` to `sample` phase 1, continue Phase 1.
4. If “See the app with no folder selected”: same recreate, reset `app.config.json` to `"inputFolder": ""` and `"phase": 1`; empty-state preview only — they still must pick a folder or select sample afterward; re-offer folders.
5. If another / switch folder: update `app.config.json` to that folder phase 1.
6. Read root `shorthands.md` before any uncertainty marks.
7. Never treat root `shorthands.md` / `phase1.json` / `phase1-suggestions.md` / `phase1-regions.md` / `phase2.json` / `phase2-suggestions.md` / `attachments/` / image files as source notes. An image embed line inside a `.md` or `.txt` note is a source line. Do not mark `![...](...)` or `![[...]]` as uncertain. Read the picture as in **Reading images**. In Phase 2, keep that embed in the card body and keep the reading with it.
8. Phase 1 artifacts must be visible in the app after refresh.
9. Every YouTube URL: metadata fetch first; non-placeholder `title`/`about`; `youtube.added: true`. On fetch failure: prefer blocked `link` panel (or `YouTube (lookup failed)` fallback).
10. Every non-YouTube URL and every `*.url.txt`: try fetch; set `link` with intent from surrounding text; on failure `blocked: true` and wait for app paste (`userPaste`/`userNote`). Preserve pastes across rewrites. After paste: derive `title`/`about`, set `added: true`.
11. Every image embed: during Phase 1, open the picture and set `image` or `images` as in **Reading images**, before writing `phase1.json`. Keep a stored reading when rewriting unless it is missing or a suggestion corrects it. Do not invent text or a scene you did not see. After Phase 1, report how many pictures you read and name every file you could not read.
12. Before rewriting Phase 1: read `phase1-suggestions.md` if present; apply shorthand notes to `shorthands.md` (one token note covers all flags of that token) and enrichment notes to `youtube` / `link` / `image` / `images` fields; keep `## Idea:` and `## Line:` notes for the next phase; do not wipe that file unless cleared in the app. Whenever grouping notes or answering which lines are one idea, read `phase1-regions.md` if present and honor its splits, joins, and deletes. A deleted line is not an idea. Judge each divider yourself (`--`, `---`, `------`, `<hr>`, a line of asterisks, or two or more blank lines in a row): a new idea stays split; a section of the same idea or article is joined. One blank line is paragraph space when any divider exists. Dash length does not decide. Single blank lines are the candidates only when the file has no divider. Neighboring notes never share a color. Do not wipe `phase1-regions.md`. Do not remove `### Deletes` unless the user restored that idea.
13. Before any Phase 2 write or revision: read `phase2-suggestions.md` if present; apply panel/item feedback (rewrite item `title` and/or `body` as the suggestion requires); do not wipe that file. If that rewrite changes what a picture says, update the stored image reading too. Also read `phase1-suggestions.md` and apply every `## Idea:` note to that whole source range and every `## Line:` note to that source line’s text. Do not wipe `phase1-suggestions.md`.
14. Phase 2 YouTube items reuse Phase 1 `youtube.title` / `youtube.about`. Link items reuse Phase 1 `link.title` / `link.about` when added. Image embeds reuse Phase 1 `image` / `images` (**Reading images**). If that reading is missing, open the picture before writing the card.
15. Phase 2: every line in every note file appears in `blame`. Every organized item has a human heading and an opening sentence or short paragraph that says what the idea is, then the original detail (**Readable note**), including each image reading with its embed. Every organized item has area tags, and `filterLayout` groups those tags for the Filter bar. Preserve tags the user already edited in the app. Preserve a heading or opening sentence they already fixed.
16. Do not advance to Phase 2 without explicit user confirmation.
17. After Phase 2 (and when continuing a phase-2 session): remind the user to review in the app whether the AI parsed their notes correctly — nothing dropped unnecessarily; **invite Suggest on any note that should change** (heading and/or body, bucket, merge, detail, tag, or an image reading → `phase2-suggestions.md`; next pass may rewrite `title` and/or `body` and retag); tags are already on each card and in the Filter bar (writes `phase2.json`). Phase 3 only fills tags that are still missing, then locks review.
18. Before Phase 3 (and before fidelity rewrites): re-read `phase2.json` and `phase2-suggestions.md` from disk; apply any pending suggestions as a Phase 2 rewrite, including tags, before filling any tag that is still missing.
19. Do not advance to Phase 3 without explicit user confirmation.
20. Phase 3: keep the Phase 2 tags; fill any item that is still untagged; preserve user tags; **refresh `filterLayout` with dividers between major tag groups**; write back to `phase2.json`; set phase 3. Remind: locked — filter + Copy into their notes app; no Suggest / green-check; they can always say **“Revert to Phase 2”** then refresh the app.
21. On **“Revert to Phase 2”**: set `"phase": 2` (keep folder + artifacts); tell them to refresh the app; resume Phase 2.
22. After each write, remind the user how to return to Cursor / Claude Code to continue (Phase 3: optional tag refine, or **“Revert to Phase 2”**).

## Return-to-chat prompts (for the user)

Across **all phases**, the user can drive the harness with short phrases:

- **“start phase 1”** / **“start phase 2”** / **“start phase 3”** — begin or jump to that phase for the active folder (respect advance gates: don’t run Phase 2/3 until the prior phase is confirmed done, unless they explicitly insist).
- **“let’s continue”** (or “continue”) — once the project is known, resume that mid/final session at whatever phase `app.config.json` already has; same as choosing **Continue** in §0. If the project is not clear, ask which `inputs/` folder first, then continue that one.
- **“Revert to Phase 2”** — from Phase 3 only: set `"phase": 2`, keep artifacts; user refreshes the app to unlock Suggest / green-check.

After Phase 1 writes, also tell them they can say things like:

- “`wtf` means write the function”
- “I added Phase 1 Suggest notes in the app — continue”
- “I pasted the blocked URL text in the app — continue”
- “The picture on line 4 is the nginx error, not a diagram”
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
