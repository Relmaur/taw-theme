---
name: content-migrate
owner: taw
description: >
    Move site STATE — posts / CPT entries, `_taw_*` metabox values, `_taw_*` options, terms,
    referenced media, and (opt-in) authorship / users / comments / environment settings — between
    a remote environment and this install, using `taw/core`'s Content Interchange
    (`bin/taw content:export` / `content:import` / `content:diff` — portable JSON snapshots,
    mandatory dry-run, automatic rollback snapshot). Triggered by the user ("pull content from
    live", "push the FAQ edits to staging", "migrate this site to the new host"). `pull` refreshes
    this install from a remote snapshot; `push` exports ONLY the records the user names and applies
    them through the reviewed importer; `--migrate` moves whole-site state. Never a bidirectional
    auto-merge. Theme/plugin code and the DB binary internals are out of scope — migrate state,
    deploy code.
argument-hint: "pull [--migrate] | push <post slugs, option keys, taxonomy:slug> | migrate <target-url>"
---

## Overview

`taw/core` (≥ v1.26.0) owns the hard parts — see its **README § "Content Interchange"** and the
docs page **https://taw.mlizardo.com/content-interchange**. A snapshot is portable JSON: posts
(by slug, not ID), decoded `_taw_*` fields, `_taw_*` options, terms, referenced media (by
filename), and — only when asked — authorship, `users[]`, `comments[]`, and an environment-settings
option allowlist. `Importer::plan()` is a **mandatory zero-write dry-run**; `apply()` writes a
**maximal-scope rollback snapshot to `wp-content/uploads/taw-private/`** before touching anything,
applies in the order **users → terms → media → posts → comments → settings**, and writes field
meta through `Metabox::writeMeta()` (the exact admin-save sanitize path). Records match by natural
key, never numeric ID. A clean `content:export --migrate` → `content:import --yes` against the
**same** site reports **0 created / 0 updated / 0 deleted**.

This skill is the thin wrapper: connection details, which direction, this repo's guardrails, and
the confirmation gates. It is **`owner: taw`** — the canonical, generic version. A site with its
own source-of-truth policy (protected pages, host-specific WAF rules, a full-DB refresh path)
should author an `owner: site` skill with a **different name** that extends or replaces this one;
`update-theme` overwrites this file every sync.

| | `pull` (remote → here) | `push` (here → remote) | `migrate` (remote → here, full state) |
|---|---|---|---|
| Carries | all TAW content (snapshot) | **only** the slugs / keys named on the command line | content + users + settings + all media + drafts (`--migrate`) |
| Transport | REST `GET` (no SSH), or SSH for a scoped export | change-set applied via the admin screen or SSH | SSH (`--migrate` is not exposed over REST) |
| Risk | low — this install is disposable in dev | **writes the remote**; dry-run + rollback + this skill's confirm are all mandatory | high — moves users and settings; only for a deliberate environment stand-up |
| Confirm | broad confirm of the plan table | per-run confirm of the vetted file + the on-remote dry-run | explicit "yes, migrate whole state" + the dry-run |

### Source-of-truth policy (default — a site may override)

- **Remote wins** for editor content on a `pull` — posts, CPT entries, `_taw_*` options. `pull`
  overwrites local freely.
- **Code wins** for anything a TAW theme defines in `inc/` — nav menus especially.
  `content:export` never includes `nav_menu` / `nav_menu_item` (core excludes it). If this theme
  registers menus in code, rebuild them after a `pull` (see Step S3).
- **Out of scope entirely:** theme / plugin PHP, `wp-config.php`, `.env`, secrets, plugin
  activation state, the database's binary internals. `push` never carries users or settings
  (those are `pull` / `migrate` only, and only with an explicit flag).

---

## Step 0 — Preflight (all flows)

1. **Versions.** `php bin/taw content:export --help` must show `--migrate` (needs `taw/core`
   ≥ 1.26 — `composer show taw/core`). For a `pull`/`push` over REST, the **remote** must also
   be ≥ 1.26 — check the route exists:

   ```sh
   curl -s -o /dev/null -w '%{http_code}' -u "${REMOTE_APP_USER}:${REMOTE_APP_PASS}" \
     "${REMOTE_URL}/wp-json/taw/v1/content/export"
   ```

   `200` = ready · `404` = remote is on an older core (deploy the bump first, or use the SSH
   export in S1) · `401`/`403` = bad credentials or a WAF is blocking `/wp-json/taw/*` (see S1).

2. **`.sync/` workspace** — add `/.sync/` to `.gitignore` if it isn't there. `mkdir -p .sync/backups`.
   `content:export --output` and `content:diff --out` write here; nothing under `.sync/` is ever
   committed.

3. **Connection** — load `.sync/remote.env`. If it's missing, write this template and **stop**,
   asking the user to fill it (the app password: remote **Users → your admin user → Application
   Passwords**; SSH values from the host):

   ```sh
   # .sync/remote.env — gitignored.
   REMOTE_URL=https://example.com
   LOCAL_URL=http://example.local
   # REST transport (snapshot pull / push) — no SSH needed:
   REMOTE_APP_USER=your-admin-login
   REMOTE_APP_PASS=xxxx xxxx xxxx xxxx xxxx xxxx      # WP application password
   # SSH transport (--migrate export, large exports, applying a push over CLI):
   REMOTE_SSH=user@example.com
   REMOTE_PORT=22
   REMOTE_PATH=/path/to/site/public_html
   ```

   ```sh
   set -a; . .sync/remote.env; set +a
   RSH="ssh -p ${REMOTE_PORT} ${REMOTE_SSH}"
   remote() { $RSH "cd ${REMOTE_PATH} && $*"; }
   TS=$(date +%Y%m%d-%H%M%S)
   ```

4. **Wrong-way guard** — `php bin/taw wp option get siteurl` MUST equal `$LOCAL_URL`. Stop
   otherwise. (A `push` writes the remote; a `pull` / `migrate` overwrites *this* install — the
   direction of travel must be unambiguous.)

5. **State the plan in one line.** For `push` and `migrate`, get the explicit go-ahead before
   any Step P/M work.

---

## PULL — remote → here (content snapshot)

### S1. Get a snapshot from the remote

**REST (preferred — no SSH):**

```sh
curl -fsS -u "${REMOTE_APP_USER}:${REMOTE_APP_PASS}" \
  "${REMOTE_URL}/wp-json/taw/v1/content/export" -o ".sync/remote-${TS}.json"
```

Add `?include_drafts=1` only if the user explicitly wants drafts (excluded by default — that's
the fix for slug-less drafts duplicating on re-import).

If that `403`s, the host's WAF is likely blocking `/wp-json/taw/*` — allowlist
`taw/v1/content/export` in the host's WAF rules (same place `wp/v2/users/me` is usually
allowlisted), or fall back to SSH:

