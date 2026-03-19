# AGENTS.md — AI Agent Guide for TAW Theme

> **TAW** = Tailwind + Alpine + WordPress
> A classic WordPress theme with a component-based block architecture, Vite asset pipeline, and a bespoke metabox framework.

---

## Quick Orientation

| Path | Purpose |
|---|---|
| `vendor/taw/core/src/Core/Block/` | Block system — `BaseBlock`, `Block`, `MetaBlock`, `BlockLoader`, `BlockRegistry` (**read-only**) |
| `vendor/taw/core/src/Core/Metabox/` | Metabox framework (`Metabox`) |
| `vendor/taw/core/src/Core/OptionsPage/` | Options page framework (`OptionsPage`) |
| `vendor/taw/core/src/Core/Theme/` | `Theme`, `ThemeUpdater` |
| `vendor/taw/core/src/Core/Menu/` | Navigation menu object model (`Menu`, `MenuItem`) |
| `vendor/taw/core/src/Core/Rest/` | REST API endpoints (`SearchEndpoints`, `VisualEditorEndpoint`) |
| `vendor/taw/core/src/Core/Form/` | Frontend form system (`Form`, `SubmissionsHandler`) |
| `vendor/taw/core/src/Core/Mail/` | Email system (`Mailer`, `MailTemplate`, `MailTester`) |
| `vendor/taw/core/src/Core/Editor/` | Visual Editor engine (⚠️ WIP) |
| `vendor/taw/core/src/Helpers/` | Utility helpers (`Framework`, `Image`, `Svg`, `Dump`, `Editor`) |
| `vendor/taw/core/src/CLI/` | Symfony Console commands (`make:block`, `export:block`, `import:block`) |
| `vendor/taw/core/src/Support/` | `utilities.php`, `performance.php` — autoloaded by composer |
| `Blocks/` | Dev block collection — one folder per block, auto-discovered |
| `inc/options.php` | Theme-level options page configuration |
| `resources/js/app.js` | Alpine.js + global JS — imports Tailwind CSS and custom SCSS |
| `resources/css/app.css` | Tailwind v4 directives (`@import "tailwindcss"`) — imported by `app.js` |
| `resources/scss/app.scss` | Global custom SCSS (fonts, overrides) — imported by `app.js` |
| `resources/scss/critical.scss` | Above-the-fold CSS — compiled and inlined in `<head>` |
| `resources/scss/_fonts.scss` | `@font-face` declarations for self-hosted fonts |
| `resources/fonts/` | Self-hosted WOFF2 font files |
| `functions.php` | Developer customisations — theme supports, nav menus, performance config. Calls `TAW\Core\Theme::boot()`. |

> **Important:** `TAW\Core`, `TAW\Helpers`, and `TAW\CLI` classes live inside the `taw/core` composer package (`vendor/taw/core/src/`), **not** in `inc/`. The theme's `inc/` only holds `options.php` and any Metabox view templates. Do not edit files inside `vendor/`.

---

## The `taw/core` Package

Framework internals are maintained as a standalone composer package at **`https://github.com/Relmaur/taw-core`** and installed at `vendor/taw/core/`.

This separation means:
- The core framework can be versioned and updated independently of any theme.
- Themes declare a version constraint in `composer.json` (`"taw/core": "^1.0"`).
- To pull a framework update: `composer update taw/core`.
- To change framework behaviour, work in the `taw-core` repo, tag a release, then update the constraint here.

**Package structure:**
```
vendor/taw/core/
├── src/
│   ├── Core/
│   │   ├── Block/
│   │   │   ├── BaseBlock.php        # Abstract base — asset loading, template rendering
│   │   │   ├── MetaBlock.php        # Data-owning blocks (metaboxes + post_meta)
│   │   │   ├── Block.php            # Presentational blocks (receives props)
│   │   │   ├── BlockRegistry.php    # Static registry — queue, enqueue, render
│   │   │   └── BlockLoader.php      # Auto-discovers blocks by scanning Blocks/
│   │   ├── Metabox/
│   │   │   └── Metabox.php          # Config-driven metabox framework
│   │   ├── OptionsPage/
│   │   │   └── OptionsPage.php      # Config-driven admin options page (wp_options)
│   │   ├── Theme/
│   │   │   ├── Theme.php            # Theme bootstrap
│   │   │   └── ThemeUpdater.php     # GitHub Releases-based auto-updater
│   │   ├── Menu/
│   │   │   ├── Menu.php             # Nav menu tree factory
│   │   │   └── MenuItem.php         # Typed menu item with active-state helpers
│   │   ├── Rest/
│   │   │   ├── SearchEndpoints.php      # GET taw/v1/search-posts
│   │   │   └── VisualEditorEndpoint.php # Visual Builder REST endpoint (⚠️ WIP)
│   │   ├── Form/
│   │   │   ├── Form.php             # Config-driven frontend form (CSRF, honeypot, PRG, email)
│   │   │   └── SubmissionsHandler.php # CPT submission storage + webhook forwarding
│   │   ├── Mail/
│   │   │   ├── Mailer.php           # Fluent wp_mail() wrapper with template support
│   │   │   ├── MailTemplate.php     # MJML/HTML template compiler ({{variable}} syntax)
│   │   │   └── MailTester.php       # Admin page: Tools → Test Emails
│   │   └── Editor/
│   │       └── VisualEditor.php     # Visual Builder engine (⚠️ WIP)
│   ├── CLI/                         # Symfony Console commands
│   │   ├── MakeBlockCommand.php
│   │   ├── ExportBlockCommand.php
│   │   └── ImportBlockCommand.php
│   ├── Helpers/
│   │   ├── Framework.php        # Path/URL resolver (Framework::path(), Framework::themePath() …)
│   │   ├── Image.php            # Performance-optimised <img> tag generator
│   │   ├── Svg.php              # SVG upload enablement, sanitization, inline/img rendering
│   │   ├── Dump.php             # Debug panel helper (WP_DEBUG only)
│   │   └── Editor.php           # Visual editor field annotation helpers
│   └── Support/
│       ├── utilities.php        # Global helpers: vite_asset_url(), dump(), taw_editable() … (autoloaded)
│       └── performance.php      # Resource hints, font preloads, WP bloat removal (autoloaded)
└── composer.json
```

