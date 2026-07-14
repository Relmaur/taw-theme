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
tests/bootstrap.php
tests/TestCase.php
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
phpunit.xml
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
tests/Unit/
```

**`tests/Unit/` is deliberately never-touched, not Tier 1, and can never become a `dir` entry** — it holds each client project's own block tests (`tests/Unit/Blocks/{Name}Test.php`), which is content, not scaffold. A `dir` entry syncs via `rsync -a --delete`; pointed at `tests/`, that would silently delete every client-authored test not present in the canonical repo. Only the harness itself (`tests/bootstrap.php`, `tests/TestCase.php`, `phpunit.xml`) is framework-owned.

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

For each `tier2[]` entry with `changed: true` from Step 1's JSON, show the user its `diff` field. Ask whether to apply it — per-file or batched, your judgment, but never apply without the user having seen the diff. If declined, skip it and say so explicitly in the final report — don't silently drop it.

**How you apply an approved change depends on the file's shape — two different files need two different treatments:**

**Prose/config files** (`AGENTS.md`, `CLAUDE.md`, `.github/copilot-instructions.md`, `.windsurfrules`, `README.md`, `vite.config.js`, `phpstan.neon`, `phpunit.xml`) — a full-file overwrite is safe once approved, since client-specific additions here are rare and the whole-document diff already showed the user exactly what they're accepting:

```bash
curl -fsSL https://raw.githubusercontent.com/Relmaur/taw-theme/main/AGENTS.md -o ./AGENTS.md
```

**`composer.json` and `package.json` — never do a full-file overwrite, even if approved.** These are structural manifests where client-specific dependencies (a project's own `mjml`, `swup`, `embla`, `photoswipe`, `alpine-collapse`, etc.) are *additive*, not incidental — a real client project accumulating its own packages over time is the normal, expected case, not drift to be corrected. A whole-file `curl -o` would silently **delete every one of those dependencies**, since they don't exist in the canonical `taw-theme` scaffold's version of the file. Instead:

1. Read the diff line by line and identify only the genuinely framework-relevant changes — e.g. a `taw/core` version constraint bump in `require`, a changed/added `scripts` entry, a PSR-4 `autoload` path change. Ignore every line that's just the client's own dependencies not being present upstream — that's not a real diff to act on, it's structural noise from the two files having different purposes. One specific case worth naming: if `phpunit.xml`/`tests/bootstrap.php`/`tests/TestCase.php` are landing on this project for the first time (see Tier 1/Tier 2 above), the matching `composer.json` lines — `require-dev` entries for `phpunit/phpunit` and `brain/monkey`, the `"test": "phpunit"` script, and the `autoload-dev` PSR-4 mapping `"TAW\\Theme\\Tests\\": "tests/"` — are framework-relevant additions to apply, not noise, even though they look like "new dependencies." Without them the harness files exist on disk but `composer run test` fails outright.
2. If there's nothing framework-relevant in the diff (the common case — it's *only* client-specific deps), tell the user plainly: "this diff is just your own project dependencies not existing in the base scaffold — nothing to apply, this is expected and will keep showing up every run." Don't ask them to re-approve the same non-decision every time `update-theme` runs.
3. If there genuinely is a framework-relevant line, edit *only that line* into the local file by hand (`Edit` tool, not `curl`/`cp`) — never replace the surrounding file content.

This same principle generalizes: **any Tier 2 file that's a structured manifest (JSON/config with discrete keys) needs a surgical, line-level merge for anything with real additive content — only free-form prose files are safe to treat as all-or-nothing.** `vite.config.js`/`phpstan.neon` are currently simple enough in practice that a full overwrite hasn't caused this problem, but apply the same judgment if a client project ever customizes one of those non-trivially — don't assume the "safe to overwrite" list above is permanently complete just because it's true today.

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
- Don't full-file-overwrite `composer.json`/`package.json` (or any other structural manifest) even after approval — surgically edit only the framework-relevant lines; a whole-file replace silently deletes the client's own additive dependencies.
- Don't hand-roll Tier 1's clone/copy logic — always go through `php bin/taw sync --apply`, so an interactive run and the automated CI workflow never diverge in behavior.
- Don't run `composer update taw/core` as a silent side effect of this skill — it's a separate, explicitly confirmed action (Step 4).
- Don't commit the synced changes — leave that decision and action to the user.
- Don't assume a project without a git relationship to `taw-theme` is broken or needs special handling — that's not a precondition this skill has.
- Don't let a Tier 2 doc (`AGENTS.md`/`CLAUDE.md`/`README.md`) start describing a file or capability as "already set up" without that file itself being added to `update-manifest.json` in the same change — this shipped once for real (the block-testing harness: `AGENTS.md` documented `phpunit.xml`/`tests/bootstrap.php`/`tests/TestCase.php` as pre-existing while they were entirely outside the manifest's scope, so `update-theme` synced the prose but never the substance). Whenever new framework infrastructure is documented as pre-existing, treat adding it to the manifest as part of the same commit, not optional follow-up.
