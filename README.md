# TAW Theme

**A modern WordPress theme framework that makes building custom pages feel like assembling components — not fighting WordPress.**

TAW (Tailwind + Alpine + WordPress) gives you a clean, component-based block architecture on top of classic WordPress. Every section of a page — hero, stats, testimonials — is a self-contained block that owns its data, markup, styles, and scripts. Only the assets a page actually uses get loaded.

No Gutenberg blocks. No ACF dependency. No bloat. Just PHP classes, templates, and a convention that works.

The framework internals (block system, metabox engine, Vite bridge) ship as the **[`taw/core`](https://github.com/Relmaur/taw-core) composer package** — versioned independently so you can update the framework across all your TAW sites with a single `composer update taw/core`.

> **Framework API reference:** The [`taw/core` README](https://github.com/Relmaur/taw-core#readme) is the authoritative source for all framework internals — field types, Metabox config options, ViteLoader API, Visual Editor, and more. When this theme README and the `taw/core` README disagree, `taw/core` wins.

---

## Why TAW?

**Zero-config blocks.** Create a folder, drop in a class and a template — it's live. No registration, no `functions.php` edits, no build step required for new blocks.

**CLI scaffolding.** `php bin/taw make:block MyBlock --type=meta --with-style` creates the folder, class, template, and stylesheet in one command. Export and import blocks between projects as portable ZIPs.

**Scoped asset loading.** Each block can ship its own CSS and JS. Assets are only enqueued on pages that use that block. Your homepage doesn't load your blog's scripts.

**A real data layer.** MetaBlocks own their data through a bespoke metabox framework. No plugin dependencies for custom fields — field registration, rendering, retrieval, validation, and sanitization are all built in.

**Rich field types.** `text`, `textarea`, `wysiwyg`, `image`, `url`, `number`, `range`, `select`, `checkbox`, `color`, `group`, `repeater`, `post_select` — with conditional logic, tabbed layouts, and responsive grid placement.

**Built-in form system.** `Form` handles CSRF, honeypot spam protection, field validation, PRG redirect, and email delivery. Drop it into any template with a config array — no plugin required.

**Transactional email.** `Mailer` wraps `wp_mail()` with a fluent API and MJML/HTML template support. Write templates once in MJML, compile to HTML, deploy. Includes a `MailTester` admin page for testing templates without real submissions.

**SVG support.** `Svg::register()` enables sanitized SVG uploads in WordPress. Render as `<img>` or inline — both ways provided.

**Theme-level options.** `OptionsPage` brings the same config-driven field experience to site-wide settings stored in `wp_options` — tabbed UI, validation, and a clean retrieval API included.

**Modern frontend, classic WordPress.** Tailwind v4 for utility CSS, Alpine.js for interactivity, Vite for instant HMR — all wired into WordPress through a lightweight bridge. No React, no REST API overhead.

**AI-native DX.** Ships with `AGENTS.md`, `CLAUDE.md`, and Copilot/Windsurf instructions so any AI coding assistant understands the architecture out of the box.

**Visual Editor** *(Work in Progress)* — a live content page editor is under active development inside `taw/core` (`TAW\Core\Editor\VisualEditor`). It is not ready for production use.

---

## Quick Start

```bash

# Move to themes directory of your WordPress installation
cd wp-content/themes/

# This command will create the starter theme with the correct structure and dependencies. Replace <theme_name> with your desired theme folder name.
composer create-project taw/theme <theme_name>  --repository='{"type":"vcs","url":"https://github.com/Relmaur/taw-theme"}'

composer install       # PHP deps — pulls taw/core framework package
npm install            # Frontend dependencies
npm run dev            # Vite dev server with HMR
```

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

use TAW\Core\MetaBlock;
use TAW\Core\Metabox\Metabox;

class Hero extends MetaBlock
{
    protected string $id = 'hero';

    protected function registerMetaboxes(): void
    {
        new Metabox([
            'id'     => 'taw_hero',
            'title'  => 'Hero Section',
            'screen' => 'page',
            'fields' => [
                ['id' => 'hero_heading', 'label' => 'Heading', 'type' => 'text'],
                ['id' => 'hero_image',   'label' => 'Image',   'type' => 'image'],
            ],
        ]);
    }

    protected function getData(int $postId): array
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
use TAW\Core\BlockRegistry;

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

UI Blocks extend `TAW\Core\Block` and define a `defaults()` method instead of metaboxes:

```php
// Blocks/Button/Button.php
namespace TAW\Blocks\Button;

use TAW\Core\Block;

class Button extends Block
{
    protected string $id = 'button';

    protected function defaults(): array
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
use TAW\Core\BlockRegistry;

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

**Field types:** `text`, `textarea`, `wysiwyg`, `url`, `number`, `range`, `select`, `checkbox`, `color`, `image`, `files`, `group`, `repeater`, `post_select`

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
use TAW\Core\OptionsPage;

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
echo Image::render($hero_id, 'full', 'Hero image', ['above_fold' => true]);

// Regular image (lazy, low priority — the default)
echo Image::render(get_post_thumbnail_id(), 'large', 'Post thumbnail');

// With CSS class and custom sizes
echo Image::render($id, 'large', 'Team photo', [
    'class' => 'rounded-lg shadow-md',
    'sizes' => '(max-width: 768px) 100vw, 50vw',
]);

// With arbitrary extra attributes
echo Image::render($id, 'medium', 'Logo', [
    'attr' => ['id' => 'site-logo', 'data-hero' => 'true'],
]);
```

### Preload tag

Generate a `<link rel="preload">` for your single most important image. Call before `wp_head()` or hook at priority 1–2.

```php
echo Image::preload_tag($hero_id, 'full');
// → <link rel="preload" href="..." as="image" imagesrcset="..." imagesize="...">
```

---

## CSS / Asset Pipeline

### Entry points

| File                           | Role                                                              |
| ------------------------------ | ----------------------------------------------------------------- |
| `resources/js/app.js`          | Main JS entry — imports `app.css` and `app.scss`                  |
| `resources/css/app.css`        | Tailwind v4 directives (imported by `app.js`, not a Vite entry)   |
| `resources/scss/app.scss`      | Global custom SCSS — `@use 'fonts'` lives here                    |
| `resources/scss/critical.scss` | Above-the-fold CSS — inlined in `<head>` as a `<style>` tag       |
| `resources/scss/_fonts.scss`   | `@font-face` declarations — never add these to `critical.scss`    |
| `resources/fonts/`             | Self-hosted WOFF2 font files                                      |

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
├── style.scss   ← per-block CSS
└── script.js    ← per-block JS (loaded in footer, type="module")
```

The `BlockRegistry::queue('id')` call schedules assets for `<head>`. If you forget to queue, `BlockRegistry::render()` enqueues assets as a fallback (they land after `wp_head`, but a `<link>` is printed inline).

---

## Forms

`TAW\Core\Form\Form` is a configuration-driven frontend form that handles everything in one place: CSRF (nonces), honeypot spam protection, field validation, PRG redirect after success, and email delivery.

```php
use TAW\Core\Form\Form;

$form = new Form([
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
$form->render();
```

**Form field types:** `text`, `email`, `textarea`, `select`, and any standard HTML input type (e.g. `tel`, `date`). Fields support `required`, `placeholder`, `rows`.

If both `email.to_self.template` and `email.to_client.template` are set, delivery uses `Mailer` + `MailTemplate` (see below). Otherwise falls back to plain-text `wp_mail()`.

`TAW\Core\Form\SubmissionsHandler` stores successful submissions as a `taw_submission` CPT in WP Admin and optionally forwards them via webhook (n8n, Zapier, Make, etc.). Activate it in `functions.php`:

```php
new \TAW\Core\Form\SubmissionsHandler();
```

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
| [Tailwind CSS v4](https://tailwindcss.com/)                                | Utility-first CSS via the official Vite plugin                   |
| [Alpine.js v3](https://alpinejs.dev/)                                      | Lightweight reactivity for interactive components                |
| [Vite v7](https://vitejs.dev/)                                             | Build tool with instant HMR in development                       |
| [SCSS](https://sass-lang.com/)                                             | Optional custom styles — global and per-block                    |
| [Symfony Console](https://symfony.com/doc/current/components/console.html) | CLI scaffolding commands (`bin/taw`) — shipped inside `taw/core` |
| PHP 8.1+                                                                   | PSR-4 autoloading via Composer                                   |
| [`taw/core`](https://github.com/Relmaur/taw-core)                          | Versioned composer package containing all framework internals    |

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
│               │   └── Editor/    #   VisualEditor (WIP)
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

## AI-Friendly

This repo ships with architecture documentation for AI coding assistants:

- **`AGENTS.md`** — comprehensive architecture guide (Claude Code, Cursor, generic agents)
- **`CLAUDE.md`** — Claude Code-specific instructions
- **`.github/copilot-instructions.md`** — GitHub Copilot instructions
- **`.windsurfrules`** — Windsurf/Codeium instructions

Any LLM-powered tool will automatically pick up the project's conventions, naming patterns, and anti-patterns. Point your AI at the repo and start building.

---

## License

GPL v2. See [LICENSE.txt](LICENSE.txt) for details.