---

## Architecture: The Block System

### Class Hierarchy

```
BaseBlock (abstract)
├── MetaBlock (abstract) — owns data via metaboxes, fetches from post_meta
│   └── Hero, Stats, Testimonials, etc.
└── Block (abstract) — presentational, receives data as props
    └── Button, Card, Badge, etc.
```

### Key Classes (all from `taw/core`)

| Class | Role |
|---|---|
| `TAW\Core\Block\BaseBlock` | Reflection-based auto-discovery of component directory, asset enqueuing (CSS/JS), template rendering via `extract()` |
| `TAW\Core\Block\MetaBlock` | Extends BaseBlock. Registers metaboxes in constructor, provides `getData(int $postId)` and `render(?int $postId)` |
| `TAW\Core\Block\Block` | Extends BaseBlock. Defines `defaults()` for props, provides `render(array $props)` |
| `TAW\Core\Block\BlockRegistry` | Static registry for MetaBlocks. Supports `register()`, `queue()`, `render()`, `enqueueQueuedAssets()` |
| `TAW\Core\Block\BlockLoader` | Auto-discovers all MetaBlock classes by scanning `Blocks/*/` directories |
| `TAW\Helpers\Framework` | Path/URL resolver. `Framework::path()` → package root; `Framework::themePath()` → active theme root |
| `TAW\Core\Metabox\Metabox` | Configuration-driven metabox framework. Field registration, rendering, saving, and static retrieval helpers |
| `TAW\Core\OptionsPage\OptionsPage` | Configuration-driven admin options page. Same field format as Metabox but stores to `wp_options` |
| `TAW\Core\Theme\ThemeUpdater` | GitHub Releases-based automatic theme updater |
| `TAW\Core\Menu\Menu` | Navigation menu object model — wraps WP nav menus into a typed tree |
| `TAW\Core\Menu\MenuItem` | Individual menu item with typed getters (url, title, children, active state) |
| `TAW\Core\Rest\SearchEndpoints` | REST API: `GET taw/v1/search-posts` — post search for authenticated users |
| `TAW\Core\Form\Form` | Configuration-driven frontend form — CSRF, honeypot, validation, PRG redirect, email delivery |
| `TAW\Core\Form\SubmissionsHandler` | Saves form submissions as `taw_submission` CPT; webhook forwarding (n8n, Zapier, Make) |
| `TAW\Core\Mail\Mailer` | Fluent `wp_mail()` wrapper with HTML template support |
| `TAW\Core\Mail\MailTemplate` | File-based email template compiler. Looks for `mails/html/{name}.html`; falls back to MJML at runtime |
| `TAW\Core\Mail\MailTester` | Admin page under Tools → Test Emails for testing compiled templates |
| `TAW\Core\Editor\VisualEditor` | Visual Builder engine (**WIP**) |
| `TAW\Core\Rest\VisualEditorEndpoint` | REST endpoint for the Visual Builder (**WIP**) |
| `TAW\Helpers\Image` | Performance-optimized `<img>` tag generator with above/below-fold attributes |
| `TAW\Helpers\Svg` | SVG upload enablement, sanitization on upload, and inline/img rendering |
| `TAW\Helpers\Dump` | Debug panel helper — `dump()` / `dd()` global functions, renders in `wp_footer` (WP_DEBUG only) |
| `TAW\Helpers\Editor` | Visual editor field annotation — `Editor::field()`, `Editor::attrs()`, `Editor::section()` |

### Naming Convention (CRITICAL)

Every block follows this exact convention — the folder name **must** match the class name:

