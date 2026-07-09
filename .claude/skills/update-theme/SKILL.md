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

## The manifest

**Tier 1 — always overwrite, no confirmation needed.** Nothing client-specific has ever lived in these paths since the `functions.php`/`inc/` split; overwriting them is always correct.

```
functions.php
.claude/skills/
.agents/skills/
bin/
.github/workflows/ci.yml
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

Anything not listed under Tier 1 or Tier 2 is implicitly never-touched. Don't expand either tier on your own initiative mid-run — this list was deliberately reviewed; if something seems like it should move tiers, ask first.

## Step 1 — Identify the canonical repo

**The canonical repo is https://github.com/Relmaur/taw-theme.** This is a **separate repo from `taw/core`** (https://github.com/Relmaur/taw-core, the framework package updated via `composer update taw/core`) — don't conflate the two. See `AGENTS.md` § "Source of Truth".

No remote setup is required for this skill to work (unlike the old git-merge design) — it only needs read access to fetch the repo's current contents, which a shallow clone gives it regardless of whether this project has any relationship to `taw-theme` in its own git history.

## Step 2 — Fetch a throwaway copy of the canonical repo

```bash
git clone --depth=1 https://github.com/Relmaur/taw-theme.git /tmp/taw-theme-update-<random>
```

A shallow clone (`--depth=1`) is enough and fast — this skill only ever reads specific files out of it, never anything history-related. If a local `upstream` remote already exists and points at the canonical repo, using that as the clone source instead is fine too, but it's not required.

## Step 3 — Apply Tier 1 (no confirmation)

For each Tier 1 path, copy it from the fresh checkout into this project, overwriting whatever is there:

```bash
cp /tmp/taw-theme-update-<random>/functions.php ./functions.php
rsync -a --delete /tmp/taw-theme-update-<random>/.claude/skills/ ./.claude/skills/
rsync -a --delete /tmp/taw-theme-update-<random>/.agents/skills/ ./.agents/skills/
rsync -a --delete /tmp/taw-theme-update-<random>/bin/ ./bin/
cp /tmp/taw-theme-update-<random>/.github/workflows/ci.yml ./.github/workflows/ci.yml
```

(`rsync -a --delete` for directories so a file removed upstream — e.g. a retired skill — is actually removed locally too, not just left behind. Plain `cp` is fine for single files.) If a Tier 1 path doesn't exist upstream (unlikely) or doesn't exist locally yet (e.g. a brand-new skill), that's fine — copy handles both add and update.

## Step 4 — Diff and confirm Tier 2

For each Tier 2 path:

```bash
diff -u ./AGENTS.md /tmp/taw-theme-update-<random>/AGENTS.md
```

Show the diff (or a summary for large files, e.g. dependency-only changes in `composer.json`/`package.json`). Ask the user whether to apply it — per-file or batched, your judgment, but never apply without the user having seen the diff. Only after confirmation, copy the file over. If declined, skip it and say so explicitly in the final report — don't silently drop it.

## Step 5 — Clean up and report

```bash
rm -rf /tmp/taw-theme-update-<random>
```

Summarize: what was applied (Tier 1), what was applied vs skipped (Tier 2), and remind the user this only touched the manifest paths above — nothing in `Blocks/`, `inc/`, page templates, or content was read or modified.

**Do not commit these changes.** Leave them staged/modified in the working tree per the project's standing git rules (never commit unless the user explicitly asks). Suggest reviewing with `git diff` / `git status` and committing when ready.

If nothing in either tier had upstream changes, say so plainly — "already up to date" is a valid, expected outcome, not a failure.

## Don't

- Don't touch, diff, or even read anything outside the two tiers above without asking first.
- Don't run `git merge`/`git pull` against the whole working tree — this skill deliberately avoids that now.
- Don't auto-apply Tier 2 changes without showing the diff and getting confirmation.
- Don't commit the synced changes — leave that decision and action to the user.
- Don't leave the temporary clone directory behind — clean it up even if the run is interrupted partway.
- Don't assume a project without a git relationship to `taw-theme` is broken or needs special handling — that's no longer a precondition this skill has.
