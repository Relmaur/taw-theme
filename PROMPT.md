# PROMPT.md — TAW Theme Prompt Boilerplates

Prompt templates for asking any AI model to build parts of this WordPress theme.
Always read `CLAUDE.md` (and optionally `AGENTS.md`) before acting on any prompt below.

---

## 1. Create an Entire Page Template

Use this when you need a new WordPress page template made up of several blocks.

```
I'm working on the TAW Theme (WordPress + Vite + Tailwind v4 + Alpine.js).
Read CLAUDE.md for the full architecture before doing anything.

Please create:

1. A page template file: `page-{slug}.php`
   - Queue all required MetaBlocks with `BlockRegistry::queue(...)` before `get_header()`
   - Include `get_header()`, render each block with `BlockRegistry::render('id')`,
     then `get_footer()`

2. The following blocks (each in its own `Blocks/{Name}/` folder):

### {BlockName} — MetaBlock
Fields:
- `{field_id}` ({type}) — {description}
- `{field_id}` ({type}) — {description}
- `{field_id}` (repeater) with sub-fields:
  - `{sub_field_id}` ({type})
  - `{sub_field_id}` ({type})

### {BlockName2} — MetaBlock
Fields:
- `{field_id}` ({type}) — {description}

### {BlockName3} — UI Block (presentational, no metabox)
Props (passed at render time):
- `{prop}` ({type}) — {description}

For each MetaBlock:
- Create `Blocks/{Name}/{Name}.php` (extends MetaBlock, registers metabox, implements getData)
- Create `Blocks/{Name}/index.php` (template, uses variables from getData, Tailwind classes)
- Optionally create `Blocks/{Name}/style.scss` if custom styles are needed beyond Tailwind

For each UI Block:
- Create `Blocks/{Name}/{Name}.php` (extends Block, defines defaults())
- Create `Blocks/{Name}/index.php` (template, guard on required props)

After creating the files, remind me to run:
  composer dump-autoload
```

**Field type reference:** `text`, `textarea`, `wysiwyg`, `url`, `number`, `range`, `select`,
`image`, `group`, `checkbox`, `color`, `repeater`, `post_select`

---

## 2. Create a Single MetaBlock

Use this when you need one data-driven section (e.g. Hero, About, Testimonials).

```
I'm working on the TAW Theme. Read CLAUDE.md first.

Please create a MetaBlock called `{Name}` (folder: `Blocks/{Name}/`).

Metabox fields:
- `{name}_heading` (text, width: 50) — Section heading
- `{name}_subheading` (text, width: 50) — Optional subheading
- `{name}_body` (wysiwyg) — Rich-text body copy
- `{name}_image` (image, width: 33.33) — Background or feature image
- `{name}_items` (repeater, max: 6, label: "Add Item") with sub-fields:
  - `title` (text, width: 50)
  - `description` (textarea)
  - `icon` (image, width: 50)
- `{name}_cta_label` (text, width: 50) — CTA button label
- `{name}_cta_url` (url, width: 50) — CTA button URL

Files to create:
- `Blocks/{Name}/{Name}.php`
  - namespace TAW\Blocks\{Name}
  - extends MetaBlock, $id = '{snake_id}'
  - registerMetaboxes(): registers one Metabox, screen: 'page'
  - getData(int $postId): returns array of extracted meta values
    (use getMeta($postId, '{name}_heading') etc.)
- `Blocks/{Name}/index.php`
  - PHPDoc block listing all @var variables
  - Guard: return early if no heading and no items
  - Use Tailwind utility classes for layout
  - Use `taw_editable($value, '{snake_id}', '{field_id}')` for inline-editable text fields
  - Use `TAW\Helpers\Image::render((int)$image, 'large', 'Alt text')` for images

Remind me to run: composer dump-autoload
```

---

## 3. Create a Single UI Block (Presentational)

Use this for reusable components with no CMS data (buttons, cards, badges, alerts, etc.).