```
Blocks/{Name}/{Name}.php    → class TAW\Blocks\{Name}\{Name}
Blocks/{Name}/index.php     → Template file
Blocks/{Name}/style.css     → Optional stylesheet (or style.scss)
Blocks/{Name}/script.js     → Optional JavaScript
```

Example:
```
Blocks/Hero/Hero.php        → class TAW\Blocks\Hero\Hero extends MetaBlock
Blocks/Hero/index.php       → Template receives extracted variables
Blocks/Hero/style.css       → Enqueued only when Hero renders
```

BlockLoader relies on this convention for auto-discovery. Breaking it will silently skip the block.

### Two Block Types

**MetaBlock** (data-owning sections):
- Registered in `BlockRegistry` via `BlockLoader::loadAll()`
- Owns metaboxes → appears in WP admin editor
- Fetches its own data from `post_meta`
- Rendered via `BlockRegistry::render('hero')`

**Block** (presentational UI components):
- NOT registered in the registry
- Receives data as props
- Instantiated directly where needed: `(new Button())->render(['text' => 'Click'])`

### Asset Loading Strategy — The Queue Pattern

Assets are loaded conditionally per page. The timing chain is:

```
functions.php        → BlockLoader::loadAll() registers all MetaBlocks
template file        → BlockRegistry::queue('hero', 'stats') ← BEFORE get_header()
get_header()         → wp_enqueue_scripts fires
                       → BlockRegistry::enqueueQueuedAssets() (only queued blocks' CSS/JS)
                     → wp_head() outputs <link>/<script> in <head>
template body        → BlockRegistry::render('hero') outputs HTML only
get_footer()         → wp_footer()
```

**Template pattern:**
```php
<?php
use TAW\Core\BlockRegistry;

// 1. Queue blocks BEFORE get_header (assets land in <head>)
BlockRegistry::queue('hero', 'stats');

get_header();
?>

<?php BlockRegistry::render('hero'); ?>
<?php BlockRegistry::render('stats'); ?>

<?php get_footer(); ?>
```

**Safety fallback:** If `render()` is called without prior `queue()`, styles are printed inline in the body via `did_action('wp_head')` check. This works but is suboptimal (potential FOUC). Always prefer `queue()` first.

---


## Creating a New MetaBlock

### Step 1: Create the directory and class

```php
<?php
// Blocks/Features/Features.php

declare(strict_types=1);

namespace TAW\Blocks\Features;

use TAW\Core\MetaBlock;
use TAW\Core\Metabox\Metabox;

class Features extends MetaBlock
{
    protected string $id = 'features';

    protected function registerMetaboxes(): void
    {
        new Metabox([
            'id'     => 'taw_features',
            'title'  => 'Features Section',
            'screen' => 'page',
            'fields' => [
                [
                    'id'    => 'features_heading',
                    'label' => 'Heading',
                    'type'  => 'text',
                ],
                // Add more fields...
            ],
        ]);
    }

    protected function getData(int $postId): array
    {
        return [
            'heading' => $this->getMeta($postId, 'features_heading'),
        ];
    }
}
```

### Step 2: Create the template

```php
<?php
// Blocks/Features/index.php

/** @var string $heading */

if (empty($heading)) return;
?>

<section class="features">
    <h2><?php echo esc_html($heading); ?></h2>
</section>
```

### Step 3: Optionally add style.css or style.scss

The block auto-discovers and enqueues these when rendered. SCSS is prioritized over CSS if both exist.

### Step 4: That's it

`BlockLoader::loadAll()` auto-discovers the block. No changes to `functions.php` needed. Just `queue()` and `render()` it in a template.

> **Shortcut:** Use the CLI scaffolder instead of writing files manually:
> ```bash
> php bin/taw make:block Features --type=meta --with-style
> ```

---

## Creating a New UI Block

```php
<?php
// Blocks/Card/Card.php

declare(strict_types=1);

namespace TAW\Blocks\Card;

use TAW\Core\Block;

class Card extends Block
{
    protected string $id = 'card';

    protected function defaults(): array
    {
        return [
            'title'       => '',
            'description' => '',
            'image_url'   => '',
        ];
    }
}
```

Usage in any template:
```php
<?php (new TAW\Blocks\Card\Card())->render([
    'title'       => 'My Card',
    'description' => 'Card content here',
]); ?>
```

---

## The Metabox Framework

Provided by `taw/core` (namespace `TAW\Core\Metabox\Metabox`). Configuration-driven, supports:

**Field types:** `text`, `textarea`, `wysiwyg`, `url`, `number`, `range`, `select`, `image`, `group`, `checkbox`, `color`, `repeater`, `post_select`

**Features:**
- `show_on` callback for conditional display (e.g., front page only)
- `tabs` for grouped field organization
- `width` property for side-by-side fields (e.g., `'width' => '50'`)
- `sanitize` => `'code'` for raw code snippet fields
- `group` type for nested field groups (e.g., CTA with text + URL)
- `repeater` type for dynamic repeatable row groups
- `post_selector` type for selecting related posts via a search-as-you-type UI (uses `taw/v1/search-posts`)
- `color` type renders a native color picker
- `checkbox` type renders a boolean toggle

