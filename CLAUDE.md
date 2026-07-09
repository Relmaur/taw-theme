# CLAUDE.md — Claude Code Instructions

> Full architecture docs + code examples: **`AGENTS.md`** in this repo.
> **`taw/core` framework reference (source of truth):** https://github.com/Relmaur/taw-core#readme — fetch when you need authoritative detail on any framework API. When this file and the `taw/core` README disagree, `taw/core` wins.
> Full online documentation: https://emelambda.documentationai.com/

## Project

TAW Theme — WordPress theme with component-based blocks, Vite, Tailwind v4, Alpine.js, and a bespoke metabox framework from the `taw/core` composer package.

## Commands

```bash
npm run dev              # Vite dev server (port 5173, HMR)
npm run build            # Production build → public/build/
composer install         # PHP deps (includes taw/core package)
composer update taw/core # Update the core framework package
composer dump-autoload   # Rebuild classmap after new block classes
php bin/taw make:block Name --type=meta --with-style         # Scaffold a new block
php bin/taw make:block Name --type=meta --group=sections     # Scaffold inside a subgroup (Blocks/sections/Name/)
php bin/taw export:block Name                                # Export block as ZIP
php bin/taw import:block path/to/Block.zip                   # Import block from ZIP
```

## Core Architecture

Framework internals live in **`vendor/taw/core/src/`** (namespaces `TAW\Core`, `TAW\Helpers`, `TAW\Support`). Do **not** look for them in `inc/` — that folder only holds `options.php` and Metabox view templates. See AGENTS.md for the full class/path map.

The theme's `composer.json` PSR-4 maps only `TAW\\Blocks\\` → `Blocks/`. Everything else comes from `taw/core`.

Two block types:
- **MetaBlock** — owns metaboxes, fetches post_meta, rendered via `BlockRegistry::render('id')`
- **Block** — presentational, receives props, rendered directly: `(new Button())->render([...])`

**`boot()` method:** Override `static boot(): void` on a MetaBlock for early-request setup (e.g. registering forms). Called during block discovery at `after_setup_theme`. Wrap translation calls in `add_action('init', ...)` inside it.

**Block Variations:** Override `static::variations()` to return an array of variation strings (default `['']`). Access the active variation with `$this->getVariation()`.

Auto-discovery: `BlockLoader::loadAll()` scans `Blocks/*/` — no manual registration needed.

Asset loading: `BlockRegistry::queue('hero', 'stats')` BEFORE `get_header()` → assets land in `<head>`. Fallback prints inline if forgotten.

**Visual Editor:** Enabled automatically by `Theme::boot()`. **Edit Visually** button in admin bar; `?taw_visual_edit=1` activates the editing shell.

## Options Page

```php
OptionsPage::get('company_phone');
OptionsPage::get_image_url('logo', 'large');
```

Same field config format as Metabox, stores to `wp_options`. Configured in `inc/options.php`. See AGENTS.md for full config.

## Navigation Menus

`Menu::get('primary')` — typed tree wrapper for WP nav menus. Use instead of `wp_nav_menu()`. See AGENTS.md for `MenuItem` API and usage example.

## Helpers

- `TAW\Helpers\Image::render($id, 'large', ['above_fold' => true])` — performance-optimised `<img>` (loading, fetchpriority, srcset)
- `TAW\Helpers\Image::preloadTag($id, 'full')` — `<link rel="preload">`
- `TAW\Helpers\Svg::register()` / `::render($id, 'Alt')` / `::inline($id)` — SVG support
- `TAW\Helpers\Dump` — global `dump()` / `dd()`; renders in `wp_footer` only when `WP_DEBUG` is true

## Forms

**Register in `boot()`, never in templates** — the AJAX handler won't exist on `admin-ajax.php` otherwise:

```php
use TAW\Core\Form\Form;

public static function boot(): void
{
    add_action('init', static function () {
        Form::register([
            'id'     => 'contact',
            'fields' => [
                ['id' => 'name',    'label' => 'Name',    'type' => 'text',     'required' => true],
                ['id' => 'email',   'label' => 'Email',   'type' => 'email',    'required' => true],
                ['id' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
            ],
        ]);
    });
}

// In the block's index.php template:
Form::display('contact');
```

Field types: `text`, `email`, `tel`, `url`, `textarea`, `select`, `checkbox`, `date`. Fields support `required`, `placeholder`, `width` (12-column grid), `conditions`. Also supports multi-step forms (`steps` key), AND/OR conditional logic, email delivery (`email` key), and structural fields (`heading`, `divider`, `html`). See AGENTS.md or taw/core README for full config.