```
I'm working on the TAW Theme. Read CLAUDE.md first.

Please create a UI Block called `{Name}` (folder: `Blocks/{Name}/`).

Props (with defaults):
- `{prop1}` (string, default: '{value}') — {description}
- `{prop2}` (string, default: '{value}') — e.g. variant: 'primary' | 'secondary' | 'ghost'
- `{prop3}` (bool, default: false) — {description}

Files to create:
- `Blocks/{Name}/{Name}.php`
  - namespace TAW\Blocks\{Name}
  - extends Block, $id = '{snake_id}'
  - defaults(): returns the prop defaults array
- `Blocks/{Name}/index.php`
  - PHPDoc block listing all @var variables
  - Guard: return early if required prop is empty
  - Pure HTML + Tailwind, no WordPress meta calls
  - Variant classes via a $class_map array keyed by $variant (avoid inline conditionals)

Usage example (for the docs comment at the top of {Name}.php):
  $block = new {Name}();
  $block->render(['prop1' => 'value', 'prop2' => 'secondary']);
```

---

## 4. Create a Block with Alpine.js Interactivity

Use this when a block needs client-side state (tabs, accordion, modal, counter, etc.).

```
I'm working on the TAW Theme. Read CLAUDE.md first.

Please create a MetaBlock (or UI Block) called `{Name}` with Alpine.js interactivity.

Behaviour: {describe the interaction — e.g. "accordion: each item expands/collapses on click"}

MetaBox fields (if MetaBlock):
- {same format as boilerplate #2}

Alpine component:
- Register it in `Blocks/{Name}/script.js` as:
    document.addEventListener('alpine:init', () => {
        Alpine.data('{ComponentName}', () => ({
            // state
            // methods
        }));
    });
- Bind with `x-data="{ComponentName}"` on the root element in index.php
- Use `x-show`, `x-transition`, `@click`, `:class` etc. as appropriate
- Keep Alpine logic in script.js; keep markup in index.php

Files to create:
- `Blocks/{Name}/{Name}.php`
- `Blocks/{Name}/index.php`
- `Blocks/{Name}/script.js`
- `Blocks/{Name}/style.scss` (if needed)

Remind me to run: composer dump-autoload
```

---

## 5. Build From a Figma Design (or Screenshot)

Use this when you have a visual reference and want code that matches it.

```
I'm working on the TAW Theme. Read CLAUDE.md first.

I have attached [a Figma link / a screenshot] of the {section/page} I want to build.

[Paste Figma URL here — e.g. https://www.figma.com/design/ABC123/My-Design?node-id=1:2]
[Or describe: "See the attached screenshot — it shows a two-column section with..."]

Please:
1. Analyse the visual: identify sections, components, typography scale, spacing rhythm,
   and any interactive states (hover, open/closed).
2. Map each distinct section to either a MetaBlock (if it contains CMS content)
   or a UI Block (if it is purely presentational / reused with props).
3. Implement the blocks following the TAW conventions from CLAUDE.md.
4. Use Tailwind v4 utility classes for all layout and spacing.
   Only add a `style.scss` file for things Tailwind cannot express
   (complex pseudo-elements, custom keyframes, etc.).
5. For images, use `TAW\Helpers\Image::render(...)`.
   For SVG icons embedded in the design, inline them directly in the template.
6. List any design tokens (colours, font sizes) you spotted so I can add them
   to `resources/css/app.css` as CSS custom properties.

Remind me to run: composer dump-autoload
```

---

## 6. Create a Contact / Lead-Generation Page with a Form

Use this for pages that include a form with email notifications.

```
I'm working on the TAW Theme. Read CLAUDE.md first.

Please create a contact/lead-generation page with the following setup:

Page template: `page-contact.php`

Blocks:
### ContactHero — MetaBlock
Fields:
- `contact_hero_heading` (text) — Page heading
- `contact_hero_subheading` (text) — Subheading

### ContactForm — UI Block
This block renders a TAW Form (TAW\Core\Form\Form).

Form config:
- id: 'contact'
- Fields:
  - name (text, required)
  - email (email, required)
  - phone (text)
  - message (textarea, required)
- Emails:
  - to_self: subject 'New contact form submission', template 'contact-self'
  - to_client: subject 'Thanks for reaching out', template 'contact-client'
- Success message: "Thanks! We'll be in touch shortly."

Also create:
- `mails/contact-self.mjml` — notification to the site owner with all field values
- `mails/contact-client.mjml` — confirmation to the submitter

Use `{{name}}`, `{{email}}`, `{{phone}}`, `{{message}}` as template placeholders.

Remind me to run: composer dump-autoload
```