**Meta key pattern:** `_taw_{field_id}` (prefix configurable, default `_taw_`)

**Static helpers:**
```php
Metabox::get(int $postId, string $fieldId, string $prefix = '_taw_'): mixed
Metabox::get_bool(int $postId, string $fieldId): bool
Metabox::get_image_url(int $postId, string $fieldId, string $size = 'full'): string
Metabox::get_color(int $postId, string $fieldId, string $fallback = ''): string
Metabox::get_posts(int $postId, string $fieldId): array   // post_select → array of IDs
Metabox::get_repeater(int $postId, string $fieldId): array // repeater → array of rows
```

---

## Form System

Provided by `taw/core` (namespace `TAW\Core\Form`).

### `Form`

Configuration-driven frontend form. Handles CSRF protection (nonces), honeypot spam guard, field sanitization & validation, PRG redirect after success, and email delivery (plain-text fallback or MJML templates via `Mailer`).

```php
use TAW\Core\Form\Form;

$form = new Form([
    'id'                   => 'contact',
    'submit_label'         => 'Send Message',
    'submit_loading_label' => 'Sending...',
    'messages' => ['success' => "Thanks! We'll be in touch."],
    'email' => [
        'to_self'   => ['subject' => 'New contact',      'template' => 'contact-self'],
        'to_client' => ['subject' => 'Got your message', 'template' => 'contact-client'],
    ],
    'fields' => [
        ['id' => 'name',    'label' => 'Name',    'type' => 'text',     'required' => true],
        ['id' => 'email',   'label' => 'Email',   'type' => 'email',    'required' => true],
        ['id' => 'service', 'label' => 'Service', 'type' => 'select',
         'options' => ['web' => 'Web Design', 'seo' => 'SEO']],
        ['id' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
    ],
]);
$form->render();
```

**Form field types:** `text`, `email`, `textarea`, `select`, and any standard HTML input type (e.g. `tel`, `date`). Fields support `required`, `placeholder`, `rows` (textarea).

If `email.to_self.template` and `email.to_client.template` are both set, delivery uses `Mailer` + `MailTemplate`. Otherwise falls back to a plain-text `wp_mail()`.

### `SubmissionsHandler`

Registers a `taw_submission` CPT for viewing submissions in WP Admin. Also provides a Settings → Form Webhook page for a webhook URL + HMAC secret.

```php
// In functions.php — register once to activate submission storage
new \TAW\Core\Form\SubmissionsHandler();
```

Submissions are saved automatically by `Form::process()` after a successful send.

---

## Mail System

Provided by `taw/core` (namespace `TAW\Core\Mail`).

### `Mailer`

Fluent wrapper around `wp_mail()`.

```php
use TAW\Core\Mail\Mailer;

(new Mailer())
    ->to('user@example.com')
    ->subject('Welcome!')
    ->template('welcome')                   // looks for mails/html/welcome.html
    ->setVariables(['name' => 'Jane', 'site_name' => 'MyBrand'])
    ->send();

// Optional extras
(new Mailer())
    ->to($email)
    ->subject($subject)
    ->template($template)
    ->setVariables($vars)
    ->from('noreply@mybrand.com', 'MyBrand')
    ->addHeader('Reply-To: support@mybrand.com')
    ->attach('/path/to/attachment.pdf')
    ->send();
```

### `MailTemplate`

Looks for templates in `mails/html/{name}.html` (pre-compiled HTML, used in production) or `mails/{name}.mjml` (compiled at runtime via `spatie/mjml-php` — dev only). The base directory can be overridden with the `taw_mail_templates_dir` filter.

Use `{{variable_name}}` placeholders in templates. `all_fields` is auto-populated with a labelled HTML list of all submitted form values.

### `MailTester`

WP Admin page (Tools → Test Emails) for sending test emails against compiled templates.

```php
// In functions.php
(new \TAW\Core\Mail\MailTester())->register();
```

---

## Options Page

Provided by `taw/core` (namespace `TAW\Core\OptionsPage`).

A configuration-driven admin settings page. Uses the **same field format as Metabox** but stores values in `wp_options` instead of `post_meta`. Supports tabs, all standard field types, and validation.

**Usage:**
```php
new OptionsPage([
    'id'         => 'taw_settings',
    'title'      => 'TAW Settings',
    'menu_title' => 'TAW Settings',
    'icon'       => 'dashicons-screenoptions',
    'position'   => 2,
    'fields'     => [
        ['id' => 'company_name',  'label' => 'Company Name',      'type' => 'text'],
        ['id' => 'company_phone', 'label' => 'Phone Number',      'type' => 'text'],
        ['id' => 'footer_text',   'label' => 'Footer Copyright',  'type' => 'textarea'],
        ['id' => 'social_facebook', 'label' => 'Facebook URL',    'type' => 'url'],
    ],
    'tabs' => [
        ['id' => 'general', 'label' => 'General', 'fields' => ['company_name', 'company_phone']],
        ['id' => 'footer',  'label' => 'Footer',  'fields' => ['footer_text']],
        ['id' => 'social',  'label' => 'Social',  'fields' => ['social_facebook']],
    ],
]);
```

