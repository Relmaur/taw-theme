---
name: update-theme
description: >
    Pulls the latest base-theme scaffold from the canonical taw-theme repository into this
    site instance via a real git merge, without touching what the user has built. Anything
    the user hasn't modified merges in silently; anything upstream changed that the user has
    also customized surfaces as a conflict, resolved per a reviewed tiered policy; anything
    that only exists locally (the user's own Blocks/, templates, content) is left alone
    automatically by git's own merge semantics. Triggers on "update the theme" / "sync the
    theme" / "pull theme updates".
argument-hint: "[optional: --dry-run to preview without merging]"
---

## Overview

Every real client site is a divergent instance of the same `taw-theme` scaffold — same `.claude/skills/`, `bin/` CLI, framework docs, build config — plus that client's own `Blocks/`, page templates, and content layered on top. This skill syncs the shared scaffold with the canonical repo using a **real `git merge`**, not a hand-maintained file copy list, because git's merge algorithm already gives the correct safety property for free:

- A path the user never modified locally, that upstream changed → merges in cleanly, no conflict, no risk (there's nothing local to lose).
- A path that only exists locally and was never part of upstream (the user's own `Blocks/Contact`, `page-contact.php`, etc.) → git's merge never touches it at all, because merging only ever considers paths where at least one side changed relative to the common ancestor.
- A path both sides changed (the user customized something upstream also touched, e.g. `Blocks/Menu` or `functions.php`) → surfaces as a real merge conflict. This is the only category needing a judgment call, and it's resolved per the tiered policy below rather than guessed at blindly.

This is a deliberate change from an earlier, narrower design that only synced a hand-picked allowlist of paths via `git checkout <remote> -- <path>`. That version is too conservative — it can't safely pick up upstream improvements to files it didn't know to list (e.g. a fix to `Blocks/Button`). A full merge, with conflict resolution scoped by tier, covers the whole tree correctly while still protecting user work, because protection now comes from git's own diff-since-common-ancestor logic instead of a list someone has to remember to keep current.

## Conflict-resolution tiers

These tiers only matter for paths that actually **conflict** during the merge (both sides changed the same content) — for every other path, the merge algorithm already resolved things correctly with no input needed.

**Tier 1 — always resolve conflicts by taking upstream's side, no confirmation.** Pure agent/CLI tooling; the user is not expected to hand-edit these, so a conflict here almost certainly just means both sides touched the same line incidentally, and upstream's version is correct.

```
.claude/skills/
.agents/skills/
bin/
```

**Tier 2 — show the conflict, resolve only after explicit confirmation.** Nominally framework docs/config, but can legitimately accumulate client-specific additions (a new catalog entry in `AGENTS.md`, an added dependency in `package.json`). Never resolve silently.

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

**Everything else that conflicts — default to keeping the local (user's) side, flag for manual review.** This includes explicitly client-owned territory (`Blocks/`, `inc/options.php`, page templates, `functions.php`, `resources/scss/_fonts.scss`, `resources/fonts/`, `release-notes.md`) and anything genuinely unclassified. When in doubt, protect the user's version and surface it rather than guessing which side is "correct" — a false negative (missed upstream improvement, easy to reapply later) is far cheaper than a false positive (destroyed client customization).

## Step 1 — Identify the upstream remote

**The canonical repo is https://github.com/Relmaur/taw-theme.** This is the source of truth for the shared scaffold — every real client site is a divergent instance of it. (Note: this specific working copy's `origin` remote uses the URL `git@github.com:Relmaur/taw.git`, an older SSH-style alias that resolves to the exact same repo — confirmed via `git ls-remote`, both return identical refs. Don't be thrown by the different-looking remote name; it is the canonical repo.)

This is a **separate repo from `taw/core`** (https://github.com/Relmaur/taw-core, the framework package installed via composer at `vendor/taw/core/`) — don't conflate the two. `taw/core` updates via `composer update taw/core`; the theme scaffold updates via this skill. See `AGENTS.md` § "Source of Truth" for both.

1. If a remote named `upstream` exists, use it.
2. Otherwise, if this working copy's `origin` points at the canonical repo (as above — or confirm with the user if a different URL is configured and it's unclear whether it's a personal fork or the canonical repo itself), use `origin`.
3. Otherwise, stop and tell the user to add one:
   > No remote pointing at the canonical taw-theme repo was found. Add one, e.g.:
   > `git remote add upstream https://github.com/Relmaur/taw-theme.git`
   Don't add the remote yourself without asking.

## Step 2 — Preconditions

```bash
git status --short
```

**The working tree must be clean before starting a merge.** If there are uncommitted changes, stop and tell the user to commit or stash them first — don't stash on their behalf (see the project's standing git safety rules). A merge with a dirty tree either gets refused by git outright or produces a confusing result mixing uncommitted work with merge conflicts.

Determine the remote's default branch (almost always `main`; confirm with `git remote show <remote>` if unsure).

## Step 3 — Fetch and start the merge

```bash
git fetch <remote>
git merge --no-commit --no-ff <remote>/<branch>
```

`--no-commit` is essential — it stops git from finalizing the merge automatically even when everything merges cleanly, so there's always a review point before anything is committed. `--no-ff` keeps an honest merge commit (once the user commits) rather than silently fast-forwarding, which matters here since this branch has its own history the merge commit should visibly record.

If `git merge` fails outright (e.g. unrelated histories, or the merge can't even start) report the exact error and stop — don't attempt workarounds like `--allow-unrelated-histories` without asking, since that flag exists specifically to bypass a safety check.

## Step 4 — Resolve conflicts by tier

```bash
git status --short
```

Conflicted paths show as `UU` (or `AA`/`DD` for add/delete conflicts). For each:

- **Tier 1 path** → `git checkout --theirs -- <path> && git add <path>` — no confirmation needed.
- **Tier 2 path** → show the conflicting hunks (`git diff -- <path>`) or a summary for large files, ask the user how to resolve (take upstream / keep local / hand-merge), then `git add <path>` once resolved.
- **Everything else** → `git checkout --ours -- <path> && git add <path>` (keep the user's version), and list it explicitly in the final report as "upstream had a change here that was skipped to protect local customization — review manually if you want it."

Non-conflicted paths that the merge already changed (files upstream touched that the user never modified) need no action — they're already staged correctly by the merge itself. This is the majority of what a routine sync should do.

## Step 5 — Report, don't commit

Summarize:
- What merged in cleanly with no conflict (the routine, safe case)
- What conflicted and how each was resolved, grouped by tier
- Anything left for manual review (Tier 2 declines, "everything else" skips)

**Do not run `git commit`.** The merge is left in progress (`MERGE_HEAD` present) per the project's standing rule to never commit unless explicitly asked. Tell the user to review with `git diff --staged` and either:
- `git commit` to finalize the merge, or
- `git merge --abort` to cancel entirely and discard all of it if the result doesn't look right.

## Don't

- Don't start a merge against a dirty working tree.
- Don't resolve Tier 2 conflicts without showing the diff and getting confirmation first.
- Don't default an unclassified conflict to "take upstream" — default to keeping the local version and flagging it, never the reverse.
- Don't commit the merge — leave that decision to the user.
- Don't use `--allow-unrelated-histories` or other safety-bypassing flags without asking first.
- Don't auto-add an `upstream` remote or assume `origin` is canonical when a separate client fork is plausible — ask if unclear.
