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

**Visual Editor** — a live content page editor for authenticated users. Activated automatically by `Theme::boot()`. An **Edit Visually** button appears in the admin bar; appending `?taw_visual_edit=1` to any URL activates the editing shell.

---

## Quick Start

```bash

# Move to themes directory of your WordPress installation
cd wp-content/themes/

# --keep-vcs is REQUIRED, not optional — see warning below.
# This command will create the starter theme with the correct structure and dependencies. Replace <theme_name> with your desired theme folder name.
composer create-project taw/theme <theme_name> --keep-vcs --repository='{"type":"vcs","url":"https://github.com/Relmaur/taw-theme"}'

cd <theme_name>
git remote rename origin upstream          # taw-theme becomes the update source
git remote add origin <your-client-repo-url>   # the client's own repo
git push -u origin master                  # or main, whatever your default branch is called

composer install       # PHP deps — pulls taw/core framework package
npm install            # Frontend dependencies
npm run dev            # Vite dev server with HMR
```

> **`--keep-vcs` is mandatory.** By default `composer create-project` deletes the `.git` directory after cloning, which severs all shared history with `taw-theme`. The `update-theme` skill relies on that shared history — it syncs base-theme updates via a real `git merge`, and `git merge` needs a common ancestor commit to work. Without `--keep-vcs` (and the `upstream`/`origin` remote setup above), a client project silently loses the ability to receive scaffold updates from day one, and the only fix later is a manual, file-by-file port instead of a clean merge. The same applies if you use GitHub's "Use this template" button instead of this command — that feature also deliberately creates history-less repos. Always clone/keep-vcs, never template.

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

#### 1. The Class

```php
// Blocks/Hero/Hero.php

namespace TAW\Blocks\Hero;

use TAW\Core\Block\MetaBlock;
use TAW\Core\Metabox\Metabox;

class Hero extends MetaBlock
{
    protected string $id = 'hero';

    protected function registerMetaboxes(): void
    {
        new Metabox([
            'id'      => 'taw_hero',
            'title'   => 'Hero Section',
            'screens' => ['page'],
            'fields' => [
                ['id' => 'hero_heading', 'label' => 'Heading', 'type' => 'text'],
                ['id' => 'hero_image',   'label' => 'Image',   'type' => 'image'],
            ],
        ]);
    }

    protected function getData(int|false $postId): array
    {
        return [
            'heading'   => $this->getMeta($postId, 'hero_heading'),
            'image_url' => $this->getImageUrl($postId, 'hero_image', 'large'),
        ];
    }
}
```

#### 2. The Template

```php
<!-- Blocks/Hero/index.php -->

<?php if (empty($heading)) return; ?>

<section class="hero">
    <h1><?php echo esc_html($heading); ?></h1>
    <?php if ($image_url): ?>
        <img src="<?php echo esc_url($image_url); ?>" alt="">
    <?php endif; ?>
</section>
```

#### 3. Use It

```php
<?php
// front-page.php
use TAW\Core\Block\BlockRegistry;

BlockRegistry::queue('hero');
get_header();
?>

<?php BlockRegistry::render('hero'); ?>

<?php get_footer(); ?>
```

That's it. No registration step. The block auto-discovers itself, its metabox appears in the editor, and its assets load only where it's used.

---

## Two Types of Blocks

|                  | MetaBlock                         | Block                           |
| ---------------- | --------------------------------- | ------------------------------- |
| **Purpose**      | Page sections that own their data | Reusable UI components          |
| **Data source**  | Metaboxes → `post_meta`           | Props passed at render time     |
| **Rendered via** | `BlockRegistry::render('id')`     | `(new Button())->render([...])` |
| **Examples**     | Hero, Stats, Testimonials, CTA    | Button, Card, Badge             |

### UI Block (Block)

UI Blocks extend `TAW\Core\Block\Block` and define a `defaultData()` method instead of metaboxes:

```php
// Blocks/Button/Button.php
namespace TAW\Blocks\Button;

use TAW\Core\Block\Block;

class Button extends Block
{
    protected string $id = 'button';

    protected function defaultData(): array
    {
        return [
            'text'   => '',
            'url'    => '#',
            'style'  => 'primary',
        ];
    }
}
```

Props passed to `render()` are merged over the defaults — missing props always have safe fallbacks.

### Nesting Blocks

UI Blocks compose naturally inside MetaBlocks:

```php
<!-- Blocks/Hero/index.php -->
<section class="hero">
    <h1><?php echo esc_html($heading); ?></h1>

    <?php (new \TAW\Blocks\Button\Button())->render([
        'text' => 'Get Started',
        'url'  => '/contact',
    ]); ?>
</section>
```

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

Register a metabox with a config array. The `screens` key accepts post types, page template filenames, and page slugs — mixed in the same array:

```php
use TAW\Core\Metabox\Metabox;

new Metabox([
    'id'      => 'taw_hero',
    'title'   => 'Hero Section',
    'screens' => ['page'],                    // post type, slug, or template filename
    'fields'  => [
        ['id' => 'heading', 'label' => 'Heading', 'type' => 'text',  'required' => true, 'width' => '50'],
        ['id' => 'image',   'label' => 'Image',   'type' => 'image', 'width' => '50'],
    ],
    'tabs' => [
        ['id' => 'content', 'label' => 'Content', 'fields' => ['heading', 'image']],
    ],
]);
```

**Field types:** `text`, `textarea`, `wysiwyg`, `url`, `number`, `range`, `select`, `checkbox`, `color`, `image`, `files`, `group`, `repeater`, `post_select`, `datepicker`

**Common field options:** `id`, `label`, `type`, `description`, `placeholder`, `default`, `required`, `width`, `conditions`

**Retrieval (inside `MetaBlock::getData()` or any template):**

```php
// Convenience wrappers on MetaBlock
$this->getMeta($postId, 'hero_heading');
$this->getImageUrl($postId, 'hero_image', 'large');

// Static helpers on Metabox
Metabox::get($postId, 'hero_heading');
Metabox::get_bool($postId, 'show_cta');
Metabox::get_image_url($postId, 'hero_image', 'large');
Metabox::get_color($postId, 'bg_color', '#ffffff');
Metabox::get_posts($postId, 'related_posts');     // post_select → int[]
Metabox::get_repeater($postId, 'team_members');   // repeater → array of rows
```