**Retrieval:**
```php
OptionsPage::get('company_name');                    // returns string
OptionsPage::get_image_url('company_logo', 'large'); // returns URL string
```

**Option key pattern:** `_taw_{field_id}` (same prefix convention as Metabox)

The theme's default options page is configured in `inc/options.php` and required from `functions.php`. To add a new options page, create a new file in `inc/` and require it in `functions.php`.

---

## Navigation Menu System

Provided by `taw/core` (namespace `TAW\Core\Menu`).

Wraps WordPress's flat nav menu data into a typed tree structure. Eliminates `wp_nav_menu()` in favour of full control over markup.

**Classes:**
- `Menu` — the menu container. Factory method `Menu::get(string $location)` retrieves and builds the tree.
- `MenuItem` — a single item with typed getters.

**Usage in templates:**
```php
<?php
use TAW\Core\Menu\Menu;

$menu = Menu::get('primary');
if ($menu && $menu->hasItems()):
?>
<nav>
    <ul>
        <?php foreach ($menu->items() as $item): ?>
            <li class="<?php echo $item->isActive() ? 'active' : ''; ?>">
                <a href="<?php echo esc_url($item->url()); ?>"
                   target="<?php echo esc_attr($item->target()); ?>">
                    <?php echo esc_html($item->title()); ?>
                </a>
                <?php if ($item->hasChildren()): ?>
                    <ul>
                        <?php foreach ($item->children() as $child): ?>
                            <li><a href="<?php echo esc_url($child->url()); ?>"><?php echo esc_html($child->title()); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
<?php endif; ?>
```

**`MenuItem` API:**
| Method | Returns | Description |
|---|---|---|
| `title()` | `string` | Display label |
| `url()` | `string` | Destination URL |
| `target()` | `string` | `_self` or `_blank` |
| `openInNewTab()` | `bool` | True when target is `_blank` |
| `isActive()` | `bool` | Current page |
| `isActiveParent()` | `bool` | Direct parent of current page |
| `isActiveAncestor()` | `bool` | Ancestor of current page |
| `isInActiveTrail()` | `bool` | Any active state |
| `hasChildren()` | `bool` | Has sub-items |
| `children()` | `MenuItem[]` | Child items |
| `classes()` | `string[]` | Custom classes (WP defaults stripped) |

Menus are registered in `functions.php` via `register_nav_menus()`. The theme registers `primary` and `footer` by default — edit that array directly to add or rename locations.

---

## REST API

Provided by `taw/core` (namespace `TAW\Core\Rest\SearchEndpoints`).

Registered automatically via `TAW\Core\Theme::boot()`.

### `GET taw/v1/search-posts`

Search for posts across post types. Powers the `post_selector` metabox field type.

**Permission:** Requires `edit_posts` capability (logged-in editors+).

**Parameters:**

| Param | Type | Default | Description |
|---|---|---|---|
| `s` | string | `''` | Search string (matches post titles) |
| `post_type` | string | `'post'` | Comma-separated post types (e.g. `post,page`) |
| `per_page` | int | `10` | Results to return (1–50) |
| `exclude` | string | `''` | Comma-separated post IDs to exclude |

**Response:** Array of post objects with `id`, `title`, `post_type`, `status`, `date`, `edit_url`, `permalink`, `thumbnail`.

---

## CLI Tools

Provided by `taw/core` (namespace `TAW\CLI`). Powered by Symfony Console. Entry point: `bin/taw`.

### `make:block`

Scaffold a new block with the correct folder structure, class, and template.

```bash
# Interactive (prompts for type)
php bin/taw make:block MyBlock

# Non-interactive
php bin/taw make:block MyBlock --type=meta --with-style --with-script

# UI block
php bin/taw make:block Badge --type=ui

# Overwrite existing
php bin/taw make:block Hero --type=meta --force
```

| Option | Description |
|---|---|
| `--type=meta\|ui` | Block type (prompted if omitted) |
| `--with-style` | Create a `style.scss` stub |
| `--with-script` | Create a `script.js` stub |
| `--force` / `-f` | Overwrite if block already exists |

After scaffolding, run `composer dump-autoload` to register the new class.

### `export:block`

Export a block as a portable ZIP archive with a `block.json` manifest.

```bash
php bin/taw export:block Hero
```

### `import:block`

Import a block from a ZIP archive exported by `export:block`.

```bash
php bin/taw import:block path/to/Hero.zip
```

---

## Image Helper