```sh
remote 'wp taw content:export --output=wp-content/uploads/taw-private/export.json'
rsync -az -e "$RSH" "${REMOTE_SSH}:${REMOTE_PATH}/wp-content/uploads/taw-private/export.json" ".sync/remote-${TS}.json"
remote 'wp eval "@unlink(WP_CONTENT_DIR.\"/uploads/taw-private/export.json\");"'
```

Sanity-check: valid JSON, `meta.source.url` == `$REMOTE_URL`, `posts` non-empty,
`meta.schema` is `1.0` or `1.1`.

### S2. Dry-run → review → apply

```sh
php bin/taw content:import ".sync/remote-${TS}.json"            # dry-run: field-level diff, writes nothing
```

Show the user the plan table. If any `registry_drift` warnings appear (a block or field the
snapshot expects that this install doesn't register), surface them — the import still runs but
those values may not render. It's a local refresh, so a broad confirm is fine — no per-record
gate. Then:

```sh
php bin/taw content:import ".sync/remote-${TS}.json" --yes --policy=update
```

The importer sideloads any referenced media from the remote URLs (with title / description / alt
/ caption) and writes the rollback snapshot first (path is in the report).

### S3. Reconcile code-owned content

```sh
# If this theme registers nav menus in code, rebuild them — the snapshot never carried them.
# The command name varies per site; common ones: `wp taw nav menus`, a `bin/taw` subcommand,
# or a WP-CLI eval of an inc/menus.php function. Skip if this site manages menus in wp-admin.
php bin/taw wp cache flush
php bin/taw wp rewrite flush --hard
```

### S4. Report

Created / updated / skipped counts · media sideloaded · rollback snapshot path · anything
reconciled in S3. **To undo:** `php bin/taw content:import <rollback-snapshot> --yes --with-settings`.

---

## MIGRATE — remote → here, whole-site state

Use when standing up this environment from another (a new host, a fresh staging box, a disaster
recovery) and you need users and settings too — not just content. `--migrate` is
`--with-users --with-settings --all-media --include-drafts`. It is **not** exposed over REST, so
this path is SSH-only.

```sh
remote 'wp taw content:export --migrate --output=wp-content/uploads/taw-private/migrate.json'
rsync -az -e "$RSH" "${REMOTE_SSH}:${REMOTE_PATH}/wp-content/uploads/taw-private/migrate.json" ".sync/migrate-${TS}.json"
remote 'wp eval "@unlink(WP_CONTENT_DIR.\"/uploads/taw-private/migrate.json\");"'

php bin/taw content:import ".sync/migrate-${TS}.json"                       # dry-run — read it in full
php bin/taw content:import ".sync/migrate-${TS}.json" --yes --with-settings # apply (settings need the flag on import too)
```

Notes:

- Add `--with-comments` to both the export and (implicitly, it's not import-gated) the import if
  the site has real discussion threads worth carrying.
- Add `--with-user-passwords` to the **export** only if editors must be able to log in on this
  environment with their existing passwords (portable phpass/bcrypt hashes). Omit it and new
  accounts get a random password — fine for a dev box.
- Roles are sanitised on import against the roles *this* install defines — a role the target
  doesn't have is never granted (it's reported as a warning).
- The rollback snapshot this writes is maximal-scope, so it can put users and settings back too.
- This is **not** a byte-for-byte database copy — plugin tables, non-`_taw_` option rows and the
  binary internals don't travel. If a site genuinely needs that, it's a separate SSH `wp db`
  concern; author an `owner: site` skill for it.

---

## PUSH — here → remote (named records only)

**There is no "push everything" path.** Targets are post slugs; options and terms come along
inside a post's snapshot, or are named explicitly by widening the export scope and vetting.

A plain scoped snapshot is **upsert-only** — `content:import` never deletes a record that isn't
in the file. So `push` doesn't need `content:diff`: export the named records, vet, import.

### P1. Export the named records

```sh
php bin/taw content:export --posts=<slug,slug,…> --output=".sync/push-${TS}.json"
```

The file carries those posts + every media file they reference + (currently) every term. This
install is the desired state.

### P2. Vet the snapshot

Open `.sync/push-${TS}.json`:

- **Remove `author`** from each post entry unless reassigning the remote post's author is
  intended — this install's user set may not match the remote's, and the importer will reassign
  when the incoming `{login,email}` resolves to a different remote user.
- Apply this site's own protected-content rules (a canonical install has none; a real site
  usually protects its seeded landing pages — check the site's `owner: site` policy or ask).
- Confirm no `nav_menu*` anywhere (export excludes it — just check).
- Trim any `terms[]` entry that would overwrite a remote term description you don't mean to touch.
- **Never** hand-add a `users[]` or settings key to a push file — production users and settings
  are out of scope for this direction.
- Summarize for the user: which posts, which fields look non-empty. **Explicit confirmation
  required** — "push" authorized *preparing* the file, not applying it.

### P3. Apply on the remote

**Preferred — the admin review screen, no SSH:** hand the user `.sync/push-${TS}.json` and tell
them: *Tools → TAW Data → Import → upload this file → review the field-level table → Apply.* The
importer writes its rollback snapshot to `uploads/taw-private/` first.

**Or over SSH:**

```sh
rsync -az -e "$RSH" ".sync/push-${TS}.json" "${REMOTE_SSH}:${REMOTE_PATH}/wp-content/uploads/taw-private/push.json"
remote 'wp taw content:import wp-content/uploads/taw-private/push.json'                       # dry-run on the remote — read the diff
remote 'wp taw content:import wp-content/uploads/taw-private/push.json --yes --policy=update'
remote 'wp cache flush'
remote 'wp eval "@unlink(WP_CONTENT_DIR.\"/uploads/taw-private/push.json\");"'
```

### P4. Verify + report

Re-fetch each target from the remote (`curl -u … "${REMOTE_URL}/wp-json/wp/v2/pages?slug=<slug>"`
or `remote 'wp post get …'`) and confirm it matches. Report: created / updated / skipped · new
remote IDs for created posts · media sideloaded · the rollback snapshot path on the remote ·
undo command (`wp taw content:import <that snapshot> --yes`).

---

## Agent-transform variant (bulk edits, restructuring)

When an agent edits content in the JSON rather than a human editing in wp-admin:

```sh
# export both sides in full
php bin/taw content:export --output=".sync/here-${TS}.json"
curl -fsS -u "${REMOTE_APP_USER}:${REMOTE_APP_PASS}" "${REMOTE_URL}/wp-json/taw/v1/content/export" -o ".sync/remote-${TS}.json"

# agent edits .sync/here-${TS}.json (bulk copy edits, field restructuring, …)

php bin/taw content:diff ".sync/remote-${TS}.json" ".sync/here-${TS}.json" --out=".sync/changes-${TS}.json"
```

The change-set is the form that carries **deletes and renames** (a plain snapshot is upsert-only)
and it now also diffs the `users` and `comments` sections. Vet `operations[]`:

- reject any stray `user` op unless the user explicitly asked for a user migration;
- apply this site's protected-content rules to `post` ops (same as P2);
- then apply as in P3 (dry-run → review → `--yes`).

---

## Don't

- **Don't** treat this as bidirectional — two explicit one-way runs, never a merge.
- **Don't** skip the `content:import` dry-run, or apply a `push` without the P2 confirmation.
- **Don't** pass `--with-users` / `--with-user-passwords` / `--with-settings` on a `push` — the
  remote's users and settings are never written from here.
- **Don't** run `wp db import` / `db reset` / `search-replace` on a `push`. `push` only ever
  applies a vetted file through `content:import`.
- **Don't** sync `wp-config.php`, `.env`, secrets, or plugin activation state — none of it is in
  the snapshot format, and it must not be added by hand.
- **Don't** commit anything under `.sync/` — gitignored on purpose.
- **Don't** edit this file for a site's own policy — it's `owner: taw` and `update-theme`
  overwrites it. Author an `owner: site` skill with a different name that calls into the same
  `bin/taw content:*` commands.
