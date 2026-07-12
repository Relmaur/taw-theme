---
name: update-theme
description: >
    Pulls the latest base-theme scaffold from the canonical taw-theme repository into this
    site instance via a direct manifest-based file sync — no git merge, no shared git history
    required. A small set of paths (functions.php, .claude/skills/, .agents/skills/, bin/,
    CI config) is unambiguously framework-owned and always safe to overwrite outright; a
    second set (docs/build config) is diffed and applied only after confirmation; everything
    else (Blocks/, inc/options.php, inc/performance.php, inc/customizations.php, page
    templates, content) is never read or touched. Triggers on "update the theme" / "sync the
    theme" / "pull theme updates".
argument-hint: "[optional: --dry-run to preview without applying]"
---

## Overview

Every real client site is a divergent instance of the same `taw-theme` scaffold. This skill syncs the shared, framework-owned parts of that scaffold from the canonical repo — **by directly copying specific files/directories from a fresh checkout, not by running `git merge`.**

**Why not `git merge` (the previous design):** a merge needs a common ancestor commit, which meant every client project had to be created via a full clone/`--keep-vcs` and could never have a truly fresh, single-commit history. That constraint is gone now. As of `taw/core` v1.16.63, `functions.php` is 100% framework-owned by construction — it's two lines (`require autoload` + `Theme::bootstrapFullSite(...)`), and every genuinely site-specific thing that used to live inside it (theme supports, nav menus, performance tuning) now lives in three files (`inc/options.php`, `inc/performance.php`, `inc/customizations.php`) that this skill never touches. Combined with the other framework-only paths (`.claude/skills/`, `bin/`, CI config), there's now a small, precisely delimited set of paths where "is this safe to overwrite" is a fact about the *path*, not something that requires diffing against a shared history to determine. No merge, no conflicts, no `MERGE_HEAD`, no `git merge-base` precondition — this works identically whether the client project was cloned, `git init`'d fresh, or anything else.

**This skill is the interactive half of a two-part system.** The actual detection/apply logic lives in `TAW\CLI\SyncCommand` (`php bin/taw sync`, shipped by `taw/core`) — this skill wraps that command for a human-in-the-loop session. The other half is `.github/workflows/framework-sync.yml` (itself Tier 1, so it propagates to every client project automatically once this skill has run there once), which runs the same command unattended on a weekly schedule and opens a PR when it finds drift. Both paths call the identical underlying logic — behavior never diverges between "an agent ran this interactively" and "CI ran this on autopilot."

## The manifest

**Single source of truth:** `vendor/taw/core/resources/update-manifest.json` (ships with `taw/core`, read directly by `php bin/taw sync`). The lists below are current as of this writing for a fast read, but if they ever look wrong, trust the JSON file over this doc.

**Tier 1 — always overwrite, no confirmation needed.** Nothing client-specific has ever lived in these paths since the `functions.php`/`inc/` split; overwriting them is always correct.

```
functions.php
.claude/skills/
.agents/skills/
bin/
.github/workflows/ci.yml
.github/workflows/framework-sync.yml
```

**Tier 2 — diff, apply only after explicit confirmation.** Nominally framework docs/config, but can legitimately accumulate client-specific additions over a project's life (a new catalog entry in `AGENTS.md`, an added dependency in `package.json`). Never overwrite silently.

```
AGENTS.md
CLAUDE.md
.github/copilot-instructions.md
.windsurfrules
README.md
composer.json
package.json
vite.config.js
phpstan.neon
```

**Never touched — not read, not diffed, entirely out of scope:**

```
Blocks/
inc/options.php
inc/performance.php
inc/customizations.php
page*.php, front-page.php, index.php
resources/scss/_fonts.scss
resources/fonts/
release-notes.md
```

Anything not listed under Tier 1 or Tier 2 is implicitly never-touched. Don't expand either tier on your own initiative mid-run — this list was deliberately reviewed; if something seems like it should move tiers, ask first (and if you do add a path, update `resources/update-manifest.json` in `taw-core`, not just this doc — they must stay in sync, since the CLI command reads the JSON, not this file).

## Step 1 — Run the sync check

```bash
php bin/taw sync --json
```

This clones a throwaway shallow copy of the canonical `taw-theme` repo (cleaned up automatically, even on failure), diffs every Tier 1/Tier 2 path against it, and separately checks whether the installed `taw/core` version is behind the latest GitHub tag. Nothing is written to disk by this step alone. Parse the JSON: `taw_core.installed`/`.latest`/`.behind`, `tier1[].path`/`.changed`, `tier2[].path`/`.changed`/`.diff`.

If `errors` is non-empty (couldn't clone, couldn't reach GitHub), report that plainly rather than treating it as "up to date" — a failed check is not a clean result.

## Step 2 — Apply Tier 1 automatically

```bash
php bin/taw sync --apply
```

Writes every changed Tier 1 path directly — no confirmation needed, per the manifest above — and never touches Tier 2. **Don't hand-roll a copy/rsync for Tier 1 yourself** — always go through this command, so behavior here stays identical to what the CI workflow does unattended. (Re-run `sync --json` afterward if you want a clean report for the final summary — Tier 1 entries will now show `changed: false`.)

## Step 3 — Review and apply Tier 2 (confirmation required)

For each `tier2[]` entry with `changed: true` from Step 1's JSON, show the user its `diff` field. Ask whether to apply it — per-file or batched, your judgment, but never apply without the user having seen the diff. For each approved file, fetch the canonical content directly and overwrite:

```bash
curl -fsSL https://raw.githubusercontent.com/Relmaur/taw-theme/main/AGENTS.md -o ./AGENTS.md
```

(Swap the path for whichever Tier 2 file was approved.) If declined, skip it and say so explicitly in the final report — don't silently drop it.

## Step 4 — taw/core is a separate decision

If Step 1 reported `taw_core.behind: true`, that's a different action from anything above — this skill only ever touches the `taw-theme` scaffold, never the `taw/core` package. Tell the user it's available and ask whether to also run `composer update taw/core` (see `AGENTS.md`'s ship pattern for the full verify-after-update sequence) — don't run it silently as a side effect of this skill.

## Step 5 — Report

Summarize: taw/core status (and whether it was updated), what Tier 1 applied, what Tier 2 applied vs skipped, and remind the user this only touched the manifest paths above — nothing in `Blocks/`, `inc/`, page templates, or content was read or modified.

**Do not commit these changes.** Leave them staged/modified in the working tree per the project's standing git rules (never commit unless the user explicitly asks). Suggest reviewing with `git diff` / `git status` and committing when ready.

If nothing in either tier had upstream changes, say so plainly — "already up to date" is a valid, expected outcome, not a failure.

## Don't

- Don't touch, diff, or even read anything outside the two tiers above without asking first.
- Don't run `git merge`/`git pull` against the whole working tree — this skill deliberately avoids that now.
- Don't auto-apply Tier 2 changes without showing the diff and getting confirmation.
- Don't hand-roll Tier 1's clone/copy logic — always go through `php bin/taw sync --apply`, so an interactive run and the automated CI workflow never diverge in behavior.
- Don't run `composer update taw/core` as a silent side effect of this skill — it's a separate, explicitly confirmed action (Step 4).
- Don't commit the synced changes — leave that decision and action to the user.
- Don't assume a project without a git relationship to `taw-theme` is broken or needs special handling — that's not a precondition this skill has.