Provided by `taw/core` (namespace `TAW\Helpers\Image`).

Generates performance-optimised `<img>` tags with correct `loading`, `fetchpriority`, `decoding`, `srcset`, and `sizes` attributes based on whether the image is above or below the fold.

```php
use TAW\Helpers\Image;

// Below the fold (default — most images)
echo Image::render(get_post_thumbnail_id(), 'large', 'Alt text');

// Above the fold (hero, banner) — eager + high priority
echo Image::render($image_id, 'full', 'Hero image', [
    'above_fold' => true,
    'sizes'      => '100vw',
    'class'      => 'w-full object-cover',
]);

// Generate a <link rel="preload"> tag for the hero image
echo Image::preload_tag($image_id, 'full');
```

| Option | Type | Default | Description |
|---|---|---|---|
| `above_fold` | `bool` | `false` | Sets `loading="eager"` + `fetchpriority="high"` |
| `sizes` | `string` | auto | Custom `sizes` attribute |
| `class` | `string` | — | CSS class(es) |
| `attr` | `array` | — | Any additional HTML attributes |

---

## SVG Helper

Provided by `taw/core` (namespace `TAW\Helpers\Svg`).

Enables SVG uploads in WordPress (sanitized via `enshrined/svg-sanitize` on upload) and provides rendering utilities.

```php
use TAW\Helpers\Svg;

// Call once in theme setup (functions.php) to allow SVG uploads + auto-sanitize:
Svg::register();

// Render as <img> tag (safe; scripts inside SVG can't execute):
echo Svg::render($attachment_id, 'Company logo', ['class' => 'logo h-8']);

// Render inline (allows CSS targeting of SVG internals / animations):
echo Svg::inline($attachment_id, ['class' => 'icon w-5 h-5', 'title' => 'Menu']);

// Get URL only:
$url = Svg::url($attachment_id);
```

---

## Debug Helper

Provided by `taw/core` (namespace `TAW\Helpers\Dump`). Global `dump()` / `dd()` functions are autoloaded from `utilities.php`.

**Only active when `WP_DEBUG` is `true`.** Renders a styled collapsible panel in `wp_footer` — no output in production.

```php
dump($someArray, 'My label');   // queues value for display in footer panel
dd($someValue);                 // dump + die
```

---

## Theme Updater

Provided by `taw/core` (namespace `TAW\Core\ThemeUpdater`).

Hooks into WordPress's theme update system to pull releases from a GitHub repository. When a new tag is published, WordPress shows the standard "Update Available" notice and one-click update UI.

Activated in `functions.php`:
```php
if (is_admin()) {
    new \TAW\Core\ThemeUpdater([
        'slug'       => 'taw-theme',
        'github_url' => 'https://api.github.com/repos/YOUR_USERNAME/taw-theme/releases/latest',
    ]);
}
```

- Caches the GitHub API response for **6 hours** to avoid rate limits.
- Prefers a built ZIP asset attached to the release; falls back to GitHub's auto-generated zipball.
- Tag names are normalised: `v1.2.0` → `1.2.0`.
- Only enabled in `is_admin()` context — no frontend overhead.

---

## Vite Integration

### How it works

`vite-loader.php` (from `taw/core`, autoloaded via composer `files`) detects whether the Vite dev server is running via `fsockopen()` on port 5173.

- **Dev:** `app.js` (+ its CSS imports) served from `http://localhost:5173` with HMR
- **Prod:** Reads `public/build/manifest.json` for hashed filenames

### CSS loading pipeline (production)

CSS is loaded in three layers to maximise paint speed:

```
1. critical.scss  → compiled → inlined as <style> in <head>   (zero network request)
2. app.js CSS     → <link rel="preload"> + async <link media="print" onload="...">
3. app.scss CSS   → same async pattern (deduped if same compiled file)
```

The async `media="print"` trick makes stylesheets non-render-blocking — the browser downloads them in the background and swaps them in when ready. A `<noscript>` fallback covers JS-disabled users.

### CSS entry points

| File | How loaded |
|---|---|
| `resources/js/app.js` | Vite JS entry. Imports `app.css` + `app.scss` — both compile into the JS entry's CSS output. In dev this is the only PHP-loaded script; Vite HMR injects styles automatically. |
| `resources/css/app.css` | Tailwind v4 (`@import "tailwindcss"`, `@source "../../**/*.php"`). Imported by `app.js`, **not** a standalone Vite entry. |
| `resources/scss/app.scss` | Custom SCSS (fonts, global rules). Imported by `app.js`. |
| `resources/scss/critical.scss` | Standalone Vite entry. Inlined into `<head>` by `vite_inline_critical_css()`. Must stay under ~14 KB. No `@font-face` here — inlined CSS resolves `url()` against the page origin, not a stylesheet location, causing 404s. |
| `Blocks/*/style.css` | Per-block styles. Auto-discovered by `vite.config.js`, separate Rollup entries. |

### Key Vite config decisions

