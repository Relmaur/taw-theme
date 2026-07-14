# TAW Theme

**A modern WordPress theme framework that makes building custom pages feel like assembling components — not fighting WordPress.**

TAW (Tailwind + Alpine + WordPress) gives you a clean, component-based block architecture on top of classic WordPress. Every section of a page — hero, stats, testimonials — is a self-contained block that owns its data, markup, styles, and scripts. Only the assets a page actually uses get loaded.

No Gutenberg blocks. No ACF dependency. No bloat. Just PHP classes, templates, and a convention that works.

The framework internals (block system, metabox engine, Vite bridge) ship as the **[`taw/core`](https://github.com/Relmaur/taw-core) composer package** — versioned independently so you can update the framework across all your TAW sites with a single `composer update taw/core`.

This theme itself is a scaffold on top of that framework: every real site is a divergent instance of **[`taw-theme`](https://github.com/Relmaur/taw-theme)**, the canonical repo. Sync the shared scaffold (agent skills, CLI, framework docs, build config) — without touching anything you've built — with the `update-theme` skill.

> **Framework API reference:** The [`taw/core` README](https://github.com/Relmaur/taw-core#readme) is the authoritative source for all framework internals — field types, Metabox config options, ViteLoader API, Visual Editor, and more. When this theme README and the `taw/core` README disagree, `taw/core` wins. For live lookups, the `mcp__taw-docs__search_documentation` MCP tool (when available) searches the current indexed docs directly.

---

## Why TAW?

**Zero-config blocks.** Create a folder, drop in a class and a template — it's live. No registration, no `functions.php` edits, no build step required for new blocks.

**CLI scaffolding.** `php bin/taw make:block MyBlock --type=meta --with-style` creates the folder, class, template, and stylesheet in one command. Export and import blocks between projects as portable ZIPs.

**Scoped asset loading.** Each block can ship its own CSS and JS. Assets are only enqueued on pages that use that block. Your homepage doesn't load your blog's scripts.

**A real data layer.** MetaBlocks own their data through a bespoke metabox framework. No plugin dependencies for custom fields — field registration, rendering, retrieval, validation, and sanitization are all built in.

**Rich field types.** `text`, `textarea`, `wysiwyg`, `image`, `url`, `number`, `range`, `select`, `checkbox`, `color`, `group`, `repeater`, `post_select`, `datepicker` — with conditional logic, tabbed layouts, and responsive grid placement.

**Built-in form system.** `Form` handles CSRF, honeypot spam protection, field validation, AJAX submission (no page reload), and email delivery. Register it in your block's `boot()` method, display it in a template — no plugin required.

**Transactional email.** `Mailer` wraps `wp_mail()` with a fluent API and MJML/HTML template support. Write templates once in MJML, compile to HTML, deploy. Includes a `MailTester` admin page for testing templates without real submissions.

**SVG support.** `Svg::register()` enables sanitized SVG uploads in WordPress. Render as `<img>` or inline — both ways provided.

**Theme-level options.** `OptionsPage` brings the same config-driven field experience to site-wide settings stored in `wp_options` — tabbed UI, validation, and a clean retrieval API included.

**Modern frontend, classic WordPress.** Tailwind v4 for utility CSS, Alpine.js for interactivity, Swup for SPA-style page transitions, and Vite for instant HMR — all wired into WordPress through a lightweight bridge. No React, no REST API overhead.

**AI-native DX.** Ships with `AGENTS.md`, `CLAUDE.md`, and Copilot/Windsurf instructions so any AI coding assistant understands the architecture out of the box.

**Visual Editor** — a live content page editor for authenticated users. Opt-in: call `TAW\Core\Editor\VisualEditor::enable()` in `inc/customizations.php`. Once enabled, an **Edit Visually** button appears in the admin bar; appending `?taw_visual_edit=1` to any URL activates the editing shell.

---

## Quick Start

```bash

# Move to themes directory of your WordPress installation
cd wp-content/themes/

# This command will create the starter theme with the correct structure and dependencies. Replace <theme_name> with your desired theme folder name.
composer create-project taw/theme <theme_name> --repository='{"type":"vcs","url":"https://github.com/Relmaur/taw-theme"}'

cd <theme_name>
git init && git add -A && git commit -m "Initial commit"
git remote add origin <your-client-repo-url>
git push -u origin main

composer install       # PHP deps — pulls taw/core framework package
npm install            # Frontend dependencies
npm run dev            # Vite dev server with HMR
```

Start with a clean, single-commit history — no `--keep-vcs`, no shared ancestry with `taw-theme` required. The `update-theme` AI skill syncs future scaffold updates via a direct file copy of a small, precisely-delimited set of framework-owned paths (`functions.php`, `.claude/skills/`, `bin/`, CI config — see `AGENTS.md` § "The `functions.php` Bootstrap"), not a git merge, so it works regardless of this project's own history.

**One manual step on the new repo:** enable **Settings → Actions → General → "Allow GitHub Actions to create and approve pull requests"** (off by default on every new GitHub repo). This project ships with `.github/workflows/framework-sync.yml` from the very first commit — a weekly check that bumps `taw/core`, syncs framework-owned scaffold files, and opens a PR if anything changed — but its PR-opening step silently fails without that setting. See `AGENTS.md` § "Automated framework-drift detection".

Activate the theme in WordPress admin. You're building.

---

## Create a Block in 10 Seconds

Every block is a folder inside `Blocks/`. The folder name **must** match the class name — that's the only rule.

### Via CLI (recommended)

```bash
php bin/taw make:block Hero --type=meta --with-style # Creates a MetaBlock

php bin/taw make:block Button --type=ui --with-style --with-script # Creates a UI Block

# Create a block inside a subgroup (optional) — great for organization and namespacing:

php bin/taw make:block Hero --group=sections # Blocks/sections/Hero/

php bin/taw make:block Badge --group=ui/cards # Blocks/ui/cards/Badge/

# For more help with the CLI tool:
php bin/taw make:block --help

composer dump-autoload
```

### Manually

A block is just a folder: a class extending `MetaBlock` (metaboxes + `getData()`) or `Block` (defaults + props), plus an `index.php` template that receives the returned array via `extract()`. No registration step — `BlockLoader::loadAll()` auto-discovers it, its metabox appears in the editor, and its assets load only where it's queued/rendered.

→ Full step-by-step class + template walkthrough: **AGENTS.md § "Creating a New MetaBlock"** and **§ "Creating a New UI Block"**.

---

## Two Types of Blocks

|                  | MetaBlock                         | Block                           |
| ---------------- | --------------------------------- | ------------------------------- |
| **Purpose**      | Page sections that own their data | Reusable UI components          |
| **Data source**  | Metaboxes → `post_meta`           | Props passed at render time     |
| **Rendered via** | `BlockRegistry::render('id')`     | `(new Button())->render([...])` |
| **Examples**     | Hero, Stats, Testimonials, CTA    | Button, Card, Badge             |

### UI Block (Block)

UI Blocks extend `TAW\Core\Block\Block` and define a defaults method instead of metaboxes; props passed to `render()` merge over those defaults, and they nest naturally inside MetaBlock templates. See **AGENTS.md § "Creating a New UI Block"** for the class shape and a nesting example.

---

## Template Patterns

### Multi-section homepage

```php
<?php
// front-page.php
use TAW\Core\Block\BlockRegistry;

BlockRegistry::queue('hero', 'features', 'stats', 'testimonials', 'cta');
get_header();
?>

<?php BlockRegistry::render('hero'); ?>
<?php BlockRegistry::render('features'); ?>
<?php BlockRegistry::render('stats'); ?>
<?php BlockRegistry::render('testimonials'); ?>
<?php BlockRegistry::render('cta'); ?>

<?php get_footer(); ?>
```

### Render for a specific post

```php
// Render a block for an explicit post ID (e.g., outside The Loop)
BlockRegistry::render('hero', $post_id);
```

### Standard page (no custom blocks)

```php
<?php
// page.php
get_header();
?>

<article>
    <?php the_content(); ?>
</article>

<?php get_footer(); ?>
```

---

## Metabox System

> **Full reference: [taw/core README → Metabox System](https://github.com/Relmaur/taw-core#metabox-system)**
> The `taw/core` repository is the single source of truth for all Metabox field types, options, retrieval API, repeater nesting, conditional logic, and tab configuration. The notes below are a quick orientation only — always defer to that README for authoritative detail.

Register a metabox with a config array (`id`, `title`, `screens`, `fields`, optional `tabs`); the `screens` key accepts post types, page template filenames, and page slugs, mixed in the same array. Read values inside `MetaBlock::getData()` via convenience wrappers (`$this->getMeta()`, `$this->getImageUrl()`) or the static `Metabox::get*()` helpers.

**Field types:** `text`, `textarea`, `wysiwyg`, `url`, `number`, `range`, `select`, `image`, `files`, `group`, `checkbox`, `color`, `repeater`, `post_select`, `datepicker`

→ Config example, retrieval API, conditional fields, repeater nesting, tabs, and the full options table: **AGENTS.md § "The Metabox Framework"** and the **[taw/core README](https://github.com/Relmaur/taw-core#metabox-system)** (authoritative for field internals).

### Locking Metabox Order

`MetaboxOrder::lockFromTemplate()` runs automatically — `Theme::bootstrapFullSite()` calls it unconditionally, nothing to add to `functions.php`. It locks each page's metabox order to match its template's `BlockRegistry::render()` sequence and disables drag-and-drop reordering, so the admin edit screen never drifts from what actually renders on the front end. Use `MetaboxOrder::lock('page', ['id1', 'id2'])` in `inc/customizations.php` for an explicit order instead.

→ Full template-resolution details: **[taw/core README → Locking Metabox Order](https://github.com/Relmaur/taw-core#locking-metabox-order)**.

---

## Repeater Field

The `repeater` field type creates a sortable, dynamic list of rows, each sharing the same set of sub-fields (including nested repeaters). Rows render as a collapsible accordion by default, or a tabbed UI via `layout: 'tabbed_horizontal'`/`'tabbed_vertical'`. Retrieve with `Metabox::get_repeater()`.

→ Full repeater and `group` field documentation, including `min`/`max` rows and the `layout` option: **[taw/core README](https://github.com/Relmaur/taw-core#metabox-system)**

---

## Theme Options

`OptionsPage` provides site-wide settings stored in `wp_options`, using the same field config format as metaboxes (`id`, `title`, `fields`, optional `tabs`, `icon`, `position`). Configured in `inc/options.php` — never touched by `update-theme`. Retrieve with `OptionsPage::get($field)` / `OptionsPage::get_image_url($field, $size)`.

→ Full config example and supported field types: **AGENTS.md § "Options Page"**.

---

## Navigation Menus

`Menu::get($location)` wraps WordPress nav menus into a typed `Menu`/`MenuItem` tree — giving you full control over markup without `wp_nav_menu()`. Items expose `title()`, `url()`, `hasChildren()`/`children()`, active-state checks (`isActive()`, `isActiveAncestor()`, `isInActiveTrail()`), and class/description accessors.

→ Full `Menu` and `MenuItem` API tables and a rendering example: **AGENTS.md § "Navigation Menu System"**.

---

## Performance-Optimised Images

`TAW\Helpers\Image` generates `<img>` tags with the correct `loading`, `fetchpriority`, `decoding`, `srcset`, and `sizes` attributes based on whether the image is above or below the fold.

```php
use TAW\Helpers\Image;

// Above-the-fold hero (eager, high priority)
echo Image::render($hero_id, 'full', ['above_fold' => true]);

// Regular image (lazy, low priority — the default)
echo Image::render(get_post_thumbnail_id(), 'large');

// With CSS class and custom sizes
echo Image::render($id, 'large', [
    'class' => 'rounded-lg shadow-md',
    'sizes' => '(max-width: 768px) 100vw, 50vw',
]);

// With arbitrary extra attributes
echo Image::render($id, 'medium', [
    'attr' => ['id' => 'site-logo', 'data-hero' => 'true'],
]);
```

### Preload tag

Generate a `<link rel="preload">` for your single most important image. Call before `wp_head()` or hook at priority 1–2.

```php
echo Image::preloadTag($hero_id, 'full');
// → <link rel="preload" href="..." as="image" imagesrcset="..." imagesize="...">
```

---

## CSS / Asset Pipeline

### Entry points

| File                           | Role                                                                            |
| ------------------------------ | ------------------------------------------------------------------------------- |
| `resources/js/app.js`          | Main JS entry — Alpine, Swup, all block DOM init, imports `app.css`/`app.scss`  |
| `resources/css/app.css`        | Tailwind v4 directives + any globally-needed third-party CSS (e.g. PhotoSwipe)  |
| `resources/scss/app.scss`      | Global custom SCSS — `@use 'fonts'` lives here                                  |
| `resources/scss/critical.scss` | Above-the-fold CSS — inlined in `<head>` as a `<style>` tag                     |
| `resources/scss/_fonts.scss`   | `@font-face` declarations — never add these to `critical.scss`                  |
| `resources/fonts/`             | Self-hosted WOFF2 font files                                                    |

Production loading: `critical.scss` is inlined in `<head>`; `app.css`/`app.scss` load asynchronously via a `media="print"` + `onload` swap; JS loads as an ES module; all filenames are content-hashed. Resolve any asset URL with `ViteLoader::assetUrl($path)`, probe the dev server with `ViteLoader::isDevServerRunning()` — never the legacy `vite_asset_url()`/`vite_is_dev()` functions, which aren't autoloaded.

Each block can ship its own `style.scss`/`style.css` and `script.js` — both auto-detected and auto-enqueued when the block is queued/rendered (SCSS wins if both exist). Because Swup keeps `<body>` scripts alive across navigations but only swaps `#content`, a block script only runs once per session — see **[Using JavaScript View Transition Libraries](#using-javascript-view-transition-libraries)** below for what that means for block script responsibilities.

→ Full `ViteLoader` API, dev-server detection, entry-point overrides, and block-asset internals: **AGENTS.md § "Vite Integration"**.

---

## Using JavaScript View Transition Libraries

TAW ships with [Swup v4](https://swup.js.org/) for SPA-style page transitions, but the architectural patterns below apply equally to Barba.js, Taxi.js, the native View Transitions API, or any library that swaps page content without a full browser reload.

**The core problem:** a view-transition library intercepts link clicks and swaps only a portion of the DOM (in TAW, `<main id="content">`) — the `<header>`, `<footer>`, and every script already in `<body>` stay alive for the whole session. Block scripts execute once, on first load, and never again. A block whose script wasn't on the landing page renders inert HTML forever. Re-running `<script>` tags on swap doesn't help (block scripts live outside the swapped container); a custom event doesn't help either (a script that never ran has no listener to fire it). Everything below works around this one fact.

| Problem | Symptom | Fix pattern | Code anchor |
|---|---|---|---|
| Block scripts run once, outside the swapped container | Blocks not on the landing page never initialize after navigating to them | Centralize all DOM init in `app.js`'s `initAll()`, run on `DOMContentLoaded` **and** the library's post-swap hook | `app.js` — sample 1 |
| `initAll()` reruns on every navigation | Carousels/handlers double-initialize on the page that had them from the start | Guard attribute (`data-*-ready`) set after init, `:not([data-*-ready])` selector before init | `initGalleries()` — sample 1 |
| Embla/Splide-style instances leak `ResizeObserver`s + listeners across swaps | Memory usage climbs with every navigation | Register a teardown closure per instance in a `window._tawCleanup` `Set`; flush it in the library's before-swap hook | sample 1 |
| Alpine bindings on swapped nodes go stale | `x-data` components inside `#content` stop reacting after navigation | `Alpine.destroyTree()` before the swap, `Alpine.initTree()` after; call `Alpine.start()` exactly once, ever | sample 2 |
| Block script registers `Alpine.data()` but loads after `Alpine.start()` already ran | `x-data="name"` elements get empty/broken state on later navigations | Check `window._alpineStarted`: if true, register then `destroyTree`/`initTree` the affected elements; if false, defer registration to `alpine:init` | sample 2 |
| Block-specific logic needs to run post-navigation beyond `initAll()` | Menu highlighting, analytics, etc. don't update on swap | Listen for the `taw:page-view` `CustomEvent` dispatched by `app.js` after every `initAll()`; also usable as a no-op-safe fallback init since guard attributes prevent double-init | `document.addEventListener('taw:page-view', ...)` |
| Third-party CSS (e.g. PhotoSwipe) imported inside multiple JS entry points | Unreliable style injection via Vite's dev-mode HMR module system | Import third-party CSS into `resources/css/app.css`, never into a JS file | sample 3 |

**Sample 1 — centralized, idempotent init with teardown (`app.js`):**

```js
import EmblaCarousel from 'embla-carousel';

function initGalleries() {
    document.querySelectorAll('.gallery__embla:not([data-ready])').forEach(root => {
        const embla = EmblaCarousel(root, { loop: true });
        root.setAttribute('data-ready', '');               // guard: skip on next initAll()
        window._tawCleanup.add(() => embla.destroy());      // teardown, flushed before next swap
    });
}

function initAll() { initGalleries(); /* ...one function per block type */ }

document.addEventListener('DOMContentLoaded', initAll);
swup.hooks.on('page:view', initAll);                          // rerun after every swap

swup.hooks.before('content:replace', () => {                  // flush teardowns before the swap
    window._tawCleanup.forEach(fn => fn());
    window._tawCleanup.clear();
});
```

**Sample 2 — Alpine lifecycle across swaps, including late-registering block scripts:**

```js
// app.js
swup.hooks.before('content:replace', () => Alpine.destroyTree(document.getElementById('content')));
swup.hooks.on('content:replace', () => Alpine.initTree(document.getElementById('content')));

// Blocks/PostGrid/script.js — registering Alpine.data() safely regardless of load order
const registerVideoModal = () => Alpine.data('videoModal', () => ({ isOpen: false }));

if (window._alpineStarted) {
    registerVideoModal();
    document.querySelectorAll('[x-data="videoModal"]').forEach(el => {
        Alpine.destroyTree(el);
        Alpine.initTree(el);
    });
} else {
    document.addEventListener('alpine:init', registerVideoModal);
}
```

**Sample 3 — third-party CSS goes in the CSS entry, not a JS import:**

```css
/* resources/css/app.css */
@import "tailwindcss";
@import "photoswipe/dist/photoswipe.css"; /* always a real stylesheet, never JS-injected */
```

### In this theme (Swup v4)

The transition animation is a simple opacity fade on `#content`:

```scss
#content { opacity: 1; transition: opacity 180ms ease; }
html.is-animating #content { opacity: 0; }
```

Swup adds `html.is-animating` when navigation starts and removes it after the enter animation ends. The same CSS rule drives both the exit and the enter.

Plugins:

| Plugin | Purpose |
|---|---|
| `@swup/head-plugin` (`persistAssets: true`) | Syncs `<head>` elements; keeps already-loaded scripts across navigations |
| `@swup/scroll-plugin` | Scrolls to top after each swap |
| `@swup/preload-plugin` | Preloads target page on hover/focus |

---

## Forms

`TAW\Core\Form\Form` is a configuration-driven frontend form handling CSRF, honeypot spam protection, field validation, AJAX submission (no page reload), and email delivery — no plugin required. **Register it in the block's `boot()` method** (wrapped in `add_action('init', ...)`), never in a template, so the AJAX handler exists on every request; display it with `Form::display($id)`. It also supports conditional AND/OR field logic, multi-step forms (`steps` instead of `fields`), and structural fields (`heading`, `divider`, `html`).

**Security is on by default:** CSRF nonce + honeypot always on; rate limiting (5/60s per IP+form) on by default, configurable or disable-able; optional Cloudflare Turnstile via `'turnstile' => true` plus `wp-config.php` constants (never an OptionsPage field — those are REST-readable).

→ Full registration example, field type tables, validation rules, conditional logic, and multi-step config: **AGENTS.md § "Form System"**.

---

## Transactional Email

`TAW\Core\Mail\Mailer` is a fluent wrapper around `wp_mail()` with MJML/HTML template support (`mails/html/{name}.html`, `{{variable}}` placeholders). `MailTester` adds a Tools → Test Emails admin page — register it in `inc/customizations.php`.

→ Full API and template compilation details: **AGENTS.md § "Mail System"**.

---

## SVG Support

`TAW\Helpers\Svg::register()` enables sanitized SVG uploads; render with `Svg::render()` (as `<img>`) or `Svg::inline()` (inline, for CSS targeting/animation).

→ Full API: **AGENTS.md § "SVG Helper"**.

---

## Theme Options — OptionsPage

See [Theme Options](#theme-options) above for the full API.

---

## Boilerplate Blocks

TAW ships with ready-to-customise blocks you can use immediately or treat as a reference implementation.

### Menu — Site Header with Live Search

`Blocks/Menu/` — a two-row site header (logo, nav with dropdowns, optional `OptionsPage` contact info) with a keyboard-accessible Alpine.js live-search overlay against `GET /wp-json/wp/v2/search`.

```php
// header.php
use TAW\Blocks\Menu\Menu;

(new Menu())->render();
```

→ Customisation checklist (restricting search post types, translating overlay labels, asset queueing): **AGENTS.md § "Boilerplate Blocks"**.

---

## Navigation Menus — registration

Menus (`primary`, `footer`) are registered in `inc/customizations.php` via `register_nav_menus()` (not `functions.php`, which is framework-owned). Edit that array directly to add or rename locations. Assign menus to locations in WordPress admin → Appearance → Menus.

---

## REST API

`TAW\Core\Rest\SearchEndpoints` registers `GET /wp-json/taw/v1/search-posts` (requires `edit_posts`), powering the `post_select` metabox field. Registered automatically via `Theme::boot()`.

→ Full parameter and response-field tables: **AGENTS.md § "REST API"**.

---

## Theme Updates

`ThemeUpdater` hooks into WordPress's update system to check a GitHub Releases URL and surface the standard "Update Available" admin notice.

> **Not for client sites with customizations** — it's a full theme-directory replacement from a ZIP that would overwrite `Blocks/`, templates, and `inc/`. Use the `update-theme` AI skill for real client sites instead.

→ Config example and caching behavior: **AGENTS.md § "Theme Updater"**.

---

## CSS Studio — Visual Dev Editor

CSS Studio is a browser-based visual editor that streams live-page edits directly to your AI coding assistant. It is pre-installed in this theme.

**Requires:** Node package `cssstudio` (installed), Vite dev server running (`npm run dev`), and the toggle enabled in WP Admin.

**Toggle:** WP Admin → TAW Settings → Developer Tools → Enable CSS Studio

**Start a session (inside Claude Code / your AI agent):**
```
/studio
```

When active, every change you make in the visual panel — text edits, style tweaks, attribute changes — is sent to the agent as structured data and applied to the source files automatically. The agent follows TAW-specific rules:

- **Text/content edits:** if the element is already wired to a metabox field, the agent leaves the template alone and tells you to update the content in WP Admin. If it's hardcoded, it asks whether to keep it that way or wire it to a new metabox field.
- **Style edits:** the agent always asks where to apply the change — Tailwind classes in the template, per-block SCSS (`style.scss`), or global SCSS.

---

## Tech Stack

| Technology                                                                 | Role                                                             |
| -------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| [Tailwind CSS v4](https://tailwindcss.com/)                                | Utility-first CSS via the official Vite plugin                    |
| [Alpine.js v3](https://alpinejs.dev/)                                      | Lightweight reactivity for interactive components                 |
| [Swup v4](https://swup.js.org/)                                            | SPA-style page transitions — swaps `#content` without full reload |
| [Embla Carousel](https://www.embla-carousel.com/)                          | Touch-friendly carousels (galleries, testimonials)                |
| [PhotoSwipe v5](https://photoswipe.com/)                                   | Lightbox for images — lazy-loads the core on first open           |
| [Vite v7](https://vitejs.dev/)                                             | Build tool with instant HMR in development                        |
| [SCSS](https://sass-lang.com/)                                             | Optional custom styles — global and per-block                     |
| [Symfony Console](https://symfony.com/doc/current/components/console.html) | CLI scaffolding commands (`bin/taw`) — shipped inside `taw/core`  |
| PHP 8.1+                                                                   | PSR-4 autoloading via Composer                                    |
| [`taw/core`](https://github.com/Relmaur/taw-core)                          | Versioned composer package containing all framework internals     |

### Architecture at a Glance

| Concept           | Implementation                                                                          |
| ----------------- | --------------------------------------------------------------------------------------- |
| Autoloading       | PSR-4 via Composer — `TAW\Blocks\` → `Blocks/` (theme); everything else from `taw/core` |
| Block system      | `BaseBlock` → `MetaBlock` / `Block` class hierarchy (`TAW\Core\Block\*` in `taw/core`)  |
| Block discovery   | `BlockLoader::loadAll()` — recursive scan, any nesting depth, no registration needed    |
| Metaboxes         | Bespoke config-driven framework (`TAW\Core\Metabox\Metabox` in `taw/core`)              |
| Options page      | Config-driven `OptionsPage` — stores to `wp_options` (`TAW\Core\OptionsPage\OptionsPage`) |
| Navigation menus  | `Menu` / `MenuItem` typed tree (`TAW\Core\Menu` in `taw/core`)                          |
| REST API          | `taw/v1/search-posts` endpoint (`TAW\Core\Rest` in `taw/core`)                          |
| Forms             | Config-driven `Form` with CSRF, honeypot, validation, PRG (`TAW\Core\Form` in `taw/core`) |
| Mail              | Fluent `Mailer` + MJML/HTML `MailTemplate` (`TAW\Core\Mail` in `taw/core`)              |
| SVG               | Sanitized uploads + inline/img rendering (`TAW\Helpers\Svg` in `taw/core`)              |
| Asset pipeline    | `TAW\Support\ViteLoader` (PSR-4 from `taw/core`) — dev detection, manifest, enqueue, preloads |
| Critical CSS      | `critical.scss` compiled and inlined in `<head>` via `ViteLoader::inlineCriticalCss()`  |
| Async CSS         | Main CSS loaded non-render-blocking via `media="print"` + `onload` swap                 |
| Fonts             | Self-hosted WOFF2 with preloads via `ViteLoader::assetUrl()` (from `taw/core`)          |
| Performance       | `performance.php` removes WP bloat, adds resource hints (autoloaded from `taw/core`)    |
| Page transitions  | Swup v4 swaps `#content`; `app.js` owns all block DOM init via `initAll()` on `page:view` |
| Alpine lifecycle  | `destroyTree` / `initTree` on content swap; `Alpine.start()` called once only           |
| Embla teardown    | `window._tawCleanup` Set — callbacks registered after init, flushed before each swap    |
| Theme updates     | GitHub Releases-based auto-updater (`TAW\Core\Theme\ThemeUpdater` in `taw/core`)        |
| Framework updates | `composer update taw/core` — update across all sites independently                      |

---

## Project Structure

```
taw-theme/
├── bin/taw            # CLI entry point (Symfony Console — delegates to taw/core)
├── Blocks/            # Your blocks — one folder per block, auto-discovered
├── inc/                # Theme-owned config — never touched by update-theme
├── vendor/taw/core/    # Framework internals (managed via composer)
├── resources/          # JS/CSS/SCSS/fonts — Vite entries
├── public/build/       # Compiled assets (gitignored)
├── functions.php       # 100% framework-owned — two lines, never hand-edited
├── vite.config.js
├── composer.json       # TAW\Blocks\ → Blocks/, requires taw/core
├── package.json
└── AGENTS.md            # AI agent architecture docs
```

→ Full path-by-path detail (including `vendor/taw/core/src/` internals): **AGENTS.md § "Quick Orientation"**.

---

## Requirements

| Dependency | Version |
| ---------- | ------- |
| WordPress  | 6.0+    |
| PHP        | 8.1+    |
| Composer   | 2.0+    |
| Node.js    | 20.19+  |
| npm        | 8+      |

---

## Commands

| Command                                    | Description                                              |
| ------------------------------------------- | -------------------------------------------------------- |
| `npm run dev`                               | Start Vite dev server (port 5173) with HMR               |
| `npm run build`                             | Production build → `public/build/` with hashed filenames |
| `composer install`                          | Install PHP dependencies (including `taw/core`)          |
| `composer update taw/core`                  | Pull the latest framework update                         |
| `composer dump-autoload`                    | Rebuild PSR-4 classmap after adding new block classes    |
| `php bin/taw make:block Name --type=meta`   | Scaffold a new block                                     |
| `php bin/taw export:block Name`             | Export a block as a portable ZIP                         |
| `php bin/taw import:block path/to/Block.zip`| Import a block from a ZIP                                |
| `composer run phpstan`                      | Static analysis (`Blocks/`, `inc/`) — also runs in CI     |

→ Full `make:block`/`export:block`/`import:block` flag reference: **AGENTS.md § "CLI Tools"**.

---

## AI-Ready

TAW isn't just documented for AI coding assistants — it ships a working toolkit for them, in the same spirit as Laravel Boost. Point an agent at a TAW project and it can build pages, sync with upstream, and inspect the live site state, not just read about how to.

**Architecture docs**, picked up automatically by any LLM-powered tool:
- **`AGENTS.md`** — comprehensive architecture guide (Claude Code, Cursor, generic agents)
- **`CLAUDE.md`** — Claude Code-specific instructions
- **`.github/copilot-instructions.md`** — GitHub Copilot instructions
- **`.windsurfrules`** — Windsurf/Codeium instructions

**Claude Code skills** (`.claude/skills/`), invoked by name or triggered by plain-language requests:
- **`make-metablock`** — "add a pricing table section" → a fully wired MetaBlock (class, metabox fields, template, styles)
- **`build-page`** — "build a homepage with hero, features, and a contact form" → an entire page assembled from existing and newly-scaffolded blocks
- **`figma-to-block`** — "implement this Figma design" → a block whose markup and metabox fields match the design, driven by the Figma MCP tools
- **`populate-content`** — "fill in the team_members repeater on the About page with this list" → writes real field values via `fields:set`, with mandatory dry-run previews and confirmation before any risky write (see `AGENTS.md` § "Content-writing safety model")
- **`update-theme`** — "update the theme" → copies a small, precisely-delimited set of framework-owned paths (`functions.php`, `.claude/skills/`, `bin/`, CI config) from the canonical `taw-theme` repo, direct file sync, no merge, no shared git history needed, never touching your `Blocks/`, templates, or content
- **`project-init`** — "onboard this new client project" → verifies `gh` CLI auth, enables and smoke-tests the `framework-sync.yml` GitHub Actions PR permission, and walks through optional integrations (Turnstile, email, CSS Studio, Visual Editor) via explicit yes/no questions — picks up right after `composer create-project` + first push
- **`sync-remote`** — "sync with remote, please" → pull → compare → resolve → push: fetches and compares against origin, reconciles diverged history via a real merge (never rebase, never a guessed conflict resolution), optionally runs `phpstan`, and always confirms before the final push — the pull/merge steps are authorized by the request itself, the push still needs an explicit yes
- **`studio`** — applies live CSS Studio visual edits back into source

**Live introspection** — `php bin/taw inspect` (or `--json`) reports the site's actual current state: registered blocks and their real metabox field schemas, registered forms, the installed `taw/core` version, whether `MetaboxOrder` is locked. An agent queries this instead of reconstructing it by grepping PHP.

**Direct field read/write** — `php bin/taw fields:get`/`fields:set` let an agent read or write any Metabox/OptionsPage field's value directly, sanitized with the exact same rules as a real admin form save — no hand-encoding a repeater's JSON shape, no guessing which sanitizer applies to which field type. This is what turns "build this page from a Figma design" into something that can also populate the content, not just scaffold empty fields. Writing content is inherently higher-stakes than scaffolding it, so every skill that writes via this primitive follows a mandatory dry-run + confirmation model — see `AGENTS.md` § "Content-writing safety model".

**CI, not just convention** — `.github/workflows/ci.yml` runs `php -l`, `composer validate`, a dedicated check that every `MetaBlock::getData()` matches the exact signature the framework requires (a mismatch there is a site-wide fatal, not a cosmetic bug), and PHPStan (level 5, WordPress-aware via `szepeviktor/phpstan-wordpress`) over `Blocks/` and `inc/`. A second job spins up a real WordPress + MySQL environment and dynamically renders every registered block and form against a real post — catching the runtime errors (undefined function calls, template/data mismatches) that static checks alone can't see.

**Framework drift never goes unnoticed** — `php bin/taw sync` (the scriptable core of the `update-theme` skill) checks whether the installed `taw/core` version is behind the latest release and whether this project's Tier 1/Tier 2 scaffold paths differ from the canonical `taw-theme` repo, without booting WordPress. `.github/workflows/framework-sync.yml` runs it unattended on a weekly schedule: Tier 1 changes are applied and verified through the same checks CI runs on every push, then a pull request is opened with Tier 2 diffs included for human review — or nothing happens at all if the project is already current. A client project only has to run `update-theme` once to start self-checking forever after, since this workflow file is itself Tier 1.

**Live documentation lookup** — the `mcp__taw-docs__search_documentation` MCP tool, when available, searches the current framework docs directly rather than requiring a URL guess.

Two separate, independently-versioned sources of truth back all of this — don't conflate them:
- **[`taw-theme`](https://github.com/Relmaur/taw-theme)** — this scaffold. Synced into a client site via `update-theme`.
- **[`taw/core`](https://github.com/Relmaur/taw-core)** — the framework package. Synced via `composer update taw/core`.

---

## License

GPL v2. See [LICENSE.txt](LICENSE.txt) for details.
