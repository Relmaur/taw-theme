---
name: export-static
description: >
    Exports the site as a static HTML/CSS/JS bundle for edge hosting (Cloudflare Pages,
    Vercel, etc.) via `php bin/taw export:static`. Triggers on "export the site as static" /
    "build a static export" / "generate the SSG bundle" / "export-static". Dynamic behavior
    (forms, search) is NOT frozen into the export — it keeps hitting this WordPress install's
    REST/AJAX endpoints, which requires TAW_HEADLESS_ORIGINS to be configured once the export
    is served from a different domain than this WordPress install.
argument-hint: "[optional: --dir=<path>] [optional: --prod-url=<url>]"
---

## Overview

`export:static` (`TAW\CLI\ExportStaticCommand`, shipped in `taw/core`) fetches every published
page/post over HTTP against its own permalink, rewrites absolute site-URL references, and
writes `<dir>/<slug>/index.html` — plus the built Vite assets (`dist/`) and
`wp-content/uploads/` — so the output directory is a self-contained static bundle.

It does **not** freeze forms or search. Both stay dynamic: forms submit to
`admin-ajax.php?action=taw_form_*`, search hits `taw/v1/search-posts` over REST. Once the
static bundle is deployed to a different domain than this WordPress install, those requests
become cross-origin and need `TAW_HEADLESS_ORIGINS` configured in `wp-config.php` — see
`TAW\Core\Rest\Cors` in `taw/core`.

## Step 1 — Build assets first

The export copies `dist/`, not source. If it's stale or missing, run:

```bash
npm run build
```

## Step 2 — Run the export

```bash
php bin/taw export:static
```

Pass through any flags the user gave:
- `--dir=<path>` — output directory (default: `static-export/` in the theme root)
- `--prod-url=<url>` — rewrite absolute links to this URL instead of root-relative paths
  (root-relative is the default and works on any domain the bundle ends up deployed to)

## Step 3 — Verify the export directory was populated

```bash
ls -la <dir>            # expect per-page directories + index.html at the root + dist/
find <dir> -name index.html | wc -l   # should roughly match the published page/post count
```

If the command reported failed pages or a missing `dist/` warning, surface that to the user
plainly — don't declare the export complete if pages failed or assets are missing.

## Step 4 — Tell the user about the REST/AJAX origin, every time

This is the part of a static export that silently breaks if skipped: forms and search in the
exported HTML still point at **this WordPress install's own domain** — that's correct, they're
not supposed to become relative paths, since there's no backend at the static host to answer
them. Explicitly remind the user:

- Form `action` attributes and any search `fetch()` calls in the exported HTML must point
  **absolutely** at the WordPress origin's `/wp-admin/admin-ajax.php` and `/wp-json/taw/v1/...`
  — never relative paths, since those would resolve against the static host instead.
- If the bundle will be served from a different domain than this WordPress install, ask
  whether `TAW_HEADLESS_ORIGINS` is already set in `wp-config.php`. If not, that's a required
  follow-up before forms/search will work cross-origin (CORS blocks them otherwise) — offer to
  add it, but confirm the exact static domain(s) with the user first rather than guessing.

## Don't

- Don't run the export before `npm run build` if `dist/` looks stale — the bundle will ship
  outdated CSS/JS.
- Don't tell the user the export is "done" without checking Step 3 — a partial export (some
  pages failed) is a real failure mode, not a rounding error.
- Don't rewrite form/search endpoints to relative paths yourself, and don't let the user assume
  they became static — they are deliberately still live requests to WordPress.
- Don't set `TAW_HEADLESS_ORIGINS` to a guessed domain — always confirm the exact static
  hostname(s) with the user first (see `TAW\Core\Rest\Cors` docblock in `taw/core` for the
  constant format).