→ For conditional fields, repeater nesting, tabs, `show_on`, `context`, `prefix`, and the full options table, see the **[taw/core README](https://github.com/Relmaur/taw-core#metabox-system)**.

### Locking Metabox Order

`MetaboxOrder::lockFromTemplate()` — called once in `functions.php` after `Theme::boot()` — locks each page's metabox order to match its template's `BlockRegistry::render()` sequence and disables drag-and-drop reordering, so the admin edit screen never drifts from what actually renders on the front end. Use `MetaboxOrder::lock('page', ['id1', 'id2'])` for an explicit order instead.

→ Full template-resolution details: **[taw/core README → Locking Metabox Order](https://github.com/Relmaur/taw-core#locking-metabox-order)**.

---

## Repeater Field

The `repeater` field type creates a sortable, dynamic list of rows. Each row contains the same set of sub-fields.

```php
[
    'id'    => 'team_members',
    'label' => 'Team Members',
    'type'  => 'repeater',
    'min'   => 1,    // optional minimum rows
    'max'   => 10,   // optional maximum rows (0 = unlimited)
    'fields' => [
        ['id' => 'name',   'label' => 'Name',   'type' => 'text',  'width' => '50'],
        ['id' => 'role',   'label' => 'Role',   'type' => 'text',  'width' => '50'],
        ['id' => 'photo',  'label' => 'Photo',  'type' => 'image'],
        ['id' => 'bio',    'label' => 'Bio',    'type' => 'textarea'],
    ],
]
```

Sub-fields support the same types as top-level fields (including nested repeaters). Rows are drag-and-drop sortable and individually collapsible. Retrieve with `Metabox::get_repeater()`.

By default rows render as a collapsible accordion. Use `layout` to switch to a tabbed UI:

| `layout` value | Description |
|---|---|
| _(omitted)_ | Accordion rows (default) |
| `tabbed_horizontal` | Tabs along the top, content below |
| `tabbed_vertical` | Tabs stacked in a left column, content on the right |

```php
[
    'id'     => 'slides',
    'label'  => 'Slides',
    'type'   => 'repeater',
    'layout' => 'tabbed_horizontal',
    'fields' => [
        ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
        ['id' => 'image', 'label' => 'Image', 'type' => 'image'],
    ],
]
```

→ Full repeater and `group` field documentation: **[taw/core README](https://github.com/Relmaur/taw-core#metabox-system)**

---

## Theme Options

`OptionsPage` provides site-wide settings stored in `wp_options` using the same field config format as metaboxes. Configured in `inc/options.php`.

```php
new OptionsPage([
    'id'         => 'taw_settings',
    'title'      => 'TAW Settings',
    'menu_title' => 'TAW Settings',   // optional — defaults to title
    'capability' => 'manage_options', // optional — defaults to manage_options
    'icon'       => 'dashicons-screenoptions', // any dashicon slug
    'position'   => 2,                // admin menu position
    'fields'     => [
        ['id' => 'company_name',  'label' => 'Company Name',  'type' => 'text', 'width' => '33.33'],
        ['id' => 'company_phone', 'label' => 'Phone Number',  'type' => 'text', 'width' => '33.33'],
        ['id' => 'company_email', 'label' => 'Email Address', 'type' => 'text', 'width' => '33.33'],
        ['id' => 'footer_text',   'label' => 'Footer Text',   'type' => 'textarea'],
        ['id' => 'logo',          'label' => 'Logo',          'type' => 'image'],
    ],
    'tabs' => [
        ['label' => 'General', 'fields' => ['company_name', 'company_phone', 'company_email']],
        ['label' => 'Footer',  'fields' => ['footer_text']],
    ],
]);
```

**Supported field types:** `text`, `textarea`, `wysiwyg`, `url`, `number`, `select`, `checkbox`, `color`, `image`.

### Retrieval

```php
use TAW\Core\OptionsPage\OptionsPage;

$phone = OptionsPage::get('company_phone');
$logo  = OptionsPage::get_image_url('logo', 'medium');
```

---

## Navigation Menus

`Menu::get()` wraps WordPress nav menus into a typed tree — giving you full control over markup without `wp_nav_menu()`.

```php
use TAW\Core\Menu\Menu;

$menu = Menu::get('primary');
if ($menu && $menu->hasItems()) {
    foreach ($menu->items() as $item) {
        echo '<a href="' . esc_url($item->url()) . '"';
        if ($item->openInNewTab()) echo ' target="_blank" rel="noopener"';
        echo '>' . esc_html($item->title()) . '</a>';

        if ($item->hasChildren()) {
            foreach ($item->children() as $child) {
                // ...
            }
        }
    }
}
```

### Menu API

| Method                  | Returns      | Description                                          |
| ----------------------- | ------------ | ---------------------------------------------------- |
| `Menu::get($location)`  | `?Menu`      | Load a menu by its registered location slug          |
| `$menu->items()`        | `MenuItem[]` | Root-level items                                     |
| `$menu->hasItems()`     | `bool`       |                                                      |
| `$menu->name()`         | `string`     | The menu name set in WordPress admin                 |

### MenuItem API

| Method                   | Returns      | Description                                              |
| ------------------------ | ------------ | -------------------------------------------------------- |
| `title()`                | `string`     | Menu item label                                          |
| `url()`                  | `string`     | Destination URL                                          |
| `target()`               | `string`     | `'_self'` or `'_blank'`                                  |
| `openInNewTab()`         | `bool`       | True when target is `_blank`                             |
| `hasChildren()`          | `bool`       |                                                          |
| `children()`             | `MenuItem[]` | Direct child items                                       |
| `isActive()`             | `bool`       | Current page matches this item                           |
| `isActiveParent()`       | `bool`       | A child of this item is the current page                 |
| `isActiveAncestor()`     | `bool`       | A descendant of this item is the current page            |
| `isInActiveTrail()`      | `bool`       | Any of the above is true                                 |
| `classes()`              | `string[]`   | Custom classes only (WP auto-classes filtered out)       |
| `wpClasses()`            | `string[]`   | All classes including WP's auto-generated ones           |
| `objectType()`           | `string`     | Object type (`'page'`, `'post'`, `'custom'`, etc.)       |
| `objectId()`             | `int`        | The underlying post/term ID                              |
| `description()`          | `string`     | Item description set in WordPress menu editor            |
| `wpPost()`               | `WP_Post`    | The raw WP menu item object                              |

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

### Production asset loading

- `critical.scss` is compiled and inlined in `<head>` — eliminates a network round-trip for above-fold styles.
- `app.css` / `app.scss` are loaded asynchronously (non-render-blocking) via `media="print"` + `onload` swap.
- JS is loaded as an ES module (`type="module"`).
- All filenames are content-hashed for cache-busting.

### Asset helpers (`TAW\Support\ViteLoader`)

`ViteLoader` is the OOP Vite bridge shipped in `taw/core`. It is PSR-4 autoloaded — no explicit include needed.

```php
use TAW\Support\ViteLoader;

// Resolve any theme asset URL — returns dev-server URL in dev, hashed build URL in prod
$fontUrl = ViteLoader::assetUrl('resources/fonts/Inter-Regular.woff2');

// Check if the Vite dev server is running (replaces the old vite_is_dev())
if (ViteLoader::isDevServerRunning()) { /* dev-only logic */ }

// Enqueue an additional Vite entry point (e.g. a standalone block script)
ViteLoader::enqueueAsset('my-block', 'resources/js/my-block.js');

// Override the main entry point — call BEFORE Theme::boot()
ViteLoader::init('src/main.ts');
Theme::boot();
```

> **Note:** The legacy procedural functions `vite_asset_url()` and `vite_is_dev()` still exist inside `vite-loader.php` but are **not** in the composer `files` autoload and will not be available globally. Use `ViteLoader` instead.

### Block assets

Each block can have a `style.scss` (or `style.css`) and a `script.js`. Both are auto-detected and auto-enqueued. SCSS takes priority over CSS when both exist.

```
Blocks/Hero/
├── Hero.php
├── index.php
├── style.scss   ← per-block CSS (enqueued as <link> in <head>)
└── script.js    ← per-block JS (type="module")
```

The `BlockRegistry::queue('id')` call schedules assets for `<head>`. If you forget to queue, `BlockRegistry::render()` enqueues assets as a fallback (they land after `wp_head`, but a `<link>` is printed inline).

**Block script responsibilities:** When a view-transition library is used (TAW ships with Swup), each block script only runs once — on the first page load. DOM initialization (Embla carousels, PhotoSwipe lightboxes, marquees, animations) should be handled centrally in `app.js` so it runs on every navigation. A block `script.js` is therefore best used for:

1. **Alpine component registration** — `Alpine.data('componentName', factory)` for any `x-data="componentName"` elements in the block's template.
2. **One-time global setup** — anything that binds to the document/window and doesn't need to re-run per navigation.

See the **[Using JavaScript View Transition Libraries](#using-javascript-view-transition-libraries)** section below for the full lifecycle and patterns.

---

## Using JavaScript View Transition Libraries

TAW ships with [Swup v4](https://swup.js.org/) for SPA-style page transitions, but the architectural patterns below apply equally to Barba.js, Taxi.js, the native View Transitions API, or any library that swaps page content without a full browser reload.

### The core problem

A view-transition library intercepts link clicks, fetches the new page, and **swaps a portion of the DOM** (in TAW, that portion is `<main id="content">`). Everything outside that container — the `<header>`, `<footer>`, and all scripts already loaded — stays alive for the whole session.

This creates a fundamental mismatch: block JavaScript files execute **once** on the initial page load and never again. When the user navigates to a page that has a block whose script wasn't loaded on the first page, the DOM for that block appears but its JavaScript initialization never runs.

### Root cause: block scripts live outside the swapped container

WordPress enqueues block scripts in `<body>` (outside `<main id="content">`). A view-transition library replaces `#content`'s HTML on each navigation but leaves everything else untouched. Any script registered for a block that wasn't on the landing page is therefore **never executed** during the session — the block renders but stays inert.

The two naive workarounds both fail:

- **Re-running scripts on each swap** — if the library re-evaluates `<script>` tags inside the swapped container, scripts in `<body>` (outside the container) are still missed.
- **Delegating to a custom event** — dispatching `myLib:page-view` and having each block script listen to it works only for scripts that have already loaded; a script that has never run has no listener to fire.

### The reliable fix: centralize initialization in `app.js`

The only script that reliably executes on every page is the **main entry point** — `app.js`. Moving all DOM-initialization logic there eliminates the timing dependency entirely.

```js
// app.js — import everything needed for all block types
import EmblaCarousel      from 'embla-carousel';
import AutoPlay           from 'embla-carousel-autoplay';
import PhotoSwipeLightbox from 'photoswipe/lightbox';

// Define one init function per block type
function initGalleries()       { /* Embla on .image-gallery__embla    */ }
function initTestimonials()    { /* Embla on .testimonials__embla     */ }
function initPhotoSwipe()      { /* PhotoSwipe on [data-pswp-gallery] */ }
function initStrategicAllies() { /* marquee on .strategic-allies__marquee */ }
function initChangingNumbers() { /* count-up on [data-target]        */ }

// Run everything on first load AND after every navigation
function initAll() {
    initOrnaments();
    initGalleries();
    initTestimonials();
    initPhotoSwipe();
    initStrategicAllies();
    initChangingNumbers();
}

document.addEventListener('DOMContentLoaded', initAll);

// Hook into your library's "content replaced" lifecycle event
// (Swup: page:view | Barba: after | native VT API: after transition)
yourTransitionLibrary.on('page:view', initAll);
```

**If you add a new block that needs per-navigation initialization, add its init function to `initAll()`.** Never rely solely on a block script's event listener.

### Make every init function idempotent

Because `initAll()` runs after every navigation — and block scripts may also run their own initialization as a fallback — each function must be safe to call multiple times on the same page.

The pattern: set a guard attribute on the element after initialization. Check for its absence before running.

```js
function initGalleries() {
    // :not([data-gallery-ready]) means "hasn't been initialized yet"
    document.querySelectorAll('.image-gallery__embla:not([data-gallery-ready])').forEach(root => {
        const viewport = root.querySelector('.image-gallery__viewport');
        if (!viewport) return; // guard against partial / broken DOM

        const embla = EmblaCarousel(viewport, { loop: true });
        // ...setup buttons, dots, etc...

        root.setAttribute('data-gallery-ready', ''); // mark as done
        window._tawCleanup.add(() => embla.destroy()); // register teardown
    });
}
```

Guard attributes are only present on **live** DOM nodes. When the transition library swaps `#content`, the new HTML comes from the server without any guard attributes, so `initAll()` correctly re-initializes all blocks on the incoming page.

### Teardown before the swap

Libraries like Embla or Splide attach internal `ResizeObserver`s and event listeners to DOM nodes. If those nodes are removed without calling `.destroy()`, the observers keep firing against detached elements — a memory leak that accumulates across navigations.

Register a teardown callback immediately after creating any such instance:

```js
// window._tawCleanup is a Set<() => void> initialized in app.js
window._tawCleanup.add(() => embla.destroy());
```

Hook into your library's **before-swap** lifecycle event to run and clear all registered teardowns:

```js
// Swup:
swup.hooks.before('content:replace', () => {
    window._tawCleanup.forEach(fn => fn());
    window._tawCleanup.clear();
});

// Barba.js:
barba.hooks.before(() => {
    window._tawCleanup.forEach(fn => fn());
    window._tawCleanup.clear();
});
```

### Alpine.js lifecycle

Alpine binds reactive state to specific DOM nodes. When those nodes are replaced, the old bindings must be cleaned up (`Alpine.destroyTree`) and the new nodes must be initialized (`Alpine.initTree`). Alpine must only be **started** once — never call `Alpine.start()` again after the first page load.

```js
// Before the swap — destroy Alpine on the outgoing content
yourLib.on('before-swap', () => {
    Alpine.destroyTree(document.getElementById('content'));
});

// After the swap — initialize Alpine on the incoming content
yourLib.on('after-swap', () => {
    Alpine.initTree(document.getElementById('content'));
});
```

Anything outside `#content` (e.g. a header menu component) is never touched by these calls and stays initialized throughout the session.

### Alpine component registration from block scripts

Block scripts that register named Alpine components (`Alpine.data('name', factory)`) must handle two scenarios: the script running before Alpine starts (first page load) and the script being injected and executed after Alpine has already started and called `initTree` (subsequent navigations).

```js
// Blocks/PostGrid/script.js

const registerVideoModal = () => {
    Alpine.data('videoModal', () => ({
        isOpen: false, embedUrl: '',
        openVideo(url) { this.embedUrl = url; this.isOpen = true; },
        close()        { this.isOpen = false; this.embedUrl = ''; },
    }));
};

if (window._alpineStarted) {
    // The script loaded after Alpine.initTree already ran.
    // Register the component, then re-initialize any elements that were
    // processed without it (Alpine initialized them with empty data).
    registerVideoModal();
    document.querySelectorAll('[x-data="videoModal"]').forEach(el => {
        Alpine.destroyTree(el);
        Alpine.initTree(el);
    });
} else {
    // Normal first-load path: register before Alpine.start() is called.
    document.addEventListener('alpine:init', registerVideoModal);
}
```

`window._alpineStarted` is set to `true` after `Alpine.start()` returns. Block scripts check this flag to know which path to take.

### The `taw:page-view` custom event

`app.js` dispatches a `taw:page-view` `CustomEvent` on `document` after `initAll()` completes on every navigation. Block scripts that need to react to page changes beyond what `initAll()` covers can listen to it:

```js
document.addEventListener('taw:page-view', () => {
    updateActiveMenuLinks(); // example: logic specific to this block
});
```

This event is also used by block scripts as a **fallback** for their own initialization. Since `app.js`'s `initAll()` runs first and sets the guard attributes, any duplicate attempt by a block script's listener is a no-op — the guards prevent double-initialization.

### PhotoSwipe CSS — put it in your main stylesheet

In dev mode, `import 'photoswipe/dist/photoswipe.css'` inside a JavaScript module makes Vite inject the CSS via a `<style>` tag through the HMR module system. When multiple entry points (e.g. `app.js` and several block scripts) each import the same CSS file, the HMR registry tracks multiple owners and can produce unreliable style injection.

**The fix:** import third-party CSS that is needed globally into your main CSS entry rather than into JS files:

```css
/* resources/css/app.css */
@import "tailwindcss";
@import "photoswipe/dist/photoswipe.css"; /* always a real stylesheet, never JS-injected */
```

This keeps it as a genuine stylesheet in both dev and production — no JS-injection conflicts, no HMR edge cases, and the CSS is always available regardless of which block scripts have loaded.

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

`TAW\Core\Form\Form` is a configuration-driven frontend form that handles everything: CSRF (nonces), honeypot spam protection, field validation, AJAX submission via `admin-ajax.php` (no page reload), and email delivery.

**Forms must be registered in the block's `boot()` method** — not in templates — so the AJAX handler exists on every request. Wrap the call in `add_action('init', ...)` so translations are safe:

```php
use TAW\Core\Form\Form;

// In your MetaBlock::boot():
public static function boot(): void
{
    add_action('init', static function () {
        Form::register([
            'id'           => 'contact',
            'submit_label' => 'Send Message',
            'messages'     => ['success' => "Thanks! We'll be in touch."],
            'email' => [
                'to_self'   => ['subject' => 'New contact',      'template' => 'contact-self'],
                'to_client' => ['subject' => 'Got your message', 'template' => 'contact-client'],
            ],
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

**Input field types:** `text`, `email`, `tel`, `url`, `number`, `textarea`, `select`, `radio` (pass `options`; accepts `layout`), `checkbox`, `checkbox_group` (pass `options`; stored as comma-separated string), `date` (accepts `min_date`, `max_date`). Any other value is passed straight through as the HTML `type` attribute.

**Structural field types** (cosmetic only — no `id`, no validation, no submission data): `heading` (dark section banner with `label` and optional `subtitle`), `divider` (`<hr>`), `html` (raw HTML via `content` key, rendered with `wp_kses_post`).

```php
['type' => 'heading', 'label' => '1. Personal Data', 'subtitle' => 'General identification'],
['type' => 'divider'],
['type' => 'html', 'content' => '<p class="text-sm text-gray-500">All fields marked * are required.</p>'],
```

All input fields accept: `id`, `label`, `type`, `required`, `placeholder`, `width`, and `conditions`. All fields (including structural) accept `width` for column placement.

**Conditional fields — AND / OR logic:** By default all conditions are combined with AND. Add `'relation' => 'any'` to switch to OR:

```php
[
    'id'         => 'spouse_name',
    'type'       => 'text',
    'label'      => 'Spouse / Partner name',
    'conditions' => [
        'relation' => 'any',
        'rules'    => [
            ['field' => 'estado_civil', 'operator' => '==', 'value' => 'married'],
            ['field' => 'estado_civil', 'operator' => '==', 'value' => 'cohabiting'],
        ],
    ],
],
```

Supported operators: `==`, `!=`, `>`, `<`, `>=`, `<=`, `contains`. Conditions are enforced in JS and on the server — hidden fields are excluded from `FormData` and re-validated on the server regardless of client-side state.

**Multi-step forms:** Replace the top-level `fields` key with `steps`. Each step has a `title` (shown in a numbered indicator) and its own `fields` array. The same field types, widths, and conditions work identically inside steps.

```php
Form::register([
    'id'           => 'application',
    'submit_label' => 'Submit',
    'next_label'   => 'Continue',   // optional; default "Next"
    'prev_label'   => 'Back',       // optional; default "Back"
    'messages'     => ['success' => 'Your form has been received.'],
    'steps' => [
        [
            'title'  => 'Personal Info',
            'fields' => [
                ['type' => 'heading', 'label' => '1. General Data'],
                ['id' => 'nombre',    'label' => 'Name',  'type' => 'text', 'required' => true, 'width' => 50],
                ['id' => 'email',     'label' => 'Email', 'type' => 'email', 'required' => true, 'width' => 50],
            ],
        ],
        [
            'title'  => 'Declaration',
            'fields' => [
                ['type' => 'html', 'content' => '<p>I declare that all information provided is true.</p>'],
                ['id' => 'confirm', 'label' => 'I confirm', 'type' => 'checkbox', 'required' => true],
            ],
        ],
    ],
]);
```

**How it works:** Next validates required fields in the current step (client-side) before advancing. Back navigates without validation. Submit only appears on the last step. All fields from all steps are submitted in a single AJAX request; if server validation fails, the form auto-navigates back to the step containing the first failing field.

If both `email.to_self.template` and `email.to_client.template` are set, delivery uses `Mailer` + `MailTemplate` (see below). Otherwise falls back to plain-text `wp_mail()`.

`TAW\Core\Form\SubmissionsHandler` stores successful submissions as a `taw_submission` CPT in WP Admin and optionally forwards them via webhook (n8n, Zapier, Make, etc.). **Auto-wired by `Theme::boot()`** — no manual instantiation needed.

---

## Transactional Email

`TAW\Core\Mail\Mailer` is a fluent wrapper around `wp_mail()` with MJML/HTML template support.

```php
use TAW\Core\Mail\Mailer;

(new Mailer())
    ->to('user@example.com')
    ->subject('Welcome!')
    ->template('welcome')                              // → mails/html/welcome.html
    ->setVariables(['name' => 'Jane', 'site_name' => get_bloginfo('name')])
    ->send();
```

Templates live in `mails/html/{name}.html` (pre-compiled, used in production) or `mails/{name}.mjml` (compiled at runtime via `spatie/mjml-php` — dev only). Use `{{variable_name}}` placeholders.

`MailTester` adds a **Tools → Test Emails** admin page for sending test emails against any compiled template. Register it with `(new \TAW\Core\Mail\MailTester())->register()` in `functions.php`.

---

## SVG Support

`TAW\Helpers\Svg` enables sanitized SVG uploads and provides rendering utilities.

```php
use TAW\Helpers\Svg;

// Call once in theme setup to allow SVG uploads + auto-sanitize on upload:
Svg::register();

// Render as <img> tag (scripts inside SVG can't execute):
echo Svg::render($attachment_id, 'Company logo', ['class' => 'logo h-8']);

// Render inline (allows CSS targeting and animations):
echo Svg::inline($attachment_id, ['class' => 'icon w-5 h-5']);

// Get URL only:
$url = Svg::url($attachment_id);
```

---

## Theme Options — OptionsPage

See [Theme Options](#theme-options) above for the full API.

---

## Boilerplate Blocks

TAW ships with ready-to-customise blocks you can use immediately or treat as a reference implementation.

### Menu — Site Header with Live Search

`Blocks/Menu/` — a two-row site header with a keyboard-accessible Alpine.js live-search overlay.

```php
// header.php — instantiate and render
use TAW\Blocks\Menu\Menu;

(new Menu())->render();
```

**What it includes:**
- Top row: custom logo (or site name), optional company address from `OptionsPage`, search trigger button
- Bottom row: `primary` nav menu (with hover dropdowns for items that have children), optional company email + phone from `OptionsPage`
- Search overlay: debounced live fetch against `GET /wp-json/wp/v2/search`, results list, empty state, loading spinner, Escape to close, body scroll lock

**To restrict search to specific post types**, edit the `subtype` param in `Blocks/Menu/script.js`:

```js
const params = new URLSearchParams({
    search: query,
    type: 'post',
    subtype: 'post,page', // ← adjust as needed
    per_page: 8,
});
```

**To translate the overlay labels**, edit `Blocks/Menu/index.php`:
- `What are you looking for?` — overlay heading
- `Search posts and pages…` — input placeholder

---

## Navigation Menus — registration

Menus (`primary`, `footer`) are registered in `functions.php` via `register_nav_menus()`. Edit that array directly to add or rename locations. Assign menus to locations in WordPress admin → Appearance → Menus.

---

## REST API

`TAW\Core\Rest\SearchEndpoints` registers `GET taw/v1/search-posts`. It powers the `post_select` metabox field and is registered automatically via `TAW\Core\Theme::boot()`.

**Endpoint:** `GET /wp-json/taw/v1/search-posts`
**Requires:** `edit_posts` capability (logged-in editors+)

| Parameter   | Default  | Description                                          |
| ----------- | -------- | ---------------------------------------------------- |
| `s`         | `''`     | Search string (omit to return recent posts)          |
| `post_type` | `'post'` | Post type(s) — comma-separated for multiple          |
| `per_page`  | `10`     | Results per page (1–50)                              |
| `exclude`   | `''`     | Comma-separated post IDs to exclude                  |

**Response fields per post:** `id`, `title`, `post_type`, `status`, `date`, `edit_url`, `permalink`, `thumbnail`

---

## Theme Updates

`ThemeUpdater` hooks into WordPress's update system to check a GitHub Releases URL for new versions. When a newer release is found, the standard "Update Available" notice appears in the admin.

> **Not for client sites with customizations.** This does a full theme-directory replacement from a ZIP — clicking "Update Now" would overwrite any `Blocks/`, page templates, or `functions.php` changes you've made. For real client sites, use the `update-theme` AI skill instead, which syncs the shared scaffold via a `git merge` and never touches what you've built. Only wire this up for a deployment that deliberately wants full-replacement updates.

```php
// In functions.php or a plugin
new TAW\Core\ThemeUpdater([
    'slug'       => 'taw-theme',
    'github_url' => 'https://api.github.com/repos/your-username/taw-theme/releases/latest',
]);
```

Updates are cached for 6 hours to avoid GitHub rate limits. The updater prefers a built ZIP asset in the release; falls back to GitHub's auto-generated zipball.

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
├── bin/
│   └── taw                    # CLI entry point (Symfony Console — delegates to taw/core)
├── Blocks/                    # Your blocks — one folder per block, auto-discovered
│   ├── Menu/                  #   ← Boilerplate: site header + live-search overlay
│   │   ├── Menu.php           #     class TAW\Blocks\Menu\Menu extends Block
│   │   ├── index.php          #     Two-row header (logo, nav, contact, search icon)
│   │   ├── style.scss         #     Search overlay styles (.search-overlay BEM)
│   │   └── script.js          #     Alpine.js Menu component (search state + fetch)
│   └── Hero/
│       ├── Hero.php           #   class TAW\Blocks\Hero\Hero extends MetaBlock
│       ├── index.php          #   Template (receives extract()-ed vars from getData())
│       ├── style.scss         #   Optional per-block styles
│       └── script.js          #   Optional per-block JS
├── inc/
│   └── options.php            # Theme options page configuration
├── vendor/
│   └── taw/
│       └── core/              # ← Framework internals (managed via composer)
│           └── src/
│               ├── Core/
│               │   ├── Block/     #   BaseBlock, MetaBlock, Block, BlockRegistry, BlockLoader
│               │   ├── Metabox/   #   Metabox
│               │   ├── OptionsPage/ # OptionsPage
│               │   ├── Theme/     #   Theme, ThemeUpdater
│               │   ├── Menu/      #   Menu, MenuItem
│               │   ├── Rest/      #   SearchEndpoints, VisualEditorEndpoint
│               │   ├── Form/      #   Form, SubmissionsHandler
│               │   ├── Mail/      #   Mailer, MailTemplate, MailTester
│               │   └── Editor/    #   VisualEditor — inline frontend editor
│               ├── Helpers/   #   Framework, Image, Svg, Dump, Editor
│               ├── CLI/       #   make:block, export:block, import:block commands
│               └── Support/   #   utilities.php, performance.php (autoloaded)
├── resources/
│   ├── css/app.css            # Tailwind v4 directives (imported by app.js)
│   ├── scss/
│   │   ├── app.scss           # Global custom SCSS
│   │   ├── critical.scss      # Above-the-fold CSS (inlined in <head>)
│   │   └── _fonts.scss        # @font-face declarations
│   ├── fonts/                 # Self-hosted WOFF2 files
│   └── js/app.js              # Alpine.js + global JS entry point
├── public/build/              # Compiled assets (gitignored)
├── functions.php              # Developer customisations — theme setup, menus, performance config
├── vite.config.js             # Vite configuration
├── composer.json              # PHP deps — TAW\Blocks\ → Blocks/, requires taw/core
├── package.json               # Node deps + scripts
└── AGENTS.md                  # AI agent architecture docs
```

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

| Command                                              | Description                                              |
| ---------------------------------------------------- | -------------------------------------------------------- |
| `npm run dev`                                        | Start Vite dev server (port 5173) with HMR               |
| `npm run build`                                      | Production build → `public/build/` with hashed filenames |
| `composer install`                                   | Install PHP dependencies (including `taw/core`)          |
| `composer update taw/core`                           | Pull the latest framework update                         |
| `composer dump-autoload`                             | Rebuild PSR-4 classmap after adding new block classes    |
| `php bin/taw make:block Name`                        | Scaffold a new block (interactive if no flags)           |
| `php bin/taw make:block Name --type=meta`            | Scaffold a MetaBlock                                     |
| `php bin/taw make:block Name --type=ui`              | Scaffold a UI Block                                      |
| `php bin/taw make:block Name --group=sections`       | Scaffold inside `Blocks/sections/Name/`                  |
| `php bin/taw make:block Name --with-style`           | Include `style.scss`                                     |
| `php bin/taw make:block Name --with-script`          | Include `script.js`                                      |
| `php bin/taw make:block Name --force`                | Overwrite an existing block                              |
| `php bin/taw export:block Name`                      | Export a block as a portable ZIP                         |
| `php bin/taw export:block sections/Name -o ./out`    | Export grouped block to a custom directory               |
| `php bin/taw import:block path/to/Block.zip`         | Import a block from a ZIP                                |
| `php bin/taw import:block path.zip --group=sections` | Import into a specific group                             |
| `php bin/taw import:block path.zip --force`          | Overwrite if block already exists                        |

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
- **`update-theme`** — "update the theme" → pulls the latest shared scaffold from the canonical `taw-theme` repo via a real `git merge`, without ever touching your `Blocks/`, templates, or content
- **`studio`** — applies live CSS Studio visual edits back into source

**Live introspection** — `php bin/taw inspect` (or `--json`) reports the site's actual current state: registered blocks and their real metabox field schemas, registered forms, the installed `taw/core` version, whether `MetaboxOrder` is locked. An agent queries this instead of reconstructing it by grepping PHP.

**CI, not just convention** — `.github/workflows/ci.yml` runs `php -l`, `composer validate`, and a dedicated check that every `MetaBlock::getData()` matches the exact signature the framework requires (a mismatch there is a site-wide fatal, not a cosmetic bug).

**Live documentation lookup** — the `mcp__taw-docs__search_documentation` MCP tool, when available, searches the current framework docs directly rather than requiring a URL guess.

Two separate, independently-versioned sources of truth back all of this — don't conflate them:
- **[`taw-theme`](https://github.com/Relmaur/taw-theme)** — this scaffold. Synced into a client site via `update-theme`.
- **[`taw/core`](https://github.com/Relmaur/taw-core)** — the framework package. Synced via `composer update taw/core`.

---

## License

GPL v2. See [LICENSE.txt](LICENSE.txt) for details.