---

## 7. Add Fields to an Existing Block

Use this when you need to extend a block that already exists.

```
I'm working on the TAW Theme. Read CLAUDE.md first.

Please read `Blocks/{Name}/{Name}.php` and `Blocks/{Name}/index.php`.

Add the following fields to the existing metabox:
- `{name}_{field_id}` ({type}, width: {width}) — {description}
- `{name}_{field_id}` ({type}) — {description}

Then:
1. In {Name}.php — add the field configs inside the existing `fields` array
   and add the new keys to the `getData()` return array.
2. In index.php — add the corresponding template output for each new field,
   following the existing patterns (guard, taw_editable, Image::render, etc.).

Do not reorganise, reformat, or change any code that already exists.
Only add what is strictly needed for the new fields.
```

---

## Quick-Reference: Field Config Snippets

Paste these into any prompt to be precise about field types:

```php
// Text (inline editable)
['id' => 'foo_title', 'label' => 'Title', 'type' => 'text', 'width' => '50', 'required' => true, 'editor' => true]

// Wysiwyg
['id' => 'foo_body', 'label' => 'Body', 'type' => 'wysiwyg']

// Image
['id' => 'foo_image', 'label' => 'Image', 'type' => 'image', 'width' => '50']

// Color
['id' => 'foo_bg', 'label' => 'Background', 'type' => 'color', 'default' => '#ffffff', 'width' => '33.33']

// Range / slider
['id' => 'foo_padding', 'label' => 'Padding', 'type' => 'range', 'min' => 0, 'max' => 200, 'step' => 10, 'unit' => 'px', 'default' => 80, 'width' => '33.33']

// Select
['id' => 'foo_layout', 'label' => 'Layout', 'type' => 'select', 'options' => ['left' => 'Image Left', 'right' => 'Image Right'], 'width' => '50']

// Checkbox
['id' => 'foo_show_cta', 'label' => 'Show CTA', 'type' => 'checkbox', 'description' => 'Toggle the call-to-action button.', 'width' => '33.33']

// Post select (single)
['id' => 'foo_post', 'label' => 'Featured Post', 'type' => 'post_select', 'post_type' => 'post,page', 'width' => '50']

// Post select (multiple)
['id' => 'foo_posts', 'label' => 'Related Posts', 'type' => 'post_select', 'post_type' => 'post', 'multiple' => true, 'max' => 5, 'width' => '50']

// Repeater
['id' => 'foo_items', 'label' => 'Items', 'type' => 'repeater', 'button_label' => 'Add Item', 'max' => 8, 'fields' => [
    ['id' => 'title',       'label' => 'Title',       'type' => 'text',  'width' => '50'],
    ['id' => 'description', 'label' => 'Description', 'type' => 'textarea'],
    ['id' => 'image',       'label' => 'Image',       'type' => 'image', 'width' => '50'],
]]

// Group (nested fields, single instance)
['id' => 'foo_social', 'label' => 'Social Links', 'type' => 'group', 'width' => '50', 'fields' => [
    ['id' => 'linkedin', 'label' => 'LinkedIn', 'type' => 'url', 'width' => '50'],
    ['id' => 'twitter',  'label' => 'Twitter',  'type' => 'url', 'width' => '50'],
]]
```

---

## Quick-Reference: Template Patterns

```php
// Inline-editable text
<?php echo taw_editable($heading, 'block_id', 'field_id'); ?>

// Image (above the fold)
<?php echo TAW\Helpers\Image::render((int) $image, 'full', 'Alt text', ['above_fold' => true]); ?>

// Image (lazy)
<?php echo TAW\Helpers\Image::render((int) $image, 'large', 'Alt text'); ?>

// Inline SVG
<?php echo TAW\Helpers\Svg::inline($icon_id, ['class' => 'size-6']); ?>

// Render a UI Block inside a template
use TAW\Blocks\Button\Button;
$button = new Button();
$button->render(['text' => 'Get Started', 'url' => '/contact', 'variant' => 'primary']);

// Loop over a repeater
foreach ($items as $item): ?>
    <div><?php echo esc_html($item['title']); ?></div>
<?php endforeach;

// Options page value
<?php echo esc_html(TAW\Core\OptionsPage\OptionsPage::get('company_phone')); ?>
```
