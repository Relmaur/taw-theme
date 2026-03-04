# TAW Theme

**A modern WordPress theme framework that makes building custom pages feel like assembling components — not fighting WordPress.**

TAW (Tailwind + Alpine + WordPress) gives you a clean, component-based block architecture on top of classic WordPress. Every section of a page — hero, stats, testimonials — is a self-contained block that owns its data, markup, styles, and scripts. Only the assets a page actually uses get loaded.

No Gutenberg blocks. No ACF dependency. No bloat. Just PHP classes, templates, and a convention that works.

The framework internals (block system, metabox engine, Vite bridge) ship as the **[`taw/core`](https://github.com/Relmaur/taw-core) composer package** — versioned independently so you can update the framework across all your TAW sites with a single `composer update taw/core`.

---

## Why TAW?

**Zero-config blocks.** Create a folder, drop in a class and a template — it's live. No registration, no `functions.php` edits, no build step required for new blocks.

**CLI scaffolding.** `php bin/taw make:block MyBlock --type=meta --with-style` creates the folder, class, template, and stylesheet in one command. Export and import blocks between projects as portable ZIPs.

**Scoped asset loading.** Each block can ship its own CSS and JS. Assets are only enqueued on pages that use that block. Your homepage doesn't load your blog's scripts.

**A real data layer.** MetaBlocks own their data through a bespoke metabox framework. No plugin dependencies for custom fields — field registration, rendering, retrieval, validation, and sanitization are all built in.

**Rich field types.** `text`, `textarea`, `wysiwyg`, `image`, `url`, `number`, `range`, `select`, `checkbox`, `color`, `group`, `repeater`, `post_select` — with conditional logic, tabbed layouts, and responsive grid placement.

**Theme-level options.** `OptionsPage` brings the same config-driven field experience to site-wide settings stored in `wp_options` — tabbed UI, validation, and a clean retrieval API included.

**Modern frontend, classic WordPress.** Tailwind v4 for utility CSS, Alpine.js for interactivity, Vite for instant HMR — all wired into WordPress through a lightweight bridge. No React, no REST API overhead.

**AI-native DX.** Ships with `AGENTS.md`, `CLAUDE.md`, and Copilot/Windsurf instructions so any AI coding assistant understands the architecture out of the box.

**Visual Builder** *(Work in Progress)* — a live drag-and-drop page builder is under active development inside `taw/core` (`TAW\Core\VisualEditor`). It is not ready for production use.

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

## Metabox Field Types

All fields share common options. The `type` key selects the field type.

### Common field options

| Option        | Type       | Description                                                              |
| ------------- | ---------- | ------------------------------------------------------------------------ |
| `id`          | `string`   | Unique field ID (without prefix)                                         |
| `label`       | `string`   | Label shown above the field                                              |
| `type`        | `string`   | Field type (see below)                                                   |
| `description` | `string`   | Help text shown below the field                                          |
| `placeholder` | `string`   | Input placeholder text                                                   |
| `default`     | `mixed`    | Default value                                                            |
| `required`    | `bool`     | Marks field as required — validation runs on save                        |
| `validate`    | `callable` | Custom validation callback: `fn($value): true\|string`                   |
| `sanitize`    | `string`   | Set to `'code'` to preserve raw HTML for `unfiltered_html` users         |
| `width`       | `string`   | Column width as percentage (e.g., `'50'`, `'33.33'`). Default `'100'`   |
| `conditions`  | `array`    | Conditional logic — show/hide based on other field values (see below)    |

### Field type reference

| Type           | Description                                                     | Extra options                                        |
| -------------- | --------------------------------------------------------------- | ---------------------------------------------------- |
| `text`         | Single-line text input                                          | `placeholder`                                        |
| `textarea`     | Multi-line text area                                            | `placeholder`, `rows` (default 4)                    |
| `wysiwyg`      | WordPress rich-text editor (TinyMCE)                            | `rows` (default 8), `media_buttons`, `teeny`         |
| `url`          | URL input with browser validation                               | `placeholder`                                        |
| `number`       | Numeric input                                                   | `min`, `max`, `step`, `placeholder`                  |
| `range`        | Slider with a live value display                                | `min`, `max`, `step`, `unit` (e.g. `'px'`), `default` |
| `select`       | Dropdown list                                                   | `options` (assoc array: `value => label`)            |
| `checkbox`     | Toggle switch (saves `'1'` or `'0'`)                            |                                                      |
| `color`        | WordPress color picker (hex)                                    | `default`                                            |
| `image`        | WordPress media library image picker (saves attachment ID)      |                                                      |
| `group`        | Flat group of sub-fields sharing a key prefix                   | `fields` (array of field defs)                       |
| `repeater`     | Dynamic list of rows — each row has the same set of sub-fields  | `fields`, `min`, `max`                               |
| `post_select`  | Searchable post picker powered by the TAW REST endpoint         | `post_type`, `multiple`, `max`                       |

### Example: full field set

```php
new Metabox([
    'id'     => 'taw_hero',
    'title'  => 'Hero Section',
    'screen' => 'page',
    'fields' => [
        // Layout with widths
        ['id' => 'heading',     'label' => 'Heading',     'type' => 'text',     'width' => '50', 'required' => true],
        ['id' => 'subheading',  'label' => 'Subheading',  'type' => 'text',     'width' => '50'],

        // Rich text
        ['id' => 'body',        'label' => 'Body',        'type' => 'wysiwyg', 'rows' => 6],

        // Select with options
        ['id' => 'style',       'label' => 'Style',       'type' => 'select',
         'options' => ['light' => 'Light', 'dark' => 'Dark']],

        // Toggle
        ['id' => 'show_cta',    'label' => 'Show CTA',    'type' => 'checkbox'],

        // Color picker
        ['id' => 'bg_color',    'label' => 'Background',  'type' => 'color', 'default' => '#ffffff'],

        // Range slider
        ['id' => 'min_height',  'label' => 'Min Height',  'type' => 'range',
         'min' => 400, 'max' => 900, 'step' => 50, 'unit' => 'px', 'default' => 600],

        // Image
        ['id' => 'image',       'label' => 'Background Image', 'type' => 'image'],

        // Post selector (single)
        ['id' => 'featured_post', 'label' => 'Featured Post', 'type' => 'post_select', 'post_type' => 'post'],

        // Post selector (multi, with max)
        ['id' => 'related',     'label' => 'Related Posts', 'type' => 'post_select',
         'post_type' => 'post', 'multiple' => true, 'max' => 3],
    ],
]);
```

---

## Conditional Fields

Fields can show or hide based on other field values. Conditions are evaluated live in the admin UI using Alpine.js, and also server-side during save.

```php
'fields' => [
    ['id' => 'show_cta',   'label' => 'Show CTA',    'type' => 'checkbox'],
    ['id' => 'cta_text',   'label' => 'CTA Text',    'type' => 'text',
     'conditions' => [
         ['field' => 'show_cta', 'operator' => '==', 'value' => '1'],
     ]],
    ['id' => 'cta_url',    'label' => 'CTA URL',     'type' => 'url',
     'conditions' => [
         ['field' => 'show_cta', 'operator' => '==', 'value' => '1'],
     ]],
],
```

**Supported operators:** `==`, `!=`, `contains`, `empty`, `!empty`

All conditions in the array use AND logic — every condition must pass for the field to show.

---

## Tabbed Metaboxes

Group fields into tabs using the `tabs` key. Each tab references field IDs from the `fields` array.

```php
new Metabox([
    'id'     => 'taw_hero',
    'title'  => 'Hero Section',
    'screen' => 'page',
    'fields' => [
        ['id' => 'heading',   'label' => 'Heading',   'type' => 'text'],
        ['id' => 'image',     'label' => 'Image',     'type' => 'image'],
        ['id' => 'bg_color',  'label' => 'Background','type' => 'color'],
        ['id' => 'show_cta',  'label' => 'Show CTA',  'type' => 'checkbox'],
        ['id' => 'cta_text',  'label' => 'CTA Text',  'type' => 'text'],
    ],
    'tabs' => [
        ['label' => 'Content', 'fields' => ['heading', 'image']],
        ['label' => 'Design',  'fields' => ['bg_color']],
        ['label' => 'CTA',     'fields' => ['show_cta', 'cta_text']],
    ],
]);
```

### Other Metabox config options

| Option     | Default      | Description                                                       |
| ---------- | ------------ | ----------------------------------------------------------------- |
| `screen`   | `'page'`     | Post type to attach to (e.g. `'post'`, `'page'`, custom type)    |
| `context`  | `'normal'`   | Position: `'normal'`, `'side'`, `'advanced'`                     |
| `priority` | `'high'`     | Order: `'high'`, `'default'`, `'low'`                            |
| `prefix`   | `'_taw_'`    | Meta key prefix applied to all field IDs                         |
| `icon`     | *(none)*     | SVG string — displayed as the metabox icon                        |
| `show_on`  | *(none)*     | `callable(WP_Post): bool` — return `false` to hide the metabox   |

---

## Metabox Retrieval API

Use these static helpers inside `getData()` or anywhere in your templates.

```php
use TAW\Core\Metabox\Metabox;

// Plain text / any scalar value
$heading = Metabox::get($postId, 'hero_heading');

// Checkbox → boolean (saves as '1'/'0', returns bool)
$showCta = Metabox::get_bool($postId, 'show_cta');

// Image attachment ID → URL
$imageUrl = Metabox::get_image_url($postId, 'hero_image', 'large');

// Color with fallback
$bgColor = Metabox::get_color($postId, 'bg_color', '#ffffff');

// post_select → array of post IDs (works for single and multi)
$featuredId  = Metabox::get_posts($postId, 'featured_post')[0] ?? null;
$relatedIds  = Metabox::get_posts($postId, 'related_posts');

// repeater → array of rows, each an associative array
$teamMembers = Metabox::get_repeater($postId, 'team_members');
foreach ($teamMembers as $member) {
    echo esc_html($member['name'] ?? '');
    echo esc_html($member['role'] ?? '');
}
```

Inside a `MetaBlock` you can also use the convenience wrappers (which delegate to `Metabox::get*`):

```php
protected function getData(int $postId): array
{
    return [
        'heading'   => $this->getMeta($postId, 'hero_heading'),
        'image_url' => $this->getImageUrl($postId, 'hero_image', 'large'),
    ];
}
```

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

Sub-fields support the same types as top-level fields (including `image`, `color`, `post_select`, etc.). Rows are drag-and-drop sortable and individually collapsible.

Retrieve with `Metabox::get_repeater()` (see above).

---

## Group Field

The `group` type nests related sub-fields under a shared key prefix. Unlike a repeater, there is always exactly one row.

```php
[
    'id'    => 'hero_cta',
    'label' => 'CTA Button',
    'type'  => 'group',
    'fields' => [
        ['id' => 'text', 'label' => 'Text', 'type' => 'text',  'width' => '50'],
        ['id' => 'url',  'label' => 'URL',  'type' => 'url',   'width' => '50'],
    ],
]
```

Group sub-fields are stored as separate meta keys: `_taw_hero_cta_text`, `_taw_hero_cta_url`. Retrieve them with `Metabox::get($postId, 'hero_cta_text')`.

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

### Asset helper functions (from `vite-loader.php`)

These are autoloaded globally — use them anywhere in your templates.

```php
// Resolve a theme asset URL — returns hashed prod URL or dev server URL
$fontUrl = vite_asset_url('resources/fonts/Inter-Regular.woff2');

// Check if the Vite dev server is running
if (vite_is_dev()) { /* dev-only logic */ }
```

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

## Theme Options — OptionsPage

See [Theme Options](#theme-options) above for the full API.

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
| Block system      | `BaseBlock` → `MetaBlock` / `Block` class hierarchy (in `taw/core`)                     |
| Block discovery   | `BlockLoader::loadAll()` — recursive scan, any nesting depth, no registration needed    |
| Metaboxes         | Bespoke config-driven framework (`TAW\Core\Metabox\Metabox` in `taw/core`)              |
| Options page      | Config-driven `OptionsPage` — stores to `wp_options` (in `taw/core`)                    |
| Navigation menus  | `Menu` / `MenuItem` typed tree (`TAW\Core\Menu` in `taw/core`)                          |
| REST API          | `taw/v1/search-posts` endpoint (`TAW\Core\Rest` in `taw/core`)                          |
| Asset pipeline    | `vite-loader.php` (autoloaded from `taw/core`) + `BlockRegistry` queue system           |
| Critical CSS      | `critical.scss` compiled and inlined in `<head>`                                        |
| Async CSS         | Main CSS loaded non-render-blocking via `media="print"` + `onload` swap                 |
| Fonts             | Self-hosted WOFF2 with preloads via `vite_asset_url()` (autoloaded from `taw/core`)     |
| Performance       | `performance.php` removes WP bloat, adds resource hints (autoloaded from `taw/core`)    |
| Theme updates     | GitHub Releases-based auto-updater (`TAW\Core\ThemeUpdater` in `taw/core`)              |
| Framework updates | `composer update taw/core` — update across all sites independently                      |

---

## Project Structure

```
taw-theme/
├── bin/
│   └── taw                    # CLI entry point (Symfony Console — delegates to taw/core)
├── Blocks/                    # Your blocks — one folder per block, auto-discovered
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
│               ├── Core/      #   BaseBlock, MetaBlock, Block, BlockRegistry, BlockLoader
│               │              #   Metabox, OptionsPage, ThemeUpdater, Framework
│               │              #   Menu/, Rest/
│               ├── Helpers/   #   Image helper
│               ├── CLI/       #   make:block, export:block, import:block commands
│               └── Support/   #   vite-loader.php, performance.php (autoloaded)
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
