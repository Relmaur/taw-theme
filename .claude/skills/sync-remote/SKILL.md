---
name: sync-remote
description: >
    Standard pull → compare → resolve → push git workflow for this repo, triggered explicitly
    by the user (e.g. "sync with remote, please" / "sync this repo" / "pull and push"). Handles
    uncommitted local changes by asking (never guessing), fetches and compares against origin,
    reconciles via a real merge (never rebase) with mandatory human resolution for genuine
    conflicts, optionally verifies (phpstan, if the project has it), and always asks for
    explicit confirmation before the final push — "sync" authorizes reconciliation, not the push.
argument-hint: "[optional: branch name, if not the current branch]"
---

## Overview

This is the "am I up to date with everyone else working across this multi-machine setup" workflow — built after real divergence happened between machines on `chcapital` (two independent commits both adding `CLAUDE-HANDOFF.md` to `.gitignore`, resolved with a clean merge earlier in this project's history). It automates the mechanical parts (fetch, compare, fast-forward or merge) while keeping the two genuinely risky decisions — what to do with uncommitted local work, and whether to push — as explicit questions, never silent choices.

**This does not replace the project's standing push-confirmation rule.** The user asking to "sync with remote" authorizes pulling and reconciling; it does not, by itself, authorize the push at the end. Always confirm the push separately (Step 5), same as any other git-push in this project.

## Step 1 — Handle uncommitted local changes

```bash
git status --porcelain
```

If clean, skip to Step 2.

If not clean, **ask the user explicitly** — never guess:
- **Commit now** — appropriate for finished work. Draft the message following this project's commit conventions (see `CLAUDE.md`/`AGENTS.md` § "Committing changes with git" if present, or the repo's own recent `git log` style), stage the relevant files (never a blanket `git add -A` without reviewing what it picks up), and commit.
- **Stash it** (`git stash push -u`) — appropriate for in-progress work not ready to commit. Restore it in Step 5 after the sync completes.
- **Abort** — let the user handle it manually outside this skill.

## Step 2 — Fetch and compare

```bash
git fetch origin
git log HEAD..origin/<branch> --oneline   # what remote has that local doesn't
git log origin/<branch>..HEAD --oneline   # what local has that remote doesn't
```

Report plainly which of these four states applies: **up to date** / **remote ahead only** / **local ahead only** / **diverged** (both non-empty).

## Step 3 — Reconcile

- **Up to date:** nothing to pull. Continue to Step 4.
- **Remote ahead only:** `git pull --ff-only` — a plain fast-forward, no merge commit, no risk.
- **Local ahead only:** nothing to pull; continue to Step 4 (push confirmation).
- **Diverged:** `git merge origin/<branch>` — **never `git rebase`** to reconcile here; rebase rewrites commits that may already be shared with a remote or another machine, which this project's git safety rules treat as a destructive operation requiring explicit user request, not something a sync skill does on its own initiative.
  - **Clean merge** (git resolves it automatically — e.g. non-overlapping files, or identical changes on both sides, exactly what happened with the `.gitignore` case this skill is modeled on): continue.
  - **Real conflicts:** stop immediately. List every conflicting file (`git diff --name-only --diff-filter=U`), show the conflict markers, and ask the user how to resolve each one. **Never pick `--ours`/`--theirs` or guess a resolution** — only continue once every conflict is resolved and staged by the user's explicit direction.

## Step 4 — Verify before pushing (if applicable)

If `composer.json` in this repo defines a `phpstan` script, run `composer run phpstan`. If it fails, stop and report the failure — don't push code that fails static analysis just because a sync was requested. If there's no such script (e.g. this is `taw-docs`, or a non-PHP context), skip this step silently — it's not every repo's job to have it.

## Step 5 — Push (confirmation required, every time)

Show what's about to be pushed:

```bash
git log origin/<branch>..HEAD --oneline
```

If empty, say so plainly and skip pushing — there's nothing to send. Otherwise, **ask explicitly**: "push these N commit(s) to origin/`<branch>`?" Only run `git push` after an explicit yes. This is the one step "sync with remote" does not pre-authorize on its own.

If Step 1 stashed changes, restore them now (`git stash pop`) — whether or not a push happened — so nothing is left sitting in the stash indefinitely.

## Step 6 — Report

Summarize: what was pulled (if anything), whether reconciliation needed conflict resolution and how it was resolved, whether verification ran and passed, what was pushed (or why nothing was), and the repo's final state relative to `origin`.

## Don't

- Don't guess how to handle uncommitted local changes — always ask (Step 1).
- Don't use `git rebase` to reconcile divergence — always `git merge`, preserving real history from every machine/session involved.
- Don't resolve genuine merge conflicts by picking a side without asking — only conflicts git's own merge algorithm resolves automatically (identical changes, non-overlapping files) go through without a question.
- Don't push without the Step 5 confirmation — "sync with remote" authorizes reconciliation, not the push itself.
- Don't leave a stash un-popped at the end of a run.
- Don't expect this skill to be present in `taw-core`/`taw-docs`/the vault — it ships via `taw-theme`'s Tier 1 scaffold, so it's only available in `taw-theme` itself and any client project that has run `update-theme`.
