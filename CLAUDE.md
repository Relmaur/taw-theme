# CLAUDE.md — Claude Code Instructions

> Full architecture docs + code examples: **`AGENTS.md`** in this repo (start with its `## Codex — Dense Reference` section at the top).
> **This theme's canonical scaffold (source of truth for base-theme sync):** https://github.com/Relmaur/taw-theme — synced via the `update-theme` skill.
> **`taw/core` framework reference (source of truth for framework APIs):** https://github.com/Relmaur/taw-core#readme — fetch when you need authoritative detail on any framework API. When this file and the `taw/core` README disagree, `taw/core` wins. Separate repo/update path from `taw-theme` — see `composer update taw/core`.
> **Live doc lookup:** prefer the `mcp__taw-docs__search_documentation` MCP tool (if available) over fetching docs by hand — it's a hybrid semantic+keyword search over the current indexed docs.
> Full online documentation: https://taw.mlizardo.com/
> **External WordPress skill references (use, don't vendor):** for general WP capabilities TAW doesn't already own an abstraction for, consult specific skills from https://github.com/WordPress/agent-skills — `wp-phpstan`, `wp-performance`, `wp-wpcli-and-ops`, `wp-playground`. Do **not** pull in `wp-block-development` or `wp-block-themes` — those teach native Gutenberg blocks and `theme.json`, which TAW replaces with its own MetaBlock/Block system and Vite pipeline.

## Project

TAW Theme — WordPress theme with component-based blocks, Vite, Tailwind v4, Alpine.js, and a bespoke metabox framework from the `taw/core` composer package.

## Commands

Full list: AGENTS.md § "Commands". The ones used almost every session:

```bash
npm run dev              # Vite dev server (port 5173, HMR)
composer run phpstan     # Static analysis (Blocks/, inc/) — also runs in CI
composer run test        # Block getData() unit tests — required for every block, AGENTS.md § "Testing Blocks"
php bin/taw inspect --json  # Live registry dump: blocks, fields, forms, taw/core version
```

`fields:get`/`fields:set` usage (including `--file`/`--dry-run`) and the content-writing safety model: AGENTS.md § "`fields:get` / `fields:set`" and § "Content-writing safety model".

## Core Architecture

See AGENTS.md § "Quick Orientation" and § "The `functions.php` Bootstrap" for the full class/path map and the `functions.php`-is-framework-owned rule. Site-specific setup always goes in `inc/options.php`, `inc/performance.php`, `inc/customizations.php` — never `functions.php`.

Two block types:
- **MetaBlock** — owns metaboxes, fetches post_meta, rendered via `BlockRegistry::render('id')`
- **Block** — presentational, receives props, rendered directly: `(new Button())->render([...])`

**`boot()` method:** Override `static boot(): void` on a MetaBlock for early-request setup (e.g. registering forms). Called during block discovery at `after_setup_theme`. Wrap translation calls in `add_action('init', ...)` inside it.

**Block Variations:** Override `static::variations()` to return an array of variation strings (default `['']`). Access the active variation with `$this->getVariation()`.

Asset loading: `BlockRegistry::queue('hero', 'stats')` BEFORE `get_header()` → assets land in `<head>`. Fallback prints inline if forgotten.

**Visual Editor:** Opt-in — call `TAW\Core\Editor\VisualEditor::enable()` in `inc/customizations.php` before `Theme::boot()`. Once enabled: **Edit Visually** button in admin bar; `?taw_visual_edit=1` activates the editing shell.

## Options Page / Navigation / Helpers / Mail / REST / CSS Pipeline

Full API for these: AGENTS.md §§ "Options Page", "Navigation Menu System", "Image Helper", "SVG Helper", "Debug Helper", "Mail System", "REST API", "Vite Integration". Quick reference:

```php
OptionsPage::get('company_phone');
Menu::get('primary');                 // typed nav tree — never wp_nav_menu()
Image::render($id, 'large', ['above_fold' => true]);
(new Mailer())->to($email)->subject($subject)->template('name')->setVariables($vars)->send();
```

**Vite helpers are on `ViteLoader`**: use `ViteLoader::isDevServerRunning()` / `ViteLoader::assetUrl()` — never the removed `vite_is_dev()` / `vite_asset_url()`.

## Forms

**Register in `boot()`, never in templates** — the AJAX handler won't exist on `admin-ajax.php` otherwise. Full pattern, code sample, field-type list, and security details (CSRF/honeypot/rate-limit/Turnstile): AGENTS.md § "Form System" and § "Form security".

Quick reference: `Form::register([...])` in `boot()`, `Form::display('contact')` in the template.

## Key Conventions

- Folder name === class name === `$id` property
- Meta keys: `_taw_{field_id}`, option keys: `_taw_{field_id}`
- Templates: `index.php` receives `extract()`-ed variables from `getData()`
- PSR-4: AGENTS.md § "PSR-4 Autoloading" — theme only maps `TAW\Blocks\` → `Blocks/`; everything else is `taw/core`

## Metabox Field Types

`text`, `textarea`, `wysiwyg`, `url`, `number`, `range`, `select`, `image`, `files`, `group`, `checkbox`, `color`, `repeater`, `post_select`, `datepicker` — full options/conditional logic: AGENTS.md § "The Metabox Framework" (or taw/core README).

## Metabox Order

`MetaboxOrder::lockFromTemplate()` runs automatically. See AGENTS.md § "Locking Metabox Order" for the explicit-order override and template-resolution details.

## When Creating New Blocks

1. **CLI (preferred):** `php bin/taw make:block Name --type=meta --with-style`, then `composer dump-autoload`
2. **Manual:** Create `Blocks/{Name}/{Name}.php` and `Blocks/{Name}/index.php` — auto-discovered, no `functions.php` changes

## Static Analysis

`composer run phpstan` — level 5, `Blocks/` + `inc/` only, runs in CI. See AGENTS.md § "Static Analysis" for the ignoreErrors/baseline policy.

## WP-CLI — live site data access

`bin/taw` is for the *framework* (blocks/fields/forms); WordPress's own `wp` CLI is for live *content* — but prefer `php bin/taw wp <args>` over a bare `wp` command, since it auto-resolves `--path` and (under Local by Flywheel) the per-site MySQL socket that otherwise fails a bare `wp` command with a DB connection error. Full walkthrough: AGENTS.md § "WP-CLI — live site data access".

## CSS Studio (Development)

- **Toggle:** WP Admin → TAW Settings → Developer Tools → Enable CSS Studio
- **Active only when:** Vite dev server is running (`npm run dev`) AND the toggle is on
- **Start a session:** `/studio`

## Don't

Full list: AGENTS.md § "Do NOT". Additional Claude-Code-session-specific reminders not in that list:

- Don't call `vite_is_dev()` or `vite_asset_url()` — use `ViteLoader::isDevServerRunning()` and `ViteLoader::assetUrl()`
- Don't use `new Form([...]) + $form->render()` — use `Form::register()` in `boot()` and `Form::display('id')` in templates
- Don't manually instantiate `SubmissionsHandler` in `functions.php` — it's auto-wired by `Theme::boot()`
- Don't register Forms inside templates — the AJAX handler won't exist when `admin-ajax.php` processes the submission
- Don't wire up `ThemeUpdater` on a client site with customizations — it does full theme-zip replacement, which would wipe `Blocks/` and every `inc/` file. Use the `update-theme` skill instead
