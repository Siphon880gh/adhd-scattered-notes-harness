# Scattered Notes Harness for ADHD Mind

By Weng (Weng Fei Fung)

For ADHD minds that jot notes everywhere in real time—then turn the mess into clear tasks and reference.

![Last Commit](https://img.shields.io/github/last-commit/Siphon880gh/adhd-scattered-notes-harness/main)
[![GitHub](https://img.shields.io/badge/GitHub--blue?style=social&logo=GitHub)](https://github.com/Siphon880gh)
[![LinkedIn](https://img.shields.io/badge/LinkedIn-blue?style=flat&logo=linkedin&labelColor=blue)](https://www.linkedin.com/in/weng-fung/)
[![Youtube](https://img.shields.io/badge/Youtube-red?style=flat&logo=youtube&labelColor=red)](https://www.youtube.com/@WayneTeachesCode/)

A Cursor / Claude Code harness for turning a folder of ad-hoc notes into grouped tasks and reference knowledge—without losing detail.

The PHP app is an **artifact container**. You do not organize notes in the browser. You run the skill in Cursor or Claude Code; the skill writes artifacts next to your notes; you open `index.php` to review them, then return to the chat to continue.

## Why this exists for ADHD

If you have ADHD, notes rarely land in one place. You jot something in a `.txt` file, sketch an idea in a `.md` note, save a half-formed thought in another file—and weeks later you have a folder of fragments with no clear through-line.

This harness meets that workflow: dump scattered notes first, process them in two fidelity-preserving phases later.

## Roles

| Piece | Role |
|-------|------|
| `.agents/skills/scattered-notes` | Agent skill: pick folder, write artifacts, update config |
| `app.config.json` | Points the app at one `inputs/` folder and the active phase |
| `index.php` | Read-only viewer for Phase 1 / Phase 2 artifacts |

There is **no folder switcher in the app**. The skill sets `app.config.json`; the app shows whatever that file points at.

## Setup

1. Create a batch folder: `inputs/whatname/`
2. Drop scattered notes as `.md` and/or `.txt`. One thought per file or many thoughts in one file—both are fine. Separate thoughts with `---` when they share a file.
3. In Cursor or Claude Code, run the skill at `.agents/skills/scattered-notes` and pick that folder (the skill will set `app.config.json`) — **or** prompt to use the scattered note organizing skill.
4. Serve the app and open it to review artifacts:

```bash
php -S localhost:8765
```

Then open `http://localhost:8765`.

## Input layout

```
inputs/
  whatname/
    note-one.md
    random-thought.txt
    shorthands.md      # glossary for this batch (skill-maintained)
    phase1.json        # uncertain marks + YouTube descriptors
    phase2.json        # organized notes + per-line blame map
```

`app.config.json`:

```json
{
  "inputFolder": "whatname",
  "phase": 1
}
```

## Two phases

### Phase 1 — clarify before organize

The skill reads `shorthands.md` first, then marks unclear symbols and shorthand. YouTube links get a green “added” descriptor (title / what the video is about) inferred from the video’s title, description, and comments—no watching required.

Review marks in `index.php`. Explain shorthand in the agent chat; the skill updates `shorthands.md` and refreshes `phase1.json`. Reload the app to see updates.

### Phase 2 — organize with fidelity

When Phase 1 is done, the skill groups notes into **tasks**, **reference knowledge**, and **article candidates**. Every source line is accounted for in a blame map so details do not disappear silently.

In the app, the main panel shows the organized result. A hamburger opens the original notes with per-line accounting comments.

## Skill handoff

Each phase in the app tells you how to return to Cursor or Claude Code to continue. Typical loop:

1. Skill writes artifacts and sets `app.config.json`
2. You review in `index.php`
3. You go back to the chat to explain marks, advance phases, or fix fidelity gaps

## Who this is for

Anyone who captures thoughts in bursts and organizes later—or never quite gets to the organizing part. The harness meets you where your notes already are.
