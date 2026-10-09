---
name: update-theme
owner: taw
description: >
    Pulls the latest base-theme scaffold from the canonical taw-theme repository into this
    site instance via a direct manifest-based file sync — no git merge, no shared git history
    required. A small set of paths (functions.php, bin/, CI config) is unambiguously
    framework-owned and always safe to overwrite outright; the skills directories
    (.claude/skills/, .agents/skills/) are reconciled per-skill so site-authored skills
    survive; a second set (docs/build config) is diffed and applied only after confirmation;
    everything
    else (Blocks/, inc/options.php, inc/performance.php, inc/customizations.php, page
    templates, content) is never read or touched. Triggers on "update the theme" / "sync the
    theme" / "pull theme updates". Has a non-interactive batch mode (§ "Batch mode") for an
    agent updating several themes at once, e.g. a subagent of taw-fleet's update-all.
argument-hint: "[optional: --dry-run to preview without applying]"
---

## Overview

Every real client site is a divergent instance of the same `taw-theme` scaffold. This skill syncs the shared, framework-owned parts of that scaffold from the canonical repo — **by directly copying specific files/directories from a fresh checkout, not by running `git merge`.**

**Why not `git merge` (the previous design):** a merge needs a common ancestor commit, which meant every client project had to be created via a full clone/`--keep-vcs` and could never have a truly fresh, single-commit history. That constraint is gone now. As of `taw/core` v1.16.63, `functions.php` is 100% framework-owned by construction — it's two lines (`require autoload` + `Theme::bootstrapFullSite(...)`), and every genuinely site-specific thing that used to live inside it (theme supports, nav menus, performance tuning) now lives in three files (`inc/options.php`, `inc/performance.php`, `inc/customizations.php`) that this skill never touches. Combined with the other framework-only paths (`bin/`, CI config, and the skills directories — the last reconciled per-skill so a client's own `owner: site` skills survive), there's now a small, precisely delimited set of paths where "is this safe to overwrite" is a fact about the *path* (and, for skills, a one-line `owner:` marker), not something that requires diffing against a shared history to determine. No merge, no conflicts, no `MERGE_HEAD`, no `git merge-base` precondition — this works identically whether the client project was cloned, `git init`'d fresh, or anything else.

**This skill is the interactive half of a two-part system.** The actual detection/apply logic lives in `TAW\CLI\SyncCommand` (`php bin/taw sync`, shipped by `taw/core`) — this skill wraps that command for a human-in-the-loop session. The other half is `.github/workflows/framework-sync.yml` (itself Tier 1, so it propagates to every client project automatically once this skill has run there once), which runs the same command unattended on a weekly schedule and opens a PR when it finds drift. Both paths call the identical underlying logic — behavior never diverges between "an agent ran this interactively" and "CI ran this on autopilot."

## The manifest

**Single source of truth:** `vendor/taw/core/resources/update-manifest.json` (ships with `taw/core`, read directly by `php bin/taw sync`). The lists below are current as of this writing for a fast read, but if they ever look wrong, trust the JSON file over this doc.

**Tier 1 — always overwrite, no confirmation needed.** Nothing client-specific has ever lived in these paths since the `functions.php`/`inc/` split; overwriting them is always correct.

```
functions.php                         (type: file)
bin/                                  (type: dir  — rsync -a --delete)
.github/workflows/ci.yml              (type: file)
.github/workflows/framework-sync.yml  (type: file)
tests/bootstrap.php                   (type: file)
tests/TestCase.php                    (type: file)
.claude/skills/                       (type: skills-dir — per-skill reconcile, see below)
.agents/skills/                       (type: skills-dir — per-skill reconcile, see below)
```

**Tier 1 skills directories are `skills-dir`, not `dir` — they are NOT a blind `rsync --delete`.** Claude Code and the agents runtime only auto-discover skills directly under `.claude/skills/` and `.agents/skills/`, so a client site's *own* skills (site-specific editorial/publishing workflows, etc.) have to live in the same folder as the framework's. `sync` reconciles that folder one skill subdirectory at a time:

| Skill subdir | What sync does |
|---|---|
| present in canonical `taw-theme` | **overwritten** in place — fresh canonical copy every run (`rsync -a --delete` *within* that one skill folder) |
| only in the client, `SKILL.md` frontmatter has `owner: site` | **preserved** untouched, and named in the sync report |
| only in the client, `SKILL.md` frontmatter has `owner: taw` | **deleted** — it's a framework skill that was retired upstream |
| only in the client, **no `owner:` key** | **preserved**, but the report emits a warning — a human decides (add `owner: site` to keep it, or delete the folder if it's a stale framework skill) |

Every canonical framework skill carries `owner: taw` in its frontmatter — that's also how you tell framework skills from site skills at a glance: `grep -rl 'owner: taw' .claude/skills` vs `grep -rl 'owner: site' .claude/skills`. The `owner:` key + `skills-dir` type are defined in `update-manifest.json` under `skillsReconcile`; the reconcile logic is in `TAW\CLI\SyncCommand`.

**A site-authored skill MUST declare `owner: site` in its `SKILL.md` frontmatter** to be safe across syncs — see § "Migrating an existing site-authored skill" at the end.

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

For the two `skills-dir` entries, each also carries a `reconcile` object — `{ overwrite: [...], delete: [...], preserve: [...], warn: [...], clash: [...] }` (`clash` since taw/core v1.59.2: a site skill with `owner: site` that shares a new framework skill's name; the site's copy is kept and the framework one is not installed — tell the user to rename theirs to get both). Surface it in the final report: name every site-authored skill under `preserve` ("left untouched"), every retired framework skill under `delete`, and — importantly — anything under `warn` (a skill folder that's neither in canonical nor marked `owner: site`), telling the user to add `owner: site` to keep it or remove it if it's stale. `preserve` alone does not make the run "not clean".

If `errors` is non-empty (couldn't clone, couldn't reach GitHub), report that plainly rather than treating it as "up to date" — a failed check is not a clean result.

## Step 2 — Apply Tier 1 automatically

```bash
php bin/taw sync --apply
```

Writes every changed Tier 1 path directly — no confirmation needed, per the manifest above — and never touches Tier 2. For the `skills-dir` entries this means: refresh every framework skill, delete any `owner: taw` skill that's gone from canonical, and leave every `owner: site` skill (and every unmarked one) exactly where it is. **Don't hand-roll a copy/rsync for Tier 1 yourself** — always go through this command, so behavior here stays identical to what the CI workflow does unattended. (Re-run `sync --json` afterward if you want a clean report for the final summary — Tier 1 entries will now show `changed: false`.)

## Step 3 — Review and apply Tier 2 (confirmation required)

For each `tier2[]` entry with `changed: true` from Step 1's JSON, show the user its `diff` field. Ask whether to apply it — per-file or batched, your judgment, but never apply without the user having seen the diff. If declined, skip it and say so explicitly in the final report — don't silently drop it.

**How you apply an approved change depends on the file's shape — two different files need two different treatments:**

**Prose/config files** (`AGENTS.md`, `CLAUDE.md`, `.github/copilot-instructions.md`, `.windsurfrules`, `README.md`, `vite.config.js`, `phpstan.neon`, `phpunit.xml`) — a full-file overwrite is safe once approved, since client-specific additions here are rare and the whole-document diff already showed the user exactly what they're accepting. Two cautions:
- The docs describe starter files that sync never adds to an existing site (`inc/security.php`, `Blocks/Chatbot`). Before applying, check whether the site has them, and tell the user which described files it doesn't. `inc/security.php` is worth copying from the starter by hand (it redirects `?author=N` probes); the chatbot is optional.
- Keep site-specific sections a site added to its `CLAUDE.md` (merge by hand), and show the user the diff of `vite.config.js` or `phpstan.neon` before overwriting: sites customize them (an extra full-reload glob, `wp-cli` stubs).

```bash
curl -fsSL https://raw.githubusercontent.com/Relmaur/taw-theme/main/AGENTS.md -o ./AGENTS.md
```

**`composer.json` and `package.json` — never do a full-file overwrite, even if approved.** These are structural manifests where client-specific dependencies (a project's own `mjml`, `swup`, `embla`, `photoswipe`, `alpine-collapse`, etc.) are *additive*, not incidental — a real client project accumulating its own packages over time is the normal, expected case, not drift to be corrected. A whole-file `curl -o` would silently **delete every one of those dependencies**, since they don't exist in the canonical `taw-theme` scaffold's version of the file. Instead:

**Since taw/core v1.78.0, start from `sync`'s suggestions, not the raw diff.** Each of these files gets a `merge` entry in `sync --json`, built by rule (`update-manifest.json` § `manifestMerge`): `add` (keys/repositories the scaffold has and the site lacks), `bump` (a dependency constraint the scaffold raised), `review` (other differences, such as a changed script, for the user), `optional` (Reactiph, the chatbot: skip unless wanted), `site_only` (the site's own keys, kept). Nothing in it is ever a removal. Show the user `add`/`bump`/`review`; once approved, `php bin/taw sync --apply-manifests` writes `add` + `bump` in place (then `composer update` the packages whose constraint changed, `npm install` after a package.json change). Apply an approved `review` item by hand, line by line. The steps below are for an older taw/core whose `sync --json` has no `merge`.

1. Read the diff line by line and identify only the genuinely framework-relevant changes — e.g. a `taw/core` version constraint bump in `require`, a changed/added `scripts` entry, a PSR-4 `autoload` path change. Ignore every line that's just the client's own dependencies not being present upstream — that's not a real diff to act on, it's structural noise from the two files having different purposes. One specific case worth naming: if `phpunit.xml`/`tests/bootstrap.php`/`tests/TestCase.php` are landing on this project for the first time (see Tier 1/Tier 2 above), the matching `composer.json` lines — `require-dev` entries for `phpunit/phpunit` and `brain/monkey`, the `"test": "phpunit"` script, and the `autoload-dev` PSR-4 mapping `"TAW\\Theme\\Tests\\": "tests/"` — are framework-relevant additions to apply, not noise, even though they look like "new dependencies." Without them the harness files exist on disk but `composer run test` fails outright.
   **Optional features, not framework changes:** the starter ships Reactiph (`reactiph/taw-bridge`, its three Reactiph `vcs` repositories and the `"minimum-stability": "dev"` it needs) in `composer.json`, and the chatbot's `marked` + `dompurify` in `package.json`. Those lines are for new sites. On an existing site, present them as optional features and default to skipping them. Add them only when the user wants Reactiph or has `Blocks/Chatbot`. Never add `minimum-stability: dev` on its own: it lets every dependency resolve to dev versions.
2. If there's nothing framework-relevant in the diff (the common case — it's *only* client-specific deps), tell the user plainly: "this diff is just your own project dependencies not existing in the base scaffold — nothing to apply, this is expected and will keep showing up every run." Don't ask them to re-approve the same non-decision every time `update-theme` runs.
3. If there genuinely is a framework-relevant line, edit *only that line* into the local file by hand (`Edit` tool, not `curl`/`cp`) — never replace the surrounding file content.

This same principle generalizes: **any Tier 2 file that's a structured manifest (JSON/config with discrete keys) needs a surgical, line-level merge for anything with real additive content — only free-form prose files are safe to treat as all-or-nothing.** `vite.config.js`/`phpstan.neon` are currently simple enough in practice that a full overwrite hasn't caused this problem, but apply the same judgment if a client project ever customizes one of those non-trivially — don't assume the "safe to overwrite" list above is permanently complete just because it's true today.

## Step 4 — taw/core is a separate decision

If Step 1 reported `taw_core.behind: true`, that's a different action from anything above — this skill only ever touches the `taw-theme` scaffold, never the `taw/core` package. Tell the user it's available (installed → latest) and ask whether to also run `composer update taw/core --with-dependencies` — don't run it silently as a side effect of this skill. (Without `--with-dependencies`, a release that needs a newer dependency is a silent no-op; check the version moved.)

If they say yes: after the update, **read `vendor/taw/core/UPGRADING.md`** (the new version's copy) and work through every section newer than the version the site came from. It lists, per release, what changes by default (for example a REST route hidden, metabox tabs rendering) and the **Check** to run for each. Then verify as it says (tests, the `visual-check` skill, wp-admin screens with metaboxes and options pages) and report each check's outcome — "not applicable" is a valid outcome, a skipped check is not.

## Step 5 — Report

Summarize: taw/core status (and whether it was updated), what Tier 1 applied, what Tier 2 applied vs skipped, and remind the user this only touched the manifest paths above — nothing in `Blocks/`, `inc/`, page templates, or content was read or modified.

**Do not commit these changes.** Leave them staged/modified in the working tree per the project's standing git rules (never commit unless the user explicitly asks). Suggest reviewing with `git diff` / `git status` and committing when ready.

If nothing in either tier had upstream changes, say so plainly — "already up to date" is a valid, expected outcome, not a failure.

## Batch mode

For an agent updating several themes in one session (taw-fleet's update-all: a coordinator with one subagent per theme). It applies when the prompt says **batch mode**; everything above still holds except where this section says otherwise. **A batch run never asks the user anything:** it decides by these rules or stops with a reason, and leaves every decision that needs the user to the coordinator, as a proposal in its result. The prompt gives the theme folder, the site's PHP binary, Local's Composer, whether the site is running, and the branch name.

Run every command with the site's own PHP (`<php> bin/taw …`, `<php> <composer.phar> …`), from the theme folder.

**B1. Preconditions.** Stop with `status: "skipped"` and the reason, changing nothing, when:
- `dirty`: `git status --porcelain` isn't empty;
- `wrong-branch`: the theme is on neither its default branch nor the batch branch;
- `pull-failed`: `git pull --ff-only` on the default branch fails (no network, diverged);
- `scaffold`: the theme is the canonical `taw-theme` or `taw-gutenberg` itself. Decide by `git remote get-url origin` (`Relmaur/taw-theme` or `Relmaur/taw-gutenberg`), not by `composer.json`'s `name`: client themes keep the scaffold's `taw/theme` name.

**B2. Branch.** `chore/taw-core-<newest version>` (or `chore/update-theme-<YYYY-MM-DD>` when taw/core is current) from the up-to-date default branch. If it already exists, check it out and continue from where it is (a re-run resumes, it doesn't ask).

**B3. taw/core first.** When taw/core is behind, run `composer update taw/core --with-dependencies` (approved in batch mode, including taw/core's own dependencies it moves). Do this **before** the sync: `bin/taw sync` runs from the theme's installed taw/core, and the manifest suggestions need v1.78.0 or later. Without `--with-dependencies`, a release that needs a newer dependency (v1.77.0 needs `enshrined/svg-sanitize ^1.0`) is a silent no-op: "Nothing to modify in lock file", exit 0, sometimes after "Found N security vulnerability advisories". **Check the version moved** (`composer show taw/core`). If it didn't, run `composer why-not taw/core <newest>`, then stop with `status: "failed"`, `reason: "core-held-back"`, and what holds it back in `notes`. Note the version before and after, and every other package that moved.

**B4. Sync.**
1. `bin/taw sync --json`, keep the output.
2. `bin/taw sync --apply`: Tier 1, as always.
3. `bin/taw sync --apply-manifests` when a `merge` entry has `add` or `bump`. Then, only if a `require`/`require-dev` key was added or bumped, `composer update <those packages>`; after a `package.json` change, `npm install`. Skip both when nothing of the kind changed.
4. **Re-read this file.** Tier 1 just refreshed `.claude/skills/`, so the theme's copy may now be newer than the one you followed (the theme's old copy, or the canonical one if the old copy had no batch mode). Follow the theme's refreshed copy from here, and say in `notes` if it differed from the one you followed.

Never hand-edit `composer.json`/`package.json` in batch mode. Without a `merge` entry (taw/core older than 1.78.0), or for `review` items, report them as proposals.

**B5. Tier 2 prose is a proposal.** Don't apply any changed Tier 2 file other than through B4.3. For each, add a proposal with the path, `diff_lines` (the line count of sync's `diff` field), a one-line summary of what changed upstream, and anything site-specific the overwrite would lose (a section the site added). The coordinator asks the user and applies what's approved. To tell whether the chatbot's optional lines apply, you may check that `Blocks/Chatbot/` **exists**; don't read anything in `Blocks/`.

**B6. UPGRADING checks.** Read `vendor/taw/core/UPGRADING.md` and give every **Check** in each section newer than the version you came from an entry of its own (a section can have several; an instruction such as "delete the regular plugin" counts as one). Checks are worded for a site upgraded by hand; judge them after the sync by their intent (a retired command that the synced `bin/taw` no longer lists is a pass). Each gets one outcome:
- `pass`, or `not-applicable` (with why);
- `needs-wordpress`: the check needs the site running and it isn't (don't start it; the coordinator asks once for every site);
- `needs-browser`: it needs a browser (never open one in batch mode);
- `failed`, with what happened.

**B7. Verify.** Run each of these that the theme has; record `pass`, `fail` (with the last lines of output), `missing` (no such script) or `not-needed` (the build, when nothing that triggers it changed):
- `composer run test`;
- `composer run phpstan`;
- `npm run build`, when `package.json`, `vite.config.js` or anything under `resources/` changed, or Tier 1 touched `resources/js/`.

**B8. Commit, never push.** Commit everything on the batch branch: "Update taw/core to <version>; sync theme scaffold" (or "Sync theme scaffold"). Commit even when a check failed, so the work is kept on the branch, and say so in the result. No push, no PR, no merge, nothing on the default branch.

**B9. Result.** End your final message with exactly one fenced `json` block in this shape (the coordinator reads it; keep keys even when empty):

```json
{
  "theme": "ls-mexico",
  "site": "ls-mxico",
  "status": "updated",
  "reason": null,
  "branch": "chore/taw-core-1.78.0",
  "commit": "a1b2c3d",
  "taw_core": { "from": "1.76.1", "to": "1.78.0" },
  "tier1": ["bin/", ".claude/skills/"],
  "manifests": { "applied": ["require-dev.phpstan/phpstan"], "optional": ["reactiph", "chatbot"] },
  "proposals": [
    { "path": "AGENTS.md", "kind": "tier2", "diff_lines": 39, "summary": "taw-hub section replaced by the companion + taw-fleet", "loses": "" }
  ],
  "skills": { "preserved": [], "deleted": [], "warn": [], "clash": [] },
  "upgrading": [ { "version": "1.77.0", "outcome": "pass", "note": "hub:* stubs print the notice" } ],
  "verify": { "test": "pass", "phpstan": "pass", "build": "not-needed" },
  "notes": ""
}
```

`status` is one of:
- `updated`: committed, every check `pass`/`not-applicable`, every verify `pass`/`missing`/`not-needed`. Open `proposals` alone don't change this: they're the coordinator's to ask about;
- `needs-attention`: committed, but a check or verify failed, or a check is `needs-wordpress`/`needs-browser`;
- `up-to-date`: nothing to change, no commit;
- `skipped`: B1, with `reason`;
- `failed`: couldn't finish; `reason` says where (e.g. `core-held-back`).

**Never in batch mode:** ask the user, push, open a PR, start or stop a site, open a browser, touch another theme or site, apply a Tier 2 prose change, or hand-edit a manifest.

## Don't

- Don't touch, diff, or even read anything outside the two tiers above without asking first.
- Don't delete or overwrite a skill folder that has `owner: site` in its `SKILL.md` — and don't "fix" a `warn`-listed unmarked skill by deleting it; surface it and let the user decide.
- Don't run `git merge`/`git pull` against the whole working tree — this skill deliberately avoids that now.
- Don't auto-apply Tier 2 changes without showing the diff and getting confirmation.
- Don't full-file-overwrite `composer.json`/`package.json` (or any other structural manifest) even after approval — surgically edit only the framework-relevant lines; a whole-file replace silently deletes the client's own additive dependencies.
- Don't hand-roll Tier 1's clone/copy logic — always go through `php bin/taw sync --apply`, so an interactive run and the automated CI workflow never diverge in behavior.
- Don't run `composer update taw/core` as a silent side effect of this skill — it's a separate, explicitly confirmed action (Step 4; batch mode has the approval in its prompt).
- Don't commit the synced changes — leave that decision and action to the user (batch mode commits on its branch; see § "Batch mode").
- Don't assume a project without a git relationship to `taw-theme` is broken or needs special handling — that's not a precondition this skill has.
- Don't let a Tier 2 doc (`AGENTS.md`/`CLAUDE.md`/`README.md`) start describing a file or capability as "already set up" without that file itself being added to `update-manifest.json` in the same change — this shipped once for real (the block-testing harness: `AGENTS.md` documented `phpunit.xml`/`tests/bootstrap.php`/`tests/TestCase.php` as pre-existing while they were entirely outside the manifest's scope, so `update-theme` synced the prose but never the substance). Whenever new framework infrastructure is documented as pre-existing, treat adding it to the manifest as part of the same commit, not optional follow-up.

## Migrating an existing site-authored skill

If a client site already has its own skill in `.claude/skills/` (or `.agents/skills/`) that predates the `skills-dir` mechanism — e.g. the FSSPX parish site's `.claude/skills/publish-news/` — it will show up under the sync report's `warn` list until it's marked. One-time fix:

1. Open the skill's `SKILL.md`.
2. Add `owner: site` to the YAML frontmatter (anywhere in the block; convention is right after `name:`):
   ```yaml
   ---
   name: publish-news
   owner: site
   description: >
       ...
   ---
   ```
3. Commit it to the client repo.

From then on `sync` reports it under `preserve` and never touches it. No manifest change, no per-site config file — the marker lives with the skill. (Framework skills are marked `owner: taw` in the canonical repo, so they're on the other side of the same check.)

A skill named identically to a framework skill (e.g. a site `build-page`) is **not** protected by `owner: site` — canonical wins and it gets overwritten. Give site skills their own distinct names.

**First run after this mechanism ships:** every framework skill will show up under `overwrite` once (the canonical copies gained the `owner: taw` marker; the client's copies haven't got it yet) — that's expected, not drift you caused. And a framework skill that was *retired from canonical before* this shipped will land under `warn` rather than `delete`, because the client's stale copy has no `owner:` marker — check each `warn` entry once by hand and delete any that are genuinely obsolete framework skills. After that first reconcile, retirements delete cleanly on their own.