```js
base: command === 'build' ? './' : '/'
```
Production uses a relative base so compiled CSS references fonts as `./Roboto-xxx.woff2` — resolves correctly relative to the CSS file regardless of WordPress install path.
Dev uses `'/'` because Vite's HMR and module resolution break with a relative base when scripts are served cross-origin.

```js
server: { origin: 'http://localhost:5173' }
```
Forces Vite to embed the full dev server URL in injected CSS (e.g. `url('http://localhost:5173/resources/fonts/...')`). Without this, Vite writes `/resources/fonts/...` which the browser resolves against the WordPress page origin, causing font 404s.

### Self-hosted fonts

- Place WOFF2 files in `resources/fonts/`
- Declare `@font-face` in `resources/scss/_fonts.scss` with `url('../fonts/Name.woff2')`
- `@use 'fonts'` in `app.scss` (linked CSS) — Vite rewrites the URL correctly
- **Never** `@use 'fonts'` in `critical.scss` — inlined styles can't resolve relative asset paths
- Register preloads via `vite_asset_url('resources/fonts/Name.woff2')` — returns dev server URL in dev and hashed build URL in prod

### Helper: `vite_asset_url(string $path): string`

Resolves any theme asset to the correct URL in both modes:
```php
vite_asset_url('resources/fonts/Roboto-Regular.woff2')
// Dev  → 'http://localhost:5173/resources/fonts/Roboto-Regular.woff2'
// Prod → 'https://example.com/.../public/build/assets/Roboto-Regular-B51t0g.woff2'
```

### Block assets in Vite

`vite.config.js` auto-discovers block assets:
```js
const componentAssets = readdirSync('Blocks', { recursive: true })
    .filter(f => f.endsWith('style.css') || f.endsWith('style.scss') || f.endsWith('script.js'))
    .map(f => `Blocks/${f}`);
```

These become separate Rollup entry points → separate cached files in production.

### Script type="module"

The `script_loader_tag` filter in `vite-loader.php` adds `type="module"` to:
- All scripts from `VITE_SERVER` (dev)
- `theme-app` and `taw-component-*` handles (prod)

---

## Tech Stack

| Technology | Version | Purpose |
|---|---|---|
| WordPress | 6.0+ | CMS |
| PHP | 8.1+ | Server-side |
| Tailwind CSS | v4 | Utility-first CSS (via `@tailwindcss/vite`) |
| Alpine.js | v3 | Lightweight JS reactivity |
| Vite | v7 | Build tool + HMR |
| SCSS | via `sass` | Optional per-block or global styles |
| Composer | v2 | PSR-4 autoloading + `taw/core` package management |
| Symfony Console | — | CLI scaffolding commands (`bin/taw`) |

---

## PSR-4 Autoloading

**Theme's `composer.json`** only registers theme-specific classes:
```json
{
    "autoload": {
        "psr-4": {
            "TAW\\Blocks\\": "Blocks/"
        }
    }
}
```

**`taw/core` package** registers everything else:
```json
{
    "autoload": {
        "psr-4": { "TAW\\": "src/" },
        "files": ["src/Support/utilities.php", "src/Support/performance.php"]
    }
}
```

`utilities.php` provides global helper functions: `vite_asset_url()`, `vite_is_dev()`, `dump()`, `dd()`, `taw_editable()`, `taw_editor_attrs()`, `taw_editor_attrs_array()`, `taw_editor_section()`.

Effective namespace mapping (combined):
- `TAW\Core\Block\BaseBlock` → `vendor/taw/core/src/Core/Block/BaseBlock.php`
- `TAW\Core\Block\MetaBlock` → `vendor/taw/core/src/Core/Block/MetaBlock.php`
- `TAW\Core\Block\Block` → `vendor/taw/core/src/Core/Block/Block.php`
- `TAW\Core\Block\BlockRegistry` → `vendor/taw/core/src/Core/Block/BlockRegistry.php`
- `TAW\Core\Block\BlockLoader` → `vendor/taw/core/src/Core/Block/BlockLoader.php`
- `TAW\Core\Metabox\Metabox` → `vendor/taw/core/src/Core/Metabox/Metabox.php`
- `TAW\Core\OptionsPage\OptionsPage` → `vendor/taw/core/src/Core/OptionsPage/OptionsPage.php`
- `TAW\Core\Theme\Theme` → `vendor/taw/core/src/Core/Theme/Theme.php`
- `TAW\Core\Theme\ThemeUpdater` → `vendor/taw/core/src/Core/Theme/ThemeUpdater.php`
- `TAW\Core\Menu\Menu` → `vendor/taw/core/src/Core/Menu/Menu.php`
- `TAW\Core\Menu\MenuItem` → `vendor/taw/core/src/Core/Menu/MenuItem.php`
- `TAW\Core\Rest\SearchEndpoints` → `vendor/taw/core/src/Core/Rest/SearchEndpoints.php`
- `TAW\Core\Form\Form` → `vendor/taw/core/src/Core/Form/Form.php`
- `TAW\Core\Form\SubmissionsHandler` → `vendor/taw/core/src/Core/Form/SubmissionsHandler.php`
- `TAW\Core\Mail\Mailer` → `vendor/taw/core/src/Core/Mail/Mailer.php`
- `TAW\Core\Mail\MailTemplate` → `vendor/taw/core/src/Core/Mail/MailTemplate.php`
- `TAW\Core\Mail\MailTester` → `vendor/taw/core/src/Core/Mail/MailTester.php`
- `TAW\Helpers\Framework` → `vendor/taw/core/src/Helpers/Framework.php`
- `TAW\Helpers\Image` → `vendor/taw/core/src/Helpers/Image.php`
- `TAW\Helpers\Svg` → `vendor/taw/core/src/Helpers/Svg.php`
- `TAW\Helpers\Dump` → `vendor/taw/core/src/Helpers/Dump.php`
- `TAW\Helpers\Editor` → `vendor/taw/core/src/Helpers/Editor.php`
- `TAW\CLI\MakeBlockCommand` → `vendor/taw/core/src/CLI/MakeBlockCommand.php`
- `TAW\Blocks\Hero\Hero` → `Blocks/Hero/Hero.php`

