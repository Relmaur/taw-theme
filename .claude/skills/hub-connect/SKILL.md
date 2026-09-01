---
name: hub-connect
owner: taw
description: >
    Connects this TAW site to a TAW Hub fleet — installs the standalone `taw-hub-companion`
    plugin (the signed `wp-json/taw-hub/v1/` receiver), writes `TAW_HUB_PUBLIC_KEY` into
    `wp-config.php`, and surfaces the site's own key for registration with the Hub. Asks for
    the Hub details it needs via AskUserQuestion; sets up nothing the user doesn't confirm.
    Triggers on "connect this site to the hub" / "add this site to the fleet" / "install
    taw-hub-companion" / "enroll with taw-hub" / "hub-connect". Reversible: deactivate the
    plugin or remove the constants and the site is unmanaged again.
argument-hint: "[optional: the Hub's base64 Ed25519 public key]"
---

## Overview

The [TAW Hub](https://github.com/Relmaur/taw-hub) is a separate control app that manages a
fleet of TAW sites — telemetry, framework syncs, allow-listed `bin/taw` runs. It talks to
each site through the **`taw-hub-companion`** plugin, which verifies every request against
the Hub's Ed25519 key ([wire protocol: taw-hub ADR-0003](https://github.com/Relmaur/taw-hub/blob/main/docs/ADR/0003-wire-protocol-and-signatures.md)).

That plugin is **not** bundled with the theme — only sites that join a fleet need it, and
it's a security boundary. This skill installs and configures it on demand.

**Security-sensitive by design** — it fetches and activates a plugin and writes a key into
`wp-config.php`. Every confirmation gate below is mandatory. Never guess a value to skip a
question; if the user doesn't have a Hub detail to hand, stop and let them get it.

Report a running checklist (✅ done / ⏭️ skipped / ⚠️ needs manual action) as you go.

## Step 1 — Confirm this is a TAW site and check current state

```bash
test -f bin/taw && php bin/taw --version   # a TAW theme with the CLI
php bin/taw wp config path                 # locates wp-config.php
php bin/taw wp config get TAW_HUB_PUBLIC_KEY 2>/dev/null   # already connected?
```

- No `bin/taw` → this isn't a TAW theme; stop.
- `TAW_HUB_PUBLIC_KEY` already returns a value, or `wp plugin is-active taw-hub-companion`
  is already true → the site is (partly) connected. Ask whether the user wants to
  **re-connect** (rotate to a new Hub / key) or **abort**. Don't silently overwrite.

## Step 2 — Check `taw/core` is new enough for `hub:install`

```bash
php bin/taw hub:install --help
```

- Command not found → `taw/core` predates `v1.20.2`. Run the `update-theme` skill (or
  `composer update taw/core` then re-check the committed `composer.lock`) first, then resume.

## Step 3 — Install the plugin

```bash
php bin/taw hub:install --activate
```

This git-clones `taw-hub-companion` into `wp-content/plugins/` and `wp plugin activate`s it.
On activation the plugin generates **this site's own** Ed25519 keypair (stored in
autoload-off options; the secret never leaves the site).

- If `--activate` fails (no `wp` on PATH, or a permissions issue) it prints the manual
  command — run `php bin/taw wp plugin activate taw-hub-companion` yourself, or hand it to
  the user, then continue.

Confirm it's active:

```bash
php bin/taw wp plugin is-active taw-hub-companion && echo active
```

## Step 4 — Get the Hub's details

The user gets these from **their TAW Hub instance** (its `php artisan security:keygen`
output, or its UI). Ask via **AskUserQuestion**:

1. **`TAW_HUB_PUBLIC_KEY`** — the Hub's base64 Ed25519 public key (44 chars, ends `=`).
   Required. If the user passed it as the skill argument, confirm it rather than re-ask.
2. **`TAW_HUB_KEY_ID`** — the Hub's key id. Options: `hub-local` (the Hub's default —
   recommended unless the operator changed it) / "other, I'll specify". Only write this
   constant if it's *not* `hub-local` (that's the plugin default).
3. **Source-IP allow-list (`TAW_HUB_ALLOWED_IPS`)** — "skip" (default) / "restrict to the
   Hub's IP(s)" → then ask for the comma-separated list.

Validate the public key before proceeding: `echo -n '<value>' | base64 -d | wc -c` must be
`32`. If it isn't, the value is wrong — stop and re-ask.

## Step 5 — Write `wp-config.php`

Show the user the **exact lines** you will add and **exactly where** (immediately above the
`/* That's all, stop editing! */` marker), and get explicit confirmation.

```php
define( 'TAW_HUB_PUBLIC_KEY', '<value>' );
// only if not the default:
define( 'TAW_HUB_KEY_ID', '<value>' );
define( 'TAW_HUB_ALLOWED_IPS', '<value>' );
```

Edit the file at the path from Step 1. If `wp-config.php` isn't writable or isn't found,
hand the user the block to paste and wait for them to confirm it's in.

Verify it took:

```bash
php bin/taw wp config get TAW_HUB_PUBLIC_KEY
```

## Step 6 — Verify the plugin is live (not inert)

An unsigned request to `/health` returns **501** when the plugin is unconfigured and
**401** once `TAW_HUB_PUBLIC_KEY` is set (the request has no signature, so it's rejected —
but the plugin is now doing its job):

```bash
SITE=$(php bin/taw wp option get siteurl)
curl -s -o /dev/null -w '%{http_code}\n' "$SITE/wp-json/taw-hub/v1/health"
```

- `401` → ✅ configured and live.
- `501` → the constant didn't take; recheck Step 5.
- `404` → the plugin isn't active; recheck Step 3.

## Step 7 — Register this site with the Hub

The Hub needs this site's public key to verify its signed responses. Print both values:

```bash
php bin/taw wp option get taw_hub_companion_public_key
php bin/taw wp option get taw_hub_companion_key_id
```

Tell the user to add the site to their Hub with those two values (the Hub's "register site"
/ `RegisterSite` flow — base URL + `key_id` + `companion_public_key`).

> When the Hub gains an enrolment endpoint (its Part 8), a `bin/taw hub:enroll` command will
> automate this step. Until then it's a manual copy into the Hub.

## Step 8 — Report

Final checklist: plugin installed/active, constants written (list which), `/health` returns
401, and the site key + key id the user must register on the Hub. Note anything left as
⚠️ manual.

## Disconnecting

To take the site out of a fleet: `php bin/taw wp plugin deactivate taw-hub-companion` (or
delete the plugin), and remove the `TAW_HUB_*` constants from `wp-config.php`. The site's
own keypair options can be left or deleted (`taw_hub_companion_*`).
