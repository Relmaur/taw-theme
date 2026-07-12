---
name: project-init
description: >
    Post-scaffold onboarding checklist for a brand-new TAW client project — verifies gh CLI
    auth, enables and smoke-tests the framework-sync.yml GitHub Actions PR permission, and walks
    through optional integrations (Cloudflare Turnstile, transactional email, CSS Studio, Visual
    Editor) via a short series of yes/no questions, only setting up what the user actually wants.
    Triggers on "init this project" / "onboard this project" / "set up this new client project" /
    "project-init". Assumes `composer create-project` + git init/push already happened (see
    AGENTS.md § "Starting a New Client Project") — this skill picks up from there.
argument-hint: "[optional: repo owner/name if it can't be inferred from the git remote]"
---

## Overview

`AGENTS.md` § "Starting a New Client Project" covers getting the code scaffolded and pushed. This skill covers everything after that which is easy to silently skip and only surfaces as a problem weeks later: the GitHub Actions permission `framework-sync.yml` needs to actually open PRs, and the handful of optional integrations (Turnstile, email, CSS Studio, Visual Editor) that don't have sensible defaults and genuinely need a human decision.

**This skill is security-sensitive by design** — it changes a GitHub repo permission and writes secrets into `wp-config.php`. Treat every confirmation gate below as mandatory, not optional. Never guess an answer to skip a question; if something is ambiguous, ask.

Run through the steps in order. Report a running checklist as you go (✅ done / ⏭️ skipped / ⚠️ needs manual action) rather than saving it all for the end — the user should be able to see exactly where things stand at any point if the session gets interrupted.

## Step 1 — Confirm this is a TAW project, past the scaffolding stage

- Check `functions.php` calls `Theme::bootstrapFullSite(` — if not, this project hasn't been scaffolded via `composer create-project taw/theme` yet, or predates the `taw/core` v1.16.63 bootstrap split. Stop and point to `AGENTS.md` § "Starting a New Client Project" first.
- Check `git remote get-url origin` resolves to a real GitHub repo (not the canonical `taw-theme`/`taw-core` repos themselves — this skill is for a *client* project). If there's no `origin` yet, or if HEAD hasn't been pushed, stop and get that done first — every later step needs a pushed remote to check against.

## Step 2 — `gh` CLI installed and authenticated

```bash
command -v gh
gh auth status
```

- **Not installed:** tell the user how to install it (`brew install gh` on macOS) and stop — this is a real install, not something to script around. Resume once they confirm it's installed.
- **Installed but not authenticated:** `gh auth login` requires an interactive OAuth flow — ask the user to run it themselves in their own terminal (don't attempt to drive it), then resume once they confirm.
- **Authenticated:** confirm which account/org, and note it — the account needs admin rights on the client repo for Step 3 to work.

## Step 3 — Enable and verify the Actions PR-creation permission

`.github/workflows/framework-sync.yml` (ships with the project from the first commit, per `AGENTS.md` § "Automated framework-drift detection") needs **Settings → Actions → General → "Allow GitHub Actions to create and approve pull requests"** enabled — off by default on every new GitHub repo.

Check the current value first:

```bash
gh api repos/<owner>/<repo>/actions/permissions/workflow
```

Look at `can_approve_pull_request_reviews` in the response.

- **Already `true`:** ✅ nothing to do, say so.
- **`false`:** explain what this setting does in one sentence (lets the repo's own Actions workflows open and approve pull requests — currently only used by `framework-sync.yml`'s weekly update PR) and **ask for explicit confirmation before changing it** — this is a real repo permission change, not a code edit. If confirmed:

  ```bash
  gh api --method PUT repos/<owner>/<repo>/actions/permissions/workflow \
    -f default_workflow_permissions=write \
    -F can_approve_pull_request_reviews=true
  ```

  Re-run the GET to confirm the change actually took effect before moving on — don't assume the PATCH succeeded just because it didn't error.

## Step 4 — Smoke-test the workflow for real

Don't wait a week to find out whether it actually works. Trigger a manual run now:

```bash
gh workflow run framework-sync.yml
```

Poll for the result rather than declaring success immediately:

```bash
gh run list --workflow=framework-sync.yml --limit=1
gh run watch <run-id>   # once the run ID from the list above is known
```

Report the outcome honestly:
- **Completed, no PR opened:** ✅ expected on a fresh project with nothing to sync yet — say so plainly, this is success, not a no-op that needs investigating.
- **Completed, PR opened:** show the user the PR — a brand-new project shouldn't usually have drift, so look at *why* one opened (likely `taw/core` moved since the scaffold was cut) before treating it as routine.
- **Failed:** read the run's logs (`gh run view <run-id> --log-failed`) before reporting anything — don't just say "it failed," say what failed and why. A `can_approve_pull_request_reviews` failure here means Step 3 didn't actually take effect; re-check it.

## Step 5 — Optional integrations (ask, don't assume)

Ask about each of these as a short yes/no question (batch them if using a multi-question tool, or ask in sequence) — **do not enable any of them without an explicit yes.** None of these have a "safe default" the framework can pick on its own; they're genuine per-project decisions.

**Cloudflare Turnstile (bot protection on forms)** — see `AGENTS.md` § "Form security" for the full reference.
- If yes: walk the user through getting a site key + secret key from the [Cloudflare Turnstile dashboard](https://dash.cloudflare.com/?to=/:account/turnstile), then add them to `wp-config.php`:
  ```php
  define('TAW_TURNSTILE_SITE_KEY', '...');
  define('TAW_TURNSTILE_SECRET_KEY', '...');
  ```
  **Never** as an OptionsPage field — that's REST-readable by anyone with `edit_posts`. If the project has forms registered already, ask which ones should get `'turnstile' => true`; if no forms exist yet, just confirm the keys are in place and note that any form built later can opt in with that one config key.
- If no/later: skip, note it as available later via the same steps.

**Transactional email delivery** — ask whether the project needs `Mailer`/MJML templates configured now, or whether default `wp_mail()` is fine for now. If Emailit or another provider is wanted, point to `taw-core`'s README § Mail Configuration rather than improvising config here.

**CSS Studio** (live visual CSS editing in dev) — ask whether to enable it now (WP Admin → TAW Settings → Developer Tools → Enable CSS Studio). Only useful once `npm run dev` is part of the regular workflow; fine to defer.

**Visual Editor** (`?taw_visual_edit=1` inline frontend editing) — ask whether this project wants it. If yes, remind that `VisualEditor::enable()` must be called in `inc/customizations.php` *before* `Theme::boot()` runs — which `bootstrapFullSite()` already guarantees the ordering for, so it's just adding the one call.

## Step 6 — Final report

Summarize the full checklist: what's confirmed working (gh auth, Actions permission, smoke-tested workflow), what was enabled (any of the optional integrations said yes), and what's explicitly deferred (said no/later — list these so nothing quietly falls through). Don't imply something is done if it was deferred.

## Don't

- Don't attempt to drive `gh auth login`'s OAuth flow yourself — it's interactive by nature, hand it to the user.
- Don't flip the Actions PR-creation permission without explicit confirmation — it's a real security-relevant repo setting, not a code change.
- Don't declare the workflow "working" without actually triggering and watching a real run — a workflow that merely exists in the repo hasn't been verified.
- Don't put Turnstile (or any other) secret key in an OptionsPage field or anywhere REST-readable — `wp-config.php` constants only.
- Don't enable any Step 5 integration on an assumed yes — every one of them needs an explicit answer from the user.
- Don't re-run Step 1's scaffolding steps if the project already exists — this skill starts *after* scaffolding, not instead of it.
