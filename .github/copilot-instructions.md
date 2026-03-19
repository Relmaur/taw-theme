# Copilot Instructions

> Full architecture docs: see `AGENTS.md` in the repo root.

## Project: TAW Theme

A classic WordPress theme with a custom block system, Vite v7, Tailwind v4, Alpine.js, and a bespoke metabox framework.

## Core Package

Framework internals (`TAW\Core`, `TAW\Helpers`, `TAW\CLI`) live in the **`taw/core` composer package**, installed at `vendor/taw/core/src/`. The theme's own `inc/` only contains `options.php` and Metabox view templates. **Do not look for `TAW\Core` classes in `inc/`**, and do not edit anything inside `vendor/`.

To update the framework: `composer update taw/core`.

## Key Architecture

- **Block system:** `TAW\Core\Block\BaseBlock`, `Block`, `MetaBlock`, `BlockLoader`, `BlockRegistry` — in `vendor/taw/core/src/Core/Block/`
- **Data:** `TAW\Core\Metabox\Metabox`, `TAW\Core\OptionsPage\OptionsPage`
- **Theme:** `TAW\Core\Theme\Theme`, `TAW\Core\Theme\ThemeUpdater`
- **Navigation:** `TAW\Core\Menu\Menu`, `TAW\Core\Menu\MenuItem`
- **REST:** `TAW\Core\Rest\SearchEndpoints`
- **Forms:** `TAW\Core\Form\Form`, `TAW\Core\Form\SubmissionsHandler`
- **Mail:** `TAW\Core\Mail\Mailer`, `TAW\Core\Mail\MailTemplate`, `TAW\Core\Mail\MailTester`
- **Helpers:** `TAW\Helpers\Framework`, `TAW\Helpers\Image`, `TAW\Helpers\Svg`, `TAW\Helpers\Dump`, `TAW\Helpers\Editor`
- `utilities.php` and `performance.php` — in `vendor/taw/core/src/Support/`, autoloaded by composer
- Dev blocks: `Blocks/{Name}/{Name}.php` — folder name must match class name, namespace `TAW\Blocks\{Name}\{Name}`
- Theme PSR-4: `TAW\Blocks\` → `Blocks/` only (everything else comes from `taw/core`)
- Two types: **MetaBlock** (data-owning, uses metaboxes) and **Block** (presentational, receives props)
- Auto-discovery via `BlockLoader::loadAll()` — no manual registration
- Asset queueing: `BlockRegistry::queue()` before `get_header()`, then `BlockRegistry::render()` in body

## New Blocks

Scaffold with the CLI: `php bin/taw make:block Name --type=meta --with-style`, then `composer dump-autoload`.
Or manually create `Blocks/{Name}/{Name}.php` + `Blocks/{Name}/index.php` — no other changes needed.

## Options Page

`OptionsPage` (from `taw/core`) stores site-wide settings in `wp_options` with the same field config as Metabox.
- Configured in `inc/options.php`
- Retrieve: `OptionsPage::get('field_id')`, `OptionsPage::get_image_url('field_id', 'size')`

## Navigation Menus

Use `TAW\Core\Menu\Menu::get('location')` instead of `wp_nav_menu()` — returns a typed tree of `MenuItem` objects with full active-state and children support.

## Image Helper

`TAW\Helpers\Image::render($id, 'size', 'alt', ['above_fold' => true])` — generates performance-optimised `<img>` tags with correct `loading`, `fetchpriority`, `decoding`, `srcset`, and `sizes`.

## SVG Helper

`TAW\Helpers\Svg` — enables SVG uploads (call `Svg::register()` in theme setup). Renders SVGs as `<img>` (`Svg::render($id, 'alt', [...]`) or inline (`Svg::inline($id, [...])`).

## Forms

`TAW\Core\Form\Form` — configuration-driven frontend form. Handles CSRF, honeypot, validation, PRG redirect, and email delivery (plain-text or MJML templates).

```php
$form = new Form([
    'id' => 'contact', 'submit_label' => 'Send',
    'email' => [
        'to_self'   => ['subject' => 'New contact',      'template' => 'contact-self'],
        'to_client' => ['subject' => 'Got your message', 'template' => 'contact-client'],
    ],
    'fields' => [
        ['id' => 'name',  'type' => 'text',     'label' => 'Name',    'required' => true],
        ['id' => 'email', 'type' => 'email',    'label' => 'Email',   'required' => true],
        ['id' => 'msg',   'type' => 'textarea', 'label' => 'Message', 'required' => true],
    ],
]);
$form->render();
```

`TAW\Core\Form\SubmissionsHandler` — CPT submission storage + webhook. Instantiate in `functions.php`.

## Mail

`TAW\Core\Mail\Mailer` — fluent `wp_mail()` wrapper with template support.

```php
(new Mailer())->to($email)->subject($subject)->template('welcome')->setVariables(['name' => 'Jane'])->send();
```

Templates live in `mails/html/{name}.html` (production) or `mails/{name}.mjml` (dev). Use `{{variable}}` placeholders.

`TAW\Core\Mail\MailTester` — admin page under Tools → Test Emails.

## Debug

`dump($value, 'label')` / `dd($value)` — global helpers autoloaded from `utilities.php`. Only output when `WP_DEBUG` is `true` (renders panel in `wp_footer`).

## REST API

`GET taw/v1/search-posts` — registered automatically. Powers the `post_select` metabox field. Requires `edit_posts` capability.

## CSS / Asset Pipeline

- `resources/js/app.js` imports `../css/app.css` (Tailwind v4) and `../scss/app.scss` (custom SCSS) — neither is a standalone Vite entry
- `resources/scss/critical.scss` is a standalone Vite entry compiled and **inlined** in `<head>` — keep under ~14 KB, no `@font-face` inside it
- Main CSS loads asynchronously (non-render-blocking via `media="print"`) in production
- Self-hosted fonts live in `resources/fonts/`; `@font-face` goes in `resources/scss/_fonts.scss`, used via `@use 'fonts'` in `app.scss` only
- `vite_asset_url()` resolves font/asset paths correctly in both dev and prod (autoloaded from `utilities.php`)

## When generating code

- New blocks: extend `TAW\Core\Block\MetaBlock` or `TAW\Core\Block\Block`, follow the naming convention exactly
- Use `use TAW\Core\Block\MetaBlock;`, `use TAW\Core\Block\Block;`, `use TAW\Core\Metabox\Metabox;`, `use TAW\Core\Block\BlockRegistry;`, `use TAW\Core\OptionsPage\OptionsPage;`
- Templates: use `esc_html()`, `esc_url()`, `esc_attr()` for all output
- Metabox/OptionsPage field types: `text`, `textarea`, `wysiwyg`, `url`, `number`, `range`, `select`, `image`, `group`, `checkbox`, `color`, `repeater`, `post_select`
- Meta keys follow pattern `_taw_{field_id}`; option keys follow the same pattern
- Styles: Tailwind utilities in templates, custom CSS/SCSS in block's `style.css`/`style.scss`
- Never add block registrations to `functions.php`
- Never use `wp_nav_menu()` — use `Menu::get('location')` instead
- Never edit files inside `vendor/taw/core/` — changes will be lost on the next `composer update`