After adding new block classes, run `composer dump-autoload`.

---

## Commands

| Command | Description |
|---|---|
| `npm run dev` | Start Vite dev server (port 5173) with HMR |
| `npm run build` | Production build → `public/build/` |
| `composer install` | Install PHP dependencies (including `taw/core`) |
| `composer update taw/core` | Pull the latest framework package update |
| `composer dump-autoload` | Rebuild autoload classmap (after adding new blocks) |
| `php bin/taw make:block Name` | Scaffold a new block |
| `php bin/taw export:block Name` | Export a block as a ZIP |
| `php bin/taw import:block path.zip` | Import a block from a ZIP |

---

## Common Patterns

### Conditional block loading per template
```php
// front-page.php
BlockRegistry::queue('hero', 'features', 'testimonials', 'cta');

// single.php
BlockRegistry::queue('post-header', 'related-posts');

// archive.php — maybe no custom blocks needed
```

### Accessing meta in MetaBlock::getData()
```php
protected function getData(int $postId): array
{
    return [
        'heading'   => $this->getMeta($postId, 'my_heading'),
        'image_url' => $this->getImageUrl($postId, 'my_image', 'large'),
    ];
}
```

### Nesting UI blocks inside MetaBlocks
```php
<!-- Blocks/Hero/index.php -->
<section class="hero">
    <h1><?php echo esc_html($heading); ?></h1>
    <?php if ($cta_text): ?>
        <?php (new \TAW\Blocks\Button\Button())->render([
            'text' => $cta_text,
            'url'  => $cta_url,
        ]); ?>
    <?php endif; ?>
</section>
```

### Reading theme options in templates
```php
use TAW\Core\OptionsPage;

$phone = OptionsPage::get('company_phone');
$logo  = OptionsPage::get_image_url('company_logo', 'medium');
```

### Rendering a performance-optimised image
```php
use TAW\Helpers\Image;

// Hero (above the fold)
echo Image::render($hero_image_id, 'full', 'Hero image', ['above_fold' => true]);

// Card image (below the fold)
echo Image::render($card_image_id, 'large', 'Card photo');
```

---

## Do NOT

- Put block logic in `functions.php` — it belongs in the block class
- Manually register blocks in `functions.php` — `BlockLoader::loadAll()` handles it
- Call `wp_enqueue_style/script` directly for blocks — the base class handles it
- Create blocks with mismatched folder/class names — auto-discovery will skip them
- Forget to `queue()` blocks before `get_header()` — assets will fall back to inline (suboptimal)
- Add `@font-face` / `@use 'fonts'` to `critical.scss` — inlined `<style>` tags resolve `url()` against the page origin, not the stylesheet, causing font 404s on any non-root install
- Add `resources/css/app.css` back as a standalone Vite entry — it is imported by `app.js` and must not be a separate entry or it will compile twice
- Set `base: './'` globally in `vite.config.js` — it must only apply to `build` (dev mode breaks with a relative base in cross-origin setups)
- Use `wp_nav_menu()` — use `Menu::get('location')` instead for full markup control
- Edit files inside `vendor/taw/core/` directly — changes will be overwritten by `composer update`. Work in the `taw-core` repo instead
- Look for `TAW\Core`, `TAW\Helpers`, or `TAW\CLI` classes in `inc/` — they live in `vendor/taw/core/src/`
- Reference `TAW\Core\Framework` — it moved to `TAW\Helpers\Framework`
- Reference `TAW\Core\BaseBlock` / `TAW\Core\MetaBlock` / `TAW\Core\Block` etc. without the `Block\` sub-namespace — e.g. use `TAW\Core\Block\MetaBlock`