`TAW\Core\Form\SubmissionsHandler` — auto-wired by `Theme::boot()`, no manual instantiation needed.

## Mail

```php
(new Mailer())->to($email)->subject($subject)->template('name')->setVariables($vars)->send();
```

Templates in `mails/html/{name}.html` (or `mails/{name}.mjml` compiled at runtime in dev). Use `{{variable_name}}` placeholders. `MailTester` adds Tools → Test Emails admin page. See AGENTS.md for full API.

## REST API

`GET taw/v1/search-posts` — auto-registered by `Theme::boot()`. Powers the `post_select` metabox field. Requires `edit_posts`.

## CSS / Asset Pipeline

- `resources/js/app.js` imports `../css/app.css` (Tailwind v4) and `../scss/app.scss` (custom SCSS)
- `resources/scss/critical.scss` — standalone Vite entry, inlined in `<head>` — keep under ~14 KB, **no `@font-face`**
- Self-hosted fonts: WOFF2 in `resources/fonts/`, `@font-face` in `resources/scss/_fonts.scss`, `@use 'fonts'` in `app.scss` only
- Font preloads: `ViteLoader::assetUrl('resources/fonts/Name.woff2')` — returns dev-server URL in dev, hashed build URL in prod
- **Vite helpers are on `ViteLoader`**: use `ViteLoader::isDevServerRunning()` (not `vite_is_dev()`) and `ViteLoader::assetUrl()` (not `vite_asset_url()`)

## Key Conventions

- Folder name === class name === `$id` property
- Meta keys: `_taw_{field_id}`, option keys: `_taw_{field_id}`
- Block assets: `style.css` (or `.scss`) and `script.js` — auto-enqueued
- Templates: `index.php` receives `extract()`-ed variables from `getData()`
- PSR-4 (theme): `TAW\Blocks\` → `Blocks/` only — all other `TAW\` classes come from `taw/core`

## Metabox Field Types

`text`, `textarea`, `wysiwyg`, `url`, `number`, `range`, `select`, `image`, `files`, `group`, `checkbox`, `color`, `repeater`, `post_select`, `datepicker`

→ Full field options, conditional logic, repeater nesting, and tabs: **[taw/core README — Metabox System](https://github.com/Relmaur/taw-core#metabox-system)**

## Metabox Order

`MetaboxOrder::lockFromTemplate()` (called once in `functions.php` after `Theme::boot()`) locks each page's metabox order to match its template's `BlockRegistry::render()` call sequence and disables drag-and-drop reordering, so the edit screen never drifts from what actually renders. Use `MetaboxOrder::lock('page', ['id1', 'id2'])` for an explicit order instead. See AGENTS.md or taw/core README for template-resolution details.

## When Creating New Blocks

1. **CLI (preferred):** `php bin/taw make:block Name --type=meta --with-style`, then `composer dump-autoload`
2. **Manual:** Create `Blocks/{Name}/{Name}.php` and `Blocks/{Name}/index.php` — auto-discovered, no `functions.php` changes

## CSS Studio (Development)

- **Toggle:** WP Admin → TAW Settings → Developer Tools → Enable CSS Studio
- **Active only when:** Vite dev server is running (`npm run dev`) AND the toggle is on
- **Start a session:** `/studio`

## Don't

- Don't manually register blocks in functions.php
- Don't call wp_enqueue_style/script for block assets directly
- Don't mismatch folder/class names (breaks auto-discovery)
- Don't forget `queue()` before `get_header()` in templates
- Don't add `@font-face` to `critical.scss` — inlined CSS can't resolve relative asset paths
- Don't add `resources/css/app.css` as a Vite entry — it's imported by `app.js`
- Don't use `wp_nav_menu()` — use `Menu::get('location')` for full markup control
- Don't look for `TAW\Core` or `TAW\Helpers` classes in `inc/` — they live in `vendor/taw/core/src/`
- Don't edit files inside `vendor/` — update the `taw/core` package in its own repo and bump the version
- Don't call `vite_is_dev()` or `vite_asset_url()` — use `ViteLoader::isDevServerRunning()` and `ViteLoader::assetUrl()`
- Don't use `new Form([...]) + $form->render()` — use `Form::register()` in `boot()` and `Form::display('id')` in templates
- Don't manually instantiate `SubmissionsHandler` in `functions.php` — it's auto-wired by `Theme::boot()`
- Don't register Forms inside templates — the AJAX handler won't exist when `admin-ajax.php` processes the submission
